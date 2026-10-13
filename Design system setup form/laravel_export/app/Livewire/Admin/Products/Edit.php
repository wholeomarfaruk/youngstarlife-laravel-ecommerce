<?php

namespace App\Livewire\Admin\Products;

use App\Models\{Brand, Category, Product, ProductVariant, Supplier};
use Livewire\WithFileUploads;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Product & Inventory', 'title' => 'Edit product', 'tabs' => 'inventory'])]
class Edit extends Component
{
    use WithFileUploads;

    public Product $product;
    #[Url] public string $tab = 'general';   // general|pricing|stock|supplier|media|shipment|variants|seo
    public array $form = [];
    public array $variants = [];
    public array $categoryIds = [];
    public array $supplierLinks = [];        // [supplier_id => [supplier_code, supplier_name]]
    public $featured; public array $gallery = [];

    public function mount(Product $product): void
    {
        $this->product = $product->load('variants', 'categories:id', 'suppliers:id');
        $this->form = $product->only(['name', 'code', 'brand_id', 'short_description', 'description', 'size_chart', 'price', 'sale_price', 'low_stock_threshold', 'weight_kg', 'status', 'meta_title', 'meta_description', 'video_url']);
        $this->variants = $product->variants->map->only(['id', 'sku', 'barcode', 'color', 'size', 'price', 'sale_price', 'stock'])->all();
        $this->categoryIds = $product->categories->pluck('id')->map('strval')->all();
        $this->supplierLinks = $product->suppliers->mapWithKeys(fn ($s) => [$s->id => ['supplier_code' => $s->pivot->supplier_code, 'supplier_name' => $s->pivot->supplier_name]])->all();
    }

    #[Computed] public function batches() { return \App\Models\Batch::whereIn('variant_id', $this->product->variants->pluck('id'))->with('supplier:id,name', 'variant:id,color,size')->latest('received_at')->get(); }
    #[Computed] public function movements() { return \App\Models\StockMovement::whereIn('variant_id', $this->product->variants->pluck('id'))->with('batch:id,code')->latest('created_at')->limit(50)->get(); }

    public function addVariant(): void { $this->variants[] = ['id' => null, 'sku' => $this->form['code'].'-', 'barcode' => null, 'color' => '', 'size' => '', 'price' => null, 'sale_price' => null, 'stock' => 0]; }
    public function removeVariant(int $i): void { unset($this->variants[$i]); $this->variants = array_values($this->variants); }

    public function save(): void
    {
        $this->validate([
            'form.name' => 'required|max:160', 'form.code' => 'required|max:40|unique:products,code,'.$this->product->id,
            'form.price' => 'required|integer|min:0', 'form.sale_price' => 'nullable|integer|lt:form.price',
            'variants.*.sku' => 'required|distinct', 'variants.*.sale_price' => 'nullable|integer', 'featured' => 'nullable|image|max:4096',
        ]);
        if ($this->featured) $this->form['featured_image'] = $this->featured->store('products', 'public');
        $this->product->update($this->form);
        $this->product->categories()->sync($this->categoryIds);
        $this->product->suppliers()->sync(collect($this->supplierLinks)->filter(fn ($l) => array_filter($l))->all());
        $keep = [];
        foreach ($this->variants as $v) {   // stock is NOT editable here — it comes from batches only
            $keep[] = ProductVariant::updateOrCreate(['id' => $v['id']], ['product_id' => $this->product->id] + collect($v)->except('id', 'stock')->all())->id;
        }
        $this->product->variants()->whereNotIn('id', $keep)->whereDoesntHave('batches')->delete();
        $this->mount($this->product->fresh());
        $this->dispatch('toast', message: 'Product saved');
    }

    public function render()
    {
        return view('livewire.admin.products.edit', [
            'brands' => cache()->remember('brands.list', 600, fn () => Brand::orderBy('name')->pluck('name', 'id')),
            'categories' => cache()->remember('categories.list', 600, fn () => Category::orderBy('name')->pluck('name', 'id')),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ])->title($this->product->name);
    }
}
