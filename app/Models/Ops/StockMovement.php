<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class StockMovement extends OpsModel
{
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];
    public function batch(): BelongsTo { return $this->belongsTo(Batch::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
    public function ref(): MorphTo { return $this->morphTo(); }
}
