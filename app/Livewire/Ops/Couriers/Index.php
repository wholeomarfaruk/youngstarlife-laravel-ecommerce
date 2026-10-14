<?php

namespace App\Livewire\Ops\Couriers;

use App\Models\Ops\{Account, Courier, CourierSettlement, Shipment, Transaction};
use App\Livewire\Ops\Concerns\WithTable;
use App\Services\Ops\CourierLedger;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Couriers', 'title' => 'Courier settlements', 'tabs' => 'couriers'])]
#[Title('Courier settlements')]
class Index extends Component
{
    use WithTable;
    public ?int $settleId = null; public array $settlePick = []; public $received = null; public $charges = null; public $deliveryCharges = null; public $codFee = null; public string $settleNote = ''; public string $settleAccount = 'Bank'; public string $reference = ''; public ?string $receivedOn = null;

    #[Url(except: '')] public string $courier = '';   // filter
    public ?int $ledgerFor = null;                     // courier statement modal

    protected function base()
    {
        $s = $this->search;
        return $this->dateRange(CourierSettlement::query(), 'received_on')
            ->when($this->courier, fn ($q, $c) => $q->where('courier_id', $c))
            ->when($s, fn ($q) => $q->where(fn ($x) => $x->where('reference', 'like', "%$s%")->orWhereHas('shipments', fn ($sh) => $sh->where('code', 'like', "%$s%")->orWhereHas('order', fn ($o) => $o->where('number', 'like', "%$s%")))));
    }
    protected function applyStatus($q, string $st)
    {
        return match ($st) { 'short' => $q->whereRaw('cod_total > charges + received'), 'full' => $q->whereRaw('cod_total <= charges + received'), default => $q };
    }

    #[Computed]
    public function counts(): array { return collect(['all', 'full', 'short'])->mapWithKeys(fn ($k) => [$k => $this->applyStatus($this->base(), $k)->count()])->all(); }

    #[Computed]
    public function totals(): array
    {
        $t = $this->applyStatus($this->base(), $this->status)->selectRaw('COUNT(*) n, COALESCE(SUM(cod_total),0) cod, COALESCE(SUM(charges),0) charges, COALESCE(SUM(received),0) received, COALESCE(SUM(GREATEST(CAST(cod_total AS SIGNED) - CAST(charges AS SIGNED) - CAST(received AS SIGNED), 0)),0) short')->first();
        $due = CourierLedger::unsettled($this->courier ? (int) $this->courier : null);
        return ['n' => (int) $t->n, 'cod' => (int) $t->cod, 'charges' => (int) $t->charges, 'received' => (int) $t->received, 'short' => (int) $t->short,
            'pending' => (int) (clone $due)->sum('cod_amount'), 'pending_n' => (clone $due)->count()];
    }

    #[Computed]
    public function settlements()
    {
        return $this->applyStatus($this->base(), $this->status)->with('courier:id,name')->withCount('shipments')->latest('received_on')->latest('id')->paginate($this->perPage);
    }

    #[Computed] public function couriers() { return Courier::orderBy('id')->get(['id', 'name']); }

    /** money held by each courier: COD collected − charges − payments received (App\Services\Ops\CourierLedger) */
    #[Computed] public function balances() { return app(CourierLedger::class)->balances(); }

    /** couriers with delivered parcels not yet settled → [id => [name, parcels, cod]] */
    #[Computed]
    public function pendingByCourier(): array
    {
        return CourierLedger::unsettled()->selectRaw('courier_id, COUNT(*) n, COALESCE(SUM(cod_amount),0) cod')->groupBy('courier_id')->get()
            ->mapWithKeys(fn ($r) => [$r->courier_id => ['name' => $this->couriers->firstWhere('id', $r->courier_id)?->name, 'n' => (int) $r->n, 'cod' => (int) $r->cod]])->all();
    }

    #[Computed] public function statement(): array { return $this->ledgerFor && ($c = Courier::find($this->ledgerFor)) ? app(CourierLedger::class)->statement($c) : []; }
    public function openLedger(int $id): void { $this->ledgerFor = $id; unset($this->statement); $this->dispatch('open-modal', name: 'courier-ledger'); }

    public function clearFilters(): void { $this->reset('search', 'status', 'from', 'to', 'filters', 'courier'); $this->resetPage(); }
    public function updatingCourier(): void { $this->resetPage(); }

    public function updatedSettleId($id): void { if ($id) $this->openSettle((int) $id, false); }

    /** "Record settlement" — pick the courier inside the dialog */
    public function newSettlement(): void
    {
        $first = array_key_first($this->pendingByCourier) ?? $this->balances->keys()->first();
        if (! $first) { $this->toast('Add a courier first (Settings → Couriers)', 'bad'); return; }
        $this->openSettle((int) ($this->courier && $this->balances->has((int) $this->courier) ? $this->courier : $first));
    }

    /** "Receive payment" on a courier balance: type the amount, then auto-match or tick the parcels it covers */
    public function receivePayment(int $id): void { $this->openSettle($id, false); }

    #[Computed] public function settleParcels() { return $this->settleId ? CourierLedger::unsettled($this->settleId)->with('order:id,number')->orderByRaw('COALESCE(delivered_at, status_updated_at, updated_at)')->orderBy('id')->get() : collect(); }

    /** @param bool $all  tick every waiting parcel (settlement) or none (lump payment) */
    public function openSettle(int $id, bool $all = true): void
    {
        $this->settleId = $id; unset($this->settleParcels);
        $this->settlePick = $all ? $this->settleParcels->pluck('id')->map(fn ($i) => (string) $i)->all() : [];
        $this->charges = $this->pickCharges();
        $this->received = $all ? max(0, (int) $this->settleParcels->sum('cod_amount') - $this->charges) : null; $this->reference = ''; $this->settleNote = ''; $this->receivedOn = today()->toDateString();
        $this->resetValidation(); $this->dispatch('open-modal', name: 'settle');
    }

    /** estimated delivery charges + COD fee of the ticked parcels (editable: type what the courier actually deducted); returns the total */
    protected function pickCharges(): int
    {
        $c = Courier::find($this->settleId); $d = 0; $f = 0;
        foreach ($this->settleParcels->whereIn('id', array_map('intval', $this->settlePick)) as $s) { [$a, $b] = CourierLedger::estParts($s, $c); $d += $a; $f += $b; }
        $this->deliveryCharges = $d; $this->codFee = $f;
        return $d + $f;
    }

    public function updatedSettlePick(): void { $this->charges = $this->pickCharges(); }
    public function updatedDeliveryCharges(): void { $this->charges = (int) $this->deliveryCharges + (int) $this->codFee; }
    public function updatedCodFee(): void { $this->updatedDeliveryCharges(); }

    /** ticks the oldest parcels whose COD − charge fits in the amount received */
    public function autoMatch(): void
    {
        $this->validate(['received' => 'required|integer|min:1'], ['received.required' => 'Type the amount received first.']);
        $this->settlePick = array_map('strval', app(CourierLedger::class)->match($this->settleId, (int) $this->received));
        $this->charges = $this->pickCharges();
        $this->toast(count($this->settlePick) ? count($this->settlePick).' oldest parcels matched' : 'Amount is less than the oldest parcel — it will be saved as a payment on account', count($this->settlePick) ? 'ok' : 'bad');
    }

    /** settlement → money in, parcels matched, short pay flagged. No parcels ticked = payment on account (only lowers the courier balance). */
    public function settle(): void
    {
        $this->validate(['settlePick' => 'array', 'received' => ['required', 'integer', $this->settlePick ? 'min:0' : 'min:1'], 'deliveryCharges' => 'nullable|integer|min:0', 'codFee' => 'nullable|integer|min:0', 'settleNote' => 'nullable|max:500', 'settleAccount' => 'required|in:'.implode(',', config('ops.accounts')), 'receivedOn' => 'required|date'],
            ['received.min' => 'Type the amount received (or tick the parcels it covers).']);
        if (! $this->settlePick) $this->deliveryCharges = $this->codFee = 0;   // charges belong to parcels
        $this->charges = (int) $this->deliveryCharges + (int) $this->codFee;
        $parcels = Shipment::whereIn('id', $this->settlePick)->where('courier_id', $this->settleId)->whereNull('settlement_id')->get();
        $courier = Courier::find($this->settleId);
        $st = DB::transaction(function () use ($parcels, $courier) {
            $st = CourierSettlement::create(['courier_id' => $courier->id, 'reference' => $this->reference ?: null, 'cod_total' => (int) $parcels->sum('cod_amount'), 'charges' => (int) $this->charges,
                'delivery_charges' => (int) $this->deliveryCharges, 'cod_fee' => (int) $this->codFee, 'received' => (int) $this->received, 'received_on' => $this->receivedOn, 'account' => $this->settleAccount, 'note' => $this->settleNote ?: null]);
            Shipment::whereIn('id', $parcels->pluck('id'))->update(['settlement_id' => $st->id]);
            if ($st->received > 0) Transaction::create(['account_id' => Account::idFor($this->settleAccount), 'direction' => 'in', 'category' => 'Courier payment', 'amount' => $st->received, 'description' => $courier->name.($parcels->count() ? ' settlement · '.$parcels->count().' parcels' : ' payment on account').($st->reference ? ' · '.$st->reference : ''), 'date' => $this->receivedOn, 'ref_type' => $st->getMorphClass(), 'ref_id' => $st->id, 'user_id' => auth()->id()]);
            return $st;
        });
        $courier->update(['last_synced_at' => now()]);
        ops_log("{$courier->name} settlement ".tk($st->received).' for '.$parcels->count().' parcels'.($st->short ? ' · SHORT '.tk($st->short) : ''), $st);
        ops_notify('settle', $courier->name.' settlement '.tk($st->received).($st->short ? ' · short paid '.tk($st->short) : ''), route('ops.couriers.index'));
        $this->dispatch('close-modal'); $this->toast($st->short ? 'Settled · short paid by '.tk($st->short) : 'Settlement recorded', $st->short ? 'bad' : 'ok');
        $this->settleId = null; unset($this->settlements, $this->totals, $this->counts, $this->pendingByCourier, $this->balances);
    }

    public function render() { return view('livewire.ops.couriers.index'); }
}
