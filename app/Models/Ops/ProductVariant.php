<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class ProductVariant extends OpsModel
{
    protected $guarded = [];
    protected $casts = ['options' => 'array'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    /** every variant has a barcode: left empty (new or cleared) → an EAN-13 is generated (App\Support\Barcode) */
    protected static function booted(): void
    {
        static::saving(function (self $v) {
            if ($v->exists && trim((string) $v->barcode) === '') $v->barcode = \App\Support\Barcode::forVariant($v->id);
        });
        static::created(function (self $v) {
            if (trim((string) $v->barcode) === '') $v->forceFill(['barcode' => \App\Support\Barcode::forVariant($v->id)])->saveQuietly();
        });
    }

    /** the legacy column an attribute is mirrored into (colour / size are read by labels, scans and reports) */
    public static function columnFor(Attribute $a): ?string { return $a->type === 'color' || $a->slug === 'color' ? 'color' : ($a->type === 'size' || $a->slug === 'size' ? 'size' : null); }

    /** "Blue / M / Cotton" — every option in attribute order, falls back to colour + size */
    public function getOptionTextAttribute(): string
    {
        if (! $this->options) return trim($this->color.' '.$this->size);
        $order = Attribute::catalog()->pluck('sort', 'name');   // MySQL JSON re-orders keys → sort by attribute order
        return collect($this->options)->filter()->sortBy(fn ($v, $k) => $order[$k] ?? 999)->join(' / ');
    }
    public function batches(): HasMany { return $this->hasMany(Batch::class, 'variant_id'); }
    public function movements(): HasMany { return $this->hasMany(StockMovement::class, 'variant_id'); }
    public function getLabelAttribute(): string { return trim($this->product->name.' · '.$this->option_text); }
    public function getAvailableAttribute(): int { return $this->stock - $this->reserved; }
    /** Scanner input → variant: product barcode, SKU, or a printed batch label (SKU-B014, see Batch::label_code). */
    public static function findByScan(string $code): ?self
    {
        $code = trim($code);
        if ($code === '') return null;
        return static::where('barcode', $code)->orWhere('sku', $code)->first()
            ?? (preg_match('/^(.+)-(B\d+)$/i', $code, $m) ? static::where('sku', $m[1])->first() : null);
    }
    /**
     * Scanner input → [variant, batch]. Product barcode / SKU → [variant, null]. The stock-in batch sticker SKU-B014 (or a bare
     * batch code B014) → [that batch's variant, batch]. A sticker whose SKU part isn't the batch's product is not trusted.
     * Packing scan and Return receive both use this, so a unit is traced to the same batch going out and coming back.
     */
    public static function resolveScan(string $code): array
    {
        $code = trim($code); $up = strtoupper($code);
        if ($code === '') return [null, null];
        if ($v = static::where('barcode', $code)->orWhere('sku', $code)->first()) return [$v, null];
        $b = Batch::where('code', $up)->with('variant')->first();
        if (! $b && ($pos = strrpos($up, '-')) !== false) {
            $b = Batch::where('code', substr($up, $pos + 1))->with('variant')->first();
            if ($b && strcasecmp((string) $b->variant?->sku, substr($code, 0, $pos)) !== 0) $b = null;   // B014 belongs to another product
        }
        return $b?->variant ? [$b->variant, $b] : [static::findByScan($code), null];
    }
    public function getEffectivePriceAttribute(): int { return (int) ($this->sale_price ?? $this->price ?? $this->product->sale_price ?? $this->product->price); }
}
