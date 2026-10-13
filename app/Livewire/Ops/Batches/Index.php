<?php

namespace App\Livewire\Ops\Batches;

use App\Models\Ops\{Batch, CustomerReturnItem, OrderItemAllocation, Product, Supplier, SupplierReturn, Warehouse};
use App\Services\Ops\StockService;
use App\Livewire\Ops\Concerns\WithTable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Product & Inventory', 'title' => 'Batches', 'tabs' => 'inventory'])]
#[Title('Batches')]
class Index extends Component
{
    use WithTable;
    #[Url(except: 'newest')] public string $sort = 'newest';   // newest | fifo | remaining | value | sellthrough | returns
    public ?int $expanded = null;
    // modal state (adjust / transfer / supplier return) — applies to $target (one batch) or the bulk selection
    public array $target = []; public $adjustQty = null; public string $adjustNote = ''; public ?int $toWarehouse = null; public $srQty = 1; public string $srReason = 'Product Defect';

    protected function base()
    {
        $q = Batch::query()
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('code', 'like', "%$s%")->orWhereHas('purchaseOrder', fn ($p) => $p->where('number', 'like', "%$s%"))->orWhereHas('variant', fn ($v) => $v->where('sku', 'like', "%$s%")->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%$s%")))))
            ->when($this->filters['supplier'] ?? null, fn ($q, $s) => $q->where('supplier_id', $s))
            ->when($this->filters['product'] ?? null, fn ($q, $p) => $q->whereHas('variant', fn ($v) => $v->where('product_id', $p)))
            ->when($this->filters['warehouse'] ?? null, fn ($q, $w) => $q->where('warehouse_id', $w))
            ->when($this->filters['labels'] ?? null, fn ($q) => $q->whereNull('labels_printed_at')->where('remaining', '>', 0));
        return $this->dateRange($q, 'received_at');
    }

    /** queued = has stock but an older batch of the same variant is still being allocated first (FIFO) */
    protected function queuedSql(): string { return 'remaining > 0 AND EXISTS (select 1 from ops_batches o where o.variant_id = ops_batches.variant_id and o.remaining > 0 and (o.received_at < ops_batches.received_at or (o.received_at = ops_batches.received_at and o.id < ops_batches.id)))'; }

    protected function applyStatus($q, string $s)
    {
        return match ($s) { 'all' => $q, 'queued' => $q->whereRaw($this->queuedSql()), 'active' => $q->where('status', 'active')->whereRaw('NOT ('.$this->queuedSql().')'), default => $q->where('status', $s) };
    }

    #[Computed]
    public function counts(): array { return collect(['all', 'active', 'queued', 'low', 'depleted'])->mapWithKeys(fn ($s) => [$s => $this->applyStatus($this->base(), $s)->count()])->all(); }

    #[Computed]
    public function batches()
    {
        $q = $this->applyStatus($this->base(), $this->status)
            ->with('variant:id,product_id,sku,color,size,price,sale_price', 'variant.product:id,name,price,sale_price', 'supplier:id,name', 'purchaseOrder:id,number', 'warehouse:id,name')
            ->withCount('returnItems as returns')->withSum(['movements as sold' => fn ($m) => $m->where('type', 'sale')], 'qty');
        $q = match ($this->sort) {
            'fifo' => $q->orderBy('received_at'), 'remaining' => $q->orderByDesc('remaining'), 'value' => $q->orderByRaw('remaining * unit_cost desc'),
            'sellthrough' => $q->orderByRaw('GREATEST(CAST(qty AS SIGNED) - remaining - CAST(damaged AS SIGNED), 0) / GREATEST(qty,1) desc'), 'returns' => $q->orderByDesc('returns'),
            default => $this->sortField !== 'id' ? $q->orderBy($this->sortField, $this->sortDir) : $q->latest('received_at'),
        };
        $page = $q->paginate($this->perPage);
        // FIFO queue position for each batch on the page (1 = allocating now)
        $avail = Batch::whereIn('variant_id', $page->pluck('variant_id'))->where('remaining', '>', 0)->orderBy('received_at')->orderBy('id')->get(['id', 'variant_id'])->groupBy('variant_id');
        $page->each(fn ($b) => $b->queue = $b->remaining > 0 ? ($avail[$b->variant_id]?->search(fn ($x) => $x->id === $b->id) ?? -1) + 1 : 0);
        return $page;
    }

    #[Computed]
    public function stats(): array
    {
        return cache()->remember('batch.stats', 60, function () {
            $a = Batch::where('remaining', '>', 0);
            return [
                'active' => (clone $a)->count(), 'units' => (int) Batch::sum('remaining'), 'value' => (int) Batch::selectRaw('SUM(remaining*unit_cost) v')->value('v'),
                'sell' => (int) round((float) Batch::where('qty', '>', 0)->selectRaw('AVG(GREATEST(CAST(qty AS SIGNED) - remaining - CAST(damaged AS SIGNED), 0) / qty * 100) v')->value('v')),
                'low' => Batch::where('status', 'low')->count(), 'oldest' => ($o = (clone $a)->min('received_at')) ? (int) \Illuminate\Support\Carbon::parse($o)->diffInDays(now()) : 0,
                'returns' => CustomerReturnItem::whereNotNull('batch_id')->count(), 'damaged' => (int) Batch::sum('damaged'),
            ];
        });
    }

    #[Computed] public function detail(): ?Batch
    {
        if (! $this->expanded) return null;
        return Batch::with(['movements' => fn ($q) => $q->latest('created_at')->limit(30), 'variant.product', 'supplier', 'purchaseOrder', 'warehouse'])->find($this->expanded);
    }
    #[Computed] public function detailOrders()
    {
        return $this->expanded ? OrderItemAllocation::where('batch_id', $this->expanded)->with('item.order:id,number,status,customer_id', 'item.order.customer:id,name')->latest('id')->limit(30)->get() : collect();
    }

    public function toggle(int $id): void { $this->expanded = $this->expanded === $id ? null : $id; unset($this->detail, $this->detailOrders); }

    /** open a modal for one batch (row action) or for the bulk selection */
    public function prepare(string $modal, ?int $id = null): void
    {
        $this->target = $id ? [$id] : array_map('intval', $this->selected);
        $this->reset('adjustQty', 'adjustNote', 'toWarehouse'); $this->srQty = 1; $this->resetValidation();
        $this->dispatch('open-modal', name: $modal);
    }

    public function adjust(StockService $stock): void
    {
        $this->validate(['adjustQty' => 'required|integer|not_in:0', 'adjustNote' => 'required|max:200'], ['adjustQty.not_in' => 'Enter + or − units.']);
        foreach (Batch::whereIn('id', $this->target)->get() as $b) {
            $qty = max((int) $this->adjustQty, -$b->remaining);   // never below zero
            if ($qty) $stock->move($b, 'adjust', $qty, null, $this->adjustNote);
            ops_log("Adjusted batch {$b->code} by $qty · {$this->adjustNote}", $b);
        }
        $this->done('Stock adjusted');
    }

    public function transfer(StockService $stock): void
    {
        $this->validate(['toWarehouse' => 'required|exists:ops_warehouses,id']);
        $to = Warehouse::find($this->toWarehouse);
        foreach (Batch::whereIn('id', $this->target)->get() as $b) {
            if ($b->warehouse_id === $to->id) continue;
            $b->update(['warehouse_id' => $to->id]);
            \App\Models\Ops\StockMovement::create(['batch_id' => $b->id, 'variant_id' => $b->variant_id, 'type' => 'transfer', 'qty' => 0, 'note' => 'Moved '.$b->remaining.' pcs to '.$to->name, 'user_id' => auth()->id()]);
            ops_log("Transferred batch {$b->code} to {$to->name}", $b);
        }
        $this->done('Transferred to '.$to->name);
    }

    public function markDepleted(?int $id = null): void
    {
        $stock = app(StockService::class);
        foreach (Batch::whereIn('id', $id ? [$id] : array_map('intval', $this->selected))->where('remaining', '>', 0)->get() as $b) {
            $stock->move($b, 'adjust', -$b->remaining, null, 'Marked depleted (write-off)');
            ops_log("Marked batch {$b->code} depleted", $b);
        }
        $this->done('Marked depleted');
    }

    public function supplierReturn(): void
    {
        $b = Batch::findOrFail($this->target[0] ?? 0);
        $this->validate(['srQty' => 'required|integer|min:1|max:'.max(1, $b->remaining + $b->damaged), 'srReason' => 'required']);
        DB::transaction(function () use ($b) {
            $sr = SupplierReturn::create(['code' => 'SR'.(SupplierReturn::max('id') + 1001), 'supplier_id' => $b->supplier_id, 'step_dates' => ['pending' => today()->toDateString()]]);
            $last = (int) \App\Models\Ops\SupplierReturnItem::where('source_ref', 'like', 'QC-%')->count();
            $sr->items()->create(['batch_id' => $b->id, 'source_ref' => 'QC-'.str_pad($last + 1, 3, '0', STR_PAD_LEFT), 'qty' => (int) $this->srQty, 'unit_cost' => $b->unit_cost, 'reason' => $this->srReason]);
            // the units leave sellable stock now: take them from remaining (damaged stock is already out)
            $fromStock = max(0, (int) $this->srQty - $b->damaged);
            if ($fromStock) app(StockService::class)->move($b, 'supplier_return', -min($fromStock, $b->remaining), $sr, 'Supplier return '.$sr->code);
            ops_log("Supplier return {$sr->code} from batch {$b->code}", $sr);
            $this->dispatch('toast', message: $sr->code.' created');
        });
        $this->dispatch('close-modal'); $this->clearCache();
    }

    public function export()
    {
        $rows = $this->applyStatus($this->base(), $this->status)->when($this->selected, fn ($q) => $q->whereIn('id', $this->selected))->with('variant.product', 'supplier', 'purchaseOrder', 'warehouse')->get();
        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w'); fputcsv($f, ['Batch', 'Product', 'SKU', 'Supplier', 'PO', 'Warehouse', 'Received', 'Qty', 'Remaining', 'Damaged', 'Unit cost', 'Value', 'Status']);
            foreach ($rows as $b) fputcsv($f, [$b->code, $b->variant->label, $b->variant->sku, $b->supplier->name, $b->purchaseOrder?->number, $b->warehouse?->name, $b->received_at->toDateString(), $b->qty, $b->remaining, $b->damaged, $b->unit_cost, $b->remaining * $b->unit_cost, $b->status]);
            fclose($f);
        }, 'batches-'.now()->format('Ymd-His').'.csv');
    }

    protected function done(string $msg): void { $this->dispatch('close-modal'); $this->toast($msg); $this->selected = []; $this->clearCache(); }
    protected function clearCache(): void { cache()->forget('batch.stats'); unset($this->batches, $this->stats, $this->counts, $this->detail, $this->detailOrders); }

    public function render()
    {
        return view('livewire.ops.batches.index', [
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'), 'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
            'products' => cache()->remember('products.pick', 300, fn () => Product::orderBy('name')->pluck('name', 'id')),
        ]);
    }
}
