<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Campaign extends OpsModel
{
    protected $guarded = [];
    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date'];
    public function orders(): HasMany { return $this->hasMany(Order::class); }
}
