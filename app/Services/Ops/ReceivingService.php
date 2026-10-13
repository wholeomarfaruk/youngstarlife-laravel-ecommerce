<?php

namespace App\Services\Ops;

use App\Models\Ops\{Batch, GoodsReceipt, PurchaseOrder, SupplierReturn, SupplierReturnItem};
use Illuminate\Support\Facades\DB;

/** Purchase order → receive (partial OK, damaged split) → one batch per variant → labels. Damaged units → supplier-return draft. */
class ReceivingService
{
    public function __construct(private StockService $stock) {}

    public static function nextBatchCode(): string
    {
        $fmt = (string) ops_setting('batch.format', 'B{###}');
        $prefix = strstr($fmt, '{', true) ?: '';
        $n = (int) Batch::where('code', 'like', $prefix.'%')->selectRaw('MAX(CAST(SUBSTRING(code, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])->value('n') + 1;
        return preg_replace_callback('/\{(#+)\}/', fn ($m) => str_pad((string) $n, strlen($m[1]), '0', STR_PAD_LEFT), $fmt);
    }

    /** @param array<int, array{now:int, damaged:int}> $lines keyed by purchase_order_item_id */
    public function receive(PurchaseOrder $po, array $lines): GoodsReceipt
    {
        return DB::transaction(function () use ($po, $lines) {
            $last = (int) GoodsReceipt::selectRaw('MAX(CAST(SUBSTRING(number, 5) AS UNSIGNED)) n')->value('n');
            $grn = $po->receipts()->create(['number' => 'GRN-'.str_pad($last + 1, 3, '0', STR_PAD_LEFT), 'received_by' => auth()->id()]);
            $value = 0; $damagedLines = [];
            foreach ($po->items as $item) {
                $now = (int) ($lines[$item->id]['now'] ?? 0); $dmg = min($now, (int) ($lines[$item->id]['damaged'] ?? 0));
                if ($now <= 0) continue;
                abort_if($now > $item->remaining, 422, 'More than ordered');
                $item->increment('received', $now); $item->increment('damaged', $dmg);
                $good = $now - $dmg;
                $batch = Batch::create([
                    'code' => static::nextBatchCode(), 'variant_id' => $item->variant_id,
                    'supplier_id' => $po->supplier_id, 'purchase_order_id' => $po->id, 'goods_receipt_id' => $grn->id,
                    'warehouse_id' => $po->warehouse_id, 'unit_cost' => $item->unit_cost, 'qty' => $now, 'remaining' => 0, 'received_at' => now(),
                ]);
                if ($good > 0) $this->stock->move($batch, 'stock_in', $good, $grn);
                if ($dmg > 0) { $this->stock->damage($batch, $dmg, $grn); $damagedLines[] = [$batch, $dmg]; }
                $batch->refresh()->update(['status' => $this->stock->batchStatus($batch)]);
                $value += $good * $item->unit_cost;   // supplier due grows only by GOOD units × cost
            }
            $po->supplier()->increment('due', $value);
            $po->update(['status' => $po->items()->whereColumn('received', '<', 'qty')->exists() ? 'partial' : 'received']);
            // damaged on arrival → supplier-return draft (QC-…)
            if ($damagedLines && (ops_setting('automations.draft_supplier_return.on') ?? true)) {
                $sr = SupplierReturn::create(['code' => 'SR'.(SupplierReturn::max('id') + 1001), 'supplier_id' => $po->supplier_id, 'step_dates' => ['pending' => today()->toDateString()], 'note' => 'Damaged on arrival · '.$grn->number]);
                foreach ($damagedLines as [$batch, $dmg]) {
                    $qc = SupplierReturnItem::where('source_ref', 'like', 'QC-%')->count() + 1;
                    $sr->items()->create(['batch_id' => $batch->id, 'source_ref' => 'QC-'.str_pad($qc, 3, '0', STR_PAD_LEFT), 'qty' => $dmg, 'unit_cost' => $batch->unit_cost, 'reason' => 'Damaged on arrival']);
                }
                ops_log('Supplier return '.$sr->code.' drafted for damaged units in '.$grn->number, $sr);
            }
            ops_log($grn->number.' received for '.$po->number.' · good stock worth '.tk($value), $po);
            return $grn;
        });
    }
}
