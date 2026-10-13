<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Brand extends OpsModel
{
    protected $guarded = [];
    public function products(): HasMany { return $this->hasMany(Product::class); }
}
