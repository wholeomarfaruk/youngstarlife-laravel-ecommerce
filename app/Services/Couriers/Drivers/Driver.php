<?php

namespace App\Services\Couriers\Drivers;

use App\Models\Ops\{CourierApiLog, Shipment};
use App\Services\Couriers\CourierException;
use Illuminate\Http\Client\{PendingRequest, Response};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Base for courier API drivers (ported from ecomx-automation's App\Courier drivers, reshaped for the Ops tables).
 * Every HTTP call goes through call(), which times it and writes one ops_courier_api_logs row — success or not,
 * with credentials stripped — so a failed booking can be diagnosed from Settings → Couriers → API log.
 *
 * A parcel handed to book() is a plain array:
 *   invoice, name, phone, alt_phone, address, cod, weight, qty, description, note, delivery_type, meta[]
 * book() / track() / parseWebhook() return our courier statuses (config('ops.courier_status')) or null = no change.
 */
abstract class Driver
{
    protected const SECRET_KEYS = ['client_secret', 'password', 'secret_key', 'Secret-Key', 'Api-Key', 'api_key', 'access_token', 'refresh_token', 'Authorization'];

    protected ?int $courierId = null;
    protected ?int $shipmentId = null;

    public function __construct(protected array $credentials = [], protected int $timeout = 30) {}

    /** label, credential fields, capabilities, webhook setup help */
    abstract public static function meta(): array;

    /** ['message' => 'Connected · balance ৳1,250', 'data' => [...]] or throws */
    abstract public function test(): array;

    /** ['consignment_id', 'tracking', 'status', 'raw_status', 'delivery_fee', 'raw'] */
    abstract public function book(array $parcel): array;

    /** ['status', 'raw_status', 'message', 'raw'] for one parcel */
    abstract public function track(Shipment $shipment): array;

    /** null when the payload is not a parcel update; else ['refs' => [...], 'status', 'raw_status', 'message', 'tracking', 'consignment_id', 'charge'] */
    abstract public function parseWebhook(array $payload): ?array;

    /** is the incoming webhook really from the courier (secret header / bearer token) */
    abstract public function authorizeWebhook(Request $request, string $secret): bool;

    /** many parcels in one call where the courier supports it; default: one by one. Keyed by invoice → result or ['error' => …] */
    public function bookMany(array $parcels): array
    {
        $out = [];
        foreach ($parcels as $p) {
            try { $out[$p['invoice']] = $this->book($p); } catch (CourierException $e) { $out[$p['invoice']] = ['error' => $e->getMessage()]; }
        }
        return $out;
    }

    public function forContext(?int $courierId, ?int $shipmentId = null): static
    {
        $this->courierId = $courierId; $this->shipmentId = $shipmentId;
        return $this;
    }

    abstract protected function client(): PendingRequest;

    protected function downKey(): string { return 'courier_down:'.static::class.':'.$this->courierId; }
    /** forget a recent outage (Test connection always tries for real) */
    public function clearDown(): static { cache()->forget($this->downKey()); return $this; }

    /**
     * One logged HTTP call; returns the decoded JSON. Throws CourierException on transport / HTTP / auth errors, and when
     * $check (fn (array $json): ?string) returns an error — couriers that answer HTTP 200 with a failure inside are logged as failed.
     */
    protected function call(string $action, string $method, string $path, array $data = [], ?PendingRequest $client = null, ?callable $check = null): array
    {
        // courier just failed with a 5xx / timeout → answer at once for a short while instead of waiting ~20 s per call
        $downKey = $this->downKey();
        if ($down = cache()->get($downKey)) throw new CourierException($down, 'gateway');
        $t0 = microtime(true); $res = null; $json = []; $error = null; $outage = false;
        try {
            $client ??= $this->client();
            $res = $method === 'GET' ? $client->get($path, $data) : $client->send($method, $path, ['json' => $data]);
            $json = $res->json() ?? [];
            if (! is_array($json)) $json = ['value' => $json];
            if ($res->failed()) {
                $error = $this->errorFrom($res, $json);
                $outage = $res->serverError();   // 500–599, incl. Cloudflare 520–524 (courier server unreachable)
                throw new CourierException($error, in_array($res->status(), [401, 403]) ? 'auth' : ($res->status() === 422 ? 'validation' : 'failed'), $this->raw($res, $json));
            }
            if ($check && ($error = $check($json))) throw new CourierException($error, 'validation', $json);
            return $json;
        } catch (CourierException $e) {
            $error ??= $e->getMessage();
            throw $e;
        } catch (\Throwable $e) {
            $error = 'Could not reach the courier: '.$e->getMessage(); $outage = true;
            throw new CourierException($error, 'gateway');
        } finally {
            if ($outage) cache()->put($downKey, 'The courier server is not responding ('.($res ? 'error '.$res->status() : 'timeout').') — try again in 2 minutes. Details: Settings → Couriers → API log.', now()->addMinutes(2));
            try {
                CourierApiLog::create([
                    'courier_id' => $this->courierId, 'shipment_id' => $this->shipmentId, 'user_id' => auth()->id(), 'action' => $action,
                    'success' => $error === null, 'http_status' => $res?->status(), 'error' => $error ? mb_substr($error, 0, 500) : null,
                    'request' => ['method' => $method, 'url' => (method_exists($this, 'base') ? rtrim($this->base(), '/') : '').$path, 'body' => $this->redact($data)],
                    'response' => $this->redact($res ? $this->raw($res, $json) : []), 'ms' => (int) ((microtime(true) - $t0) * 1000),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** courier error bodies are not always JSON ("Account is not active!") — keep the real reason */
    protected function errorFrom(Response $res, array $json): string
    {
        $msg = $json['message'] ?? $json['error'] ?? null;
        if (is_array($msg)) $msg = implode(' ', array_map(fn ($m) => is_array($m) ? implode(' ', $m) : $m, $msg));
        if (! empty($json['errors']) && is_array($json['errors'])) $msg = trim(($msg ? $msg.' — ' : '').collect($json['errors'])->flatten()->implode(' '));
        return $msg ?: (mb_substr(trim(strip_tags($res->body())), 0, 300) ?: 'HTTP '.$res->status());
    }

    protected function raw(Response $res, array $json): array
    {
        return $json ?: ['http_status' => $res->status(), 'body' => mb_substr($res->body(), 0, 2000)];
    }

    protected function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (in_array($k, self::SECRET_KEYS, true)) $data[$k] = '••••';
            elseif (is_array($v)) $data[$k] = $this->redact($v);
        }
        return $data;
    }

    protected function cred(string $key): string { return trim((string) ($this->credentials[$key] ?? '')); }

    /** 01XXXXXXXXX — both couriers reject +880… */
    protected function phone(?string $phone): string { return \App\Support\Phone::normalize($phone) ?? preg_replace('/\D/', '', (string) $phone); }
}
