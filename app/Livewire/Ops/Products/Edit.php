<?php

namespace App\Livewire\Ops\Products;

use App\Models\Ops\{Attribute, AttributeValue, Batch, Brand, Category, ComboItem, OrderItem, Product, ProductVariant, PurchaseOrderItem, StockMovement, Supplier};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\{Computed, Layout, Url};
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.ops', ['module' => 'Product & Inventory', 'title' => 'Edit product', 'tabs' => 'inventory'])]
class Edit extends Component
{
    use WithFileUploads;

    public Product $product;
    #[Url] public string $tab = 'general';   // general|pricing|stock|supplier|media|shipment|variants|combo|seo
    public array $form = [];
    public array $variants = [];
    public array $combo = [];                // [[variant_id, qty]]
    public array $categoryIds = [];
    public array $supplierLinks = [];        // [supplier_id => [supplier_code, supplier_name, image]]
    public array $supplierImages = [];       // uploads keyed by supplier id
    public array $tags = []; public string $newTag = '';
    public $featured; public array $galleryUploads = [];
    public string $newCategory = ''; public string $newBrand = '';
    public $allPrice = null; public $allSale = null;
    public array $options = [];              // [[attribute_id, values: [value_id…]]] — picked from the global attributes
    public array $newTerm = [];              // option index => quick-add value
    public ?int $addOption = null;
    public ?int $comboPick = null;

    public function mount(Product $product): void
    {
        $this->product = $product->load('variants', 'categories:id', 'suppliers:id', 'comboItems');
        $this->form = $product->only(['name', 'code', 'slug', 'brand_id', 'short_description', 'description', 'size_chart', 'price', 'sale_price', 'tax_class', 'low_stock_threshold', 'weight_kg', 'status', 'meta_title', 'meta_description', 'video_url', 'requires_shipping', 'sold_out', 'sort_order']);
        $this->form['sale_starts_at'] = $product->sale_starts_at?->toDateString(); $this->form['sale_ends_at'] = $product->sale_ends_at?->toDateString();
        $this->form['dim'] = array_merge(['l' => null, 'w' => null, 'h' => null], $product->dimensions ?? []);
        $this->form['gallery'] = $product->gallery ?? [];
        $this->options = $product->options ?? [];
        $this->variants = $product->variants->map(fn ($v) => $v->only(['id', 'sku', 'barcode', 'color', 'size', 'price', 'sale_price', 'stock']) + ['options' => $this->optionsOf($v)])->all();
        $this->combo = $product->comboItems->map(fn ($c) => ['variant_id' => $c->variant_id, 'qty' => $c->qty])->all();
        $this->categoryIds = $product->categories->pluck('id')->map('strval')->all();
        $this->supplierLinks = $product->suppliers->mapWithKeys(fn ($s) => [$s->id => ['supplier_code' => $s->pivot->supplier_code, 'supplier_name' => $s->pivot->supplier_name, 'image' => $s->pivot->image]])->all();
        $this->tags = $product->tags ?? [];
        if ($product->type === 'simple' && $this->tab === 'variants') $this->tab = 'general';
    }

    #[Computed] public function batches() { return Batch::whereIn('variant_id', $this->product->variants->pluck('id'))->with('supplier:id,name', 'variant:id,color,size,sku', 'purchaseOrder:id,number')->withSum(['movements as sold' => fn ($q) => $q->where('type', 'sale')], 'qty')->withSum(['movements as returned' => fn ($q) => $q->where('type', 'return_restock')], 'qty')->latest('received_at')->get(); }
    #[Computed] public function movements() { return StockMovement::whereIn('variant_id', $this->product->variants->pluck('id'))->with('batch:id,code', 'ref')->latest('created_at')->limit(60)->get(); }
    #[Computed] public function users() { return \App\Models\User::staff()->pluck('name', 'id'); }
    #[Computed] public function allVariants() { return ProductVariant::with('product:id,name,featured_image')->where('product_id', '!=', $this->product->id)->where('is_active', true)->get(); }

    // ── categories / brand / tags
    public function addCategory(): void
    {
        $name = trim($this->newCategory); if ($name === '') return;
        $c = Category::firstOrCreate(['name' => $name], ['slug' => str($name)->slug()->append('-'.substr(uniqid(), -4))]);
        $this->categoryIds[] = (string) $c->id; $this->newCategory = ''; cache()->forget('categories.list');
    }
    public function addBrand(): void
    {
        $name = trim($this->newBrand); if ($name === '') return;
        $b = Brand::firstOrCreate(['name' => $name], ['slug' => str($name)->slug()->append('-'.substr(uniqid(), -4))]);
        $this->form['brand_id'] = $b->id; $this->newBrand = ''; cache()->forget('brands.list');
    }
    public function addTag(): void { foreach (array_filter(array_map('trim', explode(',', $this->newTag))) as $t) if (! in_array($t, $this->tags)) $this->tags[] = $t; $this->newTag = ''; }
    public function removeTag(int $i): void { unset($this->tags[$i]); $this->tags = array_values($this->tags); }

    // ── size chart
    public function insertSizeTemplate(): void { $this->form['size_chart'] = "Size | Waist | Length | Hip\nM | 30 | 40 | 38\nL | 32 | 41 | 40\nXL | 34 | 42 | 42"; }

    // ── options (global attributes → this product)
    #[Computed] public function catalog() { return Attribute::catalog(); }

    /** variant's {AttributeName: value}; legacy rows fall back to colour / size */
    protected function optionsOf(ProductVariant $v): array
    {
        if ($v->options) return $v->options;
        return collect($this->options)->mapWithKeys(function ($o) use ($v) {
            $a = Attribute::catalog()[$o['attribute_id']] ?? null; $col = $a ? ProductVariant::columnFor($a) : null;
            return $a ? [$a->name => $col ? $v->$col : null] : [];
        })->all();
    }

    public function addOptionRow(): void
    {
        if (! $this->addOption || collect($this->options)->contains('attribute_id', $this->addOption) || ! isset($this->catalog[$this->addOption])) return;
        $this->options[] = ['attribute_id' => $this->addOption, 'values' => []]; $this->addOption = null;
    }
    public function removeOption(int $i): void
    {
        $name = $this->catalog[$this->options[$i]['attribute_id'] ?? 0]?->name;
        unset($this->options[$i]); $this->options = array_values($this->options);
        foreach ($this->variants as $k => $v) unset($this->variants[$k]['options'][$name]);
    }
    public function toggleValue(int $i, int $valueId): void
    {
        $vals = $this->options[$i]['values'] ?? [];
        $this->options[$i]['values'] = in_array($valueId, $vals) ? array_values(array_diff($vals, [$valueId])) : array_merge($vals, [$valueId]);
    }
    public function selectAll(int $i, bool $on = true): void { $this->options[$i]['values'] = $on ? $this->catalog[$this->options[$i]['attribute_id']]->terms->pluck('id')->all() : []; }

    /** type a new value right here → saved to the global attribute (reusable on every product) and picked */
    public function quickTerm(int $i): void
    {
        $text = trim($this->newTerm[$i] ?? ''); $aid = $this->options[$i]['attribute_id'] ?? null; if ($text === '' || ! $aid) return;
        $t = AttributeValue::firstOrCreate(['attribute_id' => $aid, 'value' => $text], ['slug' => str($text)->slug(), 'sort' => (int) AttributeValue::where('attribute_id', $aid)->max('sort') + 1]);
        Attribute::flush(); unset($this->catalog);
        if (! in_array($t->id, $this->options[$i]['values'])) $this->options[$i]['values'][] = $t->id;
        $this->newTerm[$i] = '';
    }

    /** picked values in attribute order: [[name, [value…]]] */
    protected function picked(): array
    {
        return collect($this->options)->map(function ($o) {
            $a = $this->catalog[$o['attribute_id']] ?? null; if (! $a) return null;
            return [$a->name, $a->terms->whereIn('id', $o['values'])->pluck('value')->all()];
        })->filter(fn ($x) => $x && $x[1])->values()->all();
    }

    /** all combinations of the picked values (cartesian product) */
    public function combinations(): array
    {
        $out = [[]];
        foreach ($this->picked() as [$name, $vals]) { $next = []; foreach ($out as $c) foreach ($vals as $v) $next[] = $c + [$name => $v]; $out = $next; }
        return $out === [[]] ? [] : $out;
    }
    protected function same(array $a, array $b): bool { return collect($a)->filter()->sortKeys()->all() == collect($b)->filter()->sortKeys()->all(); }
    /**
     * [fills, new]: existing rows missing a newly added option take the first free matching combination
     * (like Shopify — adding "Material" keeps your 6 variants as …/Cotton instead of duplicating them); the rest are new rows.
     */
    protected function plan(): array
    {
        $combos = $this->combinations(); $taken = []; $fills = [];
        foreach ($this->variants as $v) foreach ($combos as $k => $c) if ($this->same($v['options'] ?? [], $c)) $taken[$k] = true;
        foreach ($this->variants as $i => $v) {
            $have = collect($v['options'] ?? [])->filter()->all();
            if (! $have || collect($combos)->contains(fn ($c) => $this->same($have, $c))) continue;
            foreach ($combos as $k => $c) if (! isset($taken[$k]) && array_intersect_assoc($c, $have) == $have) { $fills[$i] = $c; $taken[$k] = true; break; }
        }
        return [$fills, array_values(array_diff_key($combos, $taken))];
    }
    public function missingCount(): int { [$fills, $new] = $this->plan(); return count($fills) + count($new); }

    protected function skuFor(array $combo): string
    {
        $part = fn ($v) => strtoupper(preg_match('/^[A-Za-z]+$/', $v) && mb_strlen($v) > 3 ? substr($v, 0, 3) : preg_replace('/[^A-Za-z0-9]/', '', $v));
        $base = strtoupper($this->form['code']).collect($combo)->map(fn ($v) => '-'.$part($v))->join(''); $sku = $base; $n = 2;
        while (collect($this->variants)->contains('sku', $sku) || ProductVariant::where('sku', $sku)->exists()) $sku = $base.'-'.$n++;
        return $sku;
    }

    // ── variants
    public function addVariant(): void { $this->variants[] = ['id' => null, 'sku' => $this->form['code'].'-', 'barcode' => null, 'color' => '', 'size' => '', 'price' => null, 'sale_price' => null, 'stock' => 0, 'options' => collect($this->picked())->mapWithKeys(fn ($p) => [$p[0] => null])->all()]; }
    public function removeVariant(int $i): void { unset($this->variants[$i]); $this->variants = array_values($this->variants); }
    public function applyAll(string $field): void
    {
        $v = $field === 'price' ? $this->allPrice : $this->allSale; $v = $v === '' || $v === null ? null : (int) $v;
        foreach ($this->variants as $i => $_) $this->variants[$i][$field] = $v;
        $this->dispatch('toast', message: ($field === 'price' ? 'Price' : 'Sale price').' set for all variants');
    }
    /** picked values → every missing combination becomes a variant row (SKU = CODE-BLU-M …); existing rows are kept */
    public function generate(): void
    {
        $combos = $this->combinations();
        if (! $combos) { $this->dispatch('toast', message: 'Pick at least one value', type: 'bad'); return; }
        [$fills, $new] = $this->plan();
        foreach ($fills as $i => $c) $this->variants[$i]['options'] = $c;
        foreach ($new as $c) $this->variants[] = ['id' => null, 'sku' => $this->skuFor($c), 'barcode' => null, 'color' => null, 'size' => null, 'price' => null, 'sale_price' => null, 'stock' => 0, 'options' => $c];
        $msg = collect([count($new) ? count($new).' variants added' : null, count($fills) ? count($fills).' existing updated' : null])->filter()->join(' · ');
        $this->dispatch('toast', message: $msg ?: 'All combinations already exist', type: $msg ? 'ok' : 'bad');
    }

    // ── combo
    /**
     * Product type switch. Applied at once (the tabs depend on it) without touching unsaved form edits.
     * simple → variable: always · variable → simple: max one variant · → combo: no own stock batches (combo stock = its components)
     * · combo → other: its component list goes.
     */
    public function changeType(string $type): void
    {
        $from = $this->product->type;
        if ($type === $from || ! array_key_exists($type, config('ops.product_types'))) return;
        $variants = $this->product->variants()->count();
        $error = match (true) {
            $type === 'simple' && $variants > 1 => "This product has $variants variants. Remove the extra variants first (Variants tab) — a simple product has one.",
            $type === 'combo' && $variants > 1 => "This product has $variants variants. A combo is one item made of other products' variants.",
            $type === 'combo' && Batch::whereIn('variant_id', $this->product->variants()->select('id'))->exists() => 'This product has its own stock batches. A combo has no stock of its own (it uses its components\' stock), so it can\'t become a combo.',
            default => null,
        };
        if ($error) { $this->dispatch('toast', message: $error, type: 'bad'); return; }

        DB::transaction(function () use ($type, $from) {
            if ($from === 'combo') $this->product->comboItems()->delete();
            $this->product->update(['type' => $type]);
            if (! $this->product->variants()->exists()) $this->product->variants()->create(['sku' => $this->product->code]);   // every type sells through a variant
        });
        if ($from === 'combo') $this->combo = [];
        $this->product->refresh()->load('variants', 'comboItems');
        // a variant created just now must be in the form, or the next Save would treat it as removed
        foreach ($this->product->variants->whereNotIn('id', array_column($this->variants, 'id')) as $v)
            $this->variants[] = $v->only(['id', 'sku', 'barcode', 'color', 'size', 'price', 'sale_price', 'stock']) + ['options' => $this->optionsOf($v)];
        // the tab that belonged to the old type is gone
        if (($this->tab === 'variants' && $type === 'simple') || ($this->tab === 'combo' && $type !== 'combo')) $this->tab = 'general';
        ops_log('Product '.$this->product->name.' type: '.config("ops.product_types.$from").' → '.config("ops.product_types.$type"), $this->product);
        $this->dispatch('toast', message: 'Type changed to '.config("ops.product_types.$type"));
    }

    public function addComboItem(): void { if ($this->comboPick) { $this->combo[] = ['variant_id' => $this->comboPick, 'qty' => 1]; $this->comboPick = null; } }
    public function removeComboItem(int $i): void { unset($this->combo[$i]); $this->combo = array_values($this->combo); }

    // ── media
    public function removeFeatured(): void { $this->form['featured_image'] = null; $this->featured = null; $this->product->update(['featured_image' => null]); $this->dispatch('toast', message: 'Featured image removed'); }
    public function makeFeatured(int $i): void
    {
        $g = $this->form['gallery']; $img = $g[$i] ?? null; if (! $img) return;
        $g[$i] = $this->product->featured_image; $g = array_values(array_filter($g));
        $this->product->update(['featured_image' => $img, 'gallery' => $g]); $this->form['gallery'] = $g; $this->dispatch('toast', message: 'Featured image changed');
    }
    public function removeGallery(int $i): void { $g = $this->form['gallery']; unset($g[$i]); $this->form['gallery'] = array_values($g); $this->product->update(['gallery' => $this->form['gallery']]); }

    public function save(): void
    {
        // empty inputs arrive as '' — store them as NULL (strict MySQL rejects '' in int / FK columns)
        $blank = fn (array $a) => array_map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v, $a);
        $this->form = $blank($this->form);
        $this->variants = array_map($blank, $this->variants);
        $rules = [
            'form.name' => 'required|max:160', 'form.code' => 'required|max:40|unique:ops_products,code,'.$this->product->id,
            'form.slug' => 'nullable|max:190|unique:ops_products,slug,'.$this->product->id,
            'form.price' => 'required|integer|min:0', 'form.sale_price' => 'nullable|integer|lt:form.price', 'form.low_stock_threshold' => 'required|integer|min:0', 'form.sort_order' => 'nullable|integer|min:-9999|max:9999',
            'form.sale_starts_at' => 'nullable|date', 'form.sale_ends_at' => 'nullable|date|after_or_equal:form.sale_starts_at',
            'form.weight_kg' => 'nullable|numeric|min:0', 'form.brand_id' => 'nullable|exists:ops_brands,id', 'form.video_url' => 'nullable|url',
            'variants.*.price' => 'nullable|integer|min:0', 'variants.*.sale_price' => 'nullable|integer|min:0',
            'featured' => 'nullable|image|max:4096', 'galleryUploads.*' => 'image|max:4096', 'supplierImages.*' => 'nullable|image|max:2048',
            'combo.*.qty' => 'required|integer|min:1', 'options.*.attribute_id' => 'distinct',
        ];
        foreach ($this->variants as $i => $v) {   // SKU / barcode are unique across ALL products
            $rules["variants.$i.sku"] = ['required', 'distinct', 'max:60', Rule::unique('ops_product_variants', 'sku')->ignore($v['id'])];
            $rules["variants.$i.barcode"] = ['nullable', 'distinct', 'max:40', Rule::unique('ops_product_variants', 'barcode')->ignore($v['id'])];
        }
        $this->validate($rules, ['variants.*.sku.unique' => 'SKU already used by another variant', 'variants.*.barcode.unique' => 'Barcode already used', 'form.sale_price.lt' => 'Sale price must be lower than the regular price.']);

        // two variants with the same combination would be indistinguishable on the storefront
        $seen = [];
        foreach ($this->variants as $i => $v) {
            $k = json_encode(collect($v['options'] ?? [])->filter()->sortKeys()->all());
            if ($k !== '[]' && isset($seen[$k])) { $this->addError("variants.$i.sku", 'Same options as another variant'); return; }
            $seen[$k] = 1;
        }

        DB::transaction(function () {
            $data = collect($this->form)->except(['dim', 'gallery', 'featured_image'])->all();
            $data['dimensions'] = array_filter($this->form['dim'], fn ($v) => $v !== null && $v !== '') ?: null;
            $data['slug'] = $data['slug'] ?: str($data['name'])->slug().'-'.$this->product->id;
            $data['description'] = \App\Support\RichText::clean($data['description'] ?? null);   // rich text editor HTML, cleaned server-side
            $data['tags'] = $this->tags ?: null; $data['requires_shipping'] = (bool) ($this->form['requires_shipping'] ?? true);
            $data['sold_out'] = (bool) ($this->form['sold_out'] ?? false); $data['sort_order'] = (int) ($this->form['sort_order'] ?? 0);   // website
            if ($this->featured) $data['featured_image'] = $this->featured->store('products', 'public');
            if ($this->galleryUploads) { $data['gallery'] = array_merge($this->form['gallery'], collect($this->galleryUploads)->map->store('products', 'public')->all()); }
            $data['options'] = array_values(array_filter($this->options, fn ($o) => isset($this->catalog[$o['attribute_id']]))) ?: null;
            $this->product->update($data);
            $this->product->syncCategoriesAtEnd($this->categoryIds);   // a newly ticked category puts the product at the end of its page
            $links = collect($this->supplierLinks)->map(function ($l, $sid) {
                if ($img = $this->supplierImages[$sid] ?? null) $l['image'] = $img->store('suppliers', 'public');
                return array_intersect_key($l, array_flip(['supplier_code', 'supplier_name', 'image']));
            })->filter(fn ($l) => array_filter($l));
            $this->product->suppliers()->sync($links->all());
            $keep = [];
            foreach ($this->variants as $v) {   // stock is NOT editable here — it comes from batches only
                $opts = collect($v['options'] ?? [])->filter(fn ($x) => $x !== null && $x !== '')->all();
                foreach ($this->catalog as $a)   // mirror into the colour / size columns
                    if (($col = ProductVariant::columnFor($a)) && collect($this->options)->contains('attribute_id', $a->id)) $v[$col] = $opts[$a->name] ?? null;
                $keep[] = ProductVariant::updateOrCreate(['id' => $v['id']], ['product_id' => $this->product->id, 'options' => $opts ?: null] + collect($v)->except('id', 'stock', 'options')->all())->id;
            }
            // removed variants are deleted only if nothing references them (batches, orders, POs)
            $this->product->variants()->whereNotIn('id', $keep)->whereDoesntHave('batches')
                ->whereNotIn('id', OrderItem::select('variant_id'))->whereNotIn('id', PurchaseOrderItem::select('variant_id'))->delete();
            if ($this->product->type === 'combo') {
                $this->product->comboItems()->delete();
                foreach ($this->combo as $c) ComboItem::create(['product_id' => $this->product->id, 'variant_id' => $c['variant_id'], 'qty' => (int) $c['qty']]);
                if (! $this->product->variants()->exists()) $this->product->variants()->create(['sku' => $this->product->code]);
            }
        });
        ops_log('Saved product '.$this->product->name, $this->product);
        $this->reset('featured', 'galleryUploads', 'supplierImages');
        $this->mount($this->product->fresh());
        unset($this->batches);
        $this->dispatch('saved');
        $this->dispatch('toast', message: 'Product saved');
    }

    public function render()
    {
        return view('livewire.ops.products.edit', [
            'brands' => cache()->remember('brands.list', 600, fn () => Brand::orderBy('name')->pluck('name', 'id')),
            'categories' => cache()->remember('categories.list', 600, fn () => Category::orderBy('name')->pluck('name', 'id')),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ])->title($this->product->name);
    }
}
