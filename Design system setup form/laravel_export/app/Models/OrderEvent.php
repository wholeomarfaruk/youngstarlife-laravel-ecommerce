<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class OrderEvent extends Model
{
    protected $guarded = [];
    public $timestamps = false; protected $casts = ['created_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
