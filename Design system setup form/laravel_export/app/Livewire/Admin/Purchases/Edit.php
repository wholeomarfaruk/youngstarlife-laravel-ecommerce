<?php

namespace App\Livewire\Admin\Purchases;

use App\Models\{Batch, ProductVariant, PurchaseOrder, Supplier};
use App\Services\ReceivingService;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Product & Inventory', 'title' => 'Purchase order', 'tabs' => 'inventory'])]
class Edit extends Component
{
    public PurchaseOrder $po;
    #[Url] public string $step = 'edit';     // edit | receive | labels
    public array $items = [];                // [id, variant_id, qty, unit_cost, received]
    public ?int $pick = null; public int $pickQty = 10;
    public array $receive = [];              // item_id => [now, damaged]
    public array $labelQty = [];             // batch_id => n
    public ?int $supplier_id = null; public ?string $expected_at = null; public int $paid = 0;

    public function mount(PurchaseOrder $po): void
    {
        $this->po = $po->load('supplier', 'items.variant.product:id,name', 'batches.variant.product:id,name', 'receipts');
        $this->items = $po->items->map(fn ($i) => ['id' => $i->id, 'variant_id' => $i->variant_id, 'label' => $i->variant->label, 'qty' => $i->qty, 'unit_cost' => $i->unit_cost, 'received' => $i->received])->all();
        $this->supplier_id = $po->supplier_id; $this->expected_at = $po->expected_at?->toDateString(); $this->paid = $po->paid;
        $this->labelQty = $po->batches->whereNull('labels_printed_at')->mapWithKeys(fn ($b) => [$b->id => $b->qty])->all();
    }

    #[Computed] public function variants() { return ProductVariant::with('product:id,name')->where('is_active', true)->get(['id', 'product_id', 'sku', 'color', 'size', 'stock']); }

    public function addItem(): void
    {
        if (! $this->pick) return;
        $cost = Batch::where('variant_id', $this->pick)->latest('received_at')->value('unit_cost') ?? 0;  // pre-fill last cost
        $v = $this->variants->firstWhere('id', $this->pick);
        $this->items[] = ['id' => null, 'variant_id' => $v->id, 'label' => $v->label, 'qty' => $this->pickQty, 'unit_cost' => $cost, 'received' => 0];
        $this->pick = null;
    }

    public function save(bool $send = false): void
    {
        abort_unless(in_array($this->po->status, ['draft', 'sent']), 403);
        $this->validate(['supplier_id' => 'required|exists:suppliers,id', 'items' => 'required|array|min:1', 'items.*.qty' => 'required|integer|min:1', 'items.*.unit_cost' => 'required|integer|min:1']);
        $keep = [];
        foreach ($this->items as $i) $keep[] = $this->po->items()->updateOrCreate(['id' => $i['id']], collect($i)->only('variant_id', 'qty', 'unit_cost')->all())->id;
        $this->po->items()->whereNotIn('id', $keep)->where('received', 0)->delete();
        $this->po->update(['status' => $send && $this->po->status === 'draft' ? 'sent' : $this->po->status] + ['supplier_id' => $this->supplier_id, 'expected_at' => $this->expected_at, 'paid' => $this->paid]);
        $this->mount($this->po->fresh());
        $this->dispatch('toast', message: $send ? 'Sent to '.$this->po->supplier->name : 'Saved');
    }

    public function confirmReceive(ReceivingService $svc): void
    {
        $grn = $svc->receive($this->po, $this->receive);
        $this->reset('receive'); $this->mount($this->po->fresh()); $this->step = 'labels';
        $this->dispatch('toast', message: $grn->number.' · batches created');
    }

    public function removeItem(int $k): void { if (! ($this->items[$k]['received'] ?? 0)) { unset($this->items[$k]); $this->items = array_values($this->items); } }

    public function closeShort(): void { $this->po->update(['status' => 'received', 'closed_short' => true]); $this->mount($this->po->fresh()); }

    public function markPrinted(): void { Batch::whereIn('id', array_keys(array_filter($this->labelQty)))->update(['labels_printed_at' => now()]); $this->dispatch('toast', message: array_sum($this->labelQty).' labels sent to printer'); }

    public function render() { return view('livewire.admin.purchases.edit', ['suppliers' => Supplier::orderBy('name')->pluck('name', 'id')])->title($this->po->number); }
}
