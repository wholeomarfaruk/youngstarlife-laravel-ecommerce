<?php

namespace App\Services\Couriers;

use App\Models\Ops\{Courier, Shipment, ShipmentEvent};
use App\Services\Couriers\Drivers\Driver;
use App\Services\Ops\OrderWorkflow;
use Illuminate\Support\Collection;

/**
 * The one place Ops talks to courier APIs (pattern from ecomx-automation's CourierManager):
 * book a parcel (one or many), sync its status, apply webhook updates, test a connection, Pathao locations.
 * Drivers never touch the order — this class turns an Ops parcel into the driver's array and writes the answer back
 * (consignment id, tracking, status via OrderWorkflow::courierStatus, a tracking-timeline line, order log).
 */
class CourierGateway
{
    public function __construct(protected OrderWorkflow $workflow) {}

    // ── registry (config/couriers.php)

    public static function supports(?string $code): bool { return $code && isset(config('couriers.drivers')[$code]); }
    public static function meta(string $code): array { return self::supports($code) ? config('couriers.drivers')[$code]::meta() : []; }
    public static function fields(string $code): array { return self::meta($code)['fields'] ?? []; }
    public static function capabilities(string $code): array { return self::meta($code)['capabilities'] ?? []; }

    public function driver(Courier $courier, ?Shipment $shipment = null): Driver
    {
        if (! self::supports($courier->code)) throw new CourierException($courier->name.' has no API connection in this app — book it in the courier panel and type the tracking number here.', 'not_supported');
        $class = config('couriers.drivers')[$courier->code];
        return (new $class($courier->credentials ?? []))->forContext($courier->id, $shipment?->id);
    }

    // ── connection

    public function test(Courier $courier): array
    {
        $r = $this->driver($courier)->test();
        $courier->update(['last_synced_at' => now()]);
        return $r;
    }

    // ── booking

    /** the parcel as couriers need it */
    public function parcel(Shipment $s, array $opts = []): array
    {
        $o = $s->order->loadMissing('items.variant.product', 'customer');
        $items = $o->items->map(fn ($i) => trim(($i->variant?->product?->name ?? 'Item').' '.trim(($i->variant?->color ?? '').' '.($i->variant?->size ?? ''))).' ×'.$i->qty);
        // exchange: no courier publishes an exchange field — the rider gets a written instruction to collect the old item
        $exchange = $o->exchange_of_id ? app(\App\Services\Ops\ExchangeService::class)->courierNote($o) : null;
        $userNote = $opts['note'] ?? $o->courier_note ?? null;
        return [
            'invoice' => $s->code, 'name' => $o->ship_name ?: $o->customer?->name, 'phone' => $o->ship_phone ?: $o->customer?->phone,
            'alt_phone' => $opts['alt_phone'] ?? null, 'address' => $o->ship_address ?: $o->customer?->address,
            'cod' => $s->cod_amount ?? $o->cod, 'weight' => (float) ($opts['weight'] ?? $s->weight_kg ?: 0.5), 'qty' => max(1, (int) $o->items->sum('qty')),
            'description' => mb_substr(($exchange ? 'EXCHANGE · ' : '').$items->join(', '), 0, 250), 'note' => trim(collect([$exchange, $userNote])->filter()->join(' · ')) ?: null,
            'delivery_type' => (int) ($opts['delivery_type'] ?? 0),
            'meta' => ['pathao' => $opts['pathao'] ?? ($s->courier_meta['pathao'] ?? null)],
        ];
    }

    /** send one parcel to its courier; true = booked. Errors are kept on the parcel (booking_error) and in the API log. */
    public function book(Shipment $s, array $opts = []): bool
    {
        $s->loadMissing('courier', 'order');
        if ($s->booked_at) throw new CourierException("Parcel {$s->code} is already with {$s->courier->name} ({$s->consignment_id}).", 'validation');
        $parcel = $this->parcel($s, $opts);
        try {
            $r = $this->driver($s->courier, $s)->book($parcel);
        } catch (CourierException $e) {
            $s->update(['booking_error' => mb_substr($e->getMessage(), 0, 500), 'courier_meta' => $this->mergeMeta($s, ['pathao' => $parcel['meta']['pathao']])]);
            $s->order->log('courier', "Sending to {$s->courier->name} failed: ".$e->getMessage());
            throw $e;
        }
        $this->booked($s, $r, $parcel);
        return true;
    }

    /**
     * Many parcels (Book courier on the order list, automation). Steadfast books them in one call; others one by one.
     * @return array{booked: int, failed: array<string, string>}  failed: parcel code => reason
     */
    public function bookMany(Collection $shipments, array $opts = []): array
    {
        $done = 0; $failed = [];
        foreach ((new \Illuminate\Database\Eloquent\Collection($shipments->all()))->load('courier', 'order')->groupBy('courier_id') as $group) {
            $courier = $group->first()->courier;
            $todo = $group->reject(fn ($s) => $s->booked_at);
            if (! $courier->isConnected()) { foreach ($todo as $s) $failed[$s->code] = $courier->name.' API is not set up (Settings → Couriers).'; continue; }
            if ($courier->can('bulk_book') && $todo->count() > 1) {
                $parcels = $todo->mapWithKeys(fn ($s) => [$s->code => $this->parcel($s, $opts)]);
                try { $results = $this->driver($courier)->bookMany($parcels->values()->all()); }
                catch (CourierException $e) { foreach ($todo as $s) $failed[$s->code] = $e->getMessage(); continue; }
                foreach ($todo as $s) {
                    $r = $results[$s->code] ?? ['error' => 'No answer for this parcel.'];
                    if (isset($r['error'])) { $failed[$s->code] = $r['error']; $s->update(['booking_error' => mb_substr($r['error'], 0, 500)]); $s->order->log('courier', "Sending to {$courier->name} failed: {$r['error']}"); }
                    else { $this->booked($s, $r, $parcels[$s->code]); $done++; }
                }
                continue;
            }
            foreach ($todo as $s) {
                $pathao = $courier->code === 'pathao' ? ($s->courier_meta['pathao'] ?? $this->rememberedLocation($s) ?? $this->confidentGuess($s)) : null;
                try { $this->book($s, $opts + ['pathao' => $pathao]); $done++; } catch (CourierException $e) { $failed[$s->code] = $e->getMessage(); }
            }
        }
        return ['booked' => $done, 'failed' => $failed];
    }

    protected function booked(Shipment $s, array $r, array $parcel): void
    {
        $s->update(array_filter([
            'consignment_id' => $r['consignment_id'] ?? null, 'tracking' => $r['tracking'] ?? $s->tracking, 'booked_at' => now(), 'booking_error' => null,
            'charge' => $r['delivery_fee'] ?? null,
            'courier_meta' => $this->mergeMeta($s, array_filter(['pathao' => $parcel['meta']['pathao'] ?? null, 'delivery_fee' => $r['delivery_fee'] ?? null, 'delivery_type' => $parcel['delivery_type'] ?: null])),
        ], fn ($v) => $v !== null) + ['booking_error' => null]);
        $this->event($s, 'api', $r['status'] ?? 'pending', $r['raw_status'] ?? null, 'Sent to '.$s->courier->name.($r['consignment_id'] ?? null ? ' · '.$r['consignment_id'] : ''));
        $s->order->log('courier', "Sent to {$s->courier->name} · consignment ".($r['consignment_id'] ?? '—').(! empty($r['tracking']) && $r['tracking'] !== ($r['consignment_id'] ?? null) ? ' · tracking '.$r['tracking'] : ''));
        ops_log("Parcel {$s->code} sent to {$s->courier->name}", $s);
    }

    // ── status

    /** ask the courier for the latest status; true when it changed */
    public function sync(Shipment $s): bool
    {
        $s->loadMissing('courier', 'order');
        $r = $this->driver($s->courier, $s)->track($s);
        $s->update(['last_tracked_at' => now()]);
        return $this->apply($s, 'sync', $r['status'] ?? null, $r['raw_status'] ?? null, $r['message'] ?? null);
    }

    /** a courier update (webhook / sync) → timeline line, and the parcel + order status when it maps to one of ours */
    public function apply(Shipment $s, string $source, ?string $status, ?string $raw, ?string $message, array $extra = []): bool
    {
        $last = $s->events()->first();
        $duplicate = $last && $last->raw_status === $raw && $last->message === $message && $last->created_at->gt(now()->subMinutes(10));
        if (! $duplicate) $this->event($s, $source, $status, $raw, $message);
        $patch = array_filter(['consignment_id' => $s->consignment_id ? null : ($extra['consignment_id'] ?? null), 'tracking' => $s->tracking ? null : ($extra['tracking'] ?? null)]);
        if (! empty($extra['charge'])) { $patch['charge'] = $extra['charge']; }
        if (isset($extra['collected'])) $patch['courier_meta'] = $this->mergeMeta($s, ['collected' => $extra['collected']]);
        if ($patch) $s->update($patch);
        $changed = $status && $this->workflow->courierStatus($s, $status, $s->courier->name.($source === 'webhook' ? ' webhook' : ' sync'));
        if ($changed && $status === 'delivered' && isset($extra['collected']) && $extra['collected'] !== (int) $s->cod_amount)
            $s->order->log('courier', 'Courier collected '.tk($extra['collected']).' (COD was '.tk($s->cod_amount).')');
        $s->courier->update(['last_synced_at' => now()]);
        return $changed;
    }

    public function event(Shipment $s, string $source, ?string $status, ?string $raw, ?string $message): ShipmentEvent
    {
        return $s->events()->create(['source' => $source, 'status' => $status, 'raw_status' => $raw ? mb_substr($raw, 0, 80) : null, 'message' => $message ? mb_substr($message, 0, 255) : null]);
    }

    /** the parcel a webhook is about: our parcel code (sent as invoice / merchant_order_id), consignment id or tracking */
    public function match(Courier $courier, array $refs): ?Shipment
    {
        return Shipment::where('courier_id', $courier->id)->where(fn ($q) => $q->whereIn('consignment_id', $refs)->orWhereIn('tracking', $refs)->orWhereIn('code', $refs))->latest('id')->first()
            ?? Shipment::where(fn ($q) => $q->whereIn('consignment_id', $refs)->orWhereIn('tracking', $refs))->latest('id')->first();   // parcel moved to another courier record
    }

    // ── Pathao locations

    /** the city / zone / area this customer's last Pathao parcel went to */
    public function rememberedLocation(Shipment $s): ?array
    {
        $customer = $s->order->customer_id;
        if (! $customer) return null;
        $prev = Shipment::where('id', '!=', $s->id)->whereNotNull('courier_meta->pathao->zone_id')->whereHas('order', fn ($q) => $q->where('customer_id', $customer))->latest('id')->first();
        return $prev?->courier_meta['pathao'] ?? null;
    }

    /** city / zone whose names appear in the address (English addresses) — a suggestion, staff confirm it */
    public function guessLocation(Courier $courier, Shipment $s): array
    {
        /** @var \App\Services\Couriers\Drivers\PathaoDriver $driver */
        $driver = $this->driver($courier, $s);
        $addr = ' '.mb_strtolower(preg_replace('/[^\pL\pN]+/u', ' ', (string) $s->order->ship_address)).' ';
        $has = fn (string $name) => $name !== '' && str_contains($addr, ' '.mb_strtolower(trim(preg_replace('/[^\pL\pN]+/u', ' ', $name))).' ');
        $cities = $driver->cities();
        $city = collect($cities)->filter(fn ($c) => $has($c['name']))->sortByDesc(fn ($c) => mb_strlen($c['name']))->first();
        $insideDhaka = str_contains(mb_strtolower((string) $s->order->zone?->name), 'inside') || str_contains(mb_strtolower((string) $s->order->zone?->name), 'dhaka');
        $city ??= $insideDhaka ? collect($cities)->first(fn ($c) => mb_strtolower($c['name']) === 'dhaka') : null;
        if (! $city) return ['city_id' => null, 'zone_id' => null, 'area_id' => null, 'confident' => false];
        $zones = collect($driver->zones($city['id']))->filter(fn ($z) => $has($z['name']))->sortByDesc(fn ($z) => mb_strlen($z['name']))->values();
        return ['city_id' => $city['id'], 'zone_id' => $zones->first()['id'] ?? null, 'area_id' => null, 'confident' => $zones->count() === 1];
    }

    protected function confidentGuess(Shipment $s): ?array
    {
        try { $g = $this->guessLocation($s->courier, $s); } catch (CourierException) { return null; }
        return $g['confident'] ? ['city_id' => $g['city_id'], 'zone_id' => $g['zone_id'], 'area_id' => null] : null;
    }

    protected function mergeMeta(Shipment $s, array $add): array
    {
        return array_filter(array_merge($s->courier_meta ?? [], $add), fn ($v) => $v !== null);
    }
}
