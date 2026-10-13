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
    public ?int $complaintFor = null; public string $complaint = '';

    #[Computed] public function counts() { return Shipment::when($this->courier, fn ($q, $c) => $q->where('courier_id', $c))->selectRaw('courier_status, count(*) n')->groupBy('courier_status')->pluck('n', 'courier_status')->all(); }

    #[Computed]
    public function parcels()
    {
        $q = Shipment::with('order:id,number,customer_id,total,cod', 'order.customer:id,name,phone,area', 'courier:id,name')
            ->when($this->courier, fn ($q, $c) => $q->where('courier_id', $c))->when($this->status !== 'all', fn ($q) => $q->where('courier_status', $this->status))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('code', 'like', "%$s%")->orWhere('tracking', 'like', "%$s%")->orWhereHas('order', fn ($o) => $o->where('number', 'like', '%'.ltrim($s, '#').'%'))));
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

    public function render() { return view('livewire.ops.orders.in-courier', ['couriers' => Courier::pluck('name', 'id')]); }
}
