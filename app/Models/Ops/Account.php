<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Account extends OpsModel
{
    protected $guarded = [];
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }
}
