<?php

namespace App\Services\Ops;

use App\Models\Ops\{Courier, Order, Shipment};

/** Single place for order status changes + automations, so every screen (list, ⋯ menu, detail, drafts) keeps stock in sync. */
class OrderWorkflow
{
    public function __construct(private BatchAllocator $allocator) {}

    protected function rule(string $key): bool { return (bool) (ops_setting("automations.$key.on") ?? false); }

    /** A new live order (Draft → Create, website, phone…): automations from Settings → Automations. */
    public function placed(Order $order): void
    {
        $c = $order->customer->fresh(); $rate = $c->delivery_rate;
        try { app(Blacklist::class)->screen($order); } catch (\Throwable $e) { report($e); }   // "flag" blacklist entries → tag + notify
        if ($this->rule('flag_risky') && $rate !== null && $rate < .75) {
            $order->tag('Advance required'); $order->log('automation', 'Risky customer ('.round($rate * 100).'% delivery) → advance required');
            ops_notify('risky', 'Risky customer order '.$order->number.' · '.$c->name.' ('.round($rate * 100).'%)', route('ops.orders.show', $order));
        }
        if ($this->rule('auto_courier') && ($courier = $this->courierFor($order))) { $order->tag('Auto courier: '.$courier->name); $order->log('automation', 'Auto courier: '.$courier->name); }
        ops_notify('newOrder', 'New order '.$order->number.' · '.$c->name.' · '.tk($order->total), route('ops.orders.show', $order));
        if (ops_setting('batch.reserve_when') === 'placed') $this->allocator->allocate($order);
        if ($this->rule('auto_confirm') && $c->orders_count >= 3 && ($rate ?? 0) >= .9 && ! $order->hasTag('Advance required')) {
            $this->setStatus($order, 'confirmed'); $order->tag('Auto-confirmed'); $order->log('automation', 'Auto-confirmed trusted customer');
        }
    }

    /** Courier for a parcel: "Courier: X" chosen by Book courier → zone default → Steadfast inside Dhaka / Pathao outside → first active. */
    public function courierFor(Order $order): ?Courier
    {
        foreach ($order->tags ?? [] as $t) if (preg_match('/^(?:Auto )?[Cc]ourier: (.+)$/', $t, $m) && ($c = Courier::where('name', $m[1])->where('is_active', true)->first())) return $c;
        $zone = $order->zone;
        if ($zone?->default_courier && ($c = Courier::where('is_active', true)->where('code', $zone->default_courier)->first())) return $c;
        $code = $zone && str_contains(strtolower($zone->name), 'outside') ? 'pathao' : 'steadfast';
        return Courier::where('is_active', true)->where('code', $code)->first() ?? Courier::where('is_active', true)->orderBy('id')->first();
    }

    public function setStatus(Order $order, string $status): void
    {
        abort_unless(array_key_exists($status, config('ops.order_status')), 422);
        if (! $order->status) $order->status = $order->fresh()->status;   // freshly created model doesn't hold the DB default ('pending')
        if ($order->status === $status) return;
        $reserve = ops_setting('batch.reserve_when', 'confirmed');

        // reserve from batches on confirm unless Settings say "reserve when packed" or allocation is Manual (then it happens at packing)
        if ($status === 'confirmed' && $reserve !== 'packed' && ops_setting('batch.allocation', 'FIFO') !== 'Manual') $this->allocator->allocate($order);
        if ($status === 'cancelled') $this->allocator->release($order);
        if ($status === 'shipped') $order->shipment?->update(['handed_over_at' => now(), 'status_updated_at' => now()]
            + ($order->shipment->courier_status === 'pending' ? ['courier_status' => 'picked_up'] : []));

        $from = $order->status;
        $order->update(['status' => $status]);
        $order->log('status', 'Status → '.config("ops.order_status.$status.0"));
        ops_log("Order {$order->number}: ".config("ops.order_status.$from.0").' → '.config("ops.order_status.$status.0"), $order);
    }

    /** statuses from which the order can go out again in a new parcel */
    public const RESENDABLE = ['returning', 'returned', 'on_hold', 'shipped'];

    /**
     * Send the order again in a NEW parcel (customer sent it back / refused, then asked for it again).
     * The current parcel is marked replaced (still tracked; when it comes back, Return receive restocks its units without
     * touching the order), stock is reserved again, and the order restarts at Confirmed → Pack & scan → courier.
     */
    public function resend(Order $order, string $reason, ?int $cod = null): void
    {
        $order->refresh();
        abort_unless(in_array($order->status, self::RESENDABLE) && ! $order->is_draft, 422, 'This order can’t be sent again now.');
        $old = $order->shipment;
        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $old, $reason, $cod) {
            if ($old) {
                // legacy rows: packed units without a parcel id belong to the parcel being replaced
                \App\Models\Ops\OrderItemAllocation::whereIn('order_item_id', $order->items()->pluck('id'))->whereNotNull('packed_at')->whereNull('shipment_id')->update(['shipment_id' => $old->id]);
                $old->update(['superseded_at' => now()]);
            }
            // a return already received for that parcel counted the items as returned — they go out again now
            if ($order->status === 'returned') $order->items()->update(['returned_qty' => 0]);
            $order->update(['status' => 'pending', 'cod' => $cod ?? $order->cod, 'tags' => array_values(array_unique(array_merge($order->tags ?? [], ['Re-send'])))]);
            $order->log('resend', 'Sending again'.($old ? ' — parcel '.$old->code.' replaced' : '').' · '.$reason.($cod !== null ? ' · COD '.tk($cod) : ''));
            $this->setStatus($order->fresh(), 'confirmed');   // reserves the stock for the new parcel
            ops_log("Order {$order->number}: sending again".($old ? " (replaces {$old->code})" : ''), $order);
        });
    }

    /** Courier status change (webhook or manual on the order page) → parcel + order + customer counters. Returns false if nothing changed. */
    public function courierStatus(Shipment $s, string $status, string $by): bool
    {
        abort_unless(array_key_exists($status, config('ops.courier_status')), 422);
        if ($status === $s->courier_status) return false;   // repeated callback → no double counting
        $o = $s->order;
        $s->update(['courier_status' => $status, 'status_updated_at' => now(), 'delivered_at' => $status === 'delivered' ? now() : $s->delivered_at]);
        if ($s->isSuperseded()) {   // a parcel replaced by a re-send: track it, but the order follows its new parcel
            $o->log('courier', 'Old parcel '.$s->code.': '.$by.' → '.config("ops.courier_status.$status.0"));
            ops_log("Parcel {$s->code} (replaced): ".config("ops.courier_status.$status.0")." ($by)", $s);
            return true;
        }
        if ($status === 'delivered') { $o->update(['status' => 'delivered', 'payment_status' => 'paid']); if (! $o->exchange_of_id) $o->customer->increment('delivered_count'); }   // an exchange is not a new delivery for the delivery rate
        if ($status === 'partial_delivered') $o->update(['status' => 'partially_delivered']);
        if (in_array($status, ['cancelled', 'returning', 'returned'])) $o->update(['status' => 'returning']);   // "returned" once received at the warehouse
        if (in_array($status, ['picked_up', 'in_transit', 'out_for_delivery']) && $o->status === 'packed') $o->update(['status' => 'shipped']);
        if (in_array($status, ['picked_up', 'in_transit', 'out_for_delivery']) && ! $s->handed_over_at) $s->update(['handed_over_at' => now()]);
        if ($status === 'hold') {   // failed attempt → automation: auto-hold after 2
            $s->increment('failed_attempts');
            if ($this->rule('auto_hold') && $s->failed_attempts >= 2 && $o->status !== 'on_hold') { $o->update(['status' => 'on_hold']); $o->log('automation', '2 failed delivery attempts → on hold'); }
        }
        $o->log('courier', $by.' → '.config("ops.courier_status.$status.0"));
        ops_log("Parcel {$s->code}: ".config("ops.courier_status.$status.0")." ($by)", $s);
        return true;
    }
}
