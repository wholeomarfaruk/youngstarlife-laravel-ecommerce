<?php

namespace App\Livewire\Admin\Suppliers;

use App\Models\Supplier;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Customers & Suppliers', 'title' => 'Suppliers', 'tabs' => 'parties'])]
#[Title('Suppliers')]
class Index extends Component
{
    use WithTable;
    #[Computed]
    public function rows()
    {
        return Supplier::query()->withCount('purchaseOrders', 'batches')->withSum('batches as stock', 'remaining')->when($this->search, fn ($q, $s) => $q->where('name', 'like', "%$s%"))->when($this->status === 'due', fn ($q) => $q->where('due', '>', 0))->orderByDesc('due')->paginate($this->perPage);
    }

    public function render() { return view('livewire.admin.suppliers.index'); }
}
