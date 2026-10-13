<?php

namespace App\Livewire\Ops;

use App\Models\Ops\{Batch, Courier, Customer, CustomerReturn, Order, OrderItem, ProductVariant, PurchaseOrder, Setting, Shipment, Supplier, SupplierReturn};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Overview', 'title' => 'Dashboard'])]
#[Title('Dashboard')]
class Dashboard extends Component
{
    #[Url] public string $range = 'today'; // today | yesterday | 7d | 30d | custom
    #[Url(except: '')] public string $from = '';   // custom range
    #[Url(except: '')] public string $to = '';

    public const RANGES = ['today' => 'Today', 'yesterday' => 'Yesterday', '7d' => '7 days', '30d' => '30 days',
        'month' => 'This month', 'last_month' => 'Last month', '90d' => 'Last 90 days', 'year' => 'This year', 'all' => 'All time', 'custom' => 'Custom'];
    public const BUTTONS = ['today', 'yesterday', '7d', '30d'];   // the rest sit in the "More" dropdown

    public function mount(): void
    {
        if (! array_key_exists($this->range, self::RANGES)) $this->range = 'today';
        if ($this->range === 'custom' && (! $this->from || ! $this->to)) $this->setCustomDefaults();
    }

    public function updatedRange(string $r): void { if ($r === 'custom' && (! $this->from || ! $this->to)) $this->setCustomDefaults(); }
    public function updatedFrom(): void { $this->range = 'custom'; if ($this->to && $this->from > $this->to) $this->to = $this->from; }
    public function updatedTo(): void { $this->range = 'custom'; if ($this->from && $this->to < $this->from) $this->from = $this->to; }
    protected function setCustomDefaults(): void { $this->from = today()->subDays(6)->toDateString(); $this->to = today()->toDateString(); }

    /** [start, end, previous-period start, days] of the selected range — the comparison period is the same length just before it */
    protected function period(): array
    {
        [$start, $end] = match ($this->range) {
            'yesterday' => [today()->subDay(), today()->subDay()->endOfDay()],
            '7d' => [today()->subDays(6), now()], '30d' => [today()->subDays(29), now()], '90d' => [today()->subDays(89), now()],
            'month' => [now()->startOfMonth(), now()], 'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'year' => [now()->startOfYear(), now()],
            'all' => [Carbon::parse(Order::live()->min('placed_at') ?? today())->startOfDay(), now()],
            'custom' => [Carbon::parse($this->from ?: today())->startOfDay(), min(Carbon::parse($this->to ?: today())->endOfDay(), now())],
            default => [today(), now()],
        };
        $days = max(1, (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        return [$start, $end, $start->copy()->subDays($days), $days];
    }

    protected function key(): string { return $this->range.($this->range === 'custom' ? ".$this->from.$this->to" : ''); }

    public function rangeLabel(): string
    {
        return $this->range === 'custom' ? Carbon::parse($this->from)->format('d M').' – '.Carbon::parse($this->to)->format('d M') : self::RANGES[$this->range];
    }

    /** One grouped query per widget, cached 60s → dashboard renders in a few ms. */
    #[Computed]
    public function kpis(): array
    {
        return cache()->remember('dash.kpis.'.$this->key(), 60, function () {
            [$start, $end, $prev, $days] = $this->period();
            $agg = fn ($from, $to) => Order::live()->whereBetween('placed_at', [$from, $to])->selectRaw("COUNT(*) n, COALESCE(SUM(total),0) sales, COALESCE(SUM(CASE WHEN status='delivered' THEN total END),0) delivered,
                SUM(status='cancelled') cancelled, SUM(status='delivered') del_n, SUM(status IN ('returned','returning')) ret_n")->first();
            $cur = $agg($start, $end); $old = $agg($prev, $start->copy()->subSecond());
            $pct = fn ($a, $b) => $b ? (int) round(($a - $b) / $b * 100) : null;
            // 12-bar sparklines across the period
            $bucket = max(1, (int) ceil(($days * 86400) / 12));
            $spark = Order::live()->whereBetween('placed_at', [$start, $end])->selectRaw('FLOOR(TIMESTAMPDIFF(SECOND, ?, placed_at) / ?) b, COALESCE(SUM(total),0) s, COUNT(*) n, COALESCE(SUM(CASE WHEN status=\'delivered\' THEN total END),0) d', [$start, $bucket])
                ->groupBy('b')->get()->keyBy('b');
            $series = fn ($k) => collect(range(0, 11))->map(fn ($i) => (int) ($spark[$i]->$k ?? 0))->all();
            $closed = $cur->del_n + $cur->ret_n;
            return [
                'sales' => (int) $cur->sales, 'sales_pct' => $pct($cur->sales, $old->sales), 'sales_spark' => $series('s'),
                'orders' => (int) $cur->n, 'orders_pct' => $pct($cur->n, $old->n), 'orders_spark' => $series('n'),
                'new_customers' => Customer::real()->whereBetween('created_at', [$start, $end])->count(), 'cancelled' => (int) $cur->cancelled,
                'revenue' => (int) $cur->delivered, 'revenue_pct' => $pct($cur->delivered, $old->delivered), 'revenue_spark' => $series('d'),
                'aov' => $cur->n ? (int) round($cur->sales / $cur->n) : 0,
                'delivery_rate' => $closed ? (int) round($cur->del_n / $closed * 100) : null, 'return_rate' => $closed ? (int) round($cur->ret_n / $closed * 100) : null,
                'cod_in_courier' => (int) Shipment::where(fn ($q) => $q->whereNotIn('courier_status', ['returned', 'cancelled', 'lost', 'returning']))->whereNull('settlement_id')->sum('cod_amount'),
                'supplier_due' => (int) Supplier::sum('due'), 'customer_due' => (int) Customer::sum('due'),
                'pending_returns' => Shipment::whereIn('courier_status', ['returning', 'returned', 'cancelled', 'partial_delivered'])->whereNotExists(fn ($q) => $q->from('ops_customer_returns')->whereColumn('ops_customer_returns.shipment_id', 'ops_shipments.id'))->count(),
                'receivable_courier' => (int) Shipment::where('courier_status', 'delivered')->whereNull('settlement_id')->sum('cod_amount'),
            ];
        });
    }

    /** order groups for the stats strip: [label, statuses, tone] */
    public const GROUPS = [
        'pending' => ['Pending orders', ['pending', 'confirmed', 'on_hold', 'processing', 'packed'], 'mute'],   // still in the warehouse, not shipped
        'courier' => ['In courier', ['shipped', 'returning'], 'info'],                                          // with the courier (incl. exchange orders), not back yet
        'delivered' => ['Delivered', ['delivered', 'partially_delivered'], 'ok'],
        'cancelled' => ['Cancelled', ['cancelled'], 'mute'],
        'returned' => ['Returned', ['returned'], 'bad'],
    ];

    #[Computed]
    public function groups(): array
    {
        [$start, $end] = $this->period();
        $rows = cache()->remember('dash.groups.'.$this->key(), 60, fn () => Order::live()->whereBetween('placed_at', [$start, $end])
            ->selectRaw('status, COUNT(*) n, COALESCE(SUM(total),0) v, SUM(exchange_of_id IS NOT NULL) ex')->groupBy('status')->get()->keyBy('status')->map(fn ($r) => ['n' => (int) $r->n, 'v' => (int) $r->v, 'ex' => (int) $r->ex])->all());
        return collect(self::GROUPS)->map(fn ($g) => ['n' => collect($g[1])->sum(fn ($s) => $rows[$s]['n'] ?? 0), 'v' => collect($g[1])->sum(fn ($s) => $rows[$s]['v'] ?? 0),
            'exchange' => collect($g[1])->sum(fn ($s) => $rows[$s]['ex'] ?? 0)])->all();
    }

    /** today / yesterday use the daily target · 7d / 30d their own · custom = daily target × days */
    protected function targetKey(): ?string { return ['today' => 'today', 'yesterday' => 'today', '7d' => '7d', '30d' => '30d'][$this->range] ?? null; }

    #[Computed]
    public function target(): int
    {
        if ($key = $this->targetKey()) return (int) ops_setting("targets.$key", 0);
        return (int) ops_setting('targets.today', 0) * $this->period()[3];
    }

    public function saveTarget($value): void
    {
        $key = $this->targetKey() ?? 'today';
        $amount = max(0, (int) preg_replace('/\D/', '', (string) $value));
        if (! $this->targetKey()) $amount = (int) round($amount / max(1, $this->period()[3]));   // custom: store as a daily target
        $t = Setting::get('targets', ops_setting('targets')); $t[$key] = $amount;
        Setting::put('targets', $t); unset($this->target);
        ops_log('Sales target ('.$key.') → '.tk($amount));
        $this->dispatch('toast', message: 'Sales target updated');
    }

    #[Computed]
    public function orderStatus(): array { return cache()->remember('dash.ost', 60, fn () => Order::live()->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all()); }

    #[Computed]
    public function courierStatus(): array { return cache()->remember('dash.cst', 60, fn () => Shipment::selectRaw('courier_status, count(*) n')->groupBy('courier_status')->pluck('n', 'courier_status')->all()); }

    #[Computed]
    public function chart(): array
    {
        return cache()->remember('dash.chart', 300, function () {
            $rows = Order::live()->where('placed_at', '>=', now()->subDays(13)->startOfDay())
                ->selectRaw("DATE(placed_at) d, SUM(CASE WHEN status='delivered' THEN total ELSE 0 END) delivered, SUM(CASE WHEN status NOT IN ('delivered','cancelled') THEN total ELSE 0 END) booked, COUNT(*) n")
                ->groupBy('d')->get()->keyBy('d');
            return collect(range(13, 0))->map(function ($i) use ($rows) {
                $d = today()->subDays($i)->toDateString(); $r = $rows[$d] ?? null;
                return ['d' => $d, 'delivered' => (int) ($r->delivered ?? 0), 'booked' => (int) ($r->booked ?? 0), 'n' => (int) ($r->n ?? 0)];
            })->all();
        });
    }

    #[Computed]
    public function channels(): array
    {
        [$start, $end] = $this->period();
        return cache()->remember('dash.ch.'.$this->key(), 60, fn () => Order::live()->whereBetween('placed_at', [$start, $end])->selectRaw('source, COUNT(*) n, COALESCE(SUM(total),0) v')->groupBy('source')->orderByDesc('v')->get()->map(fn ($r) => ['source' => $r->source, 'n' => (int) $r->n, 'v' => (int) $r->v])->all());
    }

    #[Computed]
    public function recent() { return Order::live()->with(['customer:id,name,area', 'items:id,order_id,qty', 'shipment:id,order_id,courier_status,courier_id', 'shipment.courier:id,name'])->latest('placed_at')->limit(8)->get(['id', 'number', 'customer_id', 'total', 'status', 'payment_status', 'placed_at']); }

    #[Computed]
    public function topProducts()
    {
        [$start, $end] = $this->period();
        return cache()->remember('dash.top.'.$this->key(), 60, fn () => OrderItem::join('ops_orders as o', 'o.id', '=', 'ops_order_items.order_id')->where('o.is_draft', false)->whereNull('o.exchange_of_id')->whereBetween('o.placed_at', [$start, $end])->whereNotIn('o.status', ['cancelled'])
            ->join('ops_product_variants as v', 'v.id', '=', 'ops_order_items.variant_id')->join('ops_products as p', 'p.id', '=', 'v.product_id')
            ->selectRaw('p.id, p.name, SUM(ops_order_items.qty) units, SUM(ops_order_items.qty * ops_order_items.unit_price) revenue')->groupBy('p.id', 'p.name')->orderByDesc('units')->limit(5)->get());
    }

    #[Computed]
    public function couriers()
    {
        return cache()->remember('dash.couriers', 60, fn () => Courier::withCount(['shipments as total', 'shipments as delivered' => fn ($q) => $q->where('courier_status', 'delivered'), 'shipments as returned' => fn ($q) => $q->whereIn('courier_status', ['returned', 'returning'])])
            ->withSum(['shipments as receivable' => fn ($q) => $q->where('courier_status', 'delivered')->whereNull('settlement_id')], 'cod_amount')->get());
    }

    #[Computed]
    public function stock(): array
    {
        return cache()->remember('dash.stock', 60, fn () => [
            'variants' => ProductVariant::count(), 'batches' => Batch::where('remaining', '>', 0)->count(),
            'low' => ProductVariant::whereColumn('stock', '<', DB::raw('(select low_stock_threshold from ops_products where ops_products.id = ops_product_variants.product_id)'))->count(),
            'damaged' => (int) ProductVariant::sum('damaged'), 'supplier_returns' => SupplierReturn::whereNotIn('status', ['resolved', 'rejected', 'cancelled'])->count(),
        ]);
    }

    /** coloured dot + message → target screen */
    #[Computed]
    public function attention(): array
    {
        $a = [];
        if ($n = Order::live()->where('status', 'pending')->where('placed_at', '<', now()->subDay())->count()) $a[] = ['bad', "$n orders pending for more than 24 h", route('ops.orders.index', ['status' => 'pending'])];
        if ($n = Shipment::whereIn('courier_status', ['picked_up', 'in_transit', 'pending'])->where('status_updated_at', '<', now()->subDays(3))->count()) $a[] = ['warn', "$n parcels with no movement for 3 days", route('ops.orders.in-courier', ['status' => 'in_transit'])];
        if ($n = $this->stock['low']) $a[] = ['warn', "$n variants below low-stock threshold", route('ops.products.index', ['status' => 'low'])];
        if ($n = PurchaseOrder::whereIn('status', ['sent', 'partial'])->whereDate('expected_at', '<', today())->count()) $a[] = ['bad', "$n purchase orders overdue", route('ops.purchases.index', ['status' => 'open'])];
        if ($n = CustomerReturn::where('refund_amount', '>', 0)->whereNull('refunded_at')->count()) $a[] = ['warn', "$n returns waiting for refund", route('ops.returns.receive', ['view' => 'refund'])];
        if ($n = $this->kpis['pending_returns']) $a[] = ['info', "$n return parcels to receive", route('ops.returns.receive')];
        if ($n = Batch::whereNull('labels_printed_at')->where('remaining', '>', 0)->count()) $a[] = ['info', "$n batches need labels", route('ops.batches.index', ['filters' => ['labels' => 1]])];
        if ($n = Order::live()->where('status', 'on_hold')->count()) $a[] = ['warn', "$n orders on hold", route('ops.orders.index', ['status' => 'on_hold'])];
        if (! $a) $a[] = ['ok', 'All clear — nothing needs attention right now', null];
        return $a;
    }

    /** Work waiting on a person — [tone, label, hint, count, value|null, href, cta]; zero rows hidden, most urgent first */
    #[Computed]
    public function actions(): array
    {
        return cache()->remember('dash.actions', 60, function () {
            $os = Order::live()->selectRaw('status, COUNT(*) n, COALESCE(SUM(total),0) v')->groupBy('status')->get()->keyBy('status');
            $o = fn (array $s) => [collect($s)->sum(fn ($x) => (int) ($os[$x]->n ?? 0)), collect($s)->sum(fn ($x) => (int) ($os[$x]->v ?? 0))];
            $refund = CustomerReturn::where('refund_amount', '>', 0)->whereNull('refunded_at')->selectRaw('COUNT(*) n, COALESCE(SUM(refund_amount),0) v')->first();
            $settle = Shipment::where('courier_status', 'delivered')->whereNull('settlement_id')->selectRaw('COUNT(*) n, COALESCE(SUM(cod_amount),0) v')->first();
            $out = DB::table('ops_products as p')->where('p.status', 'active')
                ->whereRaw('COALESCE((select SUM(stock) from ops_product_variants v where v.product_id = p.id), 0) <= 0')->count();
            $late = Order::live()->where('status', 'pending')->where('placed_at', '<', now()->subDay())->count();
            [$pn, $pv] = $o(['pending']); [$cn, $cv] = $o(['confirmed', 'processing']); [$kn, $kv] = $o(['packed']); [$hn, $hv] = $o(['on_hold']);
            $rows = [
                ['bad', 'Pending orders to confirm', $late ? "$late waiting more than 24 h" : 'Call / confirm new orders', $pn, $pv, route('ops.orders.index', ['status' => 'pending']), 'Confirm'],
                ['warn', 'Refund requests', 'Customers waiting for their money', (int) $refund->n, (int) $refund->v, route('ops.returns.receive', ['view' => 'refund']), 'Refund'],
                ['bad', 'Out of stock', 'Active products with no stock', $out, null, route('ops.products.index', ['status' => 'out']), 'Restock'],
                ['warn', 'Orders on hold', 'Resolve and release', $hn, $hv, route('ops.orders.index', ['status' => 'on_hold']), 'Review'],
                ['info', 'Ready to pack', 'Confirmed / processing orders', $cn, $cv, route('ops.packing.scan'), 'Pack'],
                ['info', 'Packed — hand to courier', 'Waiting for pickup', $kn, $kv, route('ops.orders.index', ['status' => 'packed']), 'Ship'],
                ['warn', 'Return parcels to receive', 'Back from courier, not scanned in', (int) $this->kpis['pending_returns'], null, route('ops.returns.receive'), 'Receive'],
                ['warn', 'Returns to inspect', 'Condition not set yet', CustomerReturn::whereHas('items', fn ($i) => $i->whereNull('condition'))->count(), null, route('ops.returns.receive', ['view' => 'inspect']), 'Inspect'],
                ['bad', 'Parcels stuck at courier', 'No movement for 3 days', Shipment::whereIn('courier_status', ['picked_up', 'in_transit', 'pending'])->where('status_updated_at', '<', now()->subDays(3))->count(), null, route('ops.orders.in-courier', ['status' => 'in_transit']), 'Follow up'],
                ['warn', 'Parcels on hold at courier', 'Customer unreachable / reschedule', Shipment::where('courier_status', 'hold')->count(), null, route('ops.orders.in-courier', ['status' => 'hold']), 'Follow up'],
                ['warn', 'Low stock', 'Below the low-stock threshold', (int) $this->stock['low'], null, route('ops.products.index', ['status' => 'low']), 'Reorder'],
                ['bad', 'Purchase orders overdue', 'Past the expected date', PurchaseOrder::whereIn('status', ['sent', 'partial'])->whereDate('expected_at', '<', today())->count(), null, route('ops.purchases.index', ['status' => 'open']), 'Chase'],
                ['mute', 'Draft purchase orders', 'Not sent to supplier yet', PurchaseOrder::where('status', 'draft')->count(), null, route('ops.purchases.index', ['status' => 'draft']), 'Send'],
                ['info', 'Courier payment to collect', 'Delivered, not settled', (int) $settle->n, (int) $settle->v, route('ops.couriers.index'), 'Settle'],
                ['warn', 'Damaged stock', 'Send back to supplier', (int) $this->stock['damaged'], null, route('ops.supplier-returns.index', ['view' => 'pool']), 'Return'],
                ['mute', 'Supplier returns open', 'Waiting on supplier', (int) $this->stock['supplier_returns'], null, route('ops.supplier-returns.index', ['status' => 'open']), 'Track'],
                ['mute', 'Batches need labels', 'Print barcode labels', Batch::whereNull('labels_printed_at')->where('remaining', '>', 0)->count(), null, route('ops.batches.index', ['filters' => ['labels' => 1]]), 'Print'],
                ['mute', 'Draft orders', 'Incomplete — finish or discard', Order::drafts()->count(), null, route('ops.orders.drafts'), 'Open'],
                ['mute', 'Customer dues', 'Customers who owe money', Customer::where('due', '>', 0)->count(), (int) $this->kpis['customer_due'], route('ops.customers.index', ['status' => 'due']), 'Collect'],
            ];
            return array_values(array_filter($rows, fn ($r) => $r[3] > 0));
        });
    }

    #[Computed] public function activity() { return \App\Models\Ops\ActivityLog::with('user:id,name')->latest('created_at')->limit(8)->get(); }

    public function render() { return view('livewire.ops.dashboard'); }
}
