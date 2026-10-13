<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Shipment extends Model
{
    protected $guarded = [];
    protected $casts = ['packed_at' => 'datetime', 'handed_over_at' => 'datetime', 'delivered_at' => 'datetime', 'status_updated_at' => 'datetime'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
}
