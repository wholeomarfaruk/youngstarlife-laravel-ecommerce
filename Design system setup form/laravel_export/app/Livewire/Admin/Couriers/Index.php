<?php

namespace App\Livewire\Admin\Couriers;

use App\Models\Courier;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Couriers', 'title' => 'Couriers'])]
#[Title('Couriers')]
class Index extends Component
{
    use WithTable;
    #[Computed]
    public function rows()
    {
        return Courier::query()->withCount(['shipments as total', 'shipments as delivered' => fn ($q) => $q->where('courier_status', 'delivered'), 'shipments as returned' => fn ($q) => $q->whereIn('courier_status', ['returned', 'returning'])])->withSum(['shipments as receivable' => fn ($q) => $q->where('courier_status', 'delivered')->whereNull('settlement_id')], 'cod_amount')->paginate($this->perPage);
    }

    public function render() { return view('livewire.admin.couriers.index'); }
}
