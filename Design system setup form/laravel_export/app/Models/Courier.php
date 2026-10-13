<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Courier extends Model
{
    protected $guarded = [];
    protected $casts = ['credentials' => 'encrypted:array', 'is_active' => 'bool'];
    public function shipments(): HasMany { return $this->hasMany(Shipment::class); }
}
