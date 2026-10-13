<?php

namespace App\Services\Ops;

use App\Models\Ops\{Batch, Courier, Order, OrderItemAllocation, ProductVariant, Shipment};
use Illuminate\Support\Facades\DB;

/** FIFO allocation on confirm (reserves), cost captured on pack. */
class BatchAllocator
{
    public function __construct(private StockService $stock) {}

    /** What an order line takes from stock: [variant_id => units]. A combo takes its components (qty × component qty). */
    protected function parts($item): array
    {
        $p = $item->variant->product;
        // combo with sizes chosen on the website → exactly those component variants
        if ($item->combo_selection) return collect($item->combo_selection)->groupBy('variant_id')->map(fn ($g) => $g->sum(fn ($c) => (int) ($c['qty'] ?? 1)) * $item->qty)->all();
        if ($p?->type === 'combo' && $p->comboItems->isNotEmpty()) return $p->comboItems->mapWithKeys(fn ($c) => [$c->variant_id => $c->qty * $item->qty])->all();
        return [$item->variant_id => $item->qty];
    }

    protected function lines(Order $order) { return $order->items()->with('allocations.batch:id,variant_id', 'variant.product.comboItems')->get(); }

    /** Idempotent: only allocates what is still unallocated (FIFO by received date), and reserves exactly what it allocated. */
    public function allocate(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($this->lines($order) as $item) {
                foreach ($this->parts($item) as $vid => $want) {
                    $need = $want - $item->allocations->filter(fn ($a) => $a->batch?->variant_id == $vid)->sum('qty');
                    if ($need <= 0) continue;
                    $reserved = 0;
                    foreach (Batch::where('variant_id', $vid)->available()->lockForUpdate()->get() as $batch) {
                        if ($need <= 0) break;
                        $free = $batch->remaining - $batch->allocations()->whereNull('packed_at')->sum('qty');
                        if ($free <= 0) continue;
                        $take = min($need, $free);
                        $item->allocations()->create(['batch_id' => $batch->id, 'qty' => $take]);
                        $need -= $take; $reserved += $take;
                    }
                    if ($reserved) ProductVariant::whereKey($vid)->increment('reserved', $reserved);
                }
            }
        });
    }

    /** Units of the order that could not be allocated from batches (0 = fully covered). */
    public function shortfall(Order $order): int
    {
        return $this->lines($order)->sum(fn ($i) => collect($this->parts($i))->map(fn ($want, $vid) => max(0, $want - $i->allocations->filter(fn ($a) => $a->batch?->variant_id == $vid)->sum('qty')))->sum());
    }

    /** Called by Packing scan when the parcel is verified. Deducts batches, stores purchase price, books the parcel. */
    public function pack(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->allocate($order);   // pick up stock received since the order was confirmed
            $cost = 0; $touched = [];
            foreach (OrderItemAllocation::whereIn('order_item_id', $order->items()->pluck('id'))->whereNull('packed_at')->with('batch', 'item')->get() as $a) {
                $a->update(['unit_cost' => $a->batch->unit_cost, 'packed_at' => now()]);
                $this->stock->move($a->batch, 'sale', -$a->qty, $order);
                ProductVariant::whereKey($a->batch->variant_id)->decrement('reserved', $a->qty);   // batch's variant (= component for combos)
                $cost += $a->qty * $a->batch->unit_cost; $touched[] = $a->batch->variant_id;
            }
            $order->update(['cost' => $order->cost + $cost, 'status' => 'packed']);
            // low-stock alert for variants that just dropped below their product threshold
            foreach (ProductVariant::whereIn('id', array_unique($touched))->with('product:id,name,low_stock_threshold')->get() as $v)
                if ($v->stock < $v->product->low_stock_threshold) ops_notify('lowStock', $v->label.' is low · '.$v->stock.' left', route('ops.products.edit', ['product' => $v->product_id, 'tab' => 'stock']));
            ops_log("Packed {$order->number} · cost ".tk($cost), $order);
            if (! $order->shipment()->exists()) {
                $courier = app(OrderWorkflow::class)->courierFor($order);
                if ($courier) {
                    $last = (int) Shipment::where('code', 'like', 'P%')->selectRaw('MAX(CAST(SUBSTRING(code, 2) AS UNSIGNED)) n')->value('n');
                    $s = $order->shipment()->create(['code' => 'P'.max(1001, $last + 1), 'courier_id' => $courier->id, 'cod_amount' => $order->cod,
                        'packed_by' => auth()->id(), 'packed_at' => now(), 'courier_status' => 'pending', 'status_updated_at' => now()]);
                    $order->log('packed', 'Packed and verified by scan · parcel '.$s->code.' · '.$courier->name);
                    // Automations → "Parcel is packed → send it to the courier API": after the response, so the packing scan stays fast
                    if ((ops_setting('automations.auto_book.on') ?? false) && $courier->can('book')) {
                        $id = $s->id;
                        DB::afterCommit(fn () => dispatch(function () use ($id) {
                            try { app(\App\Services\Couriers\CourierGateway::class)->bookMany(Shipment::whereKey($id)->get()); } catch (\Throwable $e) { report($e); }
                        })->afterResponse());
                    }
                    return;
                }
            }
            $order->log('packed', 'Packed and verified by scan');
        });
    }

    /** Cancelling: drop unpacked reservations and put already-packed units back into their original batch. */
    public function release(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach (OrderItemAllocation::whereIn('order_item_id', $order->items()->pluck('id'))->with('batch', 'item')->get() as $a) {
                if ($a->packed_at) $this->stock->move($a->batch, 'adjust', $a->qty, $order, 'Order cancelled');
                else ProductVariant::whereKey($a->batch->variant_id)->decrement('reserved', $a->qty);
                $a->delete();
            }
            $order->update(['cost' => 0]);
        });
    }
}
