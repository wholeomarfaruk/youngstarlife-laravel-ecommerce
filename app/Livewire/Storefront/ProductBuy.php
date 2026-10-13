<?php

namespace App\Livewire\Storefront;

use App\Models\Ops\{DeliveryZone, Product};
use App\Services\Storefront\{CartService, CheckoutService, ProductPage};
use App\Support\Phone;
use Illuminate\Support\Facades\{Log, RateLimiter};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * Product page: price, size / combo choice, quantity, add to cart and the cash-on-delivery order form.
 * All choosing happens in Alpine (no server round trip); the three actions below are #[Renderless] — one small JSON
 * answer each, no re-render. Prices, stock, delivery fee, blacklist and duplicate rules are always decided here.
 */
class ProductBuy extends Component
{
    public int $productId;
    public string $token = '';          // idempotency: a double tap places one order
    protected ?Product $product = null;

    public function mount(Product $product): void
    {
        $this->productId = $product->id; $this->product = $product; $this->token = (string) Str::uuid();
    }

    /** @param array{qty?:int, size?:?string, combo?:array} $pick */
    #[Renderless]
    public function addToCart(array $pick, CartService $cart): array
    {
        if ($e = $this->limited('cart', 30)) return $e;
        try {
            $p = $this->product();
            $qty = $this->qty($pick);
            $item = $cart->add($p, $qty, $pick['size'] ?? null, $pick['combo'] ?? null);
        } catch (ValidationException $e) {
            return ['ok' => false, 'error' => collect($e->errors())->flatten()->first()];
        }
        $summary = $cart->summary();
        return ['ok' => true, 'count' => $summary['count'], 'item_id' => $item->id, 'cart' => $summary,
            'event' => ['event' => 'add_to_cart', 'ecommerce' => ['currency' => 'BDT', 'value' => (int) $item->price * $qty, 'items' => [$this->gaItem($p, (int) $item->price, $qty, $item->selection_key ?: $item->variant?->size)]]]];
    }

    /** @param array{name?:string, phone?:string, address?:string, area?:int} $form */
    #[Renderless]
    public function placeOrder(array $pick, array $form, CartService $cart, CheckoutService $checkout): array
    {
        if ($e = $this->limited('order', 8)) return $e;
        $phone = Phone::normalize($form['phone'] ?? null);
        $name = trim((string) ($form['name'] ?? '')); $address = trim((string) ($form['address'] ?? ''));
        $area = DeliveryZone::where('is_active', true)->whereKey((int) ($form['area'] ?? 0))->value('id');
        if ($name === '' || ! $phone || $address === '') return ['ok' => false, 'error' => 'নাম, সঠিক ১১ ডিজিটের মোবাইল নাম্বার ও ঠিকানা লিখুন', 'field' => ! $phone ? 'phone' : ($name === '' ? 'name' : 'address')];
        if (! $area) return ['ok' => false, 'error' => 'ডেলিভারি এরিয়া নির্বাচন করুন'];

        try {
            $p = $this->product();
            $summary = $cart->quote($p, $this->qty($pick), $pick['size'] ?? null, $pick['combo'] ?? null, $area);
            if (! collect($summary['lines'])->every('available')) return ['ok' => false, 'error' => 'এই প্রোডাক্টটি এখন স্টকে নেই'];
            $order = $checkout->place(request(), ['name' => mb_substr($name, 0, 255), 'phone' => $phone, 'address' => mb_substr($address, 0, 1000), 'delivery_area' => $area, 'checkout_token' => $this->token], $summary);
        } catch (ValidationException $e) {
            return ['ok' => false, 'error' => collect($e->errors())->flatten()->first()];
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return ['ok' => false, 'error' => 'অর্ডার প্রসেস হচ্ছে, কয়েক সেকেন্ড পর আবার চেষ্টা করুন।'];
        } catch (\Throwable $e) {
            report($e);
            return ['ok' => false, 'error' => site()->text('content.order_failed')];
        }

        $this->token = (string) Str::uuid();   // "আবার অর্ডার করুন" is a new order
        $lines = $checkout->lines($order);
        return ['ok' => true, 'invoice' => CheckoutService::publicNumber($order), 'phone' => $phone, 'total' => (int) $order->total, 'url' => route('checkout.order', $order),
            'purchase' => $checkout->shouldTrackPurchase($order) ? $checkout->purchaseEvent($order, $lines) : null];
    }

    /** a lead for Orders → Drafts once the phone is complete (the customer may still leave) — never shows an error */
    #[Renderless]
    public function autosave(array $pick, array $form, CartService $cart, CheckoutService $checkout): void
    {
        $phone = Phone::normalize($form['phone'] ?? null);
        if (! $phone || $this->limited('autosave', 6)) return;
        try {
            $area = DeliveryZone::where('is_active', true)->whereKey((int) ($form['area'] ?? 0))->value('id');
            $summary = $cart->quote($this->product(), $this->qty($pick), $pick['size'] ?? null, $pick['combo'] ?? null, $area);
            $checkout->autosave(request(), ['phone' => $phone, 'name' => Str::limit((string) ($form['name'] ?? ''), 250, ''), 'address' => Str::limit((string) ($form['address'] ?? ''), 1000, ''), 'delivery_area' => $area], $summary);
        } catch (ValidationException) {
            // size not chosen yet — nothing to save
        } catch (\Throwable $e) {
            Log::warning('Product page autosave failed: '.$e->getMessage());
        }
    }

    protected function product(): Product
    {
        return $this->product ??= Product::storefront()->with('variants')->find($this->productId) ?? throw ValidationException::withMessages(['product_id' => 'প্রোডাক্টটি পাওয়া যায়নি, পেজটি রিফ্রেশ করুন']);
    }

    protected function qty(array $pick): int { return max(1, min(CartService::MAX_QTY, (int) ($pick['qty'] ?? 1))); }

    /** per visitor (the device cookie) and minute — not per IP: mobile users in BD share carrier IPs (CGNAT) */
    protected function limited(string $what, int $perMinute): ?array
    {
        $device = app(\App\Services\Storefront\DeviceManager::class)->current()?->id;
        $key = 'pp:'.$what.':'.($device ? 'd'.$device : 'ip'.request()->ip());
        if (! $device) $perMinute *= 10;   // cookie-less: shared IP, be generous
        if (RateLimiter::tooManyAttempts($key, $perMinute)) return ['ok' => false, 'error' => 'একটু পরে আবার চেষ্টা করুন।'];
        RateLimiter::hit($key, 60);
        return null;
    }

    protected function gaItem(Product $p, int $price, int $qty, ?string $variant): array
    {
        return ['item_name' => $p->name, 'item_id' => $p->catalogId(), 'price' => $price, 'quantity' => $qty, 'item_category' => site('pixels.item_category'), 'item_brand' => site('pixels.item_brand'),
            'item_variant' => $variant ?: null];
    }

    public function render()
    {
        $p = $this->product();
        return view('livewire.storefront.product-buy', ['p' => $p, 'd' => app(ProductPage::class)->data($p)]);
    }
}
