<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Batch extends OpsModel
{
    protected $guarded = [];
    protected $casts = ['received_at' => 'datetime', 'labels_printed_at' => 'datetime'];
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function receipt(): BelongsTo { return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id'); }
    public function movements(): HasMany { return $this->hasMany(StockMovement::class); }
    public function allocations(): HasMany { return $this->hasMany(OrderItemAllocation::class); }
    public function returnItems(): HasMany { return $this->hasMany(CustomerReturnItem::class); }
    public function scopeAvailable($q) { return $q->where('remaining', '>', 0)->orderBy('received_at'); } // FIFO
    public function getLabelCodeAttribute(): string { return $this->variant->sku.'-'.$this->code; }
    public function getAgeDaysAttribute(): int { return (int) $this->received_at?->diffInDays(now()); }
    /** % of received units that have left the batch (sold, not damaged) */
    public function getSellThroughAttribute(): int { return $this->qty ? (int) round(max(0, $this->qty - $this->remaining - $this->damaged) / $this->qty * 100) : 0; }
}
