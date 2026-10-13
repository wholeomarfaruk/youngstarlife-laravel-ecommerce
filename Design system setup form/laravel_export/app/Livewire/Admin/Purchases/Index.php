<?php

namespace App\Livewire\Admin\Purchases;

use App\Models\{PurchaseOrder, Supplier, Warehouse};
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Product & Inventory', 'title' => 'Purchases', 'tabs' => 'inventory'])]
#[Title('Purchases')]
class Index extends Component
{
    use WithTable;

    #[Computed]
    public function orders()
    {
        return PurchaseOrder::with('supplier:id,name')->withSum('items as qty_total', 'qty')->withSum('items as qty_received', 'received')
            ->selectSub('select coalesce(sum(qty*unit_cost),0) from purchase_order_items where purchase_order_id = purchase_orders.id', 'total')
            ->when($this->search, fn ($q, $s) => $q->where('number', 'like', "%$s%")->orWhereHas('supplier', fn ($x) => $x->where('name', 'like', "%$s%")))
            ->when($this->status !== 'all', fn ($q) => $this->status === 'open' ? $q->whereIn('status', ['sent', 'partial']) : $q->where('status', $this->status))
            ->latest('ordered_at')->paginate($this->perPage);
    }

    public function newOrder(?int $supplier = null)
    {
        $po = PurchaseOrder::create(['number' => 'PO-'.(PurchaseOrder::max('id') + 101), 'supplier_id' => $supplier ?? Supplier::value('id'), 'warehouse_id' => Warehouse::where('is_default', true)->value('id') ?? Warehouse::value('id'), 'ordered_at' => today(), 'expected_at' => today()->addWeek(), 'created_by' => auth()->id()]);
        return $this->redirectRoute('admin.purchases.edit', $po, navigate: true);
    }

    public function render() { return view('livewire.admin.purchases.index'); }
}
