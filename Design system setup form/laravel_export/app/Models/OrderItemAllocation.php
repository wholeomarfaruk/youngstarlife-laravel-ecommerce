<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class OrderItemAllocation extends Model
{
    protected $guarded = [];
    protected $casts = ['packed_at' => 'datetime', 'is_manual' => 'bool'];
    public function item(): BelongsTo { return $this->belongsTo(OrderItem::class, 'order_item_id'); }
    public function batch(): BelongsTo { return $this->belongsTo(Batch::class); }
}
