<?php

namespace App\Livewire\Ops\Orders;

use App\Models\Ops\{Courier, Shipment};
use App\Livewire\Ops\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Orders & Packing', 'title' => 'In courier', 'tabs' => 'orders'])]
#[Title('In courier')]
class InCourier extends Component
{
    use WithTable;
    #[Url] public string $courier = '';
    #[Url(except: false)] public bool $attention = false;   // "Needs attention": stuck / hold / failed attempts / booking failed / complaint
    public ?int $complaintFor = null; public string $complaint = '';

    public function updatedAttention(): void { $this->resetPage(); $this->selected = []; }
    public function updatedCourier(): void { $this->resetPage(); $this->selected = []; }

    #[Computed] public function attentionCount(): int { return Shipment::needsAttention()->when($this->courier, fn ($q, $c) => $q->where('courier_id', $c))->count(); }

    #[Computed] public function counts() { return Shipment::when($this->courier, fn ($q, $c) => $q->where('courier_id', $c))->selectRaw('courier_status, count(*) n')->groupBy('courier_status')->pluck('n', 'courier_status')->all(); }

    #[Computed]
    public function parcels()
    {
        $q = Shipment::with('order:id,number,customer_id,total,cod', 'order.customer:id,name,phone,area', 'courier:id,name')
            ->when($this->courier, fn ($q, $c) => $q->where('courier_id', $c))->when($this->status !== 'all', fn ($q) => $q->where('courier_status', $this->status))
            ->when($this->attention, fn ($q) => $q->needsAttention())
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('code', 'like', "%$s%")->orWhere('tracking', 'like', "%$s%")->orWhere('consignment_id', 'like', "%$s%")->orWhereHas('order', fn ($o) => $o->where('number', 'like', '%'.ltrim($s, '#').'%'))));
        return $this->dateRange($q, 'packed_at')->latest('status_updated_at')->paginate($this->perPage);
    }

    public function openComplaint(int $id): void { $this->complaintFor = $id; $this->complaint = (string) Shipment::find($id)?->complaint; $this->resetValidation(); $this->dispatch('open-modal', name: 'complaint'); }

    /** Raise complaint with the courier: stored on the parcel, logged on the order timeline. */
    public function raiseComplaint(): void
    {
        $this->validate(['complaint' => 'required|max:500']);
        $s = Shipment::with('order', 'courier')->findOrFail($this->complaintFor);
        $s->update(['complaint' => $this->complaint]);
        $s->order->log('complaint', 'Complaint to '.$s->courier->name.': '.$this->complaint);
        ops_log("Complaint raised on parcel {$s->code}", $s);
        $this->dispatch('close-modal'); $this->toast('Complaint recorded for '.$s->code);
    }

    // ── bulk actions on the selected parcels

    /** ask each courier API for the latest status (connected couriers, parcels already sent) */
    public function syncSelected(\App\Services\Couriers\CourierGateway $gw): void
    {
        $ok = 0; $changed = 0; $skipped = 0; $failed = [];
        foreach (Shipment::with('courier', 'order')->whereIn('id', $this->selected)->get() as $s) {
            if (! $s->booked_at || $s->isFinal() || ! $s->courier?->isConnected()) { $skipped++; continue; }
            try { $changed += (int) $gw->sync($s); $ok++; }
            catch (\App\Services\Couriers\CourierException $e) { $failed[] = $s->code; }
        }
        unset($this->parcels, $this->counts, $this->attentionCount);
        $this->toast("Synced $ok · $changed changed".($skipped ? " · $skipped skipped (not sent through an API, or finished)" : '').($failed ? ' · failed: '.implode(', ', array_slice($failed, 0, 5)) : ''), $failed ? 'bad' : 'ok');
    }

    /** parcel labels of the selected parcels (one per order) in a new tab */
    public function printSelected(): void
    {
        $ids = Shipment::whereIn('id', $this->selected)->pluck('order_id')->unique()->implode(',');
        if ($ids) $this->js('window.open('.json_encode(route('ops.orders.parcel-labels', ['ids' => $ids])).', "_blank")');
    }

    /** CSV of the selected parcels — or of every parcel in the current filter when nothing is selected */
    public function export()
    {
        $q = Shipment::with('order:id,number,customer_id,ship_name,ship_phone,ship_address', 'courier:id,name');
        $rows = $this->selected ? $q->whereIn('id', $this->selected)->get() : $this->dateRange($q->when($this->courier, fn ($q, $c) => $q->where('courier_id', $c))
            ->when($this->status !== 'all', fn ($q) => $q->where('courier_status', $this->status))->when($this->attention, fn ($q) => $q->needsAttention()), 'packed_at')->latest('status_updated_at')->limit(5000)->get();
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Parcel', 'Order', 'Customer', 'Phone', 'Address', 'Courier', 'Tracking', 'COD', 'Status', 'Packed', 'Last update', 'Needs attention', 'Complaint']);
            foreach ($rows as $p) fputcsv($out, [$p->code, $p->order?->number, $p->order?->ship_name, $p->order?->ship_phone, $p->order?->ship_address, $p->courier?->name, $p->tracking, $p->cod_amount,
                config("ops.courier_status.{$p->courier_status}.0", $p->courier_status), $p->packed_at?->format('Y-m-d H:i'), $p->status_updated_at?->format('Y-m-d H:i'), implode(' · ', $p->attentionReasons()), $p->complaint]);
            fclose($out);
        }, 'parcels-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render() { return view('livewire.ops.orders.in-courier', ['couriers' => Courier::pluck('name', 'id')]); }
}
