<?php

namespace App\Livewire\Admin;

use App\Models\products as ModelsProducts;
use Livewire\Component;
use Livewire\WithPagination;

class Products extends Component
{
    use WithPagination;
    public $search='';
        protected $queryString = [
        'search' => ['except' => '']
    ];
    public function updatingSearch()
    {
        $this->resetPage();
    }
    public function render()
    {
 $products = ModelsProducts::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('id', 'like', '%' . $this->search . '%')
                      ->orWhere('price', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(20);
        return view('livewire.admin.products', compact('products'));
    }
    public function updateStatus($id)
    {
        $product = ModelsProducts::find($id);
        $product->status = !$product->status;
        $product->save();
    }
}
