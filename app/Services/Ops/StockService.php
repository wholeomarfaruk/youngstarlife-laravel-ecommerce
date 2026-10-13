<?php

namespace App\Services\Ops;

use App\Models\Ops\{Batch, ProductVariant, StockMovement};
use Illuminate\Support\Facades\DB;

/** All stock changes go through here so batches, variant counters and the ledger stay in sync. */
class StockService
{
    public function move(Batch $batch, string $type, int $qty, $ref = null, ?string $note = null): void
    {
        DB::transaction(function () use ($batch, $type, $qty, $ref, $note) {
            $batch->increment('remaining', $qty);
            $batch->refresh()->update(['status' => $this->batchStatus($batch)]);
            ProductVariant::whereKey($batch->variant_id)->increment('stock', $qty);
            StockMovement::create([
                'batch_id' => $batch->id, 'variant_id' => $batch->variant_id, 'type' => $type, 'qty' => $qty,
                'ref_type' => $ref?->getMorphClass(), 'ref_id' => $ref?->getKey(), 'note' => $note, 'user_id' => auth()->id(),
            ]);
        });
    }

    public function damage(Batch $batch, int $qty, $ref = null): void
    {
        $batch->increment('damaged', $qty);
        ProductVariant::whereKey($batch->variant_id)->increment('damaged', $qty);
        StockMovement::create(['batch_id' => $batch->id, 'variant_id' => $batch->variant_id, 'type' => 'damage', 'qty' => 0,
            'ref_type' => $ref?->getMorphClass(), 'ref_id' => $ref?->getKey(), 'note' => "$qty damaged", 'user_id' => auth()->id()]);
    }

    public function batchStatus(Batch $b): string
    {
        return match (true) { $b->remaining <= 0 => 'depleted', $b->qty && $b->remaining / $b->qty < .3 => 'low', default => 'active' };
    }
}
