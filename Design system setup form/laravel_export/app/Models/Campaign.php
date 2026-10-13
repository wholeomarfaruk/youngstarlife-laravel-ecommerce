<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Campaign extends Model
{
    protected $guarded = [];
    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date'];
    public function orders(): HasMany { return $this->hasMany(Order::class); }
}
