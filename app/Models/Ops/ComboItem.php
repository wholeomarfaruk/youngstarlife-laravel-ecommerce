<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class ComboItem extends OpsModel
{
    protected $guarded = [];
    public $timestamps = false; // table has no created_at/updated_at
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }

    /** the sizes this item can go out in: the linked product's active variants in the linked variant's colour (all of them when it has none) */
    public function choices(): \Illuminate\Support\Collection
    {
        $base = $this->variant; if (! $base) return collect();
        $same = $base->product?->variants?->where('is_active', true)->filter(fn ($v) => blank($base->color) || strcasecmp((string) $v->color, (string) $base->color) === 0)->values();
        return $same && $same->isNotEmpty() ? $same : collect([$base]);
    }
}
