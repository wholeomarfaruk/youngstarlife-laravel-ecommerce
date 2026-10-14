<?php

namespace App\Services\Ops;

use App\Models\Ops\{Courier, Order, Shipment};
use App\Services\Couriers\{CourierException, CourierGateway};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Booking one order with a courier — shared by the Book courier modal and the Bulk courier sheet:
 * save the delivery details staff checked (onto the order), make the parcel when the order isn't packed yet,
 * send it to the courier API, and confirm a pending order (its stock gets reserved).
 */
class CourierBooking
{
    /** order statuses a courier can still be booked for */
    public const OPEN = ['pending', 'confirmed', 'processing', 'packed', 'on_hold'];

    public function __construct(private CourierGateway $gw, private OrderWorkflow $workflow) {}

    /** active couriers whose API can book */
    public function bookable(): Collection
    {
        return Courier::where('is_active', true)->orderBy('name')->get()->filter(fn (Courier $c) => $c->can('book'))->values();
    }

    /** why this order can't be booked now (null = it can) */
    public function blocked(Order $o): ?string
    {
        if ($o->is_draft) return "{$o->number} is a draft.";
        if (! in_array($o->status, self::OPEN)) return "Order {$o->number} is ".mb_strtolower(config("ops.order_status.{$o->status}.0", $o->status)).' — it can’t be booked now.';
        if (($s = $o->shipment) && $s->booked_at) return "Parcel {$s->code} is already with ".($s->courier?->name ?? 'the courier').($s->tracking ? " ({$s->tracking})" : '').'.';
        return null;
    }

    /**
     * Delivery details checked by staff → the order (logged), courier tag, rider note, parcel contents.
     * $d: name, phone, address, cod, note, description
     */
    public function saveDetails(Order $o, Courier $c, array $d): void
    {
        $edits = array_filter(['ship_name' => trim($d['name']), 'ship_phone' => trim($d['phone']), 'ship_address' => trim($d['address']), 'cod' => (int) $d['cod']],
            fn ($v, $k) => $v != $o->$k, ARRAY_FILTER_USE_BOTH);
        $tags = array_values(array_merge(array_filter($o->tags ?? [], fn ($t) => ! preg_match('/^(Auto )?[Cc]ourier: /', $t)), ['Courier: '.$c->name]));
        $desc = trim((string) ($d['description'] ?? ''));   // kept only when staff changed it, so later item edits still flow through
        $o->update($edits + ['courier_note' => trim((string) ($d['note'] ?? '')) ?: null, 'courier_description' => $desc !== '' && $desc !== $this->gw->itemDescription($o) ? $desc : null, 'tags' => $tags]);
        if ($edits) $o->log('edit', 'Delivery details changed before booking: '.collect($edits)->map(fn ($v, $k) => ['ship_name' => 'name', 'ship_phone' => 'phone', 'ship_address' => 'address', 'cod' => 'COD'][$k].' → '.($k === 'cod' ? tk($v) : $v))->join(', '));
        if (($s = $o->shipment()->first()) && ! $s->booked_at) $s->update(['courier_id' => $c->id, 'cod_amount' => (int) $d['cod'], 'weight_kg' => $d['weight'] ?? $s->weight_kg]);
    }

    /**
     * Send to the courier API now. An order without a parcel gets one (not packed — Pack & scan packs that same parcel later).
     * $d: weight, qty?, note?, alt_phone?, delivery_type?, pathao? (['city_id','zone_id','area_id'] or null = Pathao reads the address)
     * Throws CourierException (nothing half-made is left behind); returns the booked parcel.
     */
    public function send(Order $o, Courier $c, array $d): Shipment
    {
        $lock = Cache::lock("ops-courier-book:{$o->id}", 120);   // two people / two clicks never book one order twice
        if (! $lock->get()) throw new CourierException('This order is being booked right now — try again in a moment.', 'validation');
        try {
            $o->refresh();
            if ($why = $this->blocked($o)) throw new CourierException($why, 'validation');
            $s = $o->shipment()->first(); $new = ! $s;
            $parcel = ['courier_id' => $c->id, 'cod_amount' => (int) $o->cod, 'weight_kg' => $d['weight'] ?? 0.5];
            if ($new) {
                $s = $o->shipments()->create($parcel + ['kind' => $o->shipments()->exists() ? 'resend' : 'delivery', 'code' => Shipment::nextCode(), 'courier_status' => 'pending', 'status_updated_at' => now()]);
                $o->log('courier', 'Parcel '.$s->code.' created for '.$c->name.' (not packed yet)');
            } else $s->update($parcel);
            $pathao = $c->code === 'pathao' ? (($d['pathao'] ?? null) ?: $this->gw->rememberedLocation($s->setRelation('order', $o))) : null;
            try {
                $this->gw->book($s->fresh(['courier', 'order']), array_filter(['note' => $d['note'] ?? $o->courier_note, 'weight' => $d['weight'] ?? null, 'qty' => $d['qty'] ?? null,
                    'delivery_type' => (int) ($d['delivery_type'] ?? 0), 'alt_phone' => ($d['alt_phone'] ?? null) ?: null, 'pathao' => $pathao], fn ($v) => $v !== null));
            } catch (CourierException $e) {
                if ($new) $s->delete();   // nothing was sent → no half-made parcel (the API log keeps the request)
                throw $e;
            }
            $o->log('courier', 'Booked with '.$c->name); ops_log("Order {$o->number} booked with {$c->name}", $o);
            if (($fresh = $o->fresh())->status === 'pending') $this->workflow->setStatus($fresh, 'confirmed');   // booked = confirmed (stock reserved)
            return $s->fresh();
        } finally {
            $lock->release();
        }
    }
}
