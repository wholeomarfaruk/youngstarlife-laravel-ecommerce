<?php

namespace App\Livewire\Ops\Concerns;

use App\Models\Ops\{Courier, Order};
use App\Services\Ops\OrderWorkflow;

/** Actions behind the order ⋯ menu and the bulk bar — shared by every order list. */
trait OrderActions
{
    public ?int $bookCourierId = null;
    public bool $bookSend = true;          // also send packed parcels through the courier API
    public array $bookFailed = [];         // parcel code => reason, shown after a bulk send

    public function setStatus(int $id, string $status): void
    {
        $o = Order::findOrFail($id);
        app(OrderWorkflow::class)->setStatus($o, $status);
        $this->dispatch('toast', message: "Order {$o->number} · ".config("ops.order_status.$status.0"));
        $this->dispatch('ops-order-updated');
    }

    /** on hold → back to where it was: shipped (handed over) / packed (parcel booked) / confirmed */
    public function resume(int $id): void
    {
        $o = Order::with('shipment')->findOrFail($id);
        $to = $o->shipment?->handed_over_at ? 'shipped' : ($o->shipment ? 'packed' : 'confirmed');
        app(OrderWorkflow::class)->setStatus($o, $to);
        $this->dispatch('toast', message: "Order {$o->number} resumed · ".config("ops.order_status.$to.0"));
        $this->dispatch('ops-order-updated');
    }

    /**
     * Cancelled → back to Pending, then the normal flow (Confirm → Processing → Pack & scan). Cancelling already put its stock back,
     * so it is reserved again on confirm (or now, when Settings reserve at "placed"); an unsent parcel from before is dropped and packing makes a new one.
     */
    public function restore(int $id): void
    {
        $o = Order::with('shipments')->findOrFail($id);
        if ($o->status !== 'cancelled') return;
        if ($o->shipments->contains(fn ($s) => $s->booked_at || $s->handed_over_at)) { $this->dispatch('toast', message: "Can’t restore {$o->number}: its parcel was already sent to the courier.", type: 'bad'); return; }
        \Illuminate\Support\Facades\DB::transaction(function () use ($o) {
            $o->shipments()->delete();
            app(OrderWorkflow::class)->setStatus($o, 'pending');
            $o->log('status', 'Restored from cancelled');
            if (ops_setting('batch.reserve_when') === 'placed') app(\App\Services\Ops\BatchAllocator::class)->allocate($o);
        });
        $this->dispatch('toast', message: "Order {$o->number} restored · Pending");
        $this->dispatch('ops-order-updated');
    }

    /** copy into a new draft (same customer, address and items) and open it */
    public function duplicate(int $id)
    {
        $o = Order::with('items')->findOrFail($id);
        $copy = $o->replicate(['number', 'status', 'payment_status', 'cost', 'tags', 'exchange_of_id']);
        $copy->fill(['number' => 'D-'.(Order::max('id') + 2001), 'is_draft' => true, 'status' => 'pending', 'payment_status' => 'pending', 'placed_at' => now(), 'created_by' => auth()->id(), 'advance' => 0, 'cod' => $o->total])->save();
        foreach ($o->items as $i) $copy->items()->create(['variant_id' => $i->variant_id, 'qty' => $i->qty, 'unit_price' => $i->unit_price]);
        cache()->forget('drafts.count'); ops_log("Duplicated {$o->number} → draft {$copy->number}", $copy);
        return $this->redirectRoute('ops.orders.drafts', ['edit' => $copy->id], navigate: true);
    }

    /** statuses an order can still be deleted in (nothing handed to a courier, nothing delivered) — the ⋯ menu shows "Delete order" for these */
    public const DELETABLE = ['pending', 'confirmed', 'packed', 'on_hold', 'cancelled'];

    /** Delete for good (confirmed with SweetAlert): reserved / packed stock goes back to its batches, the customer's counters drop. */
    public function deleteOrder(int $id)
    {
        $o = Order::with('shipments', 'customer')->findOrFail($id);
        $why = match (true) {
            ! in_array($o->status, self::DELETABLE) => 'it is '.mb_strtolower(config("ops.order_status.{$o->status}.0", $o->status)),
            $o->shipments->contains(fn ($s) => $s->booked_at || $s->handed_over_at) => 'its parcel is already with the courier',
            $o->returns()->exists() || $o->exchanges()->exists() || $o->exchange_of_id => 'it has a return / exchange',
            \App\Models\Ops\Transaction::where('ref_type', $o->getMorphClass())->where('ref_id', $o->id)->exists() => 'a payment is recorded on it',
            default => null,
        };
        if ($why) { $this->dispatch('toast', message: "Can’t delete {$o->number}: $why — cancel it instead.", type: 'bad'); return; }

        \Illuminate\Support\Facades\DB::transaction(function () use ($o) {
            app(\App\Services\Ops\BatchAllocator::class)->release($o);
            if (! $o->is_draft && ($c = $o->customer)) $c->update(['orders_count' => max(0, $c->orders_count - 1), 'total_spent' => max(0, $c->total_spent - $o->total)]);
            $o->delete();   // items, allocations, events and parcels cascade
            ops_log("Deleted order {$o->number} · {$o->ship_name} · ".tk($o->total));
        });
        $this->dispatch('toast', message: "Order {$o->number} deleted");
        $this->dispatch('ops-order-updated');
        if ($this instanceof \App\Livewire\Ops\Orders\Show) return $this->redirectRoute('ops.orders.index', navigate: true);
    }

    /** Bulk "Book courier": parcels already packed move to the courier; others remember it for packing. */
    public function bookCourier(): void
    {
        $this->validate(['bookCourierId' => 'required|exists:ops_couriers,id'], ['bookCourierId.required' => 'Choose a courier.']);
        $c = Courier::find($this->bookCourierId); $n = 0;
        foreach (Order::with('shipment')->whereIn('id', $this->selected)->get() as $o) {
            if (in_array($o->status, ['delivered', 'returned', 'cancelled'])) continue;
            if ($o->shipment) $o->shipment->update(['courier_id' => $c->id]);
            $o->update(['tags' => array_values(array_merge(array_filter($o->tags ?? [], fn ($t) => ! preg_match('/^(Auto )?[Cc]ourier: /', $t)), ['Courier: '.$c->name]))]);
            $o->log('courier', 'Booked with '.$c->name); $n++;
        }
        ops_log("Booked $n orders with {$c->name}");
        $msg = "$n orders booked with {$c->name}"; $this->bookFailed = [];
        if ($this->bookSend && $c->can('book')) {
            $parcels = \App\Models\Ops\Shipment::whereIn('order_id', $this->selected)->where('courier_id', $c->id)->whereNull('booked_at')->get();
            if ($parcels->isNotEmpty()) {
                $r = app(\App\Services\Couriers\CourierGateway::class)->bookMany($parcels);
                $this->bookFailed = $r['failed'];
                $msg .= " · {$r['booked']} sent to the {$c->name} API".($r['failed'] ? ' · '.count($r['failed']).' failed' : '');
            }
        }
        $this->dispatch('close-modal'); $this->dispatch('toast', message: $msg, type: $this->bookFailed ? 'bad' : 'ok'); $this->selected = [];
        if ($this->bookFailed) $this->dispatch('open-modal', name: 'book-failed');
    }
}
