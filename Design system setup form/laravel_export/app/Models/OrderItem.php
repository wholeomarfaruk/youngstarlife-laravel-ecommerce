<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class OrderItem extends Model
{
    protected $guarded = [];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
    public function allocations(): HasMany { return $this->hasMany(OrderItemAllocation::class); }
}
