<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class ComboItem extends Model
{
    protected $guarded = [];
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
}
