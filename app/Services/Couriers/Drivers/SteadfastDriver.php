<?php

namespace App\Services\Couriers\Drivers;

use App\Models\Ops\Shipment;
use App\Services\Couriers\CourierException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Steadfast Courier (portal.packzy.com) — two static headers (Api-Key / Secret-Key from the merchant panel → API).
 * Endpoints: create_order, create_order/bulk-order (≤500, body {data: "<json>"}), status_by_cid|trackingcode|invoice, get_balance.
 * No cancel / rate endpoints. Webhook: the merchant panel's "Callback URL" + "Auth token" (sent as Authorization: Bearer …),
 * payloads notification_type = delivery_status | tracking_update; answer {status: success}.
 */
class SteadfastDriver extends Driver
{
    public const BASE_URL = 'https://portal.packzy.com/api/v1';

    /** Steadfast word → our courier status (null = keep, timeline message only) */
    protected const STATUS = [
        'in_review' => 'pending',                         // booked, not collected yet
        'pending' => 'in_transit',                        // with Steadfast, on its way
        'hold' => 'hold',
        'delivered_approval_pending' => 'out_for_delivery',
        'partial_delivered_approval_pending' => 'out_for_delivery',
        'cancelled_approval_pending' => null,             // rider says cancelled — final word comes after Steadfast approves
        'unknown_approval_pending' => null,
        'delivered' => 'delivered',
        'partial_delivered' => 'partial_delivered',
        'cancelled' => 'cancelled',                       // → order "returning" (parcel comes back)
        'unknown' => null,
    ];

    public static function meta(): array
    {
        return [
            'label' => 'Steadfast Courier',
            'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'text', 'required' => true],
                ['key' => 'secret_key', 'label' => 'Secret key', 'type' => 'password', 'required' => true],
            ],
            'capabilities' => ['book' => true, 'bulk_book' => true, 'track' => true, 'balance' => true, 'webhook' => true, 'locations' => false, 'quote' => false, 'cancel' => false],
            'webhook_help' => 'Steadfast merchant panel → API → Webhook: paste the Callback URL, and put the secret below in "Auth token" (Steadfast sends it as a Bearer token).',
            'webhook_secret_label' => 'Auth token (Bearer)',
        ];
    }

    protected function client(): PendingRequest
    {
        return Http::timeout($this->timeout)->baseUrl(self::BASE_URL)->acceptJson()
            ->withHeaders(['Api-Key' => $this->cred('api_key'), 'Secret-Key' => $this->cred('secret_key')]);
    }

    public function test(): array
    {
        if (! $this->cred('api_key') || ! $this->cred('secret_key')) throw new CourierException('Add the API key and secret key first.', 'auth');
        $balance = $this->balance();
        return ['message' => 'Connected · balance '.tk((int) round($balance)), 'data' => ['balance' => $balance]];
    }

    public function balance(): float
    {
        $r = $this->call('balance', 'GET', '/get_balance', check: $this->ok('Could not read the Steadfast balance.'));
        return (float) ($r['current_balance'] ?? 0);
    }

    public function book(array $p): array
    {
        $r = $this->call('book', 'POST', '/create_order', $this->payload($p), check: $this->ok('Steadfast did not accept the parcel.'));
        $c = $r['consignment'] ?? [];
        return $this->result($c);
    }

    /** one call for up to 500 parcels; each comes back success / error on its own */
    public function bookMany(array $parcels): array
    {
        $out = [];
        foreach (array_chunk($parcels, 500) as $chunk) {
            $r = $this->call('bulk_book', 'POST', '/create_order/bulk-order', ['data' => json_encode(array_map(fn ($p) => $this->payload($p), $chunk), JSON_UNESCAPED_UNICODE)]);
            $rows = array_is_list($r) ? $r : ($r['data'] ?? []);
            $byInvoice = collect($rows)->keyBy(fn ($row) => (string) ($row['invoice'] ?? ''));
            foreach ($chunk as $p) {
                $row = $byInvoice->get((string) $p['invoice']);
                $out[$p['invoice']] = ! $row ? ['error' => $r['message'] ?? 'Steadfast returned no result for this parcel.']
                    : (($row['status'] ?? '') === 'success' || ! empty($row['consignment_id']) ? $this->result($row) : ['error' => is_array($row['error'] ?? null) ? implode(' ', \Illuminate\Support\Arr::flatten($row['error'])) : ($row['error'] ?? $row['message'] ?? 'Rejected by Steadfast.')]);
            }
        }
        return $out;
    }

    public function track(Shipment $s): array
    {
        [$path, $ref] = $s->consignment_id ? ['/status_by_cid/', $s->consignment_id] : ($s->tracking ? ['/status_by_trackingcode/', $s->tracking] : ['/status_by_invoice/', $s->code]);
        $r = $this->call('track', 'GET', $path.rawurlencode($ref), check: $this->ok('Steadfast has no status for this parcel.'));
        $raw = (string) ($r['delivery_status'] ?? '');
        return ['status' => self::STATUS[$raw] ?? null, 'raw_status' => $raw, 'message' => str_replace('_', ' ', ucfirst($raw)), 'raw' => $r];
    }

    public function parseWebhook(array $p): ?array
    {
        $type = $p['notification_type'] ?? (isset($p['status']) ? 'delivery_status' : null);
        if (! $type) return null;
        $refs = array_values(array_filter([(string) ($p['consignment_id'] ?? ''), (string) ($p['tracking_code'] ?? ''), (string) ($p['invoice'] ?? '')]));
        if (! $refs) return null;
        $raw = $type === 'delivery_status' ? strtolower((string) ($p['status'] ?? '')) : null;
        return [
            'refs' => $refs, 'event' => $type,
            'status' => $raw ? (self::STATUS[$raw] ?? null) : null, 'raw_status' => $raw ?: $type,
            'message' => trim(($raw ? str_replace('_', ' ', ucfirst($raw)) : '').(! empty($p['tracking_message']) ? ($raw ? ' — ' : '').$p['tracking_message'] : '')) ?: 'Update',
            'consignment_id' => isset($p['consignment_id']) ? (string) $p['consignment_id'] : null, 'tracking' => $p['tracking_code'] ?? null,
            'charge' => isset($p['delivery_charge']) ? (int) round((float) $p['delivery_charge']) : null,
            'collected' => $raw && in_array($raw, ['delivered', 'partial_delivered']) && isset($p['cod_amount']) ? (int) round((float) $p['cod_amount']) : null,
        ];
    }

    /** Authorization: Bearer <secret> (the old /api/steadfast/webhook token keeps working until it is changed in Steadfast) */
    public function authorizeWebhook(Request $request, string $secret): bool
    {
        $given = (string) ($request->bearerToken() ?? $request->header('X-Webhook-Secret') ?? $request->query('secret', ''));
        if ($given === '') return false;
        foreach (array_filter([$secret, (string) config('couriers.steadfast.legacy_webhook_token')]) as $ok) if (hash_equals($ok, $given)) return true;
        return false;
    }

    protected function payload(array $p): array
    {
        return array_filter([
            'invoice' => (string) $p['invoice'], 'recipient_name' => mb_substr((string) $p['name'], 0, 100), 'recipient_phone' => $this->phone($p['phone']),
            'alternative_phone' => ! empty($p['alt_phone']) ? $this->phone($p['alt_phone']) : null,
            'recipient_address' => mb_substr((string) $p['address'], 0, 250), 'cod_amount' => (int) round((float) $p['cod']),
            'note' => $p['note'] ?? null, 'item_description' => $p['description'] ?? null,
            'delivery_type' => (int) ($p['delivery_type'] ?? 0),   // 0 home delivery · 1 point delivery
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected function result(array $c): array
    {
        $raw = strtolower((string) ($c['status'] ?? 'in_review'));
        return ['consignment_id' => isset($c['consignment_id']) ? (string) $c['consignment_id'] : null, 'tracking' => $c['tracking_code'] ?? null,
            'status' => self::STATUS[$raw] ?? 'pending', 'raw_status' => $raw, 'delivery_fee' => null, 'raw' => $c];
    }

    /** Steadfast answers HTTP 200 with {"status": 400, "message": …} for business errors */
    protected function ok(string $fallback): \Closure
    {
        return function (array $r) use ($fallback): ?string {
            if ((int) ($r['status'] ?? 0) === 200) return null;
            $msg = is_string($r['message'] ?? null) ? $r['message'] : $fallback;
            return ! empty($r['errors']) ? $msg.' — '.collect($r['errors'])->flatten()->implode(' ') : $msg;
        };
    }
}
