<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Customer extends Model
{
    protected $guarded = [];
    protected $casts = ['last_order_at' => 'datetime', 'is_blocked' => 'bool'];
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function getDeliveryRateAttribute(): ?float { $d = $this->delivered_count + $this->returned_count; return $d ? $this->delivered_count / $d : null; }
}
