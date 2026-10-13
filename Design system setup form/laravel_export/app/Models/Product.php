<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    protected $guarded = [];
    use SoftDeletes;
    protected $casts = ['gallery' => 'array', 'dimensions' => 'array'];

    public function variants(): HasMany { return $this->hasMany(ProductVariant::class); }
    public function categories(): BelongsToMany { return $this->belongsToMany(Category::class); }
    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function suppliers(): BelongsToMany { return $this->belongsToMany(Supplier::class)->withPivot('supplier_code', 'supplier_name', 'image'); }
    public function comboItems(): HasMany { return $this->hasMany(ComboItem::class); }

    public function scopeSearch($q, ?string $s) { return $s ? $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")) : $q; }
}
