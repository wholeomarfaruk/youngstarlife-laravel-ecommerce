<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class DeliveryZone extends OpsModel
{
    protected $guarded = [];
    protected $casts = ['areas' => 'array', 'is_active' => 'bool'];
}
