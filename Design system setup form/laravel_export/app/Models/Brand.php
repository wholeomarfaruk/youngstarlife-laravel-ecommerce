<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Brand extends Model
{
    protected $guarded = [];
    public function products(): HasMany { return $this->hasMany(Product::class); }
}
