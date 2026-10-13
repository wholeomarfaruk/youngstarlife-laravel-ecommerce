<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\Ops\{Courier, WebhookLog};
use App\Services\Couriers\{CourierException, CourierGateway};
use App\Services\Couriers\Drivers\PathaoDriver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /ops/webhooks/courier/{code} — every courier's status callbacks (also the old /api/steadfast/webhook).
 * Results: processed (status changed) · no_change (timeline line only / repeat) · unmatched · unauthorized · ignored · handshake · error.
 * Each call is logged (Settings → Webhooks → Delivery log) before anything else. Unknown parcels are acknowledged
 * (logged as "unmatched") so the courier does not keep retrying or switch the webhook off.
 */
class CourierWebhookController extends Controller
{
    public function __invoke(Request $request, string $code, CourierGateway $gateway): Response
    {
        $t0 = microtime(true);
        $courier = Courier::where('code', $code)->first();
        $payload = $request->all();
        $log = WebhookLog::create(['courier_id' => $courier?->id, 'event' => $this->eventName($payload), 'status_code' => 0, 'ms' => 0, 'ip' => $request->ip(),
            'payload' => collect($payload)->except(['api_key', 'secret_key', 'password', 'client_secret'])->all()]);
        $done = function (int $http, string $result, ?string $error = null, ?int $shipmentId = null) use ($log, $t0, $code) {
            $log->update(['status_code' => $http, 'result' => $result, 'error' => $error ? mb_substr($error, 0, 255) : null, 'shipment_id' => $shipmentId, 'ms' => (int) ((microtime(true) - $t0) * 1000)]);
            return $this->answer($code, $http, $result);
        };

        if (! $courier || ! CourierGateway::supports($code)) return $done(404, 'unmatched', 'Unknown courier '.$code);

        // Pathao checks the URL once when the webhook is added — answer before the secret check (that call carries none)
        if ($code === 'pathao' && ($payload['event'] ?? null) === 'webhook_integration') return $done(202, 'handshake');

        $driver = $gateway->driver($courier);
        if (! $driver->authorizeWebhook($request, (string) $courier->webhook_secret)) return $done(401, 'unauthorized', 'Missing or wrong secret');

        if (! ($courier->credentials['webhook_on'] ?? true) || ! (ops_setting('automations.webhook_sync.on') ?? true)) return $done(202, 'ignored', 'Webhook switched off in Settings');

        try {
            $u = $driver->parseWebhook($payload);
            if (! $u) return $done(202, 'ignored', 'No parcel reference in the payload');
            $s = $gateway->match($courier, $u['refs']);
            if (! $s) return $done(202, 'unmatched', 'No parcel for '.implode(' / ', $u['refs']));
            $events = $courier->credentials['events'] ?? null;   // Settings → Webhooks → subscribed statuses
            $status = $u['status'] && (! is_array($events) || in_array($u['status'], $events, true)) ? $u['status'] : null;
            $changed = $gateway->apply($s, 'webhook', $status, $u['raw_status'], $u['message'], $u);
            return $done(200, $changed ? 'processed' : 'no_change', null, $s->id);
        } catch (CourierException|\Throwable $e) {
            report($e);
            return $done(500, 'error', $e->getMessage());
        }
    }

    /** each courier's expected reply */
    protected function answer(string $code, int $http, string $result): Response
    {
        if ($code === 'pathao') return response()->json(['message' => $result === 'handshake' ? 'Webhook integrated' : $result], $http === 200 ? 202 : $http)
            ->header('X-Pathao-Merchant-Webhook-Integration-Secret', PathaoDriver::INTEGRATION_SECRET);
        if ($code === 'steadfast') return response()->json($http < 300 ? ['status' => 'success', 'message' => 'Webhook received successfully.'] : ['status' => 'error', 'message' => $result], $http);
        return response()->json(['message' => $result], $http);
    }

    protected function eventName(array $p): string
    {
        $inner = is_array($p['payload'] ?? null) ? $p['payload'] : [];
        return mb_substr((string) ($p['status'] ?? $inner['event'] ?? $p['event'] ?? $p['order_status'] ?? $p['notification_type'] ?? 'unknown'), 0, 60);
    }
}
