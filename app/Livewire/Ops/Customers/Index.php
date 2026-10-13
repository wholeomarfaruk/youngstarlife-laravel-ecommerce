<?php

namespace App\Livewire\Ops\Customers;

use App\Models\Ops\{Customer, Order, Shipment};
use App\Livewire\Ops\Concerns\WithTable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Users', 'title' => 'Customers', 'tabs' => 'parties'])]
#[Title('Customers')]
class Index extends Component
{
    use WithTable;
    #[Url(except: 'spent')] public string $sort = 'spent';   // spent | orders | last | rate | due
    public ?int $expanded = null;
    public string $note = ''; public string $tag = ''; public string $sms = ''; public array $smsTo = []; public ?int $ledgerFor = null;

    protected const RATE = '(delivered_count / NULLIF(delivered_count + returned_count, 0))';

    protected function base()
    {
        return Customer::real()
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")->orWhere('area', 'like', "%$s%")))
            ->when($this->filters['zone'] ?? null, fn ($q, $z) => $q->whereHas('orders', fn ($o) => $o->whereHas('zone', fn ($zz) => $zz->where('name', 'like', ($z === 'inside' ? 'Inside' : 'Outside').'%'))))
            ->when($this->filters['source'] ?? null, fn ($q, $s) => $q->where('source', $s));
    }

    protected function applySegment($q, string $s)
    {
        return match ($s) {
            'vip' => $q->where('total_spent', '>=', 12000), 'repeat' => $q->where('orders_count', '>=', 3)->whereRaw(self::RATE.' >= 0.75'),
            'new' => $q->where('orders_count', '<=', 3)->whereYear('created_at', now()->year), 'risky' => $q->whereRaw(self::RATE.' < 0.75'),
            'due' => $q->where('due', '>', 0), default => $q,
        };
    }

    #[Computed] public function counts(): array { return collect(['all', 'vip', 'repeat', 'new', 'risky', 'due'])->mapWithKeys(fn ($s) => [$s => $this->applySegment($this->base(), $s)->count()])->all(); }

    #[Computed]
    public function rows()
    {
        $q = $this->applySegment($this->base(), $this->status);
        $q = match ($this->sort) { 'orders' => $q->orderByDesc('orders_count'), 'last' => $q->orderByDesc('last_order_at'), 'rate' => $q->orderByRaw(self::RATE.' IS NULL, '.self::RATE.' asc'), 'due' => $q->orderByDesc('due'), default => $q->orderByDesc('total_spent') };
        return $q->paginate($this->perPage);
    }

    #[Computed]
    public function kpis(): array
    {
        $c = Customer::real();
        $closed = (int) (clone $c)->sum(DB::raw('delivered_count + returned_count'));
        $orders = Order::live()->whereNotIn('status', ['cancelled']);
        return [
            'customers' => (clone $c)->count(), 'new' => (clone $c)->where('created_at', '>=', now()->startOfMonth())->count(),
            'repeat' => ($n = (clone $c)->where('orders_count', '>', 0)->count()) ? round((clone $c)->where('orders_count', '>=', 2)->count() / $n * 100) : 0,
            'aov' => (int) (clone $orders)->avg('total'), 'lifetime' => (int) (clone $c)->sum('total_spent'), 'due' => (int) (clone $c)->sum('due'),
            'risky' => (clone $c)->whereRaw(self::RATE.' < 0.75')->count(), 'rate' => $closed ? round((clone $c)->sum('delivered_count') / $closed * 100) : 0,
        ];
    }

    #[Computed] public function detail(): ?Customer { return $this->expanded ? Customer::with(['user:id,name', 'orders' => fn ($q) => $q->live()->latest('placed_at')->limit(8)])->find($this->expanded) : null; }

    /** delivered vs returned per courier for this customer (internal courier history) */
    #[Computed]
    public function fraud()
    {
        if (! $this->expanded) return collect();
        return Shipment::whereHas('order', fn ($o) => $o->where('customer_id', $this->expanded))->join('ops_couriers as c', 'c.id', '=', 'ops_shipments.courier_id')
            ->selectRaw("c.name, SUM(courier_status='delivered') delivered, SUM(courier_status IN ('returned','returning','cancelled')) returned, COUNT(*) total")->groupBy('c.name')->get();
    }

    public function toggle(int $id): void { $this->expanded = $this->expanded === $id ? null : $id; $this->note = (string) $this->detail?->note; unset($this->fraud); }

    public function saveNote(): void { $this->detail->update(['note' => $this->note ?: null]); ops_log('Note on customer '.$this->detail->name, $this->detail); $this->toast('Note saved'); }

    public function block(?int $id = null): void
    {
        $ids = $id ? [$id] : array_map('intval', $this->selected);
        $bl = app(\App\Services\Ops\Blacklist::class); $bad = 0;
        foreach (Customer::whereIn('id', $ids)->get() as $c) {
            if ($c->is_blocked) { $bl->unblockCustomer($c); continue; }
            try { $bl->blockCustomer($c, ['reason' => 'other', 'note' => 'Blocked from the Customers page'], true); } catch (\InvalidArgumentException) { $bad++; }
        }
        $this->toast(count($ids).' customer(s) updated · see Users → Blacklist'.($bad ? " · $bad without a valid phone skipped" : ''), $bad ? 'bad' : 'ok'); $this->selected = []; unset($this->detail, $this->rows);
    }

    public function addTag(): void
    {
        $this->validate(['tag' => 'required|max:30']);
        foreach (Customer::whereIn('id', $this->selected)->get() as $c) $c->update(['tags' => array_values(array_unique(array_merge($c->tags ?? [], [$this->tag])))]);
        $this->toast('Tag “'.$this->tag.'” added to '.count($this->selected).' customers'); $this->reset('tag'); $this->selected = []; $this->dispatch('close-modal');
    }

    public function openSms(?int $id = null): void { $this->smsTo = $id ? [$id] : array_map('intval', $this->selected); $this->sms = ''; $this->dispatch('open-modal', name: 'sms'); }

    /** No SMS gateway is connected yet → message is logged against the customers (demo until a gateway is added in Settings). */
    public function sendSms(): void
    {
        $this->validate(['sms' => 'required|max:320']);
        $n = Customer::whereIn('id', $this->smsTo)->count();
        ops_log("SMS to $n customer(s): ".\Illuminate\Support\Str::limit($this->sms, 120));
        $this->dispatch('close-modal'); $this->toast("SMS queued for $n customer(s) · gateway not connected (demo)"); $this->selected = [];
    }

    #[Computed] public function ledger() { return $this->ledgerFor ? Order::live()->where('customer_id', $this->ledgerFor)->with('returns:id,order_id,refund_amount,refunded_at')->latest('placed_at')->get(['id', 'number', 'placed_at', 'total', 'advance', 'cod', 'status', 'payment_status']) : collect(); }
    public function openLedger(int $id): void { $this->ledgerFor = $id; unset($this->ledger); $this->dispatch('open-modal', name: 'ledger'); }

    public function export()
    {
        $rows = $this->applySegment($this->base(), $this->status)->when($this->selected, fn ($q) => $q->whereIn('id', $this->selected))->get();
        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w'); fputcsv($f, ['Name', 'Phone', 'Area', 'Source', 'Segments', 'Orders', 'Delivered', 'Returned', 'Total spent', 'Due', 'Last order', 'Blocked']);
            foreach ($rows as $c) fputcsv($f, [$c->name, $c->phone, $c->area, $c->source, implode(' ', $c->segments), $c->orders_count, $c->delivered_count, $c->returned_count, $c->total_spent, $c->due, $c->last_order_at?->toDateString(), $c->is_blocked ? 'yes' : '']);
            fclose($f);
        }, 'customers-'.now()->format('Ymd-His').'.csv');
    }

    public function render() { return view('livewire.ops.customers.index'); }
}
