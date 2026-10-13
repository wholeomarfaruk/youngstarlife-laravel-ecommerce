<?php

namespace App\Livewire\Ops\Returns;

use App\Models\Ops\{Account, Courier, CustomerReturn, CustomerReturnItem, Order, ProductVariant, Shipment, Transaction};
use App\Services\Ops\{ExchangeService, ReturnStock, StockService};
use App\Livewire\Ops\Concerns\WithTable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.ops', ['module' => 'Orders & Packing', 'title' => 'Return receive', 'tabs' => 'orders'])]
#[Title('Return receive')]
class Receive extends Component
{
    use WithTable, WithFileUploads;

    public const RETURN_STATUSES = ['returning', 'returned', 'cancelled', 'partial_delivered'];

    #[Url] public ?int $courier = null;
    #[Url(except: '')] public string $order = '';     // ?order=#5 → manual return of a delivered order (Create return)
    #[Url(except: 'all')] public string $view = 'all'; // received panel chips: all | today | inspect | refund | damaged
    public bool $fast = false;                         // parcel scan = all items good
    public ?int $activeId = null;                      // shipment being checked
    public ?int $activeOrderId = null;                 // manual (delivered order) return
    public array $lines = [];                          // order_item_id => [got, condition, reason]
    public string $note = ''; public string $rider = '';
    public array $photos = [];
    public ?array $flash = null;
    public array $log = [];                            // scan log [time, code, result, tone]
    public array $unexpected = [];                     // codes that matched nothing
    public array $scannedIds = [];                     // shipments confirmed in this session
    #[Url(except: '')] public string $exchange = '';   // ?exchange=#1001-EX1 from the exchange order page
    public ?int $exchangeId = null;                    // exchange whose OLD item is being checked in

    public function mount(): void
    {
        $this->courier ??= $this->couriers->sortByDesc('returning')->first()?->id;   // open on the courier with the most parcels coming back
        if ($this->order && ($o = Order::live()->numbered($this->order)->first())) $this->openManual($o);
        if ($this->exchange && ($x = app(ExchangeService::class)->findReceivable($this->exchange))) $this->openExchange($x);
    }

    // ── exchanges: the old item comes back after the new one was delivered (ExchangeService)

    /** delivered / with-courier exchanges of this courier whose old item is still to come */
    #[Computed]
    public function exchangeDue()
    {
        return Order::live()->whereIn('exchange_state', ExchangeService::RECEIVABLE)
            ->whereHas('shipment', fn ($s) => $s->where('courier_id', $this->courier))
            ->whereHas('exchangeLine', fn ($l) => $l->where('status', 'expected'))
            ->with('exchangeOf:id,number', 'customer:id,name', 'shipment', 'exchangeLine.items.orderItem.variant.product:id,name')->orderBy('id')->get();
    }

    #[Computed] public function activeExchange(): ?Order { return $this->exchangeId ? Order::with('exchangeOf:id,number', 'customer:id,name', 'shipment.courier', 'exchangeLine.items.orderItem.variant.product:id,name', 'exchangeLine.items.orderItem.allocations.batch')->find($this->exchangeId) : null; }

    public function openExchangeById(int $id): void { if ($x = Order::find($id)) $this->openExchange($x); }

    protected function openExchange(Order $x): void
    {
        $this->reset('activeId', 'activeOrderId'); $this->exchangeId = $x->id; unset($this->activeExchange, $this->active, $this->activeOrder);
        if ($x->shipment?->courier_id && $x->shipment->courier_id !== $this->courier) { $this->courier = $x->shipment->courier_id; unset($this->expected, $this->session, $this->exchangeDue); }
        $this->lines = $this->activeExchange?->exchangeLine?->items->mapWithKeys(fn ($i) => [$i->order_item_id => ['got' => true, 'condition' => 'good', 'reason' => $i->reason]])->all() ?? [];
        $this->note = ''; $this->photos = [];
        $this->flash = ['tone' => 'info', 'title' => 'Exchange old item · '.$x->number.' (original '.$x->exchangeOf?->number.')', 'sub' => 'Check what the rider brought back. Untick what is missing — no refund, the customer has the new item.'];
    }

    public function confirmExchange(): void
    {
        $x = $this->activeExchange; abort_unless($x, 422);
        $this->validate(['photos.*' => 'image|max:4096']);
        $conds = collect($this->lines)->map(fn ($l) => $l['got'] ? ($l['condition'] ?: null) : 'missing')->all();
        try {
            $line = app(ExchangeService::class)->receiveOld($x, $conds, ['session' => $this->session, 'rider' => $this->rider ?: null, 'note' => $this->note ?: null, 'courier_id' => $this->courier,
                'photos' => collect($this->photos)->map->store('returns', 'public')->all() ?: null]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->flash = ['tone' => 'bad', 'title' => 'Not received', 'sub' => collect($e->errors())->flatten()->first()]; return;
        }
        if ($x->shipment) $this->entrySession()->items()->create(['shipment_id' => $x->shipment->id, 'type' => 'returned', 'customer_return_id' => $line->id, 'note' => 'Exchange old item', 'user_id' => auth()->id()]);
        $this->logScan($line->code, 'Old item of '.$x->number, 'ok');
        $this->reset('exchangeId', 'exchange', 'lines', 'note', 'photos'); unset($this->exchangeDue, $this->activeExchange, $this->received, $this->receivedCounts, $this->summary);
        $this->flash = ['tone' => 'ok', 'title' => '✓ '.$line->code.' · old item of '.$x->number.' received', 'sub' => $line->status === 'inspected' ? 'Stock updated · exchange complete' : 'Set the condition below to finish'];
        $this->dispatch('toast', message: 'Exchange '.$x->number.' complete');
    }

    #[Computed] public function couriers() { return Courier::where('is_active', true)->withCount(['shipments as returning' => fn ($q) => $q->whereIn('courier_status', self::RETURN_STATUSES)->whereNotExists(fn ($x) => $x->from('ops_customer_returns')->whereColumn('ops_customer_returns.shipment_id', 'ops_shipments.id'))])->orderBy('id')->get(['id', 'name', 'code']); }

    /** RRN-MMDD-NN (NN = courier position) */
    #[Computed] public function session(): string { $i = $this->couriers->search(fn ($c) => $c->id === $this->courier); return 'RRN-'.now()->format('md').'-'.str_pad((string) (($i === false ? 0 : $i) + 1), 2, '0', STR_PAD_LEFT); }

    /** Parcels this courier is sending back (not yet received here). */
    #[Computed]
    public function expected()
    {
        return Shipment::where('courier_id', $this->courier)->whereIn('courier_status', self::RETURN_STATUSES)
            ->whereNotExists(fn ($q) => $q->from('ops_customer_returns')->whereColumn('ops_customer_returns.shipment_id', 'ops_shipments.id'))
            ->with('order:id,number,customer_id,status,advance,cod,total,delivery_charge,exchange_of_id', 'order.customer:id,name', 'order.items.variant.product:id,name', 'order.items.allocations.batch:id,code,supplier_id,purchase_order_id', 'order.items.allocations.batch.supplier:id,name', 'order.items.allocations.batch.purchaseOrder:id,number')->get();
    }

    #[Computed] public function active(): ?Shipment { return $this->activeId ? ($this->expected->firstWhere('id', $this->activeId) ?? Shipment::with('order.items.variant.product', 'order.items.allocations.batch.supplier', 'order.items.allocations.batch.purchaseOrder', 'order.customer')->find($this->activeId)) : null; }
    #[Computed] public function activeOrder(): ?Order
    {
        if ($this->activeId) return $this->active?->order;
        return $this->activeOrderId ? Order::with('items.variant.product:id,name', 'items.allocations.batch.supplier:id,name', 'items.allocations.batch.purchaseOrder:id,number', 'customer:id,name', 'shipment')->find($this->activeOrderId) : null;
    }

    /** kept-items mode: partial delivery or a manual return of a delivered order → unticked items stay with the customer */
    public function keptMode(): bool { return ! $this->activeId || $this->active?->courier_status === 'partial_delivered'; }

    #[Computed]
    public function received()
    {
        return CustomerReturn::whereNotIn('status', ['expected', 'cancelled'])->with('order:id,number,customer_id', 'order.customer:id,name', 'items.batch:id,code', 'items.orderItem.variant.product:id,name', 'exchangeOrder:id,number')
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('code', 'like', "%$s%")->orWhereHas('order', fn ($o) => $o->where('number', 'like', '%'.ltrim($s, '#').'%'))))
            ->when($this->view === 'today', fn ($q) => $q->whereDate('created_at', today()))
            ->when($this->view === 'inspect', fn ($q) => $q->whereHas('items', fn ($i) => $i->whereNull('condition')))
            ->when($this->view === 'refund', fn ($q) => $q->where('refund_amount', '>', 0)->whereNull('refunded_at'))
            ->when($this->view === 'damaged', fn ($q) => $q->whereHas('items', fn ($i) => $i->whereIn('condition', ['damaged', 'used', 'broken'])))
            ->latest()->limit(40)->get();
    }

    #[Computed]
    public function receivedCounts(): array
    {
        $r = fn () => CustomerReturn::whereNotIn('status', ['expected', 'cancelled']);
        return ['all' => $r()->count(), 'today' => $r()->whereDate('created_at', today())->count(), 'inspect' => $r()->whereHas('items', fn ($i) => $i->whereNull('condition'))->count(),
            'refund' => $r()->where('refund_amount', '>', 0)->whereNull('refunded_at')->count(), 'damaged' => $r()->whereHas('items', fn ($i) => $i->whereIn('condition', ['damaged', 'used', 'broken']))->count()];
    }

    #[Computed]
    public function summary(): array
    {
        $done = CustomerReturn::where('session', $this->session)->with('items')->get();
        $items = $done->flatMap->items;
        return ['expected' => $this->expected->count() + $done->whereNotNull('shipment_id')->count(), 'received' => $done->count(), 'checking' => $this->activeId || $this->activeOrderId ? 1 : 0,
            'not_scanned' => $this->expected->count() - ($this->activeId ? 1 : 0), 'restock' => $items->where('condition', 'good')->sum('qty'),
            'damaged' => $items->whereIn('condition', ['damaged', 'used', 'broken'])->sum('qty'), 'unexpected' => count($this->unexpected), 'exchange' => $this->exchangeDue->count()];
    }

    protected function logScan(string $code, string $result, string $tone): void { array_unshift($this->log, [now()->format('H:i:s'), $code, $result, $tone]); $this->log = array_slice($this->log, 0, 30); }

    protected function startLines(Order $o, bool $got): void
    {
        $this->lines = $o->items->mapWithKeys(fn ($i) => [$i->id => ['got' => $got, 'condition' => 'good', 'reason' => 'Customer Changed Mind']])->all();
        $this->note = ''; $this->photos = [];
    }

    protected function openManual(Order $o): void
    {
        if ($x = Order::where('exchange_of_id', $o->id)->whereIn('exchange_state', ExchangeService::RECEIVABLE)->latest('id')->first()) { $this->openExchange($x); return; }   // it is the exchange's old item, not a refund return
        if (! in_array($o->status, ['delivered', 'partially_delivered', 'returning', 'shipped'])) { $this->flash = ['tone' => 'bad', 'title' => 'Order '.$o->number.' is '.strtolower(config("ops.order_status.{$o->status}.0")), 'sub' => 'Only shipped or delivered orders can be returned']; return; }
        $this->activeId = null; $this->activeOrderId = $o->id; $this->startLines($o, false);
        unset($this->activeOrder);
        $this->flash = ['tone' => 'info', 'title' => 'Customer return · '.$o->number, 'sub' => 'Tick the items the customer sent back. Unticked items stay with the customer.'];
    }

    public function scan(string $code): void
    {
        $code = trim($code); if ($code === '') return;
        $up = strtoupper($code); $num = preg_replace('/^\D+/', '', $code);
        $matches = fn ($p) => in_array($up, [strtoupper($p->code), strtoupper((string) $p->tracking), strtoupper($p->order->number)]) || ($num !== '' && preg_replace('/^\D+/', '', $p->order->number) === $num && ctype_digit($num));
        $s = $this->expected->first($matches);
        if (! $s && ! $this->activeId && ! $this->activeOrderId) {   // parcel belongs to another courier's list → switch to that courier
            $other = Shipment::whereIn('courier_status', self::RETURN_STATUSES)->where('courier_id', '!=', $this->courier)
                ->whereNotExists(fn ($q) => $q->from('ops_customer_returns')->whereColumn('ops_customer_returns.shipment_id', 'ops_shipments.id'))
                ->with('order:id,number')->get()->first($matches);
            if ($other) { $this->courier = $other->courier_id; unset($this->expected, $this->active, $this->session); $s = $this->expected->firstWhere('id', $other->id); }
        }
        if ($s) {
            $this->activeId = $s->id; $this->activeOrderId = null; unset($this->active, $this->activeOrder);
            $this->startLines($s->order, $this->fast);
            $w = \App\Services\Ops\EntrySessions::flagText($s);
            $this->flash = ['tone' => $w ? 'warn' : 'info', 'title' => 'Parcel matched · '.$s->code.' · '.$s->order->number.($w ? ' · ⚠ '.$w : ''), 'sub' => $this->fast ? 'Fast mode: all items good' : 'Scan each product inside'];
            $this->logScan($code, 'Parcel '.$s->code, 'info');
            if ($this->fast) $this->confirm();
            return;
        }
        if ($this->exchangeId && ($v = ProductVariant::findByScan($code))) {
            $it = $this->activeExchange?->exchangeLine?->items->first(fn ($i) => $i->orderItem->variant_id === $v->id);
            if ($it) { $this->lines[$it->order_item_id]['got'] = true; $this->flash = ['tone' => 'ok', 'title' => '✓ '.$v->label, 'sub' => 'Old item verified']; $this->logScan($code, '✓ '.$v->label, 'ok'); return; }
            $this->flash = ['tone' => 'bad', 'title' => 'Not the old item of this exchange', 'sub' => $v->label]; $this->logScan($code, 'Wrong item', 'bad'); return;
        }
        if (! $this->activeId && ! $this->activeOrderId && ! $this->exchangeId && ($x = app(ExchangeService::class)->findReceivable($code, $this->courier))) {
            $this->openExchange($x); $this->logScan($code, 'Exchange '.$x->number, 'info');
            if ($this->fast) $this->confirmExchange();
            return;
        }
        if (($o = $this->activeOrder) && ($v = ProductVariant::findByScan($code))) {
            $item = $o->items->firstWhere('variant_id', $v->id);
            if ($item) { $this->lines[$item->id]['got'] = true; $this->flash = ['tone' => 'ok', 'title' => '✓ '.$v->label, 'sub' => 'Item verified']; $this->logScan($code, '✓ '.$v->label, 'ok'); return; }
            $this->flash = ['tone' => 'bad', 'title' => 'Wrong item for this parcel', 'sub' => $v->label]; $this->logScan($code, 'Wrong item', 'bad'); return;
        }
        if (! $this->activeId && ! $this->activeOrderId && ($o = Order::live()->numbered($code)->first()) && in_array($o->status, ['delivered', 'partially_delivered'])) { $this->openManual($o); $this->logScan($code, 'Manual return '.$o->number, 'info'); return; }
        if (! in_array($code, $this->unexpected)) $this->unexpected[] = $code;
        $this->flash = ['tone' => 'bad', 'title' => 'Not in this courier\'s return list', 'sub' => $code.' · added to unexpected scans'];
        $this->logScan($code, 'Unexpected', 'bad');
    }

    public function markAllGood(): void { foreach ($this->lines as $id => $l) $this->lines[$id] = ['got' => true, 'condition' => 'good', 'reason' => $l['reason'] ?? '']; }

    public function cancelActive(): void { $this->reset('activeId', 'activeOrderId', 'exchangeId', 'exchange', 'lines', 'note', 'photos', 'flash'); unset($this->active, $this->activeOrder, $this->activeExchange); }

    /** what the customer paid that must come back (delivery charge is still billed) */
    public function refundFor(Order $o, array $returnedIds): int
    {
        $paid = $o->advance + (in_array($o->status, ['delivered']) || $o->payment_status === 'paid' ? $o->cod : 0);
        $kept = $o->items->reject(fn ($i) => in_array($i->id, $returnedIds))->sum(fn ($i) => $i->qty * $i->unit_price);
        $keptValue = $kept ? max(0, $kept - $o->discount) + $o->delivery_charge : $o->delivery_charge;
        return max(0, $paid - $keptValue);
    }

    public function confirm(): void
    {
        $o = $this->activeOrder; abort_unless($o, 422);
        $s = $this->activeId ? $this->active : null; $kept = $this->keptMode();
        $this->validate(['photos.*' => 'image|max:4096']);
        $returned = collect($this->lines)->filter(fn ($l) => $l['got'])->keys()->map(fn ($k) => (int) $k)->all();
        if ($kept && ! $returned) { $this->flash = ['tone' => 'warn', 'title' => 'Tick at least one returned item', 'sub' => 'Unticked items stay with the customer']; return; }
        $stock = app(StockService::class);
        $ret = DB::transaction(function () use ($o, $s, $kept, $returned, $stock) {
            $all = count($returned) === $o->items->count();
            $type = $o->exchange_of_id ? 'exchange' : ($all ? 'full' : 'partial');
            $ret = CustomerReturn::create(['code' => 'R'.(CustomerReturn::max('id') + 1001), 'order_id' => $o->id, 'shipment_id' => $s?->id ?? $o->shipment?->id, 'courier_id' => $s?->courier_id ?? $o->shipment?->courier_id,
                'session' => $s ? $this->session : null, 'rider' => $this->rider ?: null, 'type' => $type, 'status' => 'inspected', 'note' => $this->note ?: null, 'received_by' => auth()->id(),
                'photos' => collect($this->photos)->map->store('returns', 'public')->all() ?: null, 'refund_amount' => $this->refundFor($o, $returned)]);
            foreach ($o->items as $i) {
                $l = $this->lines[$i->id] ?? null; if (! $l) continue;
                if (! $l['got'] && $kept) continue;                    // customer kept it → NOT returned
                $cond = $l['got'] ? ($l['condition'] ?: null) : 'missing';
                $action = $cond ? config("ops.return_conditions.$cond.1") : null;
                $allocs = $i->allocations()->whereNotNull('packed_at')->with('batch')->get();
                $ret->items()->create(['order_item_id' => $i->id, 'batch_id' => $allocs->first()?->batch_id, 'qty' => $i->qty, 'reason' => $cond === 'missing' ? 'Missing in parcel' : $l['reason'], 'condition' => $cond, 'action' => $action]);
                $this->applyStock($cond, $allocs, $ret, $stock, $i->qty);
                if ($l['got']) $i->increment('returned_qty', $i->qty);
            }
            $partial = $kept && ! $all;
            $o->update(['status' => $partial ? 'partially_delivered' : 'returned']); $o->log('return', 'Return '.$ret->code.' received ('.$type.')');
            if ($s && ! $partial) $s->update(['courier_status' => 'returned', 'status_updated_at' => now()]);
            // delivery rate counts courier outcomes only: a parcel coming back = returned; partial parcel = delivered (customer kept some).
            // A customer returning an already-delivered order (manual) doesn't change it.
            if ($s && $s->courier_status !== 'delivered' && ! $o->exchange_of_id) $partial ? $o->customer()->increment('delivered_count') : $o->customer()->increment('returned_count');   // refused exchanges don't count against the delivery rate
            if ($s) $this->entrySession()->items()->create(['shipment_id' => $s->id, 'type' => 'returned', 'customer_return_id' => $ret->id, 'user_id' => auth()->id()]);   // Couriers → Entry sessions
            ops_log("Return {$ret->code} received for {$o->number}", $ret);
            return $ret;
        });
        if ($s) $this->scannedIds[] = $s->id;
        app(\App\Services\Ops\Blacklist::class)->autoCheck($o->customer);   // automation: auto-blacklist after repeated refusals
        ops_notify('returnIn', 'Return '.$ret->code.' received · '.$o->number.($ret->refund_amount ? ' · refund due '.tk($ret->refund_amount) : ''), route('ops.returns.receive', ['view' => $ret->refund_amount ? 'refund' : 'all']));
        $this->dispatch('toast', message: $ret->code.' received · stock updated');
        $this->logScan($ret->code, 'Confirmed '.$o->number, 'ok');
        $this->reset('activeId', 'activeOrderId', 'lines', 'note', 'photos', 'order'); unset($this->expected, $this->received, $this->active, $this->activeOrder, $this->couriers, $this->summary);
        $this->flash = ['tone' => 'ok', 'title' => $ret->code.' received', 'sub' => $ret->refund_amount ? 'Prepaid — refund '.tk($ret->refund_amount).' due' : 'Scan the next parcel'];
    }

    /** good → restock ORIGINAL batch · damaged/used/broken → damaged stock · others → no stock change */
    protected function applyStock(?string $cond, $allocs, CustomerReturn $ret, StockService $stock, ?int $qty = null): void
    {
        ReturnStock::apply($cond, $allocs, $ret, $qty);
    }

    /** "Not inspected" → set condition later (inline on the received panel) */
    public function setCondition(int $itemId, string $cond): void
    {
        abort_unless(array_key_exists($cond, config('ops.return_conditions')), 422);
        $it = CustomerReturnItem::with('orderItem.allocations.batch', 'customerReturn')->findOrFail($itemId);
        abort_if($it->condition !== null, 422);
        DB::transaction(function () use ($it, $cond) {
            $it->update(['condition' => $cond, 'action' => config("ops.return_conditions.$cond.1")]);
            $this->applyStock($cond, $it->orderItem->allocations()->whereNotNull('packed_at')->with('batch')->get(), $it->customerReturn, app(StockService::class), $it->qty);
            // last item of an exchange old-item line inspected → line done, exchange complete
            $r = $it->customerReturn;
            if ($r->status === 'received' && $r->items()->whereNull('condition')->doesntExist()) $r->update(['status' => 'inspected']);
            if ($r->exchange_order_id && ($x = Order::find($r->exchange_order_id))) app(ExchangeService::class)->sync($x);
        });
        ops_log("Return {$it->customerReturn->code}: item inspected as $cond", $it->customerReturn);
        unset($this->received, $this->receivedCounts); $this->dispatch('toast', message: 'Condition set · '.config("ops.return_conditions.$cond.2"));
    }

    public function refund(int $id, string $via): void
    {
        abort_unless(in_array($via, config('ops.accounts')), 422);
        $r = CustomerReturn::with('order')->findOrFail($id);
        if ($r->refunded_at) { $this->dispatch('toast', message: 'Already refunded', type: 'bad'); return; }   // a refund can post only once
        DB::transaction(function () use ($r, $via) {
            Transaction::create(['account_id' => Account::where('name', $via)->value('id'), 'direction' => 'out', 'category' => 'Refund', 'amount' => $r->refund_amount, 'description' => 'Refund · '.$r->code.' · '.$r->order->number, 'date' => today(), 'ref_type' => $r->getMorphClass(), 'ref_id' => $r->id, 'user_id' => auth()->id()]);
            $r->update(['refunded_at' => now(), 'refund_via' => $via, 'status' => 'closed']);
            $r->order->update(['payment_status' => 'refunded']); $r->order->log('refund', 'Refunded '.tk($r->refund_amount).' via '.$via);
        });
        ops_log("Refunded {$r->code} ".tk($r->refund_amount)." via $via", $r);
        unset($this->received, $this->receivedCounts);
        $this->dispatch('toast', message: 'Refunded '.tk($r->refund_amount).' via '.$via);
    }

    /** unscanned expected parcels = missing → courier claim; then print the session receipt */
    /** the Couriers → Entry sessions record for this receiving session */
    protected function entrySession(): \App\Models\Ops\CourierSession
    {
        $es = \App\Models\Ops\CourierSession::firstOrCreate(['code' => $this->session], ['courier_id' => $this->courier, 'rider' => $this->rider ?: null, 'status' => 'open', 'opened_by' => auth()->id()]);
        if ($es->status === 'closed') $es->update(['status' => 'open', 'closed_at' => null]);
        return $es;
    }

    public function finishSession()
    {
        $missing = $this->expected->reject(fn ($s) => $s->id === $this->activeId);
        DB::transaction(function () use ($missing) {
            foreach ($missing as $s) {
                $o = $s->order;
                $ret = CustomerReturn::create(['code' => 'R'.(CustomerReturn::max('id') + 1001), 'order_id' => $o->id, 'shipment_id' => $s->id, 'courier_id' => $s->courier_id, 'session' => $this->session, 'rider' => $this->rider ?: null,
                    'type' => 'full', 'status' => 'inspected', 'note' => 'Not handed over in session '.$this->session.' — courier claim', 'received_by' => auth()->id(), 'refund_amount' => 0]);
                foreach ($o->items as $i) $ret->items()->create(['order_item_id' => $i->id, 'batch_id' => $i->allocations->first()?->batch_id, 'qty' => $i->qty, 'reason' => 'Missing in parcel', 'condition' => 'missing', 'action' => 'courier_claim']);
                $s->update(['courier_status' => 'lost', 'status_updated_at' => now(), 'complaint' => 'Courier claim: parcel not returned ('.$this->session.')']);
                $o->log('return', 'Parcel '.$s->code.' not returned by courier → courier claim');
                $this->entrySession()->items()->create(['shipment_id' => $s->id, 'type' => 'rejected', 'customer_return_id' => $ret->id, 'note' => 'Missing — courier claim', 'user_id' => auth()->id()]);
            }
        });
        \App\Models\Ops\CourierSession::where('code', $this->session)->update(['status' => 'closed', 'closed_at' => now()]);
        ops_log("Finished return session {$this->session} · ".$missing->count().' missing parcels claimed');
        $session = $this->session;
        if (! CustomerReturn::where('session', $session)->exists()) { $this->dispatch('toast', message: 'Session closed — nothing was received', type: 'bad'); return null; }
        $this->reset('log', 'unexpected', 'scannedIds', 'rider'); unset($this->expected, $this->couriers, $this->summary, $this->received);
        $this->dispatch('toast', message: 'Session '.$session.' finished · '.$missing->count().' courier claims');
        $this->js("window.open('".route('ops.returns.receipt', $session)."', '_blank')");
    }

    public function render() { return view('livewire.ops.returns.receive'); }
}
