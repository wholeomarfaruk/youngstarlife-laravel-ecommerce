<?php

namespace App\Services\Ops;

use Illuminate\Support\Collection;

/**
 * Stock for a returned item, by inspection result: good → back into its ORIGINAL batch (Automations → restock_good),
 * damaged / used / broken → damaged stock, anything else → no stock change.
 * $qty caps the units moved (an exchange of 1 of 2 units must not restock the whole allocation).
 */
class ReturnStock
{
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
