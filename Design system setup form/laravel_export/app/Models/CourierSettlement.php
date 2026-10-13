<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class CourierSettlement extends Model
{
    protected $guarded = [];
    protected $casts = ['received_on' => 'date'];
    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
}
