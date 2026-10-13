<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class PurchaseOrder extends OpsModel
{
    protected $guarded = [];
    protected $casts = ['ordered_at' => 'date', 'expected_at' => 'date', 'closed_short' => 'bool'];
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }
    public function batches(): HasMany { return $this->hasMany(Batch::class); }
    public function receipts(): HasMany { return $this->hasMany(GoodsReceipt::class); }
    public function getTotalAttribute(): int { return $this->items->sum(fn ($i) => $i->qty * $i->unit_cost); }
    public function getReceivedValueAttribute(): int { return $this->items->sum(fn ($i) => ($i->received - $i->damaged) * $i->unit_cost); }
}
