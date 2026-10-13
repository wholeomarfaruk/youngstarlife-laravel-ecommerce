<?php

namespace App\Livewire\Admin\Customers;

use App\Models\Customer;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Customers & Suppliers', 'title' => 'Customers', 'tabs' => 'parties'])]
#[Title('Customers')]
class Index extends Component
{
    use WithTable;
    #[Computed]
    public function rows()
    {
        return Customer::query()->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")->orWhere('area', 'like', "%$s%")))->when($this->status === 'due', fn ($q) => $q->where('due', '>', 0))->when($this->status === 'risky', fn ($q) => $q->whereRaw('delivered_count < 0.75 * (delivered_count + returned_count)'))->orderByDesc('total_spent')->paginate($this->perPage);
    }

    public function render() { return view('livewire.admin.customers.index'); }
}
