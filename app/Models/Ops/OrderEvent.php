<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class OrderEvent extends OpsModel
{
    protected $guarded = [];
    public $timestamps = false; protected $casts = ['created_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
}
