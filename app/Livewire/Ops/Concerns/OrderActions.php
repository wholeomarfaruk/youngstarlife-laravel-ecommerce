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
