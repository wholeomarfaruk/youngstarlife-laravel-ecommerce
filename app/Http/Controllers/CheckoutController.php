<?php

namespace App\Http\Controllers;

use App\Models\Ops\{DeliveryZone, Order};
use Illuminate\Support\Facades\DB;
use App\Services\Storefront\{CartService, CheckoutService};
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * One page for cart, checkout and order received. Placing the order (fetch) swaps the page to the confirmation and
 * changes the URL to /checkout/order/{id} without a reload; that URL also renders the confirmation directly (refresh, no-JS).
 */
class CheckoutController extends Controller
{
    public function __construct(private CartService $cart, private CheckoutService $checkout) {}

    public function show()
    {
        if (! site()->cartEnabled()) return redirect()->route('shop');   // cart off: ordering happens on the product page
        $areas = DeliveryZone::where('is_active', true)->orderBy('charge')->orderBy('id')->get();
        return view('checkout', ['state' => 'checkout', 'areas' => $areas, 'summary' => $this->cart->summary($areas->first()?->id),
            'checkoutToken' => (string) Str::uuid(), 'order' => null, 'orderItems' => collect(), 'purchase' => null]);
    }

    public function order(Order $order)
    {
        if (! $this->checkout->canView($order)) return redirect()->route('checkout');
        $items = $this->checkout->lines($order);
        return view('checkout', ['state' => 'received', 'areas' => collect(), 'summary' => null, 'checkoutToken' => null, 'order' => $order, 'orderItems' => $items,
            'purchase' => $this->checkout->shouldTrackPurchase($order) ? $this->checkout->purchaseEvent($order, $items) : null]);
    }

    public function place(Request $request)
    {
        if (! site()->cartEnabled()) throw ValidationException::withMessages(['cart' => 'কার্ট এখন বন্ধ আছে, প্রোডাক্ট পেজ থেকে সরাসরি অর্ডার করুন।']);
        $phone = Phone::normalize($request->phone);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', fn ($a, $v, $fail) => $phone ? null : $fail('সঠিক মোবাইল নম্বর দিন (যেমন 01XXXXXXXXX)')],
            'address' => 'required|string|max:1000',
            'delivery_area' => ['required', 'integer', \Illuminate\Validation\Rule::exists('ops_delivery_zones', 'id')->where('is_active', true)],
            'checkout_token' => 'nullable|uuid',
        ], ['name.required' => 'আপনার নাম লিখুন', 'address.required' => 'আপনার ঠিকানা লিখুন', 'delivery_area.*' => 'ডেলিভারি এরিয়া নির্বাচন করুন']);
        $data['phone'] = $phone;

        try {
            $order = $this->checkout->place($request, $data);
        } catch (ValidationException $e) {
            if (! $request->expectsJson() && isset($e->errors()['duplicate'])) return redirect()->route('checkout')->with(['status' => 'success', 'message' => $e->errors()['duplicate'][0]]);
            throw $e;
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            throw ValidationException::withMessages(['cart' => 'অর্ডার প্রসেস হচ্ছে, কয়েক সেকেন্ড পর আবার চেষ্টা করুন।']);
        } catch (\Throwable $e) {
            report($e);   // full details for us, a friendly message for the customer
            throw ValidationException::withMessages(['cart' => site()->text('content.order_failed')]);
        }

        $url = route('checkout.order', $order);
        if (! $request->expectsJson()) return redirect($url);
        $items = $this->checkout->lines($order);
        return response()->json([
            'ok' => true, 'order_id' => $order->id, 'invoice' => CheckoutService::publicNumber($order), 'url' => $url,
            'html' => view('partials.storefront.order-received', ['order' => $order, 'orderItems' => $items])->render(),
            'purchase' => $this->checkout->shouldTrackPurchase($order) ? $this->checkout->purchaseEvent($order, $items) : null,
        ]);
    }

    /** abandoned-checkout lead, sent in the background (fetch keepalive / sendBeacon) */
    public function autosave(Request $request)
    {
        $phone = Phone::normalize($request->phone);
        if (! $phone) return response()->json(['ok' => false], 422);
        try {
            $this->checkout->autosave($request, ['phone' => $phone, 'name' => Str::limit((string) $request->name, 250, ''), 'address' => Str::limit((string) $request->address, 1000, ''),
                'delivery_area' => DeliveryZone::where('is_active', true)->whereKey($request->integer('delivery_area'))->value('id')]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false], 500);
        }
        return response()->json(['ok' => true]);
    }

    /** old links /order-received?order=ID: the old website order id → its imported Ops order (ops:import-legacy) */
    public function legacyReceived(Request $request)
    {
        $id = DB::table('ops_legacy_links')->where('source', 'order')->where('legacy_id', $request->integer('order'))->value('ops_id');
        return $id ? redirect()->route('checkout.order', $id) : redirect()->route('checkout');
    }
}
