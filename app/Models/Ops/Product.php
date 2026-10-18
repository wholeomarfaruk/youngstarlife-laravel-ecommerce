<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends OpsModel
{
    protected $guarded = [];
    use SoftDeletes;
    protected $casts = ['gallery' => 'array', 'dimensions' => 'array', 'tags' => 'array', 'sale_starts_at' => 'date', 'sale_ends_at' => 'date', 'requires_shipping' => 'bool', 'options' => 'array', 'sold_out' => 'bool'];

    /** a new product goes to the end of Home / Shop (Storefront → Product order), as in the old admin — not to the top with 0 */
    protected static function booted(): void
    {
        static::creating(fn (self $p) => $p->sort_order ??= (int) static::withTrashed()->max('sort_order') + 1);
    }

    /** attach categories at the end of each category's order (ops_category_product.sort_order); already-linked ones keep their place */
    public function syncCategoriesAtEnd(array $ids): void
    {
        $ids = array_map('intval', $ids);
        $new = array_diff($ids, $this->categories()->pluck('ops_categories.id')->all());
        $this->categories()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => in_array($id, $new)
            ? ['sort_order' => (int) \Illuminate\Support\Facades\DB::table('ops_category_product')->where('category_id', $id)->max('sort_order') + 1] : []])->all());
    }
    /** combo: sets that can be made = min over components of floor(available / qty) */
    public function getComboAvailableAttribute(): int { return (int) ($this->comboItems->map(fn ($c) => intdiv(max(0, $c->variant->stock - $c->variant->reserved), max(1, $c->qty)))->min() ?? 0); }

    public function variants(): HasMany { return $this->hasMany(ProductVariant::class); }
    public function categories(): BelongsToMany { return $this->belongsToMany(Category::class, 'ops_category_product')->withPivot('sort_order'); }
    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function suppliers(): BelongsToMany { return $this->belongsToMany(Supplier::class, 'ops_product_supplier')->withPivot('supplier_code', 'supplier_name', 'image'); }
    public function comboItems(): HasMany { return $this->hasMany(ComboItem::class); }

    // ── storefront (public website) ─────────────────────────────────────────────────────────────────
    public function scopeStorefront($q) { return $q->where('status', 'active'); }
    /** what the customer pays when no size is chosen yet: the sale price when it is lower */
    public function sellPrice(): int { return (int) ($this->sale_price && $this->sale_price < $this->price ? $this->sale_price : $this->price); }
    public function hasDiscount(): bool { return $this->sale_price && $this->sale_price < $this->price; }
    /** listed and not switched to "Stock out" (stock itself is managed in batches; the website keeps selling) */
    public function isOrderable(): bool { return $this->status === 'active' && ! $this->sold_out; }
    /** a stored path → URL: public-disk paths (uploads and imported images), public/ paths, or full URLs */
    public static function mediaUrl(?string $path): ?string
    {
        if (! $path) return null;
        if (preg_match('#^https?://#', $path)) return $path;
        return str_starts_with($path, 'images/category/') ? asset($path) : asset('storage/'.ltrim($path, '/'));
    }
    /** id used by the Facebook catalog feed + pixel / GA events: imported products keep their old website id (ads and
     *  catalog history stay matched), products created in Ops are "ops-{id}" so the two can never collide */
    public function catalogId(): string
    {
        static $legacy = null;
        $legacy ??= \Illuminate\Support\Facades\DB::table('ops_legacy_links')->where('source', 'product')->pluck('legacy_id', 'ops_id')->all();
        return isset($legacy[$this->id]) ? (string) $legacy[$this->id] : 'ops-'.$this->id;
    }
    public function getImageUrlAttribute(): string { return static::mediaUrl($this->featured_image) ?? asset('website/img/thumbnails/featured_img.jpg'); }
    /** imported images have a small copy in images/products/thumbnails/; new uploads use the image itself */
    public function getThumbUrlAttribute(): string
    {
        $f = (string) $this->featured_image;
        return preg_match('#^images/products/([^/]+)$#', $f, $m) ? asset('storage/images/products/thumbnails/'.$m[1]) : $this->image_url;
    }
    public function getGalleryUrlsAttribute(): array { return collect($this->gallery ?? [])->map(fn ($p) => static::mediaUrl($p))->filter()->values()->all(); }
    public function getSizeChartUrlAttribute(): ?string { return static::mediaUrl($this->size_chart_image); }
    /** sizes the customer can pick (active variants, in variant order) */
    public function getSizeOptionsAttribute(): array { return $this->variants->where('is_active', true)->pluck('size')->filter()->unique()->values()->all(); }

    public function scopeSearch($q, ?string $s) { return $s ? $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")) : $q; }
}
