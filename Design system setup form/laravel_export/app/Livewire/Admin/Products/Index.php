<?php

namespace App\Livewire\Admin\Products;

use App\Models\{Category, Product};
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Product & Inventory', 'title' => 'All products', 'tabs' => 'inventory'])]
#[Title('Products')]
class Index extends Component
{
    use WithTable;
    public string $newType = 'variable'; public string $newName = '';

    #[Computed]
    public function products()
    {
        return Product::query()->select(['id', 'name', 'code', 'type', 'status', 'price', 'sale_price', 'low_stock_threshold', 'featured_image'])
            ->withSum('variants as stock', 'stock')->withCount('variants')->with('categories:id,name')
            ->search($this->search)
            ->when($this->status !== 'all', fn ($q) => $this->status === 'low' ? $q->havingRaw('stock < low_stock_threshold') : $q->where('status', $this->status))
            ->when($this->filters['category'] ?? null, fn ($q, $c) => $q->whereHas('categories', fn ($x) => $x->whereKey($c)))
            ->orderBy($this->sortField === 'id' ? 'updated_at' : $this->sortField, $this->sortDir)->paginate($this->perPage);
    }

    #[Computed] public function categories() { return cache()->remember('categories.list', 600, fn () => Category::orderBy('name')->pluck('name', 'id')); }

    /** "Add product" modal → creates and jumps to the edit page (General tab). */
    public function create()
    {
        $this->validate(['newName' => 'required|string|max:160', 'newType' => 'in:simple,variable,combo']);
        $p = Product::create(['name' => $this->newName, 'type' => $this->newType, 'slug' => str($this->newName)->slug().'-'.uniqid(), 'code' => strtoupper(str($this->newName)->substr(0, 3)).'-'.random_int(100, 999)]);
        return $this->redirectRoute('admin.products.edit', ['product' => $p, 'tab' => 'general'], navigate: true);
    }

    public function bulk(string $status): void { Product::whereIn('id', $this->selected)->update(['status' => $status]); $this->selected = []; $this->toast('Products updated'); }

    public function render() { return view('livewire.admin.products.index'); }
}
