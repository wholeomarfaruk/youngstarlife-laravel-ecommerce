<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class StockMovement extends Model
{
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];
    public function batch(): BelongsTo { return $this->belongsTo(Batch::class); }
    public function ref(): MorphTo { return $this->morphTo(); }
}
