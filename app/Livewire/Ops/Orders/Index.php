<?php

namespace App\Livewire\Ops\Orders;

use App\Models\Ops\{Courier, Order};
use App\Livewire\Ops\Concerns\{OrderActions, WithTable};
use Livewire\Attributes\{Computed, Layout, On, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Orders & Packing', 'title' => 'All orders', 'tabs' => 'orders'])]
#[Title('Orders')]
class Index extends Component
{
    use WithTable, OrderActions;
    #[Url(except: 'all')] public string $preset = 'all';   // today | yesterday | 7d | month | last_month | all | custom

    public function updatedPreset(string $p): void
    {
        [$this->from, $this->to] = match ($p) {
            'today' => [today()->toDateString(), today()->toDateString()], 'yesterday' => [today()->subDay()->toDateString(), today()->subDay()->toDateString()],
            '7d' => [today()->subDays(6)->toDateString(), today()->toDateString()], 'month' => [now()->startOfMonth()->toDateString(), today()->toDateString()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            default => ['', ''],
        };
        $this->resetPage();
    }
    /** Book courier modal (its own component) changed an order → fresh rows */
    #[On('ops-order-updated')]
    public function refreshList(): void { unset($this->orders, $this->counts, $this->kpis); }

    public function updatedFrom(): void { $this->preset = 'custom'; }
    public function updatedTo(): void { $this->preset = 'custom'; }

    protected function baseQuery(bool $withStatus = true)
    {
        // an exchange is part of its order (shown as a stage on it), not an order of its own
        $q = Order::live()->whereNull('exchange_of_id')->select(['id', 'number', 'customer_id', 'source', 'status', 'payment_status', 'total', 'cod', 'advance', 'placed_at', 'tags']);
        $this->dateRange($q, 'placed_at');
        if ($s = trim($this->search)) {
            $n = ltrim($s, '#');
            $q->where(fn ($q) => $q->where('number', 'like', "%$n%")->orWhere('ship_address', 'like', "%$s%")->orWhereHas('exchanges', fn ($x) => $x->where('number', 'like', "%$n%"))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")->orWhere('area', 'like', "%$s%"))
                ->orWhereHas('shipment', fn ($c) => $c->where('tracking', $s)->orWhere('code', strtoupper($s))));
        }
        foreach (['source', 'payment_status'] as $f) if ($v = $this->filters[$f] ?? null) $q->where($f, $v);
        if ($c = $this->filters['courier'] ?? null) $c === 'none' ? $q->whereDoesntHave('shipment') : $q->whereHas('shipment', fn ($s) => $s->where('courier_id', $c));
        if ($this->filters['risky'] ?? null) $q->whereHas('customer', fn ($c) => $c->whereRaw('delivered_count + returned_count > 0 AND delivered_count < 0.75 * (delivered_count + returned_count)'));
        if ($withStatus && $this->status !== 'all') {
            // "group:pending" etc. from the Dashboard order-group cards
            if (str_starts_with($this->status, 'group:')) $q->whereIn('status', \App\Livewire\Ops\Dashboard::GROUPS[substr($this->status, 6)][1] ?? []);
            elseif ($this->status === 'exchange') $q->whereHas('exchanges', fn ($x) => $x->live()->whereIn('exchange_state', ['awaiting', 'out', 'collecting', 'refused'])->where('status', '!=', 'cancelled'));
            else $q->where('status', $this->status);
        }
        return $q;
    }

    #[Computed] public function counts(): array
    {
        $c = $this->baseQuery(false)->reorder()->select('status')->selectRaw('count(*) n')->groupBy('status')->pluck('n', 'status')->all();
        $c['exchange'] = $this->baseQuery(false)->reorder()->whereHas('exchanges', fn ($x) => $x->live()->whereIn('exchange_state', ['awaiting', 'out', 'collecting', 'refused'])->where('status', '!=', 'cancelled'))->count();
        return $c;
    }

    #[Computed]
    public function kpis(): array
    {
        // select() (not selectRaw) replaces baseQuery's column list → valid under ONLY_FULL_GROUP_BY
        $r = $this->baseQuery(false)->reorder()->select(\Illuminate\Support\Facades\DB::raw("COUNT(*) n, COALESCE(SUM(total),0) v, COALESCE(SUM(CASE WHEN status IN ('packed','shipped','on_hold') THEN cod END),0) cod,
            SUM(status IN ('pending','confirmed','packed')) waiting"))->setEagerLoads([])->first();
        return ['n' => (int) $r->n, 'v' => (int) $r->v, 'avg' => $r->n ? (int) round($r->v / $r->n) : 0, 'cod' => (int) $r->cod, 'waiting' => (int) $r->waiting];
    }

    #[Computed]
    public function orders()
    {
        return $this->baseQuery()->with(['customer:id,name,phone,area,delivered_count,returned_count', 'shipment:id,order_id,courier_id,tracking,courier_status,code', 'shipment.courier:id,name',
                'exchanges' => fn ($x) => $x->live()->select('id', 'number', 'exchange_of_id', 'exchange_state', 'status'), 'returns:id,order_id,shipment_id'])
            ->orderBy($this->sortField === 'id' ? 'placed_at' : $this->sortField, $this->sortDir)->paginate($this->perPage);
    }

    public function bulkStatus(string $status): void
    {
        $n = 0; foreach ($this->selected as $id) { $o = Order::find((int) $id); if ($o && $o->status !== $status) { app(\App\Services\Ops\OrderWorkflow::class)->setStatus($o, $status); $n++; } }
        $this->toast("$n orders · ".config("ops.order_status.$status.0")); $this->selected = [];
    }

    public function export()
    {
        $rows = $this->baseQuery()->when($this->selected, fn ($q) => $q->whereIn('id', $this->selected))->with('customer', 'shipment.courier', 'items.variant')->get();
        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w'); fputcsv($f, ['Order', 'Date', 'Channel', 'Customer', 'Phone', 'Area', 'Items', 'Total', 'COD', 'Payment', 'Status', 'Courier', 'Tracking']);
            foreach ($rows as $o) fputcsv($f, [$o->number, $o->placed_at->format('Y-m-d H:i'), $o->source, $o->customer->name, $o->customer->phone, $o->customer->area, $o->items->map(fn ($i) => $i->variant->sku.' x'.$i->qty)->join('; '), $o->total, $o->cod, $o->payment_status, $o->status, $o->shipment?->courier->name, $o->shipment?->tracking]);
            fclose($f);
        }, 'orders-'.now()->format('Ymd-His').'.csv');
    }

    public function render() { return view('livewire.ops.orders.index', ['couriers' => Courier::where('is_active', true)->pluck('name', 'id')]); }
}
