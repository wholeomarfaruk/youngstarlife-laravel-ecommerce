<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class OrderItem extends OpsModel
{
    protected $guarded = [];
    public $timestamps = false; // table has no created_at/updated_at
    protected $casts = ['combo_selection' => 'array'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
    public function allocations(): HasMany { return $this->hasMany(OrderItemAllocation::class); }

    /** combo chosen on the website: "6 Pocket Cargo (Beige, 32) + Denim Pant (Black, 34)" — what the packer must put in the parcel */
    public function getComboTextAttribute(): ?string
    {
        if (! $this->combo_selection) return null;
        return collect($this->combo_selection)->map(function ($c) {
            $what = collect([$c['color'] ?? null, $c['size'] ?? null])->filter()->join(', ');
            return ($c['name'] ?? 'Item').($what !== '' ? ' ('.$what.')' : '').(($c['qty'] ?? 1) > 1 ? ' ×'.$c['qty'] : '');
        })->join(' + ');
    }
}
