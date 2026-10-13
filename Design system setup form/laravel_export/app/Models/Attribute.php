<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Attribute extends Model
{
    protected $guarded = [];
    protected $casts = ['values' => 'array'];
}
