<?php

namespace App\Livewire\Ops\Purchases;

use App\Models\Ops\{Account, Batch, OrderItem, ProductVariant, PurchaseOrder, Supplier, Transaction, Warehouse};
use App\Services\Ops\ReceivingService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Layout, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Product & Inventory', 'title' => 'Purchase order', 'tabs' => 'inventory'])]
class Edit extends Component
{
    public PurchaseOrder $po;
    #[Url] public string $step = 'edit';     // edit | receive | labels
    public array $items = [];                // [id, variant_id, label, sku, stock, qty, unit_cost, received]
    public ?int $pick = null; public $pickQty = 10;   // untyped: an emptied number input sends ''
    public array $receive = [];              // item_id => [now, damaged, label]
    public array $labelQty = [];             // batch_id => n
    public string $labelSize = '50x30'; public bool $labelPrice = true;
    public ?int $supplier_id = null; public ?int $warehouse_id = null; public ?string $ordered_at = null; public ?string $expected_at = null; public ?string $note = null;
    public $paid = 0; public string $paid_from = 'Cash';

    public function mount(PurchaseOrder $po): void
    {
        $this->po = $po->load('supplier', 'items.variant.product:id,name', 'batches.variant.product:id,name,price,sale_price', 'receipts.batches', 'warehouse');
        $this->items = $po->items->map(fn ($i) => ['id' => $i->id, 'variant_id' => $i->variant_id, 'label' => $i->variant->label, 'sku' => $i->variant->sku, 'stock' => $i->variant->stock, 'qty' => $i->qty, 'unit_cost' => $i->unit_cost, 'received' => $i->received])->all();
        $this->supplier_id = $po->supplier_id; $this->warehouse_id = $po->warehouse_id; $this->ordered_at = $po->ordered_at?->toDateString(); $this->expected_at = $po->expected_at?->toDateString(); $this->note = $po->note;
        $this->paid = $po->paid; $this->paid_from = $po->paid_from ?: 'Cash';
        $this->labelQty = $po->batches->whereNull('labels_printed_at')->mapWithKeys(fn ($b) => [$b->id => max(0, $b->qty - $b->damaged)])->all();   // default = good pcs
        $this->receive = $po->items->mapWithKeys(fn ($i) => [$i->id => ['now' => null, 'damaged' => null, 'label' => true]])->all();
        $this->labelSize = ops_setting('barcode.size', '50x30'); $this->labelPrice = (bool) ops_setting('barcode.price_on_label', true);
    }

    #[Computed] public function variants() { return ProductVariant::with('product:id,name')->where('is_active', true)->get(['id', 'product_id', 'sku', 'color', 'size', 'stock']); }
    #[Computed] public function editable(): bool { return in_array($this->po->status, ['draft', 'sent']); }

    public function addItem(): void
    {
        if (! $this->pick) return;
        $v = $this->variants->firstWhere('id', $this->pick);
        $this->items[] = ['id' => null, 'variant_id' => $v->id, 'label' => $v->label, 'sku' => $v->sku, 'stock' => $v->stock, 'qty' => max(1, (int) $this->pickQty), 'unit_cost' => $this->lastCost($v->id), 'received' => 0];
        $this->pick = null;
    }

    protected function lastCost(int $variantId): int { return (int) (Batch::where('variant_id', $variantId)->latest('received_at')->value('unit_cost') ?? 0); }   // pre-fill last batch cost

    /** low-stock variants (of this supplier's products first) → reorder qty for ~21 days of sales */
    public function addLowStock(): void
    {
        $added = 0;
        $low = ProductVariant::with('product:id,name,low_stock_threshold')->whereColumn('stock', '<', \DB::raw('(select low_stock_threshold from ops_products where ops_products.id = ops_product_variants.product_id)'))
            ->whereNotIn('id', collect($this->items)->pluck('variant_id'))->get()
            ->sortByDesc(fn ($v) => Batch::where('variant_id', $v->id)->where('supplier_id', $this->supplier_id)->exists());
        foreach ($low as $v) {
            $sold30 = (int) OrderItem::where('variant_id', $v->id)->whereHas('order', fn ($o) => $o->where('is_draft', false)->where('placed_at', '>=', now()->subDays(30))->where('status', '!=', 'cancelled'))->sum('qty');
            $qty = max($v->product->low_stock_threshold * 2 - $v->stock, (int) ceil($sold30 / 30 * 21) - $v->stock, 5);
            $this->items[] = ['id' => null, 'variant_id' => $v->id, 'label' => $v->label, 'sku' => $v->sku, 'stock' => $v->stock, 'qty' => $qty, 'unit_cost' => $this->lastCost($v->id), 'received' => 0]; $added++;
        }
        $this->dispatch('toast', message: $added ? "$added low-stock items added" : 'No low-stock items to add', type: $added ? 'ok' : 'bad');
    }

    public function removeItem(int $k): void { if (! ($this->items[$k]['received'] ?? 0)) { unset($this->items[$k]); $this->items = array_values($this->items); } }

    public function save(bool $send = false): void
    {
        abort_if($this->po->status === 'cancelled', 403);
        $editable = $this->editable;   // items/supplier lock once stock arrives; payment can still be recorded
        $this->validate(['supplier_id' => 'required|exists:ops_suppliers,id', 'warehouse_id' => 'required|exists:ops_warehouses,id', 'ordered_at' => 'required|date', 'expected_at' => 'nullable|date',
            'paid' => 'required|integer|min:0', 'paid_from' => 'required|exists:ops_accounts,name']
            + ($editable ? ['items' => 'required|array|min:1', 'items.*.qty' => 'required|integer|min:1', 'items.*.unit_cost' => 'required|integer|min:1'] : []),
            ['items.required' => 'Add at least one item.', 'items.*.unit_cost.min' => 'Enter a unit cost for every item.']);
        DB::transaction(function () use ($editable, $send) {
            if ($editable) {
                $keep = [];
                foreach ($this->items as $i) $keep[] = $this->po->items()->updateOrCreate(['id' => $i['id']], collect($i)->only('variant_id', 'qty', 'unit_cost')->all())->id;
                $this->po->items()->whereNotIn('id', $keep)->where('received', 0)->delete();
            }
            // "Paid now" change → money out of the chosen account and off the supplier's due
            if ($delta = (int) $this->paid - $this->po->paid) {
                Transaction::create(['account_id' => Account::where('name', $this->paid_from)->value('id'), 'direction' => $delta > 0 ? 'out' : 'in', 'category' => 'Supplier',
                    'amount' => abs($delta), 'description' => 'Supplier payment · '.$this->po->number.' · '.Supplier::whereKey($this->supplier_id)->value('name'),
                    'date' => today(), 'ref_type' => $this->po->getMorphClass(), 'ref_id' => $this->po->id, 'user_id' => auth()->id()]);
                Supplier::whereKey($this->supplier_id)->decrement('due', $delta);
            }
            $this->po->update(['status' => $send && $this->po->status === 'draft' ? 'sent' : $this->po->status, 'expected_at' => $this->expected_at ?: null, 'note' => $this->note, 'paid' => (int) $this->paid, 'paid_from' => $this->paid_from]
                + ($editable ? ['warehouse_id' => $this->warehouse_id, 'ordered_at' => $this->ordered_at] : [])
                + ($this->po->status === 'draft' ? ['supplier_id' => $this->supplier_id] : []));   // supplier locked after send
        });
        ops_log(($send ? 'Sent ' : 'Saved ').$this->po->number, $this->po);
        $this->mount($this->po->fresh());
        $this->dispatch('saved');
        $this->dispatch('toast', message: $send ? 'Sent to '.$this->po->supplier->name : 'Saved');
    }

    public function duplicate()
    {
        $new = $this->po->replicate(['number', 'status', 'closed_short', 'paid', 'paid_from']);
        $new->fill(['number' => 'PO-'.(PurchaseOrder::max('id') + 101), 'status' => 'draft', 'ordered_at' => today(), 'expected_at' => today()->addWeek(), 'paid' => 0, 'created_by' => auth()->id()])->save();
        foreach ($this->po->items as $i) $new->items()->create(['variant_id' => $i->variant_id, 'qty' => $i->qty, 'unit_cost' => $i->unit_cost]);
        ops_log('Duplicated '.$this->po->number.' → '.$new->number, $new);
        return $this->redirectRoute('ops.purchases.edit', $new, navigate: true);
    }

    public function cancel(): void
    {
        if ($this->po->items()->where('received', '>', 0)->exists()) { $this->dispatch('toast', message: 'Stock already received — use Close short instead', type: 'bad'); return; }
        $this->po->update(['status' => 'cancelled']); ops_log('Cancelled '.$this->po->number, $this->po);
        $this->mount($this->po->fresh()); $this->dispatch('toast', message: $this->po->number.' cancelled');
    }

    public function receiveAll(): void { foreach ($this->po->items as $i) $this->receive[$i->id] = ['now' => $i->remaining, 'damaged' => $this->receive[$i->id]['damaged'] ?? null, 'label' => true]; }

    public function confirmReceive(ReceivingService $svc): void
    {
        $lines = collect($this->receive)->map(fn ($l) => ['now' => max(0, (int) ($l['now'] ?? 0)), 'damaged' => max(0, (int) ($l['damaged'] ?? 0)), 'label' => (bool) ($l['label'] ?? true)]);
        if ($lines->sum('now') <= 0) { $this->dispatch('toast', message: 'Enter how many pieces arrived', type: 'bad'); return; }
        foreach ($this->po->items as $i) {
            $l = $lines[$i->id] ?? null;
            if ($l && $l['now'] > $i->remaining) { $this->dispatch('toast', message: $i->variant->label.': only '.$i->remaining.' pcs still to arrive', type: 'bad'); return; }
            if ($l && $l['damaged'] > $l['now']) { $this->dispatch('toast', message: $i->variant->label.': damaged can\'t exceed arrived', type: 'bad'); return; }
        }
        $grn = $svc->receive($this->po, $lines->all());
        $skip = $lines->filter(fn ($l) => ! $l['label'])->keys();   // lines without the print-label tick
        $this->mount($this->po->fresh()); $this->step = 'labels';
        foreach ($grn->batches()->get() as $b) if ($skip->contains($this->po->items->firstWhere('variant_id', $b->variant_id)?->id)) $this->labelQty[$b->id] = 0;
        $this->dispatch('toast', message: $grn->number.' · '.$grn->batches()->count().' batches created');
    }

    public function closeShort(): void { $this->po->update(['status' => 'received', 'closed_short' => true]); ops_log('Closed '.$this->po->number.' short', $this->po); $this->mount($this->po->fresh()); $this->dispatch('toast', message: 'Closed short'); }

    public function markPrinted(): void
    {
        $q = array_filter(array_map('intval', $this->labelQty));
        if (! $q) return;
        Batch::whereIn('id', array_keys($q))->update(['labels_printed_at' => now()]);
        $this->po->load('batches.variant.product:id,name,price,sale_price');
        ops_log(array_sum($q).' labels printed for '.$this->po->number, $this->po);
        $this->dispatch('toast', message: array_sum($q).' labels sent to printer');
    }

    public function skipLabels() { return $this->redirectRoute('ops.purchases.index', navigate: true); }

    public function render()
    {
        return view('livewire.ops.purchases.edit', ['suppliers' => Supplier::orderBy('name')->pluck('name', 'id'), 'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id')])->title($this->po->number);
    }
}
