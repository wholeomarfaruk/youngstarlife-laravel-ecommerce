<?php

namespace App\Services\Couriers\Drivers;

use App\Models\Ops\Shipment;
use App\Services\Couriers\CourierException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Pathao Courier merchant API ("Aladdin", api-hermes.pathao.com · sandbox courier-api-sandbox.pathao.com).
 * OAuth password grant: client_id / client_secret / merchant email / password → access token (cached until it expires).
 * Orders need numeric store_id + recipient_city + recipient_zone (area optional) — the order page picks them from
 * Pathao's own city / zone / area lists (cached a week). No cancel endpoint; bulk is async (no ids back) so parcels book one by one.
 * Webhook: merchant panel → Developer API → Webhook: callback URL + secret. Pathao first sends event "webhook_integration"
 * and expects 202 with header X-Pathao-Merchant-Webhook-Integration-Secret; every call carries the secret in X-PATHAO-Signature.
 */
class PathaoDriver extends Driver
{
    public const LIVE_URL = 'https://api-hermes.pathao.com';
    public const SANDBOX_URL = 'https://courier-api-sandbox.pathao.com';
    public const INTEGRATION_SECRET = 'f3992ecc-59da-4cbe-a049-a13da2018d51';

    /** Pathao event / order_status (lower, '_' separated, "order_" dropped) → our courier status (null = keep, message only) */
    protected const STATUS = [
        'pending' => 'pending', 'created' => 'pending', 'pickup_requested' => 'pending', 'assigned_for_pickup' => 'pending', 'pickup_rescheduled' => 'pending', 'pickup_pending' => 'pending',
        'pickup_failed' => null, 'pickup_cancelled' => 'cancelled',
        'picked' => 'picked_up', 'picked_up' => 'picked_up', 'pickup_verified' => 'picked_up',
        'at_the_sorting_hub' => 'in_transit', 'received_at_sorting_hub' => 'in_transit', 'in_transit' => 'in_transit', 'received_at_last_mile_hub' => 'in_transit',
        'assigned_for_delivery' => 'out_for_delivery', 'delivery_in_progress' => 'out_for_delivery',
        'on_hold' => 'hold', 'delivery_failed' => 'hold', 'delivery_incomplete' => 'hold', 'not_delivered' => 'hold',   // failed attempt (2 → auto hold)
        'delivered' => 'delivered', 'partial_delivery' => 'partial_delivered', 'partial_delivered' => 'partial_delivered',
        'return' => 'returning', 'return_id_created' => 'returning', 'return_in_transit' => 'returning', 'assigned_for_return' => 'returning', 'paid_return' => 'returning',
        'returned' => 'returned', 'return_verified' => 'returned', 'partial_return' => 'returned',
        'cancelled' => 'cancelled', 'order_cancelled' => 'cancelled',
        'paid' => null, 'exchange' => null, 'updated' => null,
    ];

    public static function meta(): array
    {
        return [
            'label' => 'Pathao Courier',
            'fields' => [
                ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true],
                ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'password', 'required' => true],
                ['key' => 'username', 'label' => 'Merchant email', 'type' => 'text', 'required' => true],
                ['key' => 'password', 'label' => 'Merchant password', 'type' => 'password', 'required' => true],
                ['key' => 'store_id', 'label' => 'Store ID', 'type' => 'text', 'required' => false, 'hint' => 'Empty = first store on the account (Test connection lists them)'],
                ['key' => 'sandbox', 'label' => 'Sandbox (test server)', 'type' => 'checkbox', 'required' => false],
            ],
            'capabilities' => ['book' => true, 'bulk_book' => false, 'track' => true, 'balance' => false, 'webhook' => true, 'locations' => true, 'quote' => true, 'cancel' => false],
            'webhook_help' => 'Pathao merchant panel → Developer API → Webhook: paste the Callback URL and the secret below, tick the events, then "Add webhook" (Pathao checks the URL right away).',
            'webhook_secret_label' => 'Webhook secret',
        ];
    }

    protected function base(): string { return ! empty($this->credentials['sandbox']) ? self::SANDBOX_URL : self::LIVE_URL; }
    protected function tokenKey(): string { return 'pathao_token:'.md5($this->base().'|'.$this->cred('client_id').'|'.$this->cred('username')); }

    protected function token(bool $fresh = false): string
    {
        if ($fresh) cache()->forget($this->tokenKey());
        if ($t = cache()->get($this->tokenKey())) return $t;
        foreach (['client_id', 'client_secret', 'username', 'password'] as $k) if (! $this->cred($k)) throw new CourierException('Add the Pathao client ID, client secret, merchant email and password first.', 'auth');
        $r = $this->call('token', 'POST', '/aladdin/api/v1/issue-token', [
            'client_id' => $this->cred('client_id'), 'client_secret' => $this->cred('client_secret'),
            'username' => $this->cred('username'), 'password' => $this->cred('password'), 'grant_type' => 'password',
        ], Http::timeout($this->timeout)->baseUrl($this->base())->acceptJson(), fn ($r) => empty($r['access_token']) ? ($r['message'] ?? 'Pathao did not issue a token.') : null);
        cache()->put($this->tokenKey(), $r['access_token'], now()->addSeconds(max(300, min((int) ($r['expires_in'] ?? 3600), 30 * 86400) - 600)));
        return $r['access_token'];
    }

    protected function client(): PendingRequest
    {
        return Http::timeout($this->timeout)->baseUrl($this->base())->acceptJson()->withToken($this->token());
    }

    /** an expired / revoked token gets one fresh retry */
    protected function api(string $action, string $method, string $path, array $data = [], ?callable $check = null): array
    {
        try {
            return $this->call($action, $method, $path, $data, null, $check);
        } catch (CourierException $e) {
            if ($e->reason !== 'auth') throw $e;
            $this->token(true);
            return $this->call($action, $method, $path, $data, null, $check);
        }
    }

    public function test(): array
    {
        $this->token(true);
        $stores = $this->stores();
        if (! $stores) throw new CourierException('Connected, but the account has no store yet — add one in the Pathao merchant panel.', 'validation');
        $store = $this->storeId($stores);
        return ['message' => 'Connected'.(! empty($this->credentials['sandbox']) ? ' (sandbox)' : '').' · '.count($stores).' store'.(count($stores) === 1 ? '' : 's').' · using #'.$store, 'data' => ['stores' => $stores]];
    }

    /** [['id' => 123, 'name' => 'Mirpur WH', 'address' => …, 'active' => true], …] */
    public function stores(): array
    {
        $r = $this->api('stores', 'GET', '/aladdin/api/v1/stores');
        return collect($r['data']['data'] ?? [])->map(fn ($s) => ['id' => (int) $s['store_id'], 'name' => $s['store_name'] ?? '', 'address' => $s['store_address'] ?? '', 'active' => (bool) ($s['is_active'] ?? true)])->all();
    }

    public function cities(): array { return $this->cachedList('cities', '/aladdin/api/v1/city-list', 'city_id', 'city_name'); }
    public function zones(int $cityId): array { return $this->cachedList('zones', "/aladdin/api/v1/cities/$cityId/zone-list", 'zone_id', 'zone_name'); }
    public function areas(int $zoneId): array { return $this->cachedList('areas', "/aladdin/api/v1/zones/$zoneId/area-list", 'area_id', 'area_name'); }

    /** delivery price for a city / zone / weight (merchant price plan) */
    public function quote(int $cityId, int $zoneId, float $weight = 0.5): array
    {
        $r = $this->api('quote', 'POST', '/aladdin/api/v1/merchant/price-plan', ['store_id' => $this->storeId(), 'item_type' => 2, 'delivery_type' => 48, 'item_weight' => $this->weight($weight), 'recipient_city' => $cityId, 'recipient_zone' => $zoneId]);
        $d = $r['data'] ?? [];
        return ['price' => (float) ($d['final_price'] ?? $d['price'] ?? 0), 'cod_percent' => (float) ($d['cod_percentage'] ?? 0) * (($d['cod_percentage'] ?? 0) < 1 ? 100 : 1), 'raw' => $d];
    }

    public function book(array $p): array
    {
        $loc = $p['meta']['pathao'] ?? [];
        if (empty($loc['city_id']) || empty($loc['zone_id'])) throw new CourierException('Choose the Pathao city and zone for this address first.', 'needs_location');
        $r = $this->api('book', 'POST', '/aladdin/api/v1/orders', array_filter([
            'store_id' => $this->storeId(), 'merchant_order_id' => (string) $p['invoice'],
            'recipient_name' => mb_substr((string) $p['name'], 0, 100), 'recipient_phone' => $this->phone($p['phone']),
            'recipient_secondary_phone' => ! empty($p['alt_phone']) ? $this->phone($p['alt_phone']) : null,
            'recipient_address' => $this->address((string) $p['address'], (string) $p['name']),
            'recipient_city' => (int) $loc['city_id'], 'recipient_zone' => (int) $loc['zone_id'], 'recipient_area' => ! empty($loc['area_id']) ? (int) $loc['area_id'] : null,
            'delivery_type' => 48, 'item_type' => 2,   // normal delivery · parcel
            'special_instruction' => $p['note'] ?? null, 'item_quantity' => max(1, (int) ($p['qty'] ?? 1)), 'item_weight' => $this->weight((float) ($p['weight'] ?? 0.5)),
            'item_description' => $p['description'] ?? null, 'amount_to_collect' => (int) round((float) $p['cod']),
        ], fn ($v) => $v !== null && $v !== ''), fn ($r) => empty($r['data']['consignment_id']) ? ($r['message'] ?? 'Pathao did not return a consignment id.') : null);
        $d = $r['data'];
        $raw = $this->key((string) ($d['order_status'] ?? 'pending'));
        return ['consignment_id' => (string) $d['consignment_id'], 'tracking' => (string) $d['consignment_id'], 'status' => self::STATUS[$raw] ?? 'pending', 'raw_status' => $raw,
            'delivery_fee' => isset($d['delivery_fee']) ? (int) round((float) $d['delivery_fee']) : null, 'raw' => $d];
    }

    public function track(Shipment $s): array
    {
        $id = $s->consignment_id ?: $s->tracking;
        if (! $id) throw new CourierException('This parcel has no Pathao consignment id yet.', 'validation');
        $r = $this->api('track', 'GET', '/aladdin/api/v1/orders/'.rawurlencode($id).'/info');
        $d = $r['data'] ?? [];
        $raw = $this->key((string) ($d['order_status_slug'] ?? $d['order_status'] ?? ''));
        return ['status' => self::STATUS[$raw] ?? null, 'raw_status' => $raw, 'message' => (string) ($d['order_status'] ?? str_replace('_', ' ', ucfirst($raw))), 'raw' => $d];
    }

    public function parseWebhook(array $payload): ?array
    {
        $p = isset($payload['payload']) && is_array($payload['payload']) ? array_merge($payload, $payload['payload']) : $payload;   // real updates nest the data under "payload"
        if (($p['event'] ?? null) === 'webhook_integration') return null;
        $refs = array_values(array_filter([(string) ($p['consignment_id'] ?? ''), (string) ($p['merchant_order_id'] ?? '')]));
        if (! $refs) return null;
        $raw = $this->key((string) ($p['event'] ?? $p['order_status_slug'] ?? $p['order_status'] ?? ''));
        $msg = str_replace('_', ' ', (string) ($p['order_status'] ?? $p['order_status_slug'] ?? ucfirst($raw)));
        if ($why = ($p['reason'] ?? $p['note'] ?? $p['delivery_note'] ?? null)) $msg .= ' — '.$why;
        return ['refs' => $refs, 'event' => $raw, 'status' => self::STATUS[$raw] ?? null, 'raw_status' => $raw, 'message' => $msg,
            'consignment_id' => $p['consignment_id'] ?? null, 'tracking' => $p['consignment_id'] ?? null,
            'charge' => isset($p['delivery_fee']) ? (int) round((float) $p['delivery_fee']) : null,
            'collected' => isset($p['collected_amount']) ? (int) round((float) $p['collected_amount']) : null];
    }

    /** Pathao echoes the secret entered on its dashboard in X-PATHAO-Signature (not an HMAC) */
    public function authorizeWebhook(Request $request, string $secret): bool
    {
        $given = (string) ($request->header('X-PATHAO-Signature') ?? $request->header('X-Webhook-Secret') ?? '');
        return $secret !== '' && $given !== '' && hash_equals($secret, $given);
    }

    // ── helpers

    protected function storeId(?array $stores = null): int
    {
        if (ctype_digit($this->cred('store_id'))) return (int) $this->cred('store_id');
        $stores ??= cache()->remember('pathao_stores:'.md5($this->tokenKey()), 3600, fn () => $this->stores());
        $s = collect($stores)->firstWhere('active', true) ?? ($stores[0] ?? null);
        if (! $s) throw new CourierException('No Pathao store on this account — add one in the Pathao merchant panel.', 'validation');
        return (int) $s['id'];
    }

    protected function cachedList(string $action, string $path, string $id, string $name): array
    {
        $key = 'pathao_list:'.md5($this->base().$path);
        if ($hit = cache()->get($key)) return $hit;
        $r = $this->api($action, 'GET', $path);
        $list = collect($r['data']['data'] ?? [])->map(fn ($x) => ['id' => (int) $x[$id], 'name' => (string) $x[$name]])->sortBy('name')->values()->all();
        if ($list) cache()->put($key, $list, now()->addWeek());
        return $list;
    }

    /** "order.pickup-cancelled" / "Pickup_Cancelled" / "Pickup Cancelled" → pickup_cancelled */
    protected function key(string $raw): string
    {
        $k = trim(preg_replace('/_+/', '_', str_replace(['.', '-', ' '], '_', strtolower(trim($raw)))), '_');
        return str_starts_with($k, 'order_') && $k !== 'order_cancelled' ? substr($k, 6) : $k;
    }

    /** Pathao rejects addresses under 10 characters */
    protected function address(string $address, string $name): string
    {
        $a = trim(preg_replace('/\s+/', ' ', $address));
        if (mb_strlen($a) < 10) $a = trim($a.', '.$name);
        return mb_substr(str_pad($a, 10, '.'), 0, 220);
    }

    protected function weight(float $kg): float { return round(min(10, max(0.5, $kg ?: 0.5)), 1); }
}
