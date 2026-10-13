<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class PurchaseOrderItem extends Model
{
    protected $guarded = [];
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
    public function getRemainingAttribute(): int { return max(0, $this->qty - $this->received); }
}
