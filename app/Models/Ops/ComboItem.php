<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class ComboItem extends OpsModel
{
    protected $guarded = [];
    public $timestamps = false; // table has no created_at/updated_at
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
}
