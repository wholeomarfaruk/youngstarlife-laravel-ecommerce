<?php

namespace App\Services\Ops;

use App\Models\Ops\{Batch, Courier, Order, OrderItemAllocation, ProductVariant, Shipment};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /** parcels replaced by a re-send — their units left with that parcel (and come back through Return receive) */
    protected function superseded(Order $order): array { return $order->shipments()->whereNotNull('superseded_at')->pluck('id')->all(); }

    /** allocations that still serve the order now (not the units of a replaced parcel) */
    protected function current($allocations, array $superseded) { return $superseded ? $allocations->reject(fn ($a) => $a->shipment_id && in_array($a->shipment_id, $superseded)) : $allocations; }

    /** Idempotent: only allocates what is still unallocated (FIFO by received date), and reserves exactly what it allocated. */
    public function allocate(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $old = $this->superseded($order);
            foreach ($this->lines($order) as $item) {
                foreach ($this->parts($item) as $vid => $want) {
                    $need = $want - $this->current($item->allocations, $old)->filter(fn ($a) => $a->batch?->variant_id == $vid)->sum('qty');
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

    /**
     * Packing scan read the batch stickers: the units in this parcel come from THOSE batches. Moves this order's unpacked
     * reservations onto them (same variant, so reserved counts don't change). If other orders had reserved the scanned
     * batch, their reservation is swapped onto the batch this order just freed — no batch is promised twice.
     * $picks = [['item_id' => 12, 'batch_id' => 14, 'qty' => 1], …]
     */
    public function useScannedBatches(Order $order, array $picks): void
    {
        if (! $picks) return;
        DB::transaction(function () use ($order, $picks) {
            $own = $order->items()->pluck('id')->all();
            foreach (collect($picks)->groupBy('item_id') as $itemId => $rows) {
                $itemId = (int) $itemId;
                if (! in_array($itemId, $own)) continue;
                $want = $rows->groupBy('batch_id')->map->sum('qty');   // batch_id => units scanned from it
                $batches = Batch::whereIn('id', $want->keys())->lockForUpdate()->get()->keyBy('id');
                $mine = OrderItemAllocation::where('order_item_id', $itemId)->whereNull('packed_at')->with('batch:id,variant_id')->get();

                foreach ($batches->groupBy('variant_id') as $vid => $scannedBatches) {
                    $have = $mine->filter(fn ($a) => $a->batch?->variant_id == $vid);
                    // units reserved on a batch beyond what was scanned there: those move to the scanned batches
                    $spare = $have->filter(fn ($a) => $a->qty > (int) ($want[$a->batch_id] ?? 0))->values();
                    $freed = [];   // batch_id => units this order gave up (room for a swapped reservation)
                    foreach ($scannedBatches as $b) {
                        $need = $missing = (int) $want[$b->id] - (int) $have->where('batch_id', $b->id)->sum('qty');
                        foreach ($spare as $a) {
                            if ($need <= 0) break;
                            $t = min($need, $a->qty - (int) ($want[$a->batch_id] ?? 0));
                            if ($t <= 0) continue;
                            $a->qty -= $t; $a->qty ? $a->save() : $a->delete();
                            $freed[$a->batch_id] = ($freed[$a->batch_id] ?? 0) + $t; $need -= $t;
                        }
                        $moved = $missing - max(0, $need);   // units now taken from the scanned batch instead
                        if ($moved > 0) {
                            $row = $have->firstWhere('batch_id', $b->id);
                            $row ? $row->increment('qty', $moved) : $mine->push($row = OrderItemAllocation::create(['order_item_id' => $itemId, 'batch_id' => $b->id, 'qty' => $moved]));
                            if (! $have->contains('id', $row->id)) $have->push($row);
                            $this->makeRoom($b, $itemId, $freed);
                        }
                        OrderItemAllocation::where('order_item_id', $itemId)->where('batch_id', $b->id)->whereNull('packed_at')->update(['batch_scanned' => true]);
                    }
                }
            }
        });
    }

    /** the scanned batch now holds more reservations than it has units → move other orders' (newest first) onto the freed batches */
    protected function makeRoom(Batch $b, int $itemId, array &$freed): void
    {
        $over = (int) OrderItemAllocation::where('batch_id', $b->id)->whereNull('packed_at')->sum('qty') - (int) $b->remaining;
        if ($over <= 0) return;
        foreach (OrderItemAllocation::where('batch_id', $b->id)->whereNull('packed_at')->where('order_item_id', '!=', $itemId)->where('batch_scanned', false)->latest('id')->get() as $other) {
            foreach ($freed as $to => $room) {
                if ($over <= 0 || $other->qty <= 0) break;
                $t = min($over, $other->qty, $room);
                if ($t <= 0) continue;
                $other->qty -= $t; $other->qty ? $other->save() : $other->delete();
                $dest = OrderItemAllocation::firstOrNew(['order_item_id' => $other->order_item_id, 'batch_id' => $to, 'packed_at' => null]);
                $dest->qty = (int) $dest->qty + $t; $dest->save();
                $freed[$to] -= $t; $over -= $t;
            }
            if ($over <= 0) return;
        }
    }

    /** Units of the order that could not be allocated from batches (0 = fully covered). */
    public function shortfall(Order $order): int
    {
        $old = $this->superseded($order);
        return $this->lines($order)->sum(fn ($i) => collect($this->parts($i))->map(fn ($want, $vid) => max(0, $want - $this->current($i->allocations, $old)->filter(fn ($a) => $a->batch?->variant_id == $vid)->sum('qty')))->sum());
    }

    /** Called by Packing scan when the parcel is verified. Deducts batches, stores purchase price, books the parcel. */
    public function pack(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->allocate($order);   // pick up stock received since the order was confirmed
            $cost = 0; $touched = []; $packed = [];
            foreach (OrderItemAllocation::whereIn('order_item_id', $order->items()->pluck('id'))->whereNull('packed_at')->with('batch', 'item')->get() as $a) {
                $a->update(['unit_cost' => $a->batch->unit_cost, 'packed_at' => now()]); $packed[] = $a->id;
                $this->stock->move($a->batch, 'sale', -$a->qty, $order);
                ProductVariant::whereKey($a->batch->variant_id)->decrement('reserved', $a->qty);   // batch's variant (= component for combos)
                $cost += $a->qty * $a->batch->unit_cost; $touched[] = $a->batch->variant_id;
            }
            $order->update(['cost' => $order->cost + $cost, 'status' => 'packed']);
            // low-stock alert for variants that just dropped below their product threshold
            foreach (ProductVariant::whereIn('id', array_unique($touched))->with('product:id,name,low_stock_threshold')->get() as $v)
                if ($v->stock < $v->product->low_stock_threshold) ops_notify('lowStock', $v->label.' is low · '.$v->stock.' left', route('ops.products.edit', ['product' => $v->product_id, 'tab' => 'stock']));
            ops_log("Packed {$order->number} · cost ".tk($cost), $order);
            $stamp = fn (Shipment $s) => OrderItemAllocation::whereIn('id', $packed)->update(['shipment_id' => $s->id]);   // these units travel in this parcel
            if (! $order->shipment()->exists()) {
                $courier = app(OrderWorkflow::class)->courierFor($order);
                if ($courier) {
                    $s = $order->shipments()->create(['kind' => $order->shipments()->exists() ? 'resend' : 'delivery', 'code' => Shipment::nextCode(), 'courier_id' => $courier->id, 'cod_amount' => $order->cod,
                        'packed_by' => auth()->id(), 'packed_at' => now(), 'courier_status' => 'pending', 'status_updated_at' => now()]);
                    $stamp($s);
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
            // parcel made earlier (Book courier → sent before packing): this scan packs it
            if ($s = $order->shipment()->first()) {
                $stamp($s);
                if (! $s->packed_at) { $s->update(['packed_by' => auth()->id(), 'packed_at' => now()]); $order->log('packed', 'Packed and verified by scan · parcel '.$s->code); return; }
            }
            $order->log('packed', 'Packed and verified by scan');
        });
    }

    /**
     * Undo "Mark packed": the packed units go back into the SAME batches they were taken from and are reserved for the order
     * again (batch links, incl. stickers scanned, stay — re-packing keeps them); the cost comes off the order. The parcel made at
     * packing is removed — or, when it was booked with the courier before packing, only marked not packed. Refused once the
     * courier has the parcel. $setStatus false = the caller (OrderWorkflow::setStatus) sets the new status itself.
     */
    public function unpack(Order $order, bool $setStatus = true): void
    {
        if ($order->fresh()->status !== 'packed') throw ValidationException::withMessages(['unpack' => "{$order->number} is not packed."]);
        $s = $order->shipment()->first();
        if ($s && ($s->handed_over_at || ! in_array($s->courier_status, [null, 'pending'])))
            throw ValidationException::withMessages(['unpack' => "Parcel {$s->code} is already with the courier (".config("ops.courier_status.{$s->courier_status}.0", $s->courier_status).') — it can’t be unpacked here.']);
        DB::transaction(function () use ($order, $s, $setStatus) {
            $old = $this->superseded($order); $cost = 0; $units = 0;
            $packed = OrderItemAllocation::whereIn('order_item_id', $order->items()->pluck('id'))->whereNotNull('packed_at')->with('batch')->get();
            foreach ($this->current($packed, $old) as $a) {
                if ($s && $a->shipment_id && $a->shipment_id != $s->id) continue;   // units of another parcel (e.g. a delivered part)
                $this->stock->move($a->batch, 'sale', $a->qty, $order, 'Unpacked — back into '.$a->batch->code);   // a reversed sale: "sold" per batch nets out
                ProductVariant::whereKey($a->batch->variant_id)->increment('reserved', $a->qty);   // reserved for the order again
                $cost += $a->qty * (int) $a->unit_cost; $units += $a->qty;
                $a->update(['packed_at' => null, 'unit_cost' => null, 'shipment_id' => null]);
            }
            $order->update(['cost' => max(0, (int) $order->cost - $cost)] + ($setStatus ? ['status' => 'confirmed'] : []));
            $note = '';
            if ($s) {
                $linked = \App\Models\Ops\CustomerReturn::where('shipment_id', $s->id)->exists() || \App\Models\Ops\CourierSessionItem::where('shipment_id', $s->id)->exists();
                if ($s->booked_at || $s->tracking || $linked) { $s->update(['packed_at' => null, 'packed_by' => null]); $note = ' · parcel '.$s->code.' kept (booked with '.($s->courier?->name ?? 'the courier').'), not packed'; }
                else { $s->delete(); $note = ' · parcel '.$s->code.' removed'; }
            }
            $order->log('packed', "Unpacked: $units unit(s) back into their batches".$note);
            ops_log("Unpacked {$order->number} · $units unit(s) back in stock", $order);
        });
    }

    /** Cancelling: drop unpacked reservations and put already-packed units back into their original batch. */
    public function release(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $old = $this->superseded($order);
            foreach ($this->current(OrderItemAllocation::whereIn('order_item_id', $order->items()->pluck('id'))->with('batch', 'item')->get(), $old) as $a) {
                if ($a->packed_at) $this->stock->move($a->batch, 'sale', $a->qty, $order, 'Order cancelled');   // reversed sale: a cancelled parcel isn't "sold"
                else ProductVariant::whereKey($a->batch->variant_id)->decrement('reserved', $a->qty);
                $a->delete();
            }
            $order->update(['cost' => 0]);
        });
    }
}
