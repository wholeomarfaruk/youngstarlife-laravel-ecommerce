<?php

namespace App\Services;

use App\Models\{Batch, Order, OrderItemAllocation};
use Illuminate\Support\Facades\DB;

/** FIFO allocation on confirm (reserves), cost captured on pack. */
class BatchAllocator
{
    public function __construct(private StockService $stock) {}

    public function allocate(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items()->with('allocations')->get() as $item) {
                $need = $item->qty - $item->allocations->sum('qty');
                foreach (Batch::where('variant_id', $item->variant_id)->available()->lockForUpdate()->get() as $batch) {
                    if ($need <= 0) break;
                    $free = $batch->remaining - $batch->allocations()->whereNull('packed_at')->sum('qty');
                    if ($free <= 0) continue;
                    $take = min($need, $free);
                    $item->allocations()->create(['batch_id' => $batch->id, 'qty' => $take]);
                    $need -= $take;
                }
                $item->variant()->increment('reserved', $item->qty);
            }
        });
    }

    /** Called by Packing scan when the parcel is verified. Deducts batches and stores purchase price. */
    public function pack(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $cost = 0;
            foreach (OrderItemAllocation::whereIn('order_item_id', $order->items()->pluck('id'))->whereNull('packed_at')->with('batch')->get() as $a) {
                $a->update(['unit_cost' => $a->batch->unit_cost, 'packed_at' => now()]);
                $this->stock->move($a->batch, 'sale', -$a->qty, $order);
                $a->item->variant()->decrement('reserved', $a->qty);
                $cost += $a->qty * $a->batch->unit_cost;
            }
            $order->update(['cost' => $cost, 'status' => 'packed']);
            $order->log('packed', 'Packed and verified by scan');
        });
    }
}
