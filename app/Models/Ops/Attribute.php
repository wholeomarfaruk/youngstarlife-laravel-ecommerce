<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** Global, reusable product option (Color, Size, Material …) — values are defined once and picked on every product. */
class Attribute extends OpsModel
{
    protected $guarded = [];
    protected $hidden = ['values'];   // legacy JSON column — use terms()
    protected $casts = ['is_filterable' => 'bool'];
    public const TYPES = ['color' => 'Color swatch', 'size' => 'Size buttons', 'text' => 'Text'];

    public function terms(): HasMany { return $this->hasMany(AttributeValue::class)->orderBy('sort')->orderBy('id'); }

    /** id => Attribute with values, cached — read by product edit + storefront */
    public static function catalog() { return cache()->remember('ops.attributes', 600, fn () => static::with('terms')->orderBy('sort')->orderBy('id')->get()->keyBy('id')); }
    public static function flush(): void { cache()->forget('ops.attributes'); }

    /** products using this attribute (ops_products.options JSON) */
    public function productsCount(): int { return Product::whereJsonContains('options', ['attribute_id' => $this->id])->count(); }
}
