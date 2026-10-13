<?php

namespace App\Services\Ops;

use App\Models\Ops\{Courier, CourierSettlement, Shipment};
use Illuminate\Support\Collection;

/**
 * Money held by each courier (Steadfast, Pathao…), like an account that only the courier can pay out of:
 *   balance = COD of delivered parcels − courier charges − payments received.
 * Charges = delivery charge (the parcel's, else the courier's rate) + COD fee (courier's % of the cash collected):
 * what the settlement recorded for settled parcels, estimated for parcels not settled yet.
 * A payment (Couriers → Settlements → Receive payment) is a CourierSettlement: with parcels, or a lump sum on account.
 */
class CourierLedger
{
    /** courier statuses where the courier has collected the COD */
    public const COLLECTED = ['delivered', 'partial_delivered'];

    /** delivered parcels not covered by a settlement yet */
    public static function unsettled(?int $courierId = null)
    {
        return Shipment::whereIn('courier_status', self::COLLECTED)->whereNull('settlement_id')->when($courierId, fn ($q, $c) => $q->where('courier_id', $c));
    }

    /** what the courier will most likely deduct for a delivered parcel: [delivery charge, COD fee] */
    public static function estParts(Shipment $s, ?Courier $c = null): array
    {
        $c ??= $s->courier;
        return [(int) ($s->charge ?: $c?->charge), (int) round($s->cod_amount * (float) ($c?->cod_fee_pct ?? 0) / 100)];
    }

    public static function estCharge(Shipment $s, ?Courier $c = null): int { return array_sum(self::estParts($s, $c)); }

    /** @return Collection<int, array{courier:Courier, collected:int, charges:int, received:int, balance:int, parcels:int, unsettled:int, unsettled_cod:int, last_payment:?CourierSettlement}> keyed by courier id */
    public function balances(bool $onlyUsed = true): Collection
    {
        $col = Shipment::whereIn('courier_status', self::COLLECTED)->selectRaw('courier_id, COUNT(*) n, COALESCE(SUM(cod_amount),0) cod')->groupBy('courier_id')->get()->keyBy('courier_id');
        $open = self::unsettled()->join('ops_couriers as c', 'c.id', '=', 'ops_shipments.courier_id')
            ->selectRaw('courier_id, COUNT(*) n, COALESCE(SUM(cod_amount),0) cod, COALESCE(SUM(CASE WHEN ops_shipments.charge > 0 THEN ops_shipments.charge ELSE c.charge END + ROUND(ops_shipments.cod_amount * c.cod_fee_pct / 100)),0) est')->groupBy('courier_id')->get()->keyBy('courier_id');
        $paid = CourierSettlement::selectRaw('courier_id, COALESCE(SUM(charges),0) charges, COALESCE(SUM(received),0) received')->groupBy('courier_id')->get()->keyBy('courier_id');
        $last = CourierSettlement::whereIn('id', CourierSettlement::selectRaw('MAX(id)')->groupBy('courier_id'))->get()->keyBy('courier_id');

        return Courier::orderBy('id')->get()->map(function ($c) use ($col, $open, $paid, $last) {
            $collected = (int) ($col[$c->id]->cod ?? 0); $charges = (int) ($paid[$c->id]->charges ?? 0) + (int) ($open[$c->id]->est ?? 0); $received = (int) ($paid[$c->id]->received ?? 0);
            return ['courier' => $c, 'collected' => $collected, 'charges' => $charges, 'received' => $received, 'balance' => $collected - $charges - $received,
                'parcels' => (int) ($col[$c->id]->n ?? 0), 'unsettled' => (int) ($open[$c->id]->n ?? 0), 'unsettled_cod' => (int) ($open[$c->id]->cod ?? 0), 'last_payment' => $last[$c->id] ?? null];
        })->filter(fn ($b) => ! $onlyUsed || $b['courier']->is_active || $b['parcels'] || $b['received'])->keyBy(fn ($b) => $b['courier']->id);
    }

    public function balance(int $courierId): int { return (int) ($this->balances(false)[$courierId]['balance'] ?? 0); }

    /**
     * Running statement: one line per delivery day (COD in, estimated charges of parcels still unsettled) and one per payment
     * (charges billed on it, money received). Oldest first, with the balance after each line.
     * @return list<array{date:\Illuminate\Support\Carbon, kind:string, text:string, cod:int, charges:int, paid:int, balance:int, ref:?int, account:?string}>
     */
    public function statement(Courier $c): array
    {
        $lines = [];
        $days = Shipment::where('courier_id', $c->id)->whereIn('courier_status', self::COLLECTED)->get(['id', 'cod_amount', 'charge', 'settlement_id', 'delivered_at', 'status_updated_at', 'updated_at'])
            ->groupBy(fn ($s) => ($s->delivered_at ?? $s->status_updated_at ?? $s->updated_at)->toDateString());
        foreach ($days as $day => $ps) {
            $open = $ps->whereNull('settlement_id');
            $lines[] = ['date' => \Illuminate\Support\Carbon::parse($day), 'kind' => 'delivered', 'text' => $ps->count().' parcel'.($ps->count() === 1 ? '' : 's').' delivered'.($open->count() ? ' · '.$open->count().' not settled (charge estimated)' : ''),
                'cod' => (int) $ps->sum('cod_amount'), 'charges' => (int) $open->sum(fn ($s) => self::estCharge($s, $c)), 'paid' => 0, 'ref' => null, 'account' => null];
        }
        foreach ($c->settlements()->withCount('shipments')->get() as $st)
            $lines[] = ['date' => $st->received_on, 'kind' => 'payment', 'text' => ($st->shipments_count ? 'Settlement · '.$st->shipments_count.' parcels' : 'Payment on account').($st->reference ? ' · '.$st->reference : ''),
                'cod' => 0, 'charges' => (int) $st->charges, 'paid' => (int) $st->received, 'ref' => $st->id, 'account' => $st->account];

        usort($lines, fn ($a, $b) => [$a['date']->timestamp, $a['kind'] === 'payment'] <=> [$b['date']->timestamp, $b['kind'] === 'payment']);
        $bal = 0;
        foreach ($lines as &$l) { $bal += $l['cod'] - $l['charges'] - $l['paid']; $l['balance'] = $bal; }
        return $lines;
    }

    /** Oldest unsettled parcels whose net (COD − charge) fits in the amount received — what a lump payment most likely covers. */
    public function match(int $courierId, int $amount): array
    {
        $c = Courier::find($courierId); $sum = 0; $ids = [];
        foreach (self::unsettled($courierId)->orderByRaw('COALESCE(delivered_at, status_updated_at, updated_at)')->orderBy('id')->get() as $s) {
            $net = (int) $s->cod_amount - self::estCharge($s, $c);
            if ($sum + $net > $amount) break;
            $sum += $net; $ids[] = $s->id;
        }
        return $ids;
    }
}
