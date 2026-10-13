<?php

namespace App\Http\Controllers;

use App\Models\Ops\Product;
use App\Services\Storefront\CartService;
use Illuminate\Http\Request;

/** Device cart endpoints. JSON for the storefront scripts; plain redirects still work without JavaScript. */
class StorefrontCartController extends Controller
{
    public function __construct(private CartService $cart) {}

    /** add to cart; "buy now" (buy_now=1) goes straight to checkout */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:ops_products,id',
            'quantity' => 'nullable|integer|min:1|max:'.CartService::MAX_QTY,
            'size' => 'nullable|string|max:50',
            'combo' => 'nullable|array|max:8', 'combo.*' => 'nullable|string|max:20',
        ], ['product_id.*' => 'প্রোডাক্টটি পাওয়া যায়নি, পেজটি রিফ্রেশ করে আবার চেষ্টা করুন', 'quantity.*' => 'পরিমাণ ১ থেকে ৫০ এর মধ্যে দিন']);

        $product = Product::storefront()->with('variants')->findOrFail($data['product_id']);
        $item = $this->cart->add($product, (int) ($data['quantity'] ?? 1), $data['size'] ?? null, $data['combo'] ?? null);

        if (! $request->expectsJson()) return $request->boolean('buy_now') ? redirect()->route('checkout') : back()->with('cart_added', true);
        $cart = $this->cart->summary();
        return response()->json([
            'ok' => true, 'count' => $cart['count'], 'checkout_url' => route('checkout'),
            'item_id' => $item->id, 'cart' => $cart,   // the drawer opens with this — no second request
            // GA4 / Meta add_to_cart payload for the units just added
            'event' => ['event' => 'add_to_cart', 'ecommerce' => ['currency' => 'BDT', 'value' => (int) $item->price * (int) ($data['quantity'] ?? 1), 'items' => [[
                'item_name' => $product->name, 'item_id' => $product->catalogId(), 'price' => (int) $item->price, 'quantity' => (int) ($data['quantity'] ?? 1),
                'item_category' => site('pixels.item_category'), 'item_brand' => site('pixels.item_brand'), 'item_variant' => $item->variant?->size]]]],
        ]);
    }

    public function update(Request $request, int $item)
    {
        $this->cart->update($item, (int) $request->validate(['quantity' => 'required|integer|min:0|max:'.CartService::MAX_QTY])['quantity']);
        return $this->summary($request);
    }

    public function destroy(Request $request, int $item)
    {
        $this->cart->remove($item);
        return $this->summary($request);
    }

    /** totals for the checkout page / header badge (delivery_area optional) */
    public function summary(Request $request)
    {
        $s = $this->cart->summary($request->integer('delivery_area') ?: null);
        // per-device data: never cached by the browser or a proxy
        return $request->expectsJson() ? response()->json(['ok' => true] + $s)->header('Cache-Control', 'no-store, private') : back();
    }
}
