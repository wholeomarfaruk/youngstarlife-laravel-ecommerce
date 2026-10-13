<?php

namespace App\Services\Storefront;

use App\Models\{Cart, CartItem};
use App\Models\Ops\{DeliveryZone, Product, ProductVariant};
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** The current device's cart. Lines are Ops variants (product + size); prices are always re-read live (the stored price is only a snapshot). */
class CartService
{
    public const MAX_QTY = 50;   // per line — same cap as the old one-product order form

    public function __construct(private DeviceManager $devices) {}

    public function cart(bool $create = false): ?Cart
    {
        if (! $device = $this->devices->current()) {
            if ($create) throw ValidationException::withMessages(['cart' => 'আপনার ব্রাউজারে কুকি বন্ধ আছে। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);   // never a fatal error for a cookie-less visitor
            return null;
        }
        return $create ? Cart::firstOrCreate(['device_id' => $device->id], ['user_id' => $device->user_id]) : Cart::where('device_id', $device->id)->first();
    }

    /** @return Collection<CartItem> newest first, with only the product columns the cart shows (the drawer polls this — keep it lean) */
    public function items(): Collection
    {
        return $this->cart()?->items()->with(['variant:id,product_id,size,price,sale_price,is_active', 'variant.product:id,type,name,slug,featured_image,price,sale_price,status,sold_out'])->latest('id')->get() ?? collect();
    }

    public function count(): int { return (int) ($this->cart()?->items()->sum('quantity') ?? 0); }

    /** the variant the customer means: the chosen size, or the product's only (size-less) variant */
    public function variantFor(Product $product, ?string $size): ProductVariant
    {
        $variants = $product->variants->where('is_active', true);
        $size = trim((string) $size);
        if ($variants->whereNotNull('size')->isNotEmpty()) {
            $hit = $variants->first(fn ($v) => $v->size !== null && strcasecmp($v->size, $size) === 0);
            if (! $hit) throw ValidationException::withMessages(['size' => 'অনুগ্রহ করে একটি সাইজ সিলেক্ট করুন']);
            return $hit;
        }
        return $variants->first() ?? throw ValidationException::withMessages(['product_id' => 'এই প্রোডাক্টটি এখন স্টকে নেই']);
    }

    /** @param array<int|string,string>|null $comboSizes combo_item_id => size (combo with a size per item) */
    public function add(Product $product, int $qty, ?string $size, ?array $comboSizes = null): CartItem
    {
        $line = $this->line($product, $qty, $size, $comboSizes);
        $cart = $this->cart(true);
        $item = $cart->items()->firstOrNew(['variant_id' => $line->variant_id, 'selection_key' => $line->selection_key]);
        $item->combo_selection = $line->combo_selection;
        $item->quantity = min(self::MAX_QTY, ($item->exists ? $item->quantity : 0) + max(1, $qty));
        $item->price = $line->price;
        $item->save();
        $cart->touch();
        return $item->setRelation('variant', $line->variant);
    }

    /** an unsaved cart line for what the customer picked (shared by add-to-cart and the product page's quick order) */
    public function line(Product $product, int $qty, ?string $size, ?array $comboSizes = null): CartItem
    {
        if (! $product->isOrderable()) throw ValidationException::withMessages(['product_id' => 'এই প্রোডাক্টটি এখন স্টকে নেই']);
        $combo = $product->type === 'combo' && $product->comboItems()->exists();
        if ($combo) {
            $pick = app(ProductPage::class)->resolveCombo($product, $comboSizes ?? []);
            $variant = $product->variants->where('is_active', true)->first() ?? throw ValidationException::withMessages(['product_id' => 'এই কম্বোটি এখন স্টকে নেই']);
        } else {
            $variant = $this->variantFor($product, $size);
            if (ProductPage::trackStock() && $variant->available < max(1, $qty)) throw ValidationException::withMessages(['size' => 'দুঃখিত, এই সাইজের '.($variant->available > 0 ? 'মাত্র '.ProductPage::bn($variant->available).'টি আছে' : 'স্টক শেষ')]);
        }
        $variant->setRelation('product', $product);
        return (new CartItem(['variant_id' => $variant->id, 'selection_key' => $combo ? $pick['key'] : '', 'combo_selection' => $combo ? $pick['selection'] : null,
            'quantity' => min(self::MAX_QTY, max(1, $qty)), 'price' => $variant->effective_price]))->setRelation('variant', $variant);
    }

    /** totals for one product without touching the cart (product page order form) */
    public function quote(Product $product, int $qty, ?string $size, ?array $comboSizes, ?int $areaId): array
    {
        return $this->summary($areaId, collect([$this->line($product, $qty, $size, $comboSizes)]));
    }

    public function update(int $itemId, int $qty): void
    {
        $item = $this->find($itemId);
        $qty < 1 ? $item->delete() : $item->update(['quantity' => min(self::MAX_QTY, $qty)]);
    }

    public function remove(int $itemId): void { $this->find($itemId)->delete(); }

    public function clear(): void { $this->cart()?->items()->delete(); }

    /** Totals with live prices. Lines whose product is gone, hidden or out of stock are flagged (and left out of the total). */
    public function summary(?int $areaId = null, ?Collection $items = null): array
    {
        $items ??= $this->items();
        $lines = $items->map(function (CartItem $i) {
            $v = $i->variant; $p = $v?->product; $ok = $p && $v->is_active && $p->isOrderable();
            $price = $v && $p ? $v->effective_price : (int) $i->price;
            $regular = (int) ($v?->price ?? $p?->price ?? $price);
            $combo = $i->combo_selection ? collect($i->combo_selection)->map(fn ($c) => ($c['name'] ?? '').' ('.collect([$c['color'] ?? null, $c['size'] ?? null])->filter()->join(', ').')')->join(' + ') : null;
            return ['id' => $i->id, 'variant_id' => $i->variant_id, 'product_id' => $p?->id, 'catalog_id' => $p?->catalogId(), 'name' => $p?->name ?? 'Unavailable product', 'slug' => $p?->slug,
                'image_url' => $p?->thumb_url, 'url' => $p ? route('product.show', $p->slug) : null,
                'size' => $combo ? $i->selection_key : $v?->size, 'variant_text' => $combo ?? ($v?->size ? 'সাইজ '.$v->size : null), 'combo_selection' => $i->combo_selection,
                'qty' => (int) $i->quantity, 'price' => $price, 'regular' => max($regular, $price), 'line' => $price * $i->quantity, 'available' => $ok];
        });
        $ok = $lines->where('available', true);
        $area = $areaId ? DeliveryZone::where('is_active', true)->find($areaId) : null;
        $subtotal = (float) $ok->sum('line');
        $delivery = (float) ($area?->charge ?? 0);
        return ['lines' => $lines->values()->all(), 'count' => (int) $ok->sum('qty'), 'subtotal' => $subtotal, 'delivery' => $delivery, 'total' => $subtotal + $delivery, 'has_unavailable' => $ok->count() !== $lines->count()];
    }

    /** a line of THIS device's cart (never another visitor's) */
    protected function find(int $itemId): CartItem
    {
        $cart = $this->cart();
        abort_unless($cart, 404);
        return $cart->items()->findOrFail($itemId);
    }
}
