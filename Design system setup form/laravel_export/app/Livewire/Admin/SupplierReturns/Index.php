<?php

namespace App\Livewire\Admin\SupplierReturns;

use App\Models\{Account, CustomerReturnItem, StockMovement, SupplierReturn, Transaction};
use App\Services\StockService;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Product & Inventory', 'title' => 'Supplier returns', 'tabs' => 'inventory'])]
#[Title('Supplier returns')]
class Index extends Component
{
    use WithTable;
    #[Url] public string $view = 'returns';  // returns | pool
    #[Url] public ?int $open = null;          // detail
    public array $pick = [];                  // customer_return_item ids

    #[Computed]
    public function returns()
    {
        return SupplierReturn::with('supplier:id,name', 'items.batch.variant.product:id,name')
            ->when($this->status !== 'all', fn ($q) => $this->status === 'open' ? $q->whereNotIn('status', ['resolved', 'rejected', 'cancelled']) : $q->where('status', $this->status))
            ->when($this->search, fn ($q, $s) => $q->where('code', 'like', "%$s%"))->latest()->paginate($this->perPage);
    }

    /** Damaged customer-return items not yet on any supplier return, grouped by the batch's supplier. */
    #[Computed]
    public function pool()
    {
        return CustomerReturnItem::whereIn('condition', ['damaged', 'used'])->whereNotNull('batch_id')
            ->whereDoesntHave('supplierReturnItem')->with('batch.supplier:id,name', 'batch.variant.product:id,name', 'customerReturn:id,code')->get()->groupBy('batch.supplier_id');
    }

    #[Computed] public function current(): ?SupplierReturn { return $this->open ? SupplierReturn::with('supplier', 'items.batch.variant.product:id,name', 'items.batch.purchaseOrder:id,number')->find($this->open) : null; }

    public function createFor(int $supplierId)
    {
        $items = CustomerReturnItem::whereIn('id', $this->pick)->with('batch', 'customerReturn:id,code')->get()->where('batch.supplier_id', $supplierId);
        if ($items->isEmpty()) return;
        $sr = SupplierReturn::create(['code' => 'SR'.(SupplierReturn::max('id') + 1001), 'supplier_id' => $supplierId, 'step_dates' => ['pending' => today()->toDateString()]]);
        foreach ($items as $i) $sr->items()->create(['batch_id' => $i->batch_id, 'customer_return_item_id' => $i->id, 'source_ref' => $i->customerReturn->code, 'qty' => $i->qty, 'unit_cost' => $i->batch->unit_cost, 'reason' => $i->reason]);
        $this->reset('pick'); $this->open = $sr->id; $this->view = 'returns';
    }

    public function advance(): void
    {
        $sr = $this->current; $steps = SupplierReturn::STEPS; $next = $steps[array_search($sr->status, $steps) + 1] ?? null;
        if (! $next) return;
        $sr->update(['status' => $next, 'step_dates' => ($sr->step_dates ?? []) + [$next => today()->toDateString()]]);
        if ($next === 'resolved') {
            $value = (int) round($sr->value * (($sr->approved_qty ?? $sr->items->sum('qty')) / max(1, $sr->items->sum('qty'))));
            match ($sr->resolution) {
                'refund' => Transaction::create(['account_id' => Account::where('name', $sr->refund_account ?? 'Bank')->value('id'), 'direction' => 'in', 'category' => 'Supplier refund', 'amount' => $value, 'description' => 'Supplier refund · '.$sr->code, 'date' => today(), 'ref_type' => $sr->getMorphClass(), 'ref_id' => $sr->id]),
                'credit' => $sr->supplier->decrement('due', $value),
                'replacement' => $sr->items->each(fn ($i) => app(StockService::class)->move($i->batch, 'replacement', $i->qty, $sr)),
            };
        }
        unset($this->current); $this->dispatch('toast', message: $sr->code.' · '.config("admin.supplier_return_status.$next.0"));
    }

    public function setResolution(string $r): void { $this->current->update(['resolution' => $r]); unset($this->current); }
    public function reject(): void { $this->current->update(['status' => 'rejected']); unset($this->current); }

    public function render() { return view('livewire.admin.supplier-returns.index'); }
}
