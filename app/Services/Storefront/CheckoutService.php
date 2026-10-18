<?php

namespace App\Services\Storefront;

use App\Models\{Device, User};
use App\Models\Ops\{Campaign, Customer, DeliveryZone, Order, OrderItem};
use App\Notifications\NewOrderPushNotification;
use App\Services\Ops\{Blacklist, OrderWorkflow};
use App\Support\Attribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB, Log, Notification};
use Illuminate\Validation\ValidationException;

/**
 * Website cart → Ops order (same tables, numbering, customer counters and automations as an order made in Ops), account linking,
 * who may see an order, the purchase event, and abandoned checkouts as Ops drafts.
 */
class CheckoutService
{
    public const ABANDONED_TAG = 'Abandoned checkout';
    protected const TRACKED = 'ys_purchase_tracked';   // session: orders whose purchase event already fired

    public function __construct(private DeviceManager $devices, private CartService $cart) {}

    /**
     * @param array{name:string, phone:string, address:string, delivery_area:int, checkout_token:?string} $data  validated, phone normalized
     * Idempotent per checkout_token: a double tap / retried request returns the same order instead of a second one.
     */
    /** $quick: a ready summary (product page order form — one product, the cart is left alone); null = the device's cart */
    public function place(Request $request, array $data, ?array $quick = null): Order
    {
        $device = $this->devices->current();
        if (! $device) throw ValidationException::withMessages(['cart' => 'আপনার ব্রাউজারে কুকি বন্ধ আছে। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
        $key = 'checkout:'.$device->id.':'.($data['checkout_token'] ?? '-');

        return Cache::lock('checkout-device:'.$device->id, 20)->block(15, function () use ($request, $data, $device, $key, $quick) {
            if (($id = Cache::get($key)) && ($done = Order::find($id))) return $done;

            $summary = $quick ?? $this->cart->summary((int) $data['delivery_area']);
            if (! $summary['lines']) throw ValidationException::withMessages(['cart' => 'আপনার কার্ট খালি। আগে প্রোডাক্ট যোগ করুন।']);
            if (! empty($summary['limit_error'])) throw ValidationException::withMessages(['quantity' => $summary['limit_error']]);
            if ($summary['has_unavailable']) throw ValidationException::withMessages(['cart' => 'কার্টের কিছু প্রোডাক্ট এখন স্টকে নেই — সেগুলো সরিয়ে আবার চেষ্টা করুন।']);
            $this->guard($request, $data['phone'], $device, $data['name'] ?? null);
            $zone = DeliveryZone::where('is_active', true)->findOrFail($data['delivery_area']);
            $attribution = $this->attribution($request);

            $order = DB::transaction(function () use ($data, $summary, $zone, $device, $request, $attribution, $quick) {
                // the phone decides the customer (and through it the user account, App\Models\Ops\Concerns\HasUserAccount)
                $customer = Customer::firstOrCreate(['phone' => $data['phone']], ['name' => $data['name'], 'source' => 'website']);
                $order = Order::create([
                    'number' => Order::nextNumber(), 'customer_id' => $customer->id, 'campaign_id' => $this->campaignFor($attribution),
                    'source' => 'website', 'status' => 'pending', 'payment_status' => 'pending', 'is_draft' => false,
                    'ship_name' => mb_substr($data['name'], 0, 255), 'ship_phone' => $data['phone'], 'ship_address' => mb_substr($data['address'], 0, 255), 'delivery_zone_id' => $zone->id,
                    'subtotal' => (int) $summary['subtotal'], 'discount' => 0, 'delivery_charge' => (int) $summary['delivery'], 'total' => (int) $summary['total'],
                    'advance' => 0, 'cod' => (int) $summary['total'], 'note' => mb_strlen($data['address']) > 255 ? 'Full address: '.$data['address'] : null,
                    'device_id' => $device->id, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'attribution' => $attribution, 'placed_at' => now(),
                ]);
                foreach ($summary['lines'] as $l) OrderItem::create(['order_id' => $order->id, 'variant_id' => $l['variant_id'], 'qty' => $l['qty'], 'unit_price' => (int) $l['price'], 'combo_selection' => $l['combo_selection'] ?? null]);
                $order->log('created', $quick ? 'Placed on the website (product page)' : 'Placed on the website');
                $customer->update([
                    'name' => $customer->orders_count ? $customer->name : $data['name'], 'address' => $customer->address ?: mb_substr($data['address'], 0, 255),
                    'area' => $customer->area ?: $zone->name, 'orders_count' => $customer->orders_count + 1, 'total_spent' => $customer->total_spent + $order->total, 'last_order_at' => now(),
                ]);
                if (! $quick) $this->cart->clear();
                return $order;
            });

            // the order is saved; bookkeeping and automations must never turn it into an error page
            try {
                $this->linkAccount($order, $device);
            } catch (\Throwable $e) {
                Log::warning("Account link for order {$order->number} failed: ".$e->getMessage());
            }
            try {
                app(OrderWorkflow::class)->placed($order->fresh());   // risky-customer flag, auto courier, auto-confirm, Ops notification
            } catch (\Throwable $e) {
                Log::warning("Order automations for {$order->number} failed: ".$e->getMessage());
            }
            $this->afterOrder($order);
            cache()->forget('customers.count');
            Cache::put($key, $order->id, now()->addMinutes(30));
            return $order;
        });
    }

    /** Ops blacklist (phone, device, IP, same-browser — Users → Blacklist; attempt is logged) and the 30-minute duplicate rule of the old order form */
    protected function guard(Request $request, string $phone, Device $device, ?string $name = null): void
    {
        $bl = app(Blacklist::class);
        if ($bl->blocking('website', $this->blacklistContext($request, $phone, $device) + ['name' => $name])) throw ValidationException::withMessages(['cart' => $bl->message()]);

        if (Order::live()->where('ship_phone', $phone)->where('status', 'pending')->where('placed_at', '>=', now()->subMinutes(30))->exists())
            throw ValidationException::withMessages(['duplicate' => site()->text('content.order_duplicate')]);
    }

    protected function blacklistContext(Request $request, string $phone, ?Device $device): array
    {
        return ['phone' => $phone, 'device_id' => $device?->id, 'ip' => $request->ip(), 'user_agent' => $request->userAgent()];
    }

    /** which ad / link brought the customer (kept on the order as JSON); never blocks the order */
    protected function attribution(Request $request): ?array
    {
        try {
            $a = Attribution::forOrder($request);
        } catch (\Throwable $e) {
            Log::warning('Order attribution failed: '.$e->getMessage());
            return null;
        }
        $touches = $a['attribution'] ?? null; unset($a['attribution']);
        $a = array_filter($a, fn ($v) => $v !== null && $v !== '');
        if ($touches) $a['touches'] = $touches;
        return $a ?: null;
    }

    /** the Ops campaign for the ad (Orders → Campaign summary); created the first time a campaign brings an order */
    protected function campaignFor(?array $a): ?int
    {
        $name = trim((string) ($a['campaign_name'] ?? $a['utm_campaign'] ?? ''));
        if ($name === '') return null;
        $src = strtolower(($a['site_source'] ?? '').' '.($a['utm_source'] ?? ''));
        $platform = str_contains($src, 'instagram') || preg_match('/\big\b/', $src) ? 'Instagram' : (str_contains($src, 'google') || ! empty($a['gclid']) ? 'Google' : 'Facebook');
        return Campaign::firstOrCreate(['name' => mb_substr($name, 0, 255)], ['platform' => $platform, 'utm_campaign' => $a['utm_campaign'] ?? \Illuminate\Support\Str::slug($name), 'status' => 'active'])->id;
    }

    /** The customer's user account (the phone decides it, never the browser): a device joins it only if it has no owner yet. */
    public function linkAccount(Order $order, Device $device): ?User
    {
        $user = $order->customer?->fresh()->user;
        if ($user && (! $device->user_id || (int) $device->user_id === (int) $user->id)) $this->devices->linkUser($device, $user);
        return $user;
    }

    /** abandoned-checkout drafts of this phone are now an order; staff phone + admin push after the response is sent */
    protected function afterOrder(Order $order): void
    {
        $id = $order->id; $phone = $order->ship_phone;
        dispatch(function () use ($id, $phone) {
            try {
                Order::drafts()->where('ship_phone', $phone)->whereJsonContains('tags', self::ABANDONED_TAG)->get()->each->delete();
                cache()->forget('drafts.count');
            } catch (\Throwable $e) {
                Log::warning('Abandoned checkout cleanup failed: '.$e->getMessage());
            }
            try {
                $admins = User::whereHas('pushSubscriptions')->get()->filter->wantsOps('newOrder', 'push');   // My profile → Notifications
                if (($o = Order::find($id)) && $admins->isNotEmpty()) Notification::send($admins, new NewOrderPushNotification($o));
            } catch (\Throwable $e) {
                Log::warning('New order push notification failed: '.$e->getMessage());
            }
        })->afterResponse();
    }

    /** Orders are personal (name, phone, address): only the device that placed it, or the same account on another device. */
    public function canView(Order $order): bool
    {
        $device = $this->devices->current();
        if ($device && (int) $order->device_id === (int) $device->id) return true;
        $userId = (int) (auth()->id() ?? $device?->user_id);
        return $userId && (int) $order->customer?->user_id === $userId;
    }

    /** order lines for the confirmation view */
    public function lines(Order $order)
    {
        return $order->items()->with('variant.product')->get()->each(fn ($i) => $i->subtotal = (int) $i->unit_price * (int) $i->qty);
    }

    /** the number customers see / tracking uses: "#1042" → "1042" (imported orders keep their old website number) */
    public static function publicNumber(Order $order): string { return preg_replace('/^\D+/', '', (string) $order->number) ?: (string) $order->id; }

    /** GA4 / Meta purchase event (fired once per order per session) */
    public function purchaseEvent(Order $order, $lines): array
    {
        return ['event' => 'purchase', 'ecommerce' => [
            'value' => (float) $order->total, 'currency' => 'BDT', 'transaction_id' => self::publicNumber($order),
            'items' => $lines->map(fn ($i) => ['item_name' => $i->variant?->product?->name, 'item_id' => $i->variant?->product?->catalogId(), 'price' => (float) $i->unit_price,
                'quantity' => (int) ($i->qty ?: 1), 'item_category' => site('pixels.item_category'), 'item_brand' => site('pixels.item_brand'), 'item_variant' => $i->combo_selection ? collect($i->combo_selection)->pluck('size')->join('|') : $i->variant?->size])->values()->all(),
        ], 'user_data' => ['first_name' => $order->ship_name, 'phone_number' => \App\Support\Phone::toE164($order->ship_phone) ?? $order->ship_phone]];
    }

    /** true the first time it's asked for this order in this session */
    public function shouldTrackPurchase(Order $order): bool
    {
        $done = session(self::TRACKED, []);
        if (in_array($order->id, $done)) return false;
        session([self::TRACKED => array_slice([...$done, $order->id], -20)]);
        return true;
    }

    /** Abandoned checkout → one Ops draft per phone (Orders → Drafts), items = current cart; staff can call and convert it. */
    public function autosave(Request $request, array $data, ?array $quick = null): void
    {
        $summary = $quick ?? $this->cart->summary(isset($data['delivery_area']) ? (int) $data['delivery_area'] : null);
        $lines = collect($summary['lines'])->where('available', true);
        if ($lines->isEmpty() || Order::live()->where('ship_phone', $data['phone'])->where('placed_at', '>=', now()->subMinutes(30))->exists()) return;
        if (ops_setting('blacklist.skip_autosave', true) && ($e = app(Blacklist::class)->find($this->blacklistContext($request, $data['phone'], $this->devices->current()))) && $e->action === 'block') return;

        DB::transaction(function () use ($request, $data, $summary, $lines) {
            $customer = Customer::firstOrCreate(['phone' => $data['phone']], ['name' => ($data['name'] ?? '') ?: 'Website visitor', 'source' => 'website']);
            $draft = Order::drafts()->where('ship_phone', $data['phone'])->whereJsonContains('tags', self::ABANDONED_TAG)->latest('id')->first();
            $values = [
                'customer_id' => $customer->id, 'source' => 'website', 'status' => 'pending', 'is_draft' => true, 'tags' => [self::ABANDONED_TAG],
                'ship_name' => mb_substr(($data['name'] ?? '') ?: $customer->name, 0, 255), 'ship_phone' => $data['phone'], 'ship_address' => mb_substr((string) ($data['address'] ?? ''), 0, 255),
                'delivery_zone_id' => $data['delivery_area'] ?? null, 'subtotal' => (int) $summary['subtotal'], 'delivery_charge' => (int) $summary['delivery'],
                'total' => (int) $summary['total'], 'cod' => (int) $summary['total'], 'device_id' => $this->devices->current()?->id, 'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(), 'attribution' => $this->attribution($request), 'placed_at' => now(),
            ];
            $draft ? $draft->update($values) : $draft = Order::create($values + ['number' => 'D-'.(Order::max('id') + 2001)]);
            $draft->items()->delete();
            foreach ($lines as $l) OrderItem::create(['order_id' => $draft->id, 'variant_id' => $l['variant_id'], 'qty' => $l['qty'], 'unit_price' => (int) $l['price'], 'combo_selection' => $l['combo_selection'] ?? null]);
        });
        cache()->forget('drafts.count');
    }
}
