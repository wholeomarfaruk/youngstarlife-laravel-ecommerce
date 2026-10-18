<?php

namespace App\Livewire\Ops\Products;

use App\Models\Ops\{Batch, Brand, Category, Product, ProductVariant, StockMovement};
use App\Livewire\Ops\Concerns\{InventoryStats, WithTable};
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.ops', ['module' => 'Product & Inventory', 'title' => 'All products', 'tabs' => 'inventory'])]
#[Title('Products')]
class Index extends Component
{
    use WithTable, InventoryStats, WithFileUploads;
    public const VIEWS = ['catalog' => 'Catalog', 'variants' => 'Variants', 'movements' => 'Stock movements', 'low' => 'Low stock', 'damaged' => 'Damaged & returned', 'categories' => 'Categories', 'attributes' => 'Attributes', 'brands' => 'Brands'];
    #[Url(except: 'catalog')] public string $view = 'catalog';
    #[Url(except: '')] public string $mtype = '';                    // stock-movement type filter
    public string $catName = ''; public ?int $catParent = null; public ?int $renaming = null; public string $renameTo = '';
    public string $brandName = ''; public string $brandOrigin = '';
    public $csv;
    #[Url(except: 'updated')] public string $sort = 'updated';   // updated | name | price_low | price_high | stock_low | stock_high
    public string $newType = 'variable'; public string $newName = ''; public ?int $newCategory = null; public ?int $newBrand = null;

    protected function base()
    {
        return Product::query()->select(['id', 'name', 'code', 'type', 'status', 'price', 'sale_price', 'low_stock_threshold', 'featured_image', 'sold_out', 'updated_at'])
            ->withSum('variants as stock', 'stock')->withSum('variants as damaged', 'damaged')->withCount('variants')->with('categories:id,name')
            ->search($this->search)
            ->when($this->filters['category'] ?? null, fn ($q, $c) => $q->whereHas('categories', fn ($x) => $x->whereKey($c)))
            ->when($this->filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t));
    }

    protected function applyStatus($q, string $s)
    {
        return match ($s) {
            'all' => $q, 'low' => $q->havingRaw('COALESCE(stock,0) > 0 AND COALESCE(stock,0) < low_stock_threshold'),
            'out' => $q->havingRaw('COALESCE(stock,0) <= 0'), 'damaged' => $q->havingRaw('COALESCE(damaged,0) > 0'),
            default => $q->where('status', $s),
        };
    }

    #[Computed]
    public function counts(): array
    {
        $rows = $this->base()->setEagerLoads([])->get();   // one query, counted in PHP
        return ['all' => $rows->count(), 'active' => $rows->where('status', 'active')->count(), 'draft' => $rows->where('status', 'draft')->count(), 'archived' => $rows->where('status', 'archived')->count(),
            'low' => $rows->filter(fn ($p) => $p->stock > 0 && $p->stock < $p->low_stock_threshold)->count(), 'out' => $rows->filter(fn ($p) => $p->stock <= 0)->count(), 'damaged' => $rows->filter(fn ($p) => $p->damaged > 0)->count()];
    }

    #[Computed]
    public function products()
    {
        [$col, $dir] = match ($this->sort) { 'name' => ['name', 'asc'], 'price_low' => ['price', 'asc'], 'price_high' => ['price', 'desc'], 'stock_low' => ['stock', 'asc'], 'stock_high' => ['stock', 'desc'], default => ['updated_at', 'desc'] };
        if ($this->sortField !== 'id') [$col, $dir] = [$this->sortField, $this->sortDir];
        return $this->applyStatus($this->base(), $this->status)->orderBy($col, $dir)->paginate($this->perPage);
    }

    public function mount(): void
    {
        if ($this->view === 'attributes') { $this->redirectRoute('ops.attributes.index', navigate: true); return; }   // Attributes is its own component, same tab bar
        if (! array_key_exists($this->view, self::VIEWS)) $this->view = 'catalog';
        if (request()->boolean('new')) $this->js("window.dispatchEvent(new CustomEvent('open-modal', { detail: 'add-product' }))");
        if (request()->boolean('import')) $this->js("window.dispatchEvent(new CustomEvent('open-modal', { detail: 'import-csv' }))");
    }
    public function updatingView(): void { $this->resetPage(); $this->search = ''; $this->selected = []; $this->renaming = null; }
    public function updatingMtype(): void { $this->resetPage(); }

    // ── Variants / Low stock / Damaged & returned views
    #[Computed]
    public function variantRows()
    {
        $with = 'product:id,name,code,price,sale_price,low_stock_threshold,status';
        $s = $this->search;
        if ($this->view === 'low') {
            return static::lowQuery()->select('ops_product_variants.*')->with($with)
                ->when($s, fn ($q) => $q->where(fn ($x) => $x->where('ops_product_variants.sku', 'like', "%$s%")->orWhere('lp.name', 'like', "%$s%")))
                ->addSelect(['last_supplier' => Batch::query()->join('ops_suppliers as su', 'su.id', '=', 'ops_batches.supplier_id')->select('su.name')->whereColumn('ops_batches.variant_id', 'ops_product_variants.id')->latest('ops_batches.received_at')->limit(1)])
                ->orderBy('ops_product_variants.stock')->paginate($this->perPage);
        }
        $q = ProductVariant::query()->with($with)->whereHas('product')
            ->when($s, fn ($q) => $q->where(fn ($x) => $x->where('sku', 'like', "%$s%")->orWhere('barcode', 'like', "%$s%")->orWhere('color', 'like', "%$s%")
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"))));
        if ($this->view === 'damaged') {
            return $q->where(fn ($x) => $x->where('damaged', '>', 0)->orWhereIn('id', StockMovement::where('type', 'return_restock')->select('variant_id')))
                ->withSum(['movements as returned' => fn ($m) => $m->where('type', 'return_restock')], 'qty')->orderByDesc('damaged')->paginate($this->perPage);
        }
        return $q->orderBy('product_id')->orderBy('id')->paginate($this->perPage);
    }

    #[Computed]
    public function movements()
    {
        $s = $this->search;
        return StockMovement::with('batch:id,code', 'variant.product:id,name', 'ref', 'user:id,name')
            ->when($this->mtype, fn ($q, $t) => $q->where('type', $t))
            ->when($s, fn ($q) => $q->where(fn ($x) => $x->whereHas('batch', fn ($b) => $b->where('code', 'like', "%$s%"))->orWhereHas('variant', fn ($v) => $v->where('sku', 'like', "%$s%")->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%$s%")))))
            ->latest('created_at')->latest('id')->paginate($this->perPage);
    }

    // ── Categories (parent → children)
    #[Computed]
    public function categoryTree(): array
    {
        $all = Category::withCount('products')->orderBy('sort')->orderBy('name')->get();
        $direct = DB::table('ops_category_product')->get()->groupBy('category_id')->map(fn ($g) => $g->pluck('product_id'));
        $rows = [];
        foreach ($all->whereNull('parent_id') as $c) {
            $kids = $all->where('parent_id', $c->id);
            $ids = collect($direct[$c->id] ?? [])->merge($kids->flatMap(fn ($k) => $direct[$k->id] ?? []))->unique();   // parent counts its children's products
            $rows[] = ['cat' => $c, 'depth' => 0, 'count' => $ids->count(), 'in_use' => $ids->isNotEmpty() || $kids->isNotEmpty()];
            foreach ($kids as $k) $rows[] = ['cat' => $k, 'depth' => 1, 'count' => $k->products_count, 'in_use' => $k->products_count > 0];
        }
        return $rows;
    }

    public function addCategory(): void
    {
        $this->validate(['catName' => 'required|max:60', 'catParent' => 'nullable|exists:ops_categories,id'], ['catName.required' => 'Type a category name.']);
        if (Category::where('name', trim($this->catName))->where('parent_id', $this->catParent)->exists()) { $this->addError('catName', 'Already exists here.'); return; }
        Category::create(['name' => trim($this->catName), 'parent_id' => $this->catParent, 'slug' => str($this->catName)->slug().'-'.substr(uniqid(), -4), 'sort' => (int) Category::max('sort') + 1]);
        ops_log('Added category '.$this->catName); $this->toast('Category added');
        $this->reset('catName', 'catParent'); $this->afterCatalogChange();
    }

    public function startRename(int $id, string $type = 'category'): void { $this->renaming = $id; $this->renameTo = (string) ($type === 'brand' ? Brand::find($id)?->name : Category::find($id)?->name); }
    public function saveRename(string $type = 'category'): void
    {
        $name = trim($this->renameTo);
        if ($name !== '' && $this->renaming) { ($type === 'brand' ? Brand::query() : Category::query())->whereKey($this->renaming)->update(['name' => $name]); $this->toast('Renamed'); }
        $this->renaming = null; $this->afterCatalogChange();
    }

    public function deleteCategory(int $id): void
    {
        $c = Category::withCount('products', 'children')->findOrFail($id);
        if ($c->products_count || $c->children_count) { $this->toast($c->name.' is in use', 'bad'); return; }
        $c->delete(); ops_log('Deleted category '.$c->name); $this->toast('Category deleted'); $this->afterCatalogChange();
    }

    // ── Brands
    #[Computed] public function brandRows() { return Brand::withCount('products')->orderByDesc('products_count')->orderBy('name')->get(); }

    public function addBrand(): void
    {
        $this->validate(['brandName' => 'required|max:60|unique:ops_brands,name', 'brandOrigin' => 'nullable|max:60'], ['brandName.required' => 'Type a brand name.', 'brandName.unique' => 'Brand already exists.']);
        Brand::create(['name' => trim($this->brandName), 'origin' => trim($this->brandOrigin) ?: null, 'slug' => str($this->brandName)->slug().'-'.substr(uniqid(), -4)]);
        ops_log('Added brand '.$this->brandName); $this->toast('Brand added');
        $this->reset('brandName', 'brandOrigin'); $this->afterCatalogChange();
    }

    public function deleteBrand(int $id): void
    {
        $b = Brand::withCount('products')->findOrFail($id);
        if ($b->products_count) { $this->toast($b->name.' is in use', 'bad'); return; }
        $b->delete(); ops_log('Deleted brand '.$b->name); $this->toast('Brand deleted'); $this->afterCatalogChange();
    }

    protected function afterCatalogChange(): void
    {
        cache()->forget('categories.list'); cache()->forget('brands.list'); static::flushInv();
        unset($this->categoryTree, $this->brandRows, $this->categories, $this->brands, $this->inv);
    }

    // ── Import CSV: one row per variant; rows sharing a product code become one product. Stock is never imported (it comes from batches).
    public const CSV_COLUMNS = ['code', 'name', 'type', 'category', 'brand', 'price', 'sale_price', 'status', 'color', 'size', 'sku', 'barcode'];

    public function csvTemplate()
    {
        return response()->streamDownload(function () {
            $f = fopen('php://output', 'w'); fputcsv($f, self::CSV_COLUMNS);
            fputcsv($f, ['TSH', 'Basic T-shirt', 'variable', 'T-shirts', 'YoungStar', 650, '', 'active', 'Black', 'M', 'TSH-BLA-M', '']);
            fputcsv($f, ['TSH', 'Basic T-shirt', 'variable', 'T-shirts', 'YoungStar', 650, '', 'active', 'Black', 'L', 'TSH-BLA-L', '']);
            fclose($f);
        }, 'products-template.csv');
    }

    public function importCsv(): void
    {
        $this->validate(['csv' => 'required|file|mimes:csv,txt|max:5120'], ['csv.required' => 'Choose a CSV file.']);
        $fh = fopen($this->csv->getRealPath(), 'r');
        $head = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($fh) ?: []);
        if (! in_array('name', $head) || ! in_array('code', $head)) { fclose($fh); $this->addError('csv', 'The first row must contain at least: code, name'); return; }
        $rows = [];
        while (($r = fgetcsv($fh)) !== false) if (array_filter($r)) $rows[] = array_combine($head, array_pad(array_slice($r, 0, count($head)), count($head), ''));
        fclose($fh);
        $stats = ['created' => 0, 'updated' => 0, 'variants' => 0, 'skipped' => 0];
        DB::transaction(function () use ($rows, &$stats) {
            foreach (collect($rows)->groupBy(fn ($r) => strtoupper(trim($r['code']))) as $code => $group) {
                $f = $group->first();
                if ($code === '' || trim($f['name'] ?? '') === '') { $stats['skipped'] += $group->count(); continue; }
                $type = in_array($f['type'] ?? '', ['simple', 'variable', 'combo']) ? $f['type'] : ($group->count() > 1 || ($f['color'] ?? '') || ($f['size'] ?? '') ? 'variable' : 'simple');
                $brand = ($f['brand'] ?? '') ? Brand::firstOrCreate(['name' => trim($f['brand'])], ['slug' => str($f['brand'])->slug().'-'.substr(uniqid(), -4)])->id : null;
                $data = array_filter(['name' => trim($f['name']), 'type' => $type, 'brand_id' => $brand,
                    'price' => is_numeric($f['price'] ?? '') ? (int) $f['price'] : null, 'sale_price' => is_numeric($f['sale_price'] ?? '') ? (int) $f['sale_price'] : null,
                    'status' => in_array($f['status'] ?? '', ['active', 'draft', 'archived']) ? $f['status'] : null], fn ($v) => $v !== null);
                $p = Product::withTrashed()->where('code', $code)->first();
                if ($p) { $p->restore(); $p->update($data); $stats['updated']++; }
                else { $p = Product::create($data + ['code' => $code, 'slug' => str($f['name'])->slug().'-'.substr(uniqid(), -5), 'low_stock_threshold' => (int) ops_setting('batch.low_threshold', 15)]); $stats['created']++; }
                if ($cat = trim($f['category'] ?? '')) $p->categories()->syncWithoutDetaching([Category::firstOrCreate(['name' => $cat], ['slug' => str($cat)->slug().'-'.substr(uniqid(), -4)])->id]);
                foreach ($group as $r) {
                    $sku = strtoupper(trim($r['sku'] ?? '')) ?: $code.collect([$r['color'] ?? '', $r['size'] ?? ''])->filter()->map(fn ($x) => '-'.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $x), 0, 3)))->join('');
                    if (ProductVariant::where('sku', $sku)->where('product_id', '!=', $p->id)->exists()) { $stats['skipped']++; continue; }   // SKU of another product
                    $opts = array_filter(['Color' => trim($r['color'] ?? '') ?: null, 'Size' => trim($r['size'] ?? '') ?: null]);
                    $bc = trim($r['barcode'] ?? '') ?: null;
                    if ($bc && ProductVariant::where('barcode', $bc)->where('sku', '!=', $sku)->exists()) $bc = null;
                    ProductVariant::updateOrCreate(['sku' => $sku], ['product_id' => $p->id, 'color' => $opts['Color'] ?? null, 'size' => $opts['Size'] ?? null, 'options' => $opts ?: null, 'barcode' => $bc]);
                    $stats['variants']++;
                }
            }
        });
        ops_log("CSV import: {$stats['created']} created, {$stats['updated']} updated, {$stats['variants']} variants");
        $this->reset('csv'); $this->afterCatalogChange(); unset($this->products, $this->counts);
        $this->dispatch('close-modal', 'import-csv');
        $this->toast("Imported · {$stats['created']} new, {$stats['updated']} updated, {$stats['variants']} variants".($stats['skipped'] ? " · {$stats['skipped']} rows skipped" : ''), $stats['skipped'] ? 'bad' : 'ok');
    }

    #[Computed] public function categories() { return cache()->remember('categories.list', 600, fn () => Category::orderBy('name')->pluck('name', 'id')); }
    #[Computed] public function brands() { return cache()->remember('brands.list', 600, fn () => Brand::orderBy('name')->pluck('name', 'id')); }

    /** "Add product" modal → creates and jumps to the edit page (General tab). */
    public function create()
    {
        $this->validate(['newName' => 'required|string|max:160', 'newType' => 'in:simple,variable,combo', 'newCategory' => 'nullable|exists:ops_categories,id', 'newBrand' => 'nullable|exists:ops_brands,id'], ['newName.required' => 'Give the product a name.']);
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) str($this->newName)->ascii()), 0, 3)) ?: 'PRD';
        do { $code = $prefix.'-'.random_int(100, 999); } while (Product::withTrashed()->where('code', $code)->exists());
        $p = Product::create(['name' => $this->newName, 'type' => $this->newType, 'slug' => str($this->newName)->slug().'-'.substr(uniqid(), -5), 'code' => $code, 'brand_id' => $this->newBrand, 'low_stock_threshold' => (int) ops_setting('batch.low_threshold', 15)]);
        if ($this->newCategory) $p->categories()->attach($this->newCategory);
        // a simple product is sold / purchased / scanned through its one variant (SKU = product code)
        if ($this->newType === 'simple') $p->variants()->create(['sku' => $code]);
        ops_log('Created product '.$p->name, $p);
        return $this->redirectRoute('ops.products.edit', ['product' => $p, 'tab' => 'general'], navigate: true);
    }

    public function bulk(string $status): void
    {
        Product::whereIn('id', $this->selected)->update(['status' => $status]); static::flushInv();
        ops_log(count($this->selected).' products → '.$status); $this->toast(count($this->selected).' products · '.ucfirst($status)); $this->selected = [];
    }

    /** ⋯ row menu */
    public function setStatus(int $id, string $status): void
    {
        abort_unless(array_key_exists($status, config('ops.product_status')), 422);
        $p = Product::findOrFail($id); $p->update(['status' => $status]);
        ops_log($p->name.' → '.$status, $p); $this->toast($p->name.' · '.ucfirst($status));
    }

    /** copy as a draft: details, categories, suppliers and variants (no stock, new SKUs / codes) */
    public function duplicate(int $id)
    {
        $src = Product::with('variants', 'categories', 'suppliers')->findOrFail($id);
        $code = DB::transaction(function () use ($src) {
            do { $code = explode('-', $src->code)[0].'-'.random_int(100, 999); } while (Product::withTrashed()->where('code', $code)->exists());
            $p = $src->replicate(['created_at', 'updated_at'])->fill(['name' => $src->name.' (copy)', 'code' => $code, 'slug' => str($src->name)->slug().'-'.substr(uniqid(), -5), 'status' => 'draft']);
            $p->save();
            $p->categories()->sync($src->categories->pluck('id'));
            $p->suppliers()->sync($src->suppliers->mapWithKeys(fn ($s) => [$s->id => ['supplier_code' => $s->pivot->supplier_code, 'supplier_name' => $s->pivot->supplier_name, 'image' => $s->pivot->image]]));
            foreach ($src->variants as $v) {
                $sku = str_starts_with($v->sku, $src->code) ? $code.substr($v->sku, strlen($src->code)) : $code.'-'.$v->id;
                $p->variants()->create(collect($v->replicate()->getAttributes())->except(['product_id', 'created_at', 'updated_at'])->merge(['sku' => $sku, 'barcode' => null, 'stock' => 0, 'reserved' => 0, 'damaged' => 0, 'options' => $v->options])->all());
            }
            ops_log('Duplicated '.$src->name.' → '.$code, $p);
            return $p->id;
        });
        $this->toast('Duplicated as draft');
        return $this->redirectRoute('ops.products.edit', ['product' => $code], navigate: true);
    }

    /** only a product that was never sold, bought or stocked can be deleted — otherwise archive it */
    public function delete(int $id): void
    {
        $p = Product::withCount('variants')->findOrFail($id);
        $vids = $p->variants()->pluck('id');
        if (DB::table('ops_order_items')->whereIn('variant_id', $vids)->exists() || DB::table('ops_batches')->whereIn('variant_id', $vids)->exists() || DB::table('ops_purchase_order_items')->whereIn('variant_id', $vids)->exists()) {
            $this->toast($p->name.' has orders / batches — archive it instead', 'bad'); return;
        }
        $p->delete(); ops_log('Deleted product '.$p->name); $this->toast($p->name.' deleted');
    }

    public function export()
    {
        $rows = $this->applyStatus($this->base(), $this->status)->when($this->selected, fn ($q) => $q->whereIn('ops_products.id', $this->selected))->get();
        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w'); fputcsv($f, ['Code', 'Name', 'Type', 'Categories', 'Price', 'Sale price', 'Variants', 'Stock', 'Status']);
            foreach ($rows as $p) fputcsv($f, [$p->code, $p->name, $p->type, $p->categories->pluck('name')->join(', '), $p->price, $p->sale_price, $p->variants_count, (int) $p->stock, $p->status]);
            fclose($f);
        }, 'products-'.now()->format('Ymd-His').'.csv');
    }

    public function render() { return view('livewire.ops.products.index'); }
}
