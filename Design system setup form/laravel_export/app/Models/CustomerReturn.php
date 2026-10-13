<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class CustomerReturn extends Model
{
    protected $guarded = [];
    protected $casts = ['photos' => 'array', 'refunded_at' => 'datetime'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function items(): HasMany { return $this->hasMany(CustomerReturnItem::class); }
    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
}
