<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Batch extends Model
{
    protected $guarded = [];
    protected $casts = ['received_at' => 'datetime', 'labels_printed_at' => 'datetime'];
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function movements(): HasMany { return $this->hasMany(StockMovement::class); }
    public function allocations(): HasMany { return $this->hasMany(OrderItemAllocation::class); }
    public function scopeAvailable($q) { return $q->where('remaining', '>', 0)->orderBy('received_at'); } // FIFO
    public function getLabelCodeAttribute(): string { return $this->variant->sku.'-'.$this->code; }
}
