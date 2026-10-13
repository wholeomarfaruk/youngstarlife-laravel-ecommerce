<?php

namespace App\Services\Storefront;

use App\Models\Ops\{DeliveryZone, Product, ProductVariant};
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Everything the product page needs, as one array (rendered once, then the page runs in Alpine with no server calls until
 * add-to-cart / order). Cached 60 s per product version — stock and prices are re-checked on the server when ordering.
 *
 * Kinds (what the customer picks): simple (nothing) · variable (one size) · combo (a size per item; each item's sizes are
 * the item product's variants in the item's colour). A combo without items, or with sizes on the combo itself, behaves as variable.
 */
class ProductPage
{
    public const MAX_QTY = CartService::MAX_QTY;

    /** colour name → swatch (combo rows, description list); unknown colours get a neutral grey */
    protected const SWATCH = [
        'black' => '#1f1f1f', 'white' => '#f6f6f2', 'off-white' => '#ece6d6', 'off white' => '#ece6d6', 'cream' => '#efe6cf', 'beige' => '#c9b48f',
        'khaki' => '#b9a77c', 'olive' => '#6b6b3a', 'army' => '#4b5320', 'green' => '#3d6b45', 'navy' => '#1f2f4f', 'navy blue' => '#1f2f4f',
        'blue' => '#3b5998', 'deep blue' => '#2c3e66', 'light blue' => '#8fb1d6', 'sky blue' => '#8fc1e3', 'ash' => '#8c8f91', 'ash deep' => '#5b5e60',
        'grey' => '#8a8d8a', 'gray' => '#8a8d8a', 'dark grey' => '#4f5254', 'charcoal' => '#3b3d3f', 'brown' => '#6b4a2f', 'coffee' => '#5a3e2b',
        'chocolate' => '#4e3524', 'maroon' => '#6b1f2a', 'red' => '#b3261e', 'pink' => '#e7a5b5', 'purple' => '#5b3f7a', 'yellow' => '#e5c04a', 'orange' => '#e07b39',
    ];

    public static function trackStock(): bool { return (bool) site('batch.website_stock', false); }

    /** cached payload for the page */
    public function data(Product $p): array
    {
        $key = 'pp:'.$p->id.':'.$p->updated_at?->timestamp.':'.(int) self::trackStock();
        return cache()->remember($key, 60, fn () => $this->build($p->loadMissing(['variants' => fn ($q) => $q->orderBy('id'), 'comboItems.variant.product.variants'])));
    }

    protected function build(Product $p): array
    {
        $orderable = $p->isOrderable();
        $combo = $p->type === 'combo' && $p->comboItems->isNotEmpty() ? $this->comboRows($p) : null;
        $sized = $p->variants->where('is_active', true)->filter(fn ($v) => filled($v->size))->values();
        $kind = $combo ? 'combo' : ($sized->isNotEmpty() ? 'variable' : 'simple');

        $price = $p->sellPrice(); $old = $p->hasDiscount() ? (int) $p->price : 0;
        $sizes = $kind === 'variable' ? $sized->map(fn ($v) => $this->sizeOption($v->setRelation('product', $p), $orderable))->unique('v')->values()->all() : [];
        $simpleAvail = $kind === 'simple' ? ($p->variants->where('is_active', true)->first()?->available ?? 0) : null;
        $comboAvail = $combo ? $p->loadMissing('comboItems.variant')->combo_available : null;
        $stockMax = fn (?int $n) => self::trackStock() && $n !== null ? max(0, min(self::MAX_QTY, $n)) : self::MAX_QTY;
        $inStock = $orderable && match ($kind) {
            'variable' => collect($sizes)->contains('out', false),
            'combo' => collect($combo)->every(fn ($r) => collect($r['sizes'])->contains('out', false)),
            default => ! self::trackStock() || $simpleAvail > 0,
        };

        $images = collect([$p->image_url])->merge($p->gallery_urls)->filter()->unique()->values();
        $thumbs = $images->map(fn ($u, $i) => $i === 0 ? $p->thumb_url : $u)->all();
        [$chartHead, $chartRows] = $this->chart((string) $p->size_chart);
        $areas = DeliveryZone::where('is_active', true)->orderBy('charge')->orderBy('id')->get(['id', 'name', 'charge']);
        $default = $areas->first(fn ($a) => preg_match('/outside|বাইরে/iu', $a->name)) ?? $areas->first();

        return [
            'id' => $p->id, 'kind' => $kind, 'name' => $p->name, 'catalogId' => $p->catalogId(), 'gaCategory' => site('pixels.item_category'), 'gaBrand' => site('pixels.item_brand'),
            'price' => $price, 'old' => $old, 'inStock' => $inStock, 'orderable' => $orderable,
            'images' => $images->all(), 'thumbs' => $thumbs,
            'sizes' => $sizes, 'combo' => $combo, 'maxQty' => $kind === 'combo' ? $stockMax($comboAvail) : ($kind === 'simple' ? $stockMax($simpleAvail) : self::MAX_QTY),
            'chart' => ['head' => $chartHead, 'rows' => $chartRows, 'image' => $p->size_chart_url],
            'features' => $this->features((string) ($p->description ?: $p->short_description)),
            'areas' => $areas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'fee' => (int) $a->charge])->all(), 'area' => $default?->id,
        ];
    }

    /** a size chip: price may differ per variant */
    protected function sizeOption(ProductVariant $v, bool $orderable): array
    {
        $price = $v->effective_price; $regular = (int) ($v->price ?? $v->product->price);
        return ['v' => (string) $v->size, 'id' => $v->id, 'price' => $price, 'old' => $regular > $price ? $regular : 0,
            'out' => ! $orderable || (self::trackStock() && $v->available <= 0), 'max' => self::trackStock() ? max(0, min(self::MAX_QTY, $v->available)) : self::MAX_QTY];
    }

    /** combo rows: the admin's component variant fixes product + colour; the customer picks the size among that product's variants in that colour */
    protected function comboRows(Product $p): array
    {
        return $p->comboItems->values()->map(function ($c, $i) use ($p) {
            $base = $c->variant; $item = $base?->product;
            $choices = $this->choicesFor($base);
            $color = $base?->color ?: ($base?->options['Color'] ?? $base?->options['color'] ?? null);
            return [
                'id' => $c->id, 'n' => $i + 1, 'qty' => (int) $c->qty, 'name' => $item?->name ?? 'Item', 'color' => $color, 'swatch' => self::swatch($color),
                'image' => $item?->thumb_url,
                'sizes' => $choices->map(fn ($v) => ['v' => (string) ($v->size ?: '—'), 'id' => $v->id, 'out' => ! $p->isOrderable() || ! $item?->isOrderable() || (self::trackStock() && $v->available < $c->qty)])->values()->all(),
                'fixed' => $choices->count() === 1 && blank($choices->first()->size) ? $choices->first()->id : null,   // nothing to choose
            ];
        })->all();
    }

    /** sizes the customer may choose for a combo item: same product, same colour (or every size when the item has no colour) */
    protected function choicesFor(?ProductVariant $base): Collection
    {
        if (! $base) return collect();
        $all = $base->product?->variants?->where('is_active', true) ?? collect();
        $same = $all->filter(fn ($v) => blank($base->color) || strcasecmp((string) $v->color, (string) $base->color) === 0)->filter(fn ($v) => filled($v->size));
        return $same->isNotEmpty() ? $same->unique('size')->values() : collect([$base]);
    }

    /**
     * Customer's combo choice → the component variants that will be packed.
     * @param array<int|string, string> $sizes combo_item_id => size
     * @return array{key:string, selection:array, text:string}
     */
    public function resolveCombo(Product $p, array $sizes): array
    {
        $p->loadMissing('comboItems.variant.product.variants');
        $selection = [];
        foreach ($p->comboItems as $i => $c) {
            $choices = $this->choicesFor($c->variant);
            if ($choices->count() === 1 && blank($choices->first()->size)) { $v = $choices->first(); }
            else {
                $want = trim((string) ($sizes[$c->id] ?? ''));
                if ($want === '') throw ValidationException::withMessages(['size' => 'প্রতিটি আইটেমের সাইজ বাছাই করুন']);
                $v = $choices->first(fn ($x) => strcasecmp((string) $x->size, $want) === 0);
                if (! $v) throw ValidationException::withMessages(['size' => 'আইটেম '.self::bn($i + 1).': সাইজ '.$want.' পাওয়া যায়নি']);
            }
            if (self::trackStock() && $v->available < $c->qty) throw ValidationException::withMessages(['size' => ($v->product?->name ?? 'Item').' — সাইজ '.$v->size.' স্টকে নেই']);
            $selection[] = ['combo_item_id' => $c->id, 'variant_id' => $v->id, 'qty' => (int) $c->qty, 'size' => $v->size, 'color' => $v->color ?: $c->variant?->color, 'name' => $v->product?->name];
        }
        $text = collect($selection)->map(fn ($s) => $s['name'].' ('.collect([$s['color'], $s['size']])->filter()->join(', ').')')->join(' + ');
        return ['key' => collect($selection)->pluck('size')->map(fn ($s) => (string) $s)->join('|'), 'selection' => $selection, 'text' => $text];
    }

    /** "Size | Waist | Length" lines (Ops → product → Size chart) → table */
    protected function chart(string $text): array
    {
        $rows = collect(preg_split('/\r?\n/', trim($text)))->map(fn ($l) => array_map('trim', explode('|', $l)))->filter(fn ($r) => count($r) > 1)->values();
        return $rows->isEmpty() ? [[], []] : [$rows->first(), $rows->slice(1)->values()->all()];
    }

    /** description → one ✓ line per paragraph / list item / line break */
    protected function features(string $html): array
    {
        $text = html_entity_decode(strip_tags(preg_replace('#<(br|/p|/li|/div|/h\d)[^>]*>#i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return collect(preg_split('/\r?\n/', $text))->map(fn ($l) => trim(preg_replace('/^[\s\-–•✓✔*·»]+/u', '', $l)))->filter(fn ($l) => mb_strlen($l) > 1)->values()->take(20)->all();
    }

    public static function swatch(?string $color): string
    {
        $c = mb_strtolower(trim((string) $color));
        if ($c === '') return '#d6d6d1';
        if (preg_match('/^#[0-9a-f]{3,6}$/', $c)) return $c;
        if (isset(self::SWATCH[$c])) return self::SWATCH[$c];
        foreach (self::SWATCH as $k => $hex) if (str_contains($c, $k)) return $hex;
        return '#d6d6d1';
    }

    /** 12 → ১২ */
    public static function bn(int|string $n): string { return strtr((string) $n, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']); }
}
