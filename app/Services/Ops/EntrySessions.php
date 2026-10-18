<?php

namespace App\Services\Ops;

use App\Livewire\Ops\Returns\Receive;
use App\Models\Ops\{CourierSession, CourierSessionItem, CustomerReturn, Shipment};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Courier entry session actions: hand over, receive a return, reject a damaged parcel. */
class EntrySessions
{
    public const FLAGS = ['damaged' => ['Damaged', 'bad'], 'inspect' => ['Inspect carefully', 'warn'], 'separate' => ['Keep separate', 'info']];

    public static function flagText(Shipment $p): ?string { return $p->return_flag || $p->return_note ? trim((static::FLAGS[$p->return_flag][0] ?? 'Note').': '.$p->return_note, ': ') : null; }

    public function __construct(private OrderWorkflow $workflow) {}

    /** parcels packed for this courier, waiting for the pickup man */
    public static function toSend(int $courierId) { return Shipment::where('courier_id', $courierId)->where('courier_status', 'pending')->with('order:id,number,customer_id,status', 'order.customer:id,name,area')->orderBy('packed_at')->get(); }

    /** parcels this courier still has to bring back */
    public static function toReceive(int $courierId)
    {
        $returning = static::returningQuery()->where('courier_id', $courierId)
            ->with('order:id,number,customer_id,status,exchange_of_id', 'order.customer:id,name,area', 'order.events')->orderBy('status_updated_at')->get()
            ->each(fn ($p) => $p->return_reason = static::reason($p));
        // delivered exchanges: the rider brings back the customer's OLD item
        $exchange = static::exchangeOldQuery()->where('courier_id', $courierId)->with('order:id,number,customer_id,status,exchange_of_id,exchange_state', 'order.customer:id,name,area', 'order.exchangeLine.items.orderItem.variant.product:id,name')->get()
            ->each(fn ($p) => $p->return_reason = 'Exchange · collect old item: '.$p->order->exchangeLine?->items->map(fn ($i) => $i->orderItem->variant->label.' ×'.$i->qty)->join(', '));
        return $returning->concat($exchange)->values();
    }

    /** exchange parcels whose new item is out / delivered and whose old item is still to come back */
    public static function exchangeOldQuery()
    {
        return Shipment::query()->whereHas('order', fn ($o) => $o->whereIn('exchange_state', ExchangeService::RECEIVABLE)->whereHas('exchangeLine', fn ($l) => $l->where('status', 'expected')))
            ->whereIn('id', fn ($q) => $q->from('ops_shipments')->selectRaw('MAX(id)')->groupBy('order_id'));
    }

    /**
     * every parcel the courier still has to bring back to us:
     *  · courier says returning / returned / cancelled / partial delivered (incl. exchange orders)
     *  · handed over, but the order was cancelled before delivery (never reached the customer)
     *  · and not received yet (no return created)
     */
    public static function returningQuery()
    {
        return Shipment::query()
            ->where(fn ($q) => $q->whereIn('courier_status', Receive::RETURN_STATUSES)
                ->orWhere(fn ($x) => $x->whereIn('courier_status', ['picked_up', 'in_transit', 'out_for_delivery', 'hold'])->where(fn ($w) => $w->whereNotNull('superseded_at')->orWhereHas('order', fn ($o) => $o->whereIn('status', ['cancelled', 'returning'])))))   // replaced by a re-send = coming back
            ->whereNotExists(fn ($q) => $q->from('ops_customer_returns')->whereColumn('ops_customer_returns.shipment_id', 'ops_shipments.id'));
    }

    /** why this parcel is coming back */
    public static function reason(Shipment $p): string
    {
        $o = $p->order; $hit = fn ($types) => $o->events->first(fn ($e) => in_array($e->type, $types) && preg_match('/refus|reject|wrong|unreach|address|not home|switched off/i', $e->message))?->message;
        $why = $hit(['courier', 'status']);   // the courier's / customer's reason first; a damage complaint is shown separately
        $base = match (true) {
            $p->courier_status === 'partial_delivered' => 'Partial delivery — customer kept some, rest coming back',
            $p->courier_status === 'cancelled' => 'Cancelled at the door',
            $p->courier_status === 'returned' => 'Courier marked returned — not received yet',
            in_array($p->courier_status, ['picked_up', 'in_transit', 'out_for_delivery', 'hold']) => 'Order cancelled after hand-over — not delivered',
            default => 'Returning to merchant',
        };
        if ($p->failed_attempts) $base .= ' · '.$p->failed_attempts.' failed attempts';
        if ($why && ! str_contains($base, 'cancelled after')) $base .= ' · '.preg_replace('/^.*?→\s*/', '', $why);
        if ($p->complaint) $base .= ' · ⚠ '.preg_replace('/^Damaged parcel not accepted \(.*?\): /', 'Rejected: ', $p->complaint);
        return ($p->superseded_at ? 'Replaced parcel (order re-sent) · ' : '').($o->exchange_of_id ? 'Exchange · ' : '').$base;
    }

    /** scanned text → parcel (parcel code, tracking, order number #1042 / 1042) */
    public static function find(string $code): ?Shipment
    {
        $code = trim($code); if ($code === '') return null;
        if ($x = app(ExchangeService::class)->findReceivable($code)) return $x->shipment;   // exchange parcel / exchange no. / original no. while the old item is due
        $num = ltrim($code, '#');
        return Shipment::where('code', $code)->orWhere('tracking', $code)->orWhere('consignment_id', $code)
            ->orWhereHas('order', fn ($o) => $o->whereIn('number', [$code, '#'.$num, $num]))->latest('id')->first();
    }

    public function send(CourierSession $s, Shipment $p): CourierSessionItem
    {
        $this->guard($s, $p);
        if ($p->courier_status !== 'pending') throw ValidationException::withMessages(['scan' => $p->code.' is already '.config('ops.courier_status.'.$p->courier_status.'.0', $p->courier_status)]);
        return DB::transaction(function () use ($s, $p) {
            $this->workflow->courierStatus($p, 'picked_up', 'Handed over in '.$s->code.($s->rider ? ' to '.$s->rider : ''));
            return $s->items()->create(['shipment_id' => $p->id, 'type' => 'sent', 'user_id' => auth()->id()]);
        });
    }

    /** parcel physically back → return created (items not inspected yet — set condition in Returns → inspect) */
    public function receive(CourierSession $s, Shipment $p, ?string $note = null): CourierSessionItem
    {
        $this->guard($s, $p);
        // exchange: the rider hands back the customer's OLD item → old-item line received (inspect it in Returns)
        if (! static::returningQuery()->whereKey($p->id)->exists() && static::exchangeOldQuery()->whereKey($p->id)->exists()) {
            return DB::transaction(function () use ($s, $p, $note) {
                $line = app(ExchangeService::class)->receiveOld($p->order, [], ['session' => $s->code, 'rider' => $s->rider, 'note' => $note, 'courier_id' => $p->courier_id]);
                return $s->items()->create(['shipment_id' => $p->id, 'type' => 'returned', 'customer_return_id' => $line->id, 'note' => trim('Exchange old item '.($note ?? '')), 'user_id' => auth()->id()]);
            });
        }
        if (! static::returningQuery()->whereKey($p->id)->exists())
            throw ValidationException::withMessages(['scan' => $p->code.' is not expected back ('.config('ops.courier_status.'.$p->courier_status.'.0', $p->courier_status).')']);
        return DB::transaction(function () use ($s, $p, $note) {
            $o = $p->order()->with('items')->first();
            $ret = CustomerReturn::create(['code' => 'R'.(CustomerReturn::max('id') + 1001), 'order_id' => $o->id, 'shipment_id' => $p->id, 'courier_id' => $p->courier_id,
                'session' => $s->code, 'rider' => $s->rider, 'type' => $o->exchange_of_id ? 'exchange' : 'full', 'status' => 'received', 'note' => collect([static::flagText($p), $note])->filter()->join(' · ') ?: null, 'received_by' => auth()->id(),
                'refund_amount' => (new Receive)->refundFor($o, $o->items->pluck('id')->all())]);
            foreach ($o->items as $i) {
                // one row per batch this parcel was packed from; inspected later (no stock change until a condition is set)
                ReturnStock::record($ret, $i, (int) $i->qty, null, ['reason' => 'Returned by courier'], [], $p->id);
                $i->increment('returned_qty', $i->qty);
            }
            $wasDelivered = $p->courier_status === 'delivered';
            $p->update(['courier_status' => 'returned', 'status_updated_at' => now()]);
            $o->update(['status' => 'returned']); $o->log('return', 'Return '.$ret->code.' received in '.$s->code.' — waiting for inspection');
            if (! $wasDelivered && ! $o->exchange_of_id) { $o->customer()->increment('returned_count'); app(Blacklist::class)->autoCheck($o->customer); }
            ops_log("Return {$ret->code} received in {$s->code}", $ret);
            return $s->items()->create(['shipment_id' => $p->id, 'type' => 'returned', 'customer_return_id' => $ret->id, 'note' => $note, 'user_id' => auth()->id()]);
        });
    }

    /** damaged parcel identified at handover and NOT accepted → stays with the courier, complaint + claim recorded */
    public function reject(CourierSession $s, Shipment $p, string $note): CourierSessionItem
    {
        $this->guard($s, $p);
        if (trim($note) === '') throw ValidationException::withMessages(['note' => 'Describe the damage (torn, wet, opened…)']);
        if (CustomerReturn::where('shipment_id', $p->id)->exists()) throw ValidationException::withMessages(['scan' => $p->code.' was already received']);
        return DB::transaction(function () use ($s, $p, $note) {
            $p->update(['complaint' => 'Damaged parcel not accepted ('.$s->code.'): '.$note]);
            $p->order->log('complaint', 'Damaged parcel not accepted from '.$p->courier->name.' in '.$s->code.': '.$note);
            ops_log("Parcel {$p->code} rejected as damaged in {$s->code}", $p);
            return $s->items()->create(['shipment_id' => $p->id, 'type' => 'rejected', 'note' => $note, 'user_id' => auth()->id()]);
        });
    }

    /** undo a hand-over or a rejection made by mistake (received returns are undone from the return itself) */
    public function undo(CourierSessionItem $it): void
    {
        abort_unless($it->session->is_open, 422);
        if ($why = static::undoBlocked($it)) throw ValidationException::withMessages(['undo' => $why]);
        DB::transaction(function () use ($it) {
            $p = $it->shipment;
            if ($it->type === 'sent' && $p->courier_status === 'picked_up') {
                $p->update(['courier_status' => 'pending', 'handed_over_at' => null, 'status_updated_at' => now()]);
                if ($p->order->status === 'shipped') $p->order->update(['status' => 'packed']);
                $p->order->log('courier', 'Hand-over undone in '.$it->session->code);
            }
            if ($it->type === 'rejected') $p->update(['complaint' => null]);
            if ($it->type === 'returned' && ($r = $it->customerReturn)) {   // not inspected / refunded yet → no stock or money moved
                $o = $r->order;
                foreach ($r->items as $ri) $ri->orderItem()->decrement('returned_qty', $ri->qty);
                $it->update(['customer_return_id' => null]);
                $r->items()->delete(); $r->delete();
                $p->update(['courier_status' => 'returning', 'status_updated_at' => now()]);
                $o->update(['status' => 'returning']);
                if ($o->customer->returned_count > 0) $o->customer()->decrement('returned_count');
                $o->log('return', 'Return '.$r->code.' undone in '.$it->session->code.' — parcel still with courier');
                ops_log("Return {$r->code} undone in {$it->session->code}", $p);
            }
            $it->delete();
        });
    }

    /** why an entry can't be undone (null = it can) */
    public static function undoBlocked(CourierSessionItem $it): ?string
    {
        if ($it->type === 'sent' && $it->shipment->courier_status !== 'picked_up') return 'Courier already moved it ('.config('ops.courier_status.'.$it->shipment->courier_status.'.0').')';
        if ($it->type === 'returned') {
            $r = $it->customerReturn;
            if (! $r) return 'Received from Return receive';
            if ($r->refunded_at) return 'Already refunded';
            if ($r->items->whereNotNull('condition')->isNotEmpty()) return 'Already inspected — stock moved';
        }
        return null;
    }

    protected function guard(CourierSession $s, Shipment $p): void
    {
        if (! $s->is_open) throw ValidationException::withMessages(['scan' => 'Session '.$s->code.' is closed']);
        if ($p->courier_id !== $s->courier_id) throw ValidationException::withMessages(['scan' => $p->code.' belongs to '.$p->courier->name.', not '.$s->courier->name]);
    }

    /** demo: damaged parcels not received + damaged returns received (Pathao yesterday · Steadfast today, open) */
    public static function demo(): void
    {
        if (CourierSessionItem::where('type', 'rejected')->whereNull('customer_return_id')->exists()) return;
        $svc = app(self::class); $stock = app(StockService::class);
        $plan = [
            ['pathao', 'Kamal Hossain', '01819000111', now()->subDay()->setTime(17, 30), true, [['P1016', 'reject', 'Box crushed, items exposed'], ['P1012', 'damaged', 'Wet packet — item stained']]],
            ['steadfast', 'Rahim Uddin', '01711000222', now()->setTime(11, 15), false, [['P1014', 'reject', 'Packet torn open, tape replaced'], ['P1017', 'damaged', 'Item broken inside'], ['P1019', 'reject', 'Wet parcel, smells of water']]],
        ];
        foreach ($plan as [$code, $rider, $phone, $at, $close, $rows]) {
            $courier = \App\Models\Ops\Courier::where('code', $code)->first(); if (! $courier) continue;
            $s = CourierSession::create(['code' => 'ES-'.$at->format('md').'-'.str_pad((string) (CourierSession::where('code', 'like', 'ES-'.$at->format('md').'-%')->count() + 1), 2, '0', STR_PAD_LEFT),
                'courier_id' => $courier->id, 'rider' => $rider, 'rider_phone' => $phone, 'status' => 'open', 'opened_by' => auth()->id() ?? \App\Models\User::value('id'), 'created_at' => $at, 'updated_at' => $at]);
            foreach ($rows as [$pc, $act, $note]) {
                $p = Shipment::with('order', 'courier')->where('code', $pc)->where('courier_id', $courier->id)->first(); if (! $p) continue;
                if (! in_array($p->courier_status, Receive::RETURN_STATUSES)) $svc->workflow->courierStatus($p, 'returning', 'Customer refused');
                $p->refresh();
                if ($act === 'reject') { $svc->reject($s, $p, $note); continue; }
                $it = $svc->receive($s, $p, $note);   // received back, then inspected as damaged → damaged stock
                foreach ($it->customerReturn->items()->with('orderItem')->get() as $ri) {
                    $ri->update(['condition' => 'damaged', 'action' => config('ops.return_conditions.damaged.1')]);
                    foreach ($ri->orderItem->allocations()->whereNotNull('packed_at')->with('batch')->get() as $a) $stock->damage($a->batch, $a->qty, $it->customerReturn);
                }
                $it->customerReturn->update(['status' => 'inspected']);
            }
            $s->items()->update(['created_at' => $at]);
            if ($close) $s->update(['status' => 'closed', 'closed_at' => $at->copy()->addMinutes(25)]);
        }
    }

    /** sessions from data that existed before this feature: return receipts (RRN-…) and hand-overs grouped by courier + day */
    public static function backfill(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('ops_courier_sessions')) return;
        $by = DB::table('users')->orderBy('id')->value('id');
        foreach (DB::table('ops_customer_returns')->whereNotNull('session')->whereNotNull('courier_id')->orderBy('created_at')->get()->groupBy('session') as $code => $rows) {
            if (DB::table('ops_courier_sessions')->where('code', $code)->exists()) continue;
            $f = $rows->first(); $start = $rows->min('created_at');
            $sid = DB::table('ops_courier_sessions')->insertGetId(['code' => $code, 'courier_id' => $f->courier_id, 'rider' => $rows->pluck('rider')->filter()->first(), 'status' => 'closed',
                'opened_by' => $f->received_by ?? $by, 'closed_at' => $rows->max('created_at'), 'created_at' => $start, 'updated_at' => $rows->max('created_at')]);
            foreach ($rows as $r) if ($r->shipment_id) DB::table('ops_courier_session_items')->insert(['courier_session_id' => $sid, 'shipment_id' => $r->shipment_id, 'type' => str_starts_with((string) $r->note, 'Not handed over') ? 'rejected' : 'returned',
                'customer_return_id' => $r->id, 'note' => str_starts_with((string) $r->note, 'Not handed over') ? 'Missing — courier claim' : null, 'user_id' => $r->received_by, 'created_at' => $r->created_at]);
        }
        $sent = DB::table('ops_shipments')->whereNotNull('handed_over_at')->whereNotExists(fn ($q) => $q->from('ops_courier_session_items')->whereColumn('shipment_id', 'ops_shipments.id')->where('type', 'sent'))->orderBy('handed_over_at')->get();
        foreach ($sent->groupBy(fn ($s) => $s->courier_id.'|'.substr($s->handed_over_at, 0, 10)) as $rows) {
            $f = $rows->first(); $day = \Illuminate\Support\Carbon::parse($f->handed_over_at);
            $prefix = 'ES-'.$day->format('md').'-'; $n = DB::table('ops_courier_sessions')->where('code', 'like', $prefix.'%')->count() + 1;
            $sid = DB::table('ops_courier_sessions')->insertGetId(['code' => $prefix.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'courier_id' => $f->courier_id, 'status' => 'closed', 'opened_by' => $f->packed_by ?? $by,
                'closed_at' => $rows->max('handed_over_at'), 'created_at' => $rows->min('handed_over_at'), 'updated_at' => $rows->max('handed_over_at')]);
            foreach ($rows as $r) DB::table('ops_courier_session_items')->insert(['courier_session_id' => $sid, 'shipment_id' => $r->id, 'type' => 'sent', 'user_id' => $r->packed_by, 'created_at' => $r->handed_over_at]);
        }
    }
}
