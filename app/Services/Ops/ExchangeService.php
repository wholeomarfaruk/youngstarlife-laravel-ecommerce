<?php

namespace App\Services\Ops;

use App\Models\Ops\{CustomerReturn, CustomerReturnItem, Order, Shipment};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Exchanges, end to end. An exchange is a NEW order (#1001-EX1, exchange_of_id → original) that delivers the new items,
 * plus an "old-item line" — a CustomerReturn on the ORIGINAL order (exchange_order_id → the exchange) for what the
 * rider must bring back. The courier is not told by API (Steadfast / Pathao publish no exchange field) — the parcel
 * carries a written instruction instead (courierNote()).
 *
 * exchange_state (config('ops.exchange_state')), derived by sync() from the order, its parcel and the old-item line:
 *   awaiting → out → collecting → completed            normal exchange
 *                         └──→ old_missing             rider came back without the old item (courier claim)
 *              out → refused → failed                  customer refused: new item comes back, old one stays with them
 *   awaiting → cancelled                               cancelled before it left
 * Old-item line status: expected → received (not inspected yet) / inspected → closed; cancelled when the exchange fails.
 */
class ExchangeService
{
    public const OPEN_LINE = ['expected'];
    public const RECEIVABLE = ['out', 'collecting'];   // states in which the old item may arrive

    public function __construct(protected OrderWorkflow $workflow) {}

    // ── creating

    /** units of each original line that can still be exchanged: qty − returned − already in an open exchange */
    public function remaining(Order $original): array
    {
        $open = CustomerReturnItem::whereHas('customerReturn', fn ($q) => $q->where('order_id', $original->id)->whereNotNull('exchange_order_id')->whereIn('status', self::OPEN_LINE))
            ->selectRaw('order_item_id, SUM(qty) n')->groupBy('order_item_id')->pluck('n', 'order_item_id');
        return $original->items->mapWithKeys(fn ($i) => [$i->id => max(0, $i->qty - (int) $i->returned_qty - (int) ($open[$i->id] ?? 0))])->all();
    }

    /**
     * money of an exchange: the customer pays the charge + any price increase; a cheaper item first covers the charge,
     * anything left is refunded once the old item is back.  lines: [['old' => OrderItem, 'variant' => ProductVariant, 'qty' => n], …]
     */
    public function quote(array $lines, int $charge): array
    {
        $up = 0; $down = 0;
        foreach ($lines as $l) { $d = ($l['variant']->effective_price - $l['old']->unit_price) * $l['qty']; $d > 0 ? $up += $d : $down -= $d; }
        $discount = min($down, $charge + $up);
        return ['up' => $up, 'down' => $down, 'charge' => $charge, 'discount' => $discount, 'cod' => $charge + $up - $discount, 'refund' => max(0, $down - $up - $charge)];
    }

    /**
     * @param array<int, array{variant_id:int, qty:int, reason:string}> $picked  original order_item_id => choice
     */
    public function create(Order $original, array $picked, int $charge, ?string $note = null): Order
    {
        $original->loadMissing('items.variant', 'shipment');
        if (! in_array($original->status, ['delivered', 'partially_delivered'])) throw ValidationException::withMessages(['order' => 'Only delivered orders can be exchanged — '.$original->number.' is '.strtolower(config("ops.order_status.{$original->status}.0")).'.']);
        if (! $picked) throw ValidationException::withMessages(['lines' => 'Tick at least one item to exchange.']);
        $left = $this->remaining($original); $lines = [];
        foreach ($picked as $itemId => $p) {
            $old = $original->items->firstWhere('id', (int) $itemId);
            if (! $old) throw ValidationException::withMessages(['lines' => 'That item is not on '.$original->number.'.']);
            $qty = (int) $p['qty'];
            if ($qty < 1 || $qty > ($left[$old->id] ?? 0)) throw ValidationException::withMessages(["lines.{$old->id}.qty" => $old->variant->label.': only '.($left[$old->id] ?? 0).' can still be exchanged (the rest is already returned or in another exchange).']);
            $v = \App\Models\Ops\ProductVariant::with('product')->where('is_active', true)->find($p['variant_id']);
            if (! $v) throw ValidationException::withMessages(["lines.{$old->id}.new_variant_id" => 'Choose an active product for '.$old->variant->label.'.']);
            $lines[] = ['old' => $old, 'variant' => $v, 'qty' => $qty, 'reason' => $p['reason'] ?? 'Other'];
        }
        $q = $this->quote($lines, $charge);

        return DB::transaction(function () use ($original, $lines, $q, $note) {
            $n = Order::where('exchange_of_id', $original->id)->count() + 1;
            $x = Order::create(['number' => $original->number.'-EX'.$n, 'customer_id' => $original->customer_id, 'source' => $original->source, 'status' => 'pending', 'exchange_of_id' => $original->id, 'exchange_state' => 'awaiting',
                'ship_name' => $original->ship_name, 'ship_phone' => $original->ship_phone, 'ship_address' => $original->ship_address, 'delivery_zone_id' => $original->delivery_zone_id,
                'subtotal' => $q['up'], 'discount' => $q['discount'], 'delivery_charge' => $q['charge'], 'total' => $q['cod'], 'cod' => $q['cod'], 'advance' => 0,
                'note' => $note ?: null, 'placed_at' => now(), 'created_by' => auth()->id(), 'tags' => ['Exchange']]);
            foreach ($lines as $l) $x->items()->create(['variant_id' => $l['variant']->id, 'qty' => $l['qty'], 'unit_price' => $l['variant']->effective_price]);

            // what the rider must bring back — stays "expected" until it really arrives
            $line = CustomerReturn::create(['code' => $this->nextReturnCode(), 'order_id' => $original->id, 'courier_id' => null, 'type' => 'exchange', 'status' => 'expected',
                'exchange_order_id' => $x->id, 'exchange_charge' => $q['charge'], 'refund_amount' => $q['refund'], 'note' => 'Old item of exchange '.$x->number]);
            foreach ($lines as $l) $line->items()->create(['order_item_id' => $l['old']->id, 'batch_id' => $l['old']->allocations()->whereNotNull('packed_at')->value('batch_id'), 'qty' => $l['qty'], 'reason' => $l['reason'], 'condition' => null, 'action' => null]);

            $x->log('created', 'Exchange for '.$original->number.' · old item line '.$line->code.' · COD '.tk($q['cod']).($q['refund'] ? ' · refund '.tk($q['refund']).' after the old item is back' : ''));
            $original->log('exchange', 'Exchange '.$x->number.' created ('.collect($lines)->map(fn ($l) => $l['old']->variant->label.' → '.$l['variant']->label.' ×'.$l['qty'])->join(', ').')');
            $this->workflow->setStatus($x, 'confirmed');   // reserve the new items now
            ops_log("Exchange {$x->number} for {$original->number}", $x);
            return $x;
        });
    }

    // ── reading

    /** the old-item line of an exchange order */
    public function line(Order $x): ?CustomerReturn
    {
        return CustomerReturn::with('items.orderItem.variant.product', 'items.orderItem.allocations.batch')->where('exchange_order_id', $x->id)->latest('id')->first();
    }

    /** written instruction for the rider (courier API note / courier panel) */
    public function courierNote(Order $x): string
    {
        $line = $this->line($x);
        $old = $line ? $line->items->map(fn ($i) => $i->orderItem->variant->label.' ×'.$i->qty)->join(', ') : 'the old item';
        return 'EXCHANGE: collect '.$old.' from the customer, then hand over this parcel. Return the old item to the merchant.';
    }

    /** exchanges whose old item arrives with this scan: exchange parcel code / tracking, exchange number, original number */
    public function findReceivable(string $code, ?int $courierId = null): ?Order
    {
        $code = trim($code); if ($code === '') return null;
        $num = ltrim($code, '#'); $prefix = (string) ops_setting('orders.prefix', '#');
        $byNumber = array_unique([$code, '#'.$num, $prefix.$num]);
        return Order::live()->whereNotNull('exchange_of_id')->whereIn('exchange_state', self::RECEIVABLE)
            ->where(fn ($q) => $q->whereIn('number', $byNumber)
                ->orWhereHas('exchangeOf', fn ($o) => $o->whereIn('number', $byNumber))
                ->orWhereHas('shipments', fn ($s) => $s->where('code', $code)->orWhere('tracking', $code)->orWhere('consignment_id', $code)))
            ->when($courierId, fn ($q, $c) => $q->orderByRaw('(select courier_id from ops_shipments s where s.order_id = ops_orders.id order by s.id desc limit 1) = ? desc', [$c]))
            ->latest('id')->first();
    }

    /** delivered exchanges whose old item is not back yet for this many days */
    public function overdueQuery(?int $days = null)
    {
        $days ??= (int) ops_setting('orders.exchange_overdue_days', 5);
        return Order::live()->where('exchange_state', 'collecting')->whereHas('shipments', fn ($s) => $s->whereNotNull('delivered_at')->where('delivered_at', '<', now()->subDays($days)));
    }

    // ── the old item arrives

    /**
     * Old item physically back. $conditions: original order_item_id => condition (good / damaged / … / missing) or null = not inspected yet.
     * ctx: session, rider, note, photos[], courier_id
     */
    public function receiveOld(Order $x, array $conditions, array $ctx = []): CustomerReturn
    {
        $x->refresh();
        if (! in_array($x->exchange_state, self::RECEIVABLE)) throw ValidationException::withMessages(['exchange' => 'Exchange '.$x->number.' is '.strtolower(config("ops.exchange_state.{$x->exchange_state}.0", $x->exchange_state)).' — no old item expected.']);
        $line = $this->line($x);
        if (! $line || $line->status !== 'expected') throw ValidationException::withMessages(['exchange' => 'The old item of '.$x->number.' was already received.']);
        if (collect($conditions)->filter(fn ($c) => $c !== 'missing')->isEmpty() && collect($conditions)->isNotEmpty())
            throw ValidationException::withMessages(['exchange' => 'Nothing came back — use "Old item not returned" on the exchange instead.']);

        DB::transaction(function () use ($x, $line, $conditions, $ctx) {
            $original = $line->order()->with('items')->first();
            foreach ($line->items as $it) {
                $cond = array_key_exists($it->order_item_id, $conditions) ? ($conditions[$it->order_item_id] ?: null) : null;
                $it->update(['condition' => $cond, 'action' => $cond ? config("ops.return_conditions.$cond.1") : null, 'reason' => $cond === 'missing' ? 'Not handed back' : $it->reason]);
                if ($cond !== 'missing') {
                    $original->items->firstWhere('id', $it->order_item_id)?->increment('returned_qty', $it->qty);
                    ReturnStock::apply($cond, $it->orderItem->allocations()->whereNotNull('packed_at')->with('batch')->get(), $line, $it->qty);
                }
            }
            $inspected = $line->items()->whereNull('condition')->doesntExist();
            $incomplete = $line->items()->where('condition', 'missing')->exists();
            if ($incomplete && $line->refund_amount) $x->log('exchange', 'Price-difference refund '.tk($line->refund_amount).' held — part of the old item is missing');
            $line->update(['status' => $inspected ? 'inspected' : 'received', 'refund_amount' => $incomplete ? 0 : $line->refund_amount, 'received_at' => now(), 'received_by' => auth()->id(),
                'courier_id' => $ctx['courier_id'] ?? $x->shipment?->courier_id, 'session' => $ctx['session'] ?? null, 'rider' => $ctx['rider'] ?? null,
                'shipment_id' => $x->shipment?->id, 'photos' => $ctx['photos'] ?? $line->photos,
                'note' => trim(collect([$line->note, $ctx['note'] ?? null])->filter()->join(' · ')) ?: null]);
            $x->log('exchange', 'Old item received ('.$line->code.')'.($inspected ? '' : ' — waiting for inspection'));
            $original->log('return', 'Old item of exchange '.$x->number.' received · '.$line->code);
            ops_log("Exchange {$x->number}: old item received · {$line->code}", $line);
        });
        $this->sync($x->fresh());
        return $line->fresh('items');
    }

    /** the rider came back without the old item → line closed as missing (courier claim) */
    public function markOldMissing(Order $x, string $note): void
    {
        $x->refresh();
        if (! in_array($x->exchange_state, self::RECEIVABLE)) throw ValidationException::withMessages(['exchange' => 'Only an exchange with the courier or delivered can be marked like this.']);
        if (trim($note) === '') throw ValidationException::withMessages(['missingNote' => 'Write what happened (rider forgot, customer kept it…).']);
        $line = $this->line($x);
        if (! $line || $line->status !== 'expected') throw ValidationException::withMessages(['exchange' => 'The old item was already handled.']);
        DB::transaction(function () use ($x, $line, $note) {
            foreach ($line->items as $it) $it->update(['condition' => 'missing', 'action' => 'courier_claim', 'reason' => 'Not handed back']);
            $line->update(['status' => 'inspected', 'refund_amount' => 0, 'received_at' => now(), 'received_by' => auth()->id(), 'courier_id' => $x->shipment?->courier_id, 'note' => trim(($line->note ? $line->note.' · ' : '').'Old item not returned: '.$note)]);
            $x->log('exchange', 'Old item not returned — '.$note.' → courier claim');
            ops_log("Exchange {$x->number}: old item not returned", $line);
        });
        $this->sync($x->fresh());
    }

    /** before it leaves: cancel the exchange (new stock released, old-item line cancelled) */
    public function cancel(Order $x, string $why = ''): void
    {
        $x->refresh();
        if ($x->exchange_state !== 'awaiting') throw ValidationException::withMessages(['exchange' => 'The parcel is already with the courier — it can only come back as refused.']);
        $this->workflow->setStatus($x, 'cancelled');
        $x->log('exchange', 'Exchange cancelled'.($why ? ' — '.$why : ''));
        $this->sync($x->fresh());
    }

    // ── state

    /** recompute the state from the facts; side effects on entering a state (old-item line cancelled / courier set, alerts) */
    public function sync(Order $x, bool $quiet = false): string
    {
        if (! $x->exchange_of_id) return '';
        $line = $this->line($x); $s = $x->shipment()->first();
        $back = $line && in_array($line->status, ['received', 'inspected', 'closed']);
        $state = match (true) {
            $x->status === 'cancelled' && ! $back => 'cancelled',
            $back => $line->items->every(fn ($i) => $i->condition === 'missing') ? 'old_missing' : 'completed',
            $x->status === 'returned' => 'failed',
            $x->status === 'returning' || in_array($s?->courier_status, ['returning', 'returned', 'cancelled']) => 'refused',
            in_array($x->status, ['delivered', 'partially_delivered']) => 'collecting',
            $x->status === 'shipped' || ($s && $s->handed_over_at) || in_array($s?->courier_status, ['picked_up', 'in_transit', 'out_for_delivery', 'hold']) => 'out',
            default => 'awaiting',
        };
        $was = $x->exchange_state;
        if ($was === $state) return $state;

        // leaving the happy path: the customer keeps the old item → nothing to collect
        if (in_array($state, ['cancelled', 'failed', 'refused']) && $line && $line->status === 'expected' && $state !== 'refused')
            $line->update(['status' => 'cancelled', 'note' => trim(($line->note ? $line->note.' · ' : '').($state === 'failed' ? 'Exchange refused — old item stays with the customer' : 'Exchange cancelled'))]);
        if ($state === 'collecting' && $line && ! $line->courier_id) $line->update(['courier_id' => $s?->courier_id]);

        $x->forceFill(['exchange_state' => $state])->saveQuietly();
        if (! $quiet) {
            $x->log('exchange', 'Exchange → '.config("ops.exchange_state.$state.0"));
            if ($state === 'collecting') $x->exchangeOf?->log('exchange', 'Exchange '.$x->number.' delivered — old item coming back'.($s ? ' with '.$s->courier?->name : ''));
            if ($state === 'refused') ops_notify('exchange', 'Exchange '.$x->number.' refused by the customer — new item coming back', route('ops.orders.show', $x));
        }
        return $state;
    }

    protected function nextReturnCode(): string { return 'R'.(CustomerReturn::max('id') + 1001); }
}
