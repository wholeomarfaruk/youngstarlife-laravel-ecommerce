<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class ProductVariant extends Model
{
    protected $guarded = [];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function batches(): HasMany { return $this->hasMany(Batch::class, 'variant_id'); }
    public function getLabelAttribute(): string { return trim($this->product->name.' · '.$this->color.' '.$this->size); }
    public function getAvailableAttribute(): int { return $this->stock - $this->reserved; }
    public function getEffectivePriceAttribute(): int { return $this->sale_price ?? $this->price ?? $this->product->sale_price ?? $this->product->price; }
}
