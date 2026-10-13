<?php

namespace App\Services;

use App\Models\{Batch, GoodsReceipt, PurchaseOrder};
use Illuminate\Support\Facades\DB;

/** Purchase order → receive (partial OK) → one batch per variant → labels. */
class ReceivingService
{
    public function __construct(private StockService $stock) {}

    /** @param array<int, array{now:int, damaged:int}> $lines keyed by purchase_order_item_id */
    public function receive(PurchaseOrder $po, array $lines): GoodsReceipt
    {
        return DB::transaction(function () use ($po, $lines) {
            $grn = $po->receipts()->create(['number' => 'GRN-'.str_pad(GoodsReceipt::max('id') + 1, 3, '0', STR_PAD_LEFT), 'received_by' => auth()->id()]);
            $value = 0;
            foreach ($po->items as $item) {
                $now = (int) ($lines[$item->id]['now'] ?? 0); $dmg = min($now, (int) ($lines[$item->id]['damaged'] ?? 0));
                if ($now <= 0) continue;
                abort_if($now > $item->remaining, 422, 'More than ordered');
                $item->increment('received', $now); $item->increment('damaged', $dmg);
                $good = $now - $dmg;
                if ($good > 0) {
                    $batch = Batch::create([
                        'code' => 'B'.str_pad(Batch::max('id') + 1, 3, '0', STR_PAD_LEFT), 'variant_id' => $item->variant_id,
                        'supplier_id' => $po->supplier_id, 'purchase_order_id' => $po->id, 'goods_receipt_id' => $grn->id,
                        'warehouse_id' => $po->warehouse_id, 'unit_cost' => $item->unit_cost, 'qty' => $good, 'remaining' => 0, 'received_at' => now(),
                    ]);
                    $this->stock->move($batch, 'stock_in', $good, $grn);
                    $value += $good * $item->unit_cost;
                }
            }
            $po->supplier()->increment('due', $value);
            $po->update(['status' => $po->items()->whereColumn('received', '<', 'qty')->exists() ? 'partial' : 'received']);
            return $grn;
        });
    }
}
