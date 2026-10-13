<?php

namespace App\Livewire\Ops\SupplierReturns;

use App\Models\Ops\{Account, CustomerReturnItem, ProductVariant, Supplier, SupplierReturn, SupplierReturnItem, Transaction};
use App\Services\Ops\StockService;
use App\Livewire\Ops\Concerns\WithTable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Product & Inventory', 'title' => 'Supplier returns', 'tabs' => 'inventory'])]
#[Title('Supplier returns')]
class Index extends Component
{
    use WithTable;
    #[Url] public string $view = 'returns';  // returns | pool
    #[Url] public ?int $open = null;          // detail
    public array $pick = [];                  // customer_return_item ids
    public array $d = ['refund_account' => 'Bank', 'approved_qty' => null, 'ship_via' => '', 'tracking' => '', 'note' => ''];

    protected function base()
    {
        return SupplierReturn::query()->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('code', 'like', "%$s%")->orWhereHas('supplier', fn ($x) => $x->where('name', 'like', "%$s%"))))
            ->when($this->filters['supplier'] ?? null, fn ($q, $s) => $q->where('supplier_id', $s));
    }
    protected function applyStatus($q, string $s) { return match ($s) { 'all' => $q, 'open' => $q->whereNotIn('status', ['resolved', 'rejected', 'cancelled']), default => $q->where('status', $s) }; }

    #[Computed] public function counts(): array { return collect(['all', 'open', ...array_keys(config('ops.supplier_return_status'))])->mapWithKeys(fn ($s) => [$s => $this->applyStatus($this->base(), $s)->count()])->all(); }

    #[Computed]
    public function returns() { return $this->applyStatus($this->base(), $this->status)->with('supplier:id,name', 'items.batch.variant.product:id,name')->latest()->paginate($this->perPage); }

    #[Computed]
    public function stats(): array
    {
        $open = SupplierReturn::whereNotIn('status', ['resolved', 'rejected', 'cancelled']);
        $out = SupplierReturnItem::whereIn('supplier_return_id', (clone $open)->whereIn('status', ['sent', 'received', 'approved'])->select('id'));
        return [
            'open' => (clone $open)->count(), 'units' => (int) (clone $out)->sum('qty'), 'value' => (int) (clone $out)->selectRaw('COALESCE(SUM(qty*unit_cost),0) v')->value('v'),
            'recovered' => (int) SupplierReturnItem::whereIn('supplier_return_id', SupplierReturn::where('status', 'resolved')->select('id'))->selectRaw('COALESCE(SUM(qty*unit_cost),0) v')->value('v'),
            'awaiting' => SupplierReturn::whereIn('status', ['sent', 'received'])->count(), 'rejected' => SupplierReturn::where('status', 'rejected')->count(),
        ];
    }

    /** Damaged customer-return items not yet on any supplier return, grouped by the batch's supplier. */
    #[Computed]
    public function pool()
    {
        return CustomerReturnItem::whereIn('condition', ['damaged', 'used', 'broken'])->whereNotNull('batch_id')
            ->whereDoesntHave('supplierReturnItem')->with('batch.supplier:id,name', 'batch.purchaseOrder:id,number', 'batch.variant.product:id,name', 'customerReturn:id,code')->get()->groupBy('batch.supplier_id');
    }

    #[Computed] public function current(): ?SupplierReturn { return $this->open ? SupplierReturn::with('supplier', 'items.batch.variant.product:id,name', 'items.batch.purchaseOrder:id,number')->find($this->open) : null; }

    public function updatedOpen(): void { $this->loadDetails(); }
    public function mount(): void { $this->loadDetails(); }
    protected function loadDetails(): void
    {
        unset($this->current);
        if ($sr = $this->current) $this->d = ['refund_account' => $sr->refund_account ?: 'Bank', 'approved_qty' => $sr->approved_qty, 'ship_via' => (string) $sr->ship_via, 'tracking' => (string) $sr->tracking, 'note' => (string) $sr->note];
    }

    public function createFor(int $supplierId)
    {
        $pool = collect($this->pool->get($supplierId, []));
        $picked = $pool->whereIn('id', array_map('intval', $this->pick));
        $items = $picked->isNotEmpty() ? $picked : $pool;   // nothing ticked for this supplier → return all of its damaged stock
        if ($items->isEmpty()) { $this->dispatch('toast', message: 'Nothing to return for this supplier', type: 'bad'); return; }
        $sr = SupplierReturn::create(['code' => 'SR'.(SupplierReturn::max('id') + 1001), 'supplier_id' => $supplierId, 'step_dates' => ['pending' => today()->toDateString()]]);
        foreach ($items as $i) $sr->items()->create(['batch_id' => $i->batch_id, 'customer_return_item_id' => $i->id, 'source_ref' => $i->customerReturn->code, 'qty' => $i->qty, 'unit_cost' => $i->batch->unit_cost, 'reason' => $i->reason]);
        ops_log('Created supplier return '.$sr->code, $sr); ops_notify('srUpdate', $sr->code.' created for '.$sr->supplier->name, route('ops.supplier-returns.index', ['open' => $sr->id]));
        $this->reset('pick'); $this->open = $sr->id; $this->view = 'returns'; $this->loadDetails();
    }

    public function saveDetails(): void
    {
        $sr = $this->current; abort_unless($sr, 404);
        $this->validate(['d.approved_qty' => 'nullable|integer|min:0|max:'.$sr->items->sum('qty'), 'd.refund_account' => 'required|in:'.implode(',', config('ops.accounts'))]);
        $sr->update(['refund_account' => $this->d['refund_account'], 'approved_qty' => $this->d['approved_qty'] === '' ? null : $this->d['approved_qty'], 'ship_via' => $this->d['ship_via'] ?: null, 'tracking' => $this->d['tracking'] ?: null, 'note' => $this->d['note'] ?: null]);
        unset($this->current); $this->dispatch('toast', message: 'Saved');
    }

    public function advance(): void
    {
        $sr = $this->current; $steps = SupplierReturn::STEPS; $next = $steps[array_search($sr->status, $steps) + 1] ?? null;
        if (! $next) return;
        $this->saveDetails(); $sr = $this->current;
        DB::transaction(function () use ($sr, $next) {
            $sr->update(['status' => $next, 'step_dates' => ($sr->step_dates ?? []) + [$next => today()->toDateString()]] + ($next === 'resolved' ? ['resolved_at' => now()] : []));
            if ($next === 'sent') foreach ($sr->items as $i) {   // damaged units physically leave the warehouse
                $take = min($i->qty, $i->batch->damaged);
                if ($take > 0) { $i->batch->decrement('damaged', $take); ProductVariant::whereKey($i->batch->variant_id)->decrement('damaged', $take); }
            }
            if ($next === 'resolved') {
                $qty = $sr->approved_qty ?? $sr->items->sum('qty');
                $value = (int) round($sr->value * ($qty / max(1, $sr->items->sum('qty'))));
                match ($sr->resolution) {
                    'refund' => Transaction::create(['account_id' => Account::where('name', $sr->refund_account ?? 'Bank')->value('id'), 'direction' => 'in', 'category' => 'Supplier refund', 'amount' => $value, 'description' => 'Supplier refund · '.$sr->code, 'date' => today(), 'ref_type' => $sr->getMorphClass(), 'ref_id' => $sr->id, 'user_id' => auth()->id()]),
                    'credit' => $sr->supplier->decrement('due', $value),
                    'replacement' => $sr->items->each(function ($i) use ($sr, $qty) {   // new stock into the original batch
                        $units = (int) min($i->qty, $qty); if ($units > 0) app(StockService::class)->move($i->batch, 'replacement', $units, $sr, 'Replacement from supplier');
                    }),
                };
            }
        });
        $label = config("ops.supplier_return_status.$next.0");
        ops_log($sr->code.' → '.$label, $sr); ops_notify('srUpdate', $sr->code.' · '.$label, route('ops.supplier-returns.index', ['open' => $sr->id]));
        unset($this->current, $this->stats); $this->dispatch('toast', message: $sr->code.' · '.$label);
    }

    public function setResolution(string $r): void { abort_unless(array_key_exists($r, config('ops.supplier_return_resolutions')), 422); $this->current->update(['resolution' => $r]); unset($this->current); }
    public function reject(): void { $this->current->update(['status' => 'rejected', 'step_dates' => ($this->current->step_dates ?? []) + ['rejected' => today()->toDateString()]]); ops_log($this->current->code.' rejected by supplier', $this->current); ops_notify('srUpdate', $this->current->code.' rejected by supplier', route('ops.supplier-returns.index', ['open' => $this->open])); unset($this->current); }
    public function cancel(): void
    {
        $sr = $this->current; abort_unless($sr && in_array($sr->status, ['pending', 'sent']), 422);
        if ($sr->status === 'sent') foreach ($sr->items as $i) { $i->batch->increment('damaged', $i->qty); ProductVariant::whereKey($i->batch->variant_id)->increment('damaged', $i->qty); }   // back on our shelf
        $sr->update(['status' => 'cancelled']); $sr->items()->whereNotNull('customer_return_item_id')->delete();   // items go back to the pool
        ops_log($sr->code.' cancelled', $sr); unset($this->current); $this->dispatch('toast', message: $sr->code.' cancelled');
    }

    public function render() { return view('livewire.ops.supplier-returns.index', ['suppliers' => Supplier::orderBy('name')->pluck('name', 'id')]); }
}
