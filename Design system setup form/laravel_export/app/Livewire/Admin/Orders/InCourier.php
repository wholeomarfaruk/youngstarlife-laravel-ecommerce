<?php

namespace App\Livewire\Admin\Orders;

use App\Models\{Courier, Shipment};
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Orders & Packing', 'title' => 'In courier', 'tabs' => 'orders'])]
#[Title('In courier')]
class InCourier extends Component
{
    use WithTable;
    #[Url] public string $courier = '';
    #[Computed] public function counts() { return Shipment::when($this->courier, fn ($q, $c) => $q->where('courier_id', $c))->selectRaw('courier_status, count(*) n')->groupBy('courier_status')->pluck('n', 'courier_status')->all(); }
    #[Computed]
    public function parcels()
    {
        $q = Shipment::with('order:id,number,customer_id,total,cod', 'order.customer:id,name,phone,area', 'courier:id,name')
            ->when($this->courier, fn ($q, $c) => $q->where('courier_id', $c))->when($this->status !== 'all', fn ($q) => $q->where('courier_status', $this->status))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('code', 'like', "%$s%")->orWhere('tracking', 'like', "%$s%")->orWhereHas('order', fn ($o) => $o->where('number', 'like', '%'.ltrim($s, '#').'%'))));
        return $this->dateRange($q, 'packed_at')->latest('status_updated_at')->paginate($this->perPage);
    }
    public function render() { return view('livewire.admin.orders.in-courier', ['couriers' => Courier::pluck('name', 'id')]); }
}
