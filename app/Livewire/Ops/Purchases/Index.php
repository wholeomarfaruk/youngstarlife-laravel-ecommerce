<?php

namespace App\Livewire\Ops\Purchases;

use App\Models\Ops\{Batch, PurchaseOrder, Supplier, Warehouse};
use App\Livewire\Ops\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Product & Inventory', 'title' => 'Purchases', 'tabs' => 'inventory'])]
#[Title('Purchases')]
class Index extends Component
{
    use WithTable;
    #[Url(except: null)] public ?int $supplier = null;   // pre-filled from Suppliers → New purchase order

    protected function base()
    {
        return PurchaseOrder::query()
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', "%$s%")->orWhereHas('supplier', fn ($x) => $x->where('name', 'like', "%$s%"))))
            ->when($this->filters['supplier'] ?? null, fn ($q, $s) => $q->where('supplier_id', $s));
    }

    protected function applyStatus($q, string $s) { return match ($s) { 'all' => $q, 'open' => $q->whereIn('status', ['sent', 'partial']), default => $q->where('status', $s) }; }

    #[Computed] public function counts(): array { return collect(['all', 'draft', 'open', 'received', 'cancelled'])->mapWithKeys(fn ($s) => [$s => $this->applyStatus($this->base(), $s)->count()])->all(); }

    #[Computed]
    public function orders()
    {
        $q = $this->applyStatus($this->base(), $this->status)->with('supplier:id,name', 'items.variant.product:id,name')->withSum('items as qty_total', 'qty')->withSum('items as qty_received', 'received')
            ->withCount(['batches as labels_due' => fn ($b) => $b->whereNull('labels_printed_at')])
            ->selectSub('select coalesce(sum(qty*unit_cost),0) from ops_purchase_order_items where purchase_order_id = ops_purchase_orders.id', 'total')
            ->selectSub('select coalesce(sum((received-damaged)*unit_cost),0) from ops_purchase_order_items where purchase_order_id = ops_purchase_orders.id', 'received_value');
        $this->dateRange($q, 'ordered_at');
        return $q->latest('ordered_at')->latest('id')->paginate($this->perPage);
    }

    #[Computed]
    public function stats(): array
    {
        $open = PurchaseOrder::whereIn('status', ['sent', 'partial']);
        $sum = fn ($q, $expr) => (int) \App\Models\Ops\PurchaseOrderItem::whereIn('purchase_order_id', (clone $q)->select('id'))->selectRaw("COALESCE(SUM($expr),0) v")->value('v');
        return [
            'open' => (clone $open)->count(), 'to_arrive' => $sum($open, 'GREATEST(qty - received, 0)'), 'overdue' => (clone $open)->whereDate('expected_at', '<', today())->count(),
            'on_order' => $sum($open, 'GREATEST(qty - received, 0) * unit_cost'),
            'received_month' => (int) Batch::where('received_at', '>=', now()->startOfMonth())->whereNotNull('purchase_order_id')->selectRaw('COALESCE(SUM((qty - damaged) * unit_cost),0) v')->value('v'),
            'labels' => Batch::whereNull('labels_printed_at')->where('remaining', '>', 0)->count(),
        ];
    }

    public function newOrder(?int $supplier = null)
    {
        if (! Supplier::exists()) { $this->toast('Add a supplier first (Users → Add user → Supplier)', 'bad'); return; }
        $po = PurchaseOrder::create(['number' => 'PO-'.(PurchaseOrder::max('id') + 101), 'supplier_id' => $supplier ?? $this->supplier ?? Supplier::where('is_active', true)->value('id') ?? Supplier::value('id'),
            'warehouse_id' => Warehouse::where('is_default', true)->value('id') ?? Warehouse::value('id') ?? Warehouse::create(['name' => 'Main warehouse', 'is_default' => true])->id,
            'ordered_at' => today(), 'expected_at' => today()->addWeek(), 'created_by' => auth()->id()]);
        ops_log('Created '.$po->number, $po);
        return $this->redirectRoute('ops.purchases.edit', $po, navigate: true);
    }

    public function mount(): void { if ($this->supplier && request()->boolean('new')) $this->newOrder($this->supplier); }

    public function render() { return view('livewire.ops.purchases.index', ['suppliers' => Supplier::orderBy('name')->pluck('name', 'id')]); }
}
