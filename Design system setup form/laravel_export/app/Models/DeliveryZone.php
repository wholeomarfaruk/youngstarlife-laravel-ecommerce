<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class DeliveryZone extends Model
{
    protected $guarded = [];
    protected $casts = ['areas' => 'array', 'is_active' => 'bool'];
}
