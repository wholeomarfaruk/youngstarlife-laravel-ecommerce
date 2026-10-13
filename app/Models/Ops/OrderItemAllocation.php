<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class OrderItemAllocation extends OpsModel
{
    protected $guarded = [];
    public $timestamps = false; // table has no created_at/updated_at
    protected $casts = ['packed_at' => 'datetime', 'is_manual' => 'bool'];
    public function item(): BelongsTo { return $this->belongsTo(OrderItem::class, 'order_item_id'); }
    public function batch(): BelongsTo { return $this->belongsTo(Batch::class); }
}
