<?php

namespace App\Services\Ops;

use App\Models\Ops\{Batch, CustomerReturn, CustomerReturnItem, OrderItem};
use Illuminate\Support\Collection;

/**
 * Returned units go back to the SAME batch they were deducted from (Packing scan → OrderItemAllocation), so supplier, cost and
 * stock per batch stay true. Good → restocked into that batch (Automations → restock_good), damaged / used / broken → that
 * batch's damaged stock, anything else (missing…) → no stock change.
 *
 *   ReturnStock::batchesFor($item, 2, [14 => 1], $shipmentId)   [batch_id => units] for 2 returned units of a line
 *   ReturnStock::record($return, $item, 2, $cond, …)             one return row per batch + its stock change
 */
class ReturnStock
{
    /**
     * Which batches $qty returned units of $item came from: the batch stickers scanned at return first (only batches the line was
     * packed from), then the line's packed units — of this parcel only when $shipmentId is given — minus units of those batches
     * that already came back in earlier returns. Units with no packed record left (old data) → key 0 (no batch).
     */
    public static function batchesFor(OrderItem $item, int $qty, array $scanned = [], ?int $shipmentId = null, ?int $exceptReturnId = null): array
    {
        $packed = $item->allocations()->whereNotNull('packed_at')
            ->when($shipmentId, fn ($q) => $q->where(fn ($w) => $w->where('shipment_id', $shipmentId)->orWhereNull('shipment_id')))
            ->orderBy('id')->get(['batch_id', 'qty'])->groupBy('batch_id')->map->sum('qty');
        // units already back from earlier returns of this line (not "missing", not a line still waiting for the item)
        $back = CustomerReturnItem::where('order_item_id', $item->id)->whereNotNull('batch_id')
            ->where(fn ($q) => $q->whereNull('condition')->orWhere('condition', '!=', 'missing'))
            ->whereHas('customerReturn', fn ($r) => $r->where('status', '!=', 'expected')->when($exceptReturnId, fn ($x) => $x->whereKeyNot($exceptReturnId)))
            ->get(['batch_id', 'qty'])->groupBy('batch_id')->map->sum('qty');
        $left = $packed->map(fn ($n, $b) => max(0, $n - (int) ($back[$b] ?? 0)))->filter()->all();   // batch_id => units still out with the customer

        $out = []; $need = $qty;
        foreach ($scanned as $b => $n) {                       // stickers read at return
            $t = min((int) $n, (int) ($left[$b] ?? 0), $need);
            if ($t <= 0) continue;
            $out[$b] = ($out[$b] ?? 0) + $t; $left[$b] -= $t; $need -= $t;
        }
        foreach ($left as $b => $n) {                          // the rest: in packing order
            if ($need <= 0) break;
            $t = min($n, $need);
            if ($t <= 0) continue;
            $out[$b] = ($out[$b] ?? 0) + $t; $need -= $t;
        }
        if ($need > 0) $out[0] = ($out[0] ?? 0) + $need;       // no batch on record for these units
        return $out;
    }

    /**
     * Saves the returned units of one order line as one row per batch and moves their stock. Returns the rows.
     * $attrs: reason, action… (the same on every row); $cond null = not inspected yet (no stock change until it is).
     */
    public static function record(CustomerReturn $ret, OrderItem $item, int $qty, ?string $cond, array $attrs, array $scanned = [], ?int $shipmentId = null): Collection
    {
        $rows = collect();
        foreach (self::batchesFor($item, $qty, $scanned, $shipmentId, $ret->id) as $batchId => $n) {
            $rows->push($row = $ret->items()->create($attrs + ['order_item_id' => $item->id, 'batch_id' => $batchId ?: null, 'qty' => $n, 'condition' => $cond]));
            self::applyRow($row, $ret);
        }
        return $rows;
    }

    /** stock for one return row (its own batch and qty) — on receive, or later when "Not inspected" gets a condition */
    public static function applyRow(CustomerReturnItem $row, $ref): void
    {
        if (! $row->condition || ! $row->batch_id || ! ($batch = Batch::find($row->batch_id))) return;
        $stock = app(StockService::class);
        if ($row->condition === 'good' && (ops_setting('automations.restock_good.on') ?? true)) $stock->move($batch, 'return_restock', $row->qty, $ref);
        if (in_array($row->condition, ['damaged', 'used', 'broken'])) $stock->damage($batch, $row->qty, $ref);
    }

    /** @deprecated old rows without a batch: spread over the line's packed batches (kept for returns recorded before per-batch rows) */
    public static function apply(?string $condition, Collection $allocations, $ref, ?int $qty = null): void
    {
        if (! $condition) return;
        $stock = app(StockService::class); $left = $qty;
        foreach ($allocations as $a) {
            $n = $left === null ? $a->qty : min($a->qty, $left);
            if ($n <= 0) break;
            if ($condition === 'good' && (ops_setting('automations.restock_good.on') ?? true)) $stock->move($a->batch, 'return_restock', $n, $ref);
            if (in_array($condition, ['damaged', 'used', 'broken'])) $stock->damage($a->batch, $n, $ref);
            if ($left !== null) $left -= $n;
        }
    }
}
