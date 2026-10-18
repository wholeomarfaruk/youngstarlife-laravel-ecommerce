<?php

namespace App\Livewire\Ops\Storefront;

use App\Models\Ops\{Category, Product};
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Url};
use Livewire\Component;

/**
 * Ops → Storefront → Product order. Same idea as the old admin's "Manage products": drag rows or type a position, saved at once.
 *  - All products: ops_products.sort_order → order on Home and Shop (HomeController)
 *  - A category:   ops_category_product.sort_order → order on that category's page; products can be added / removed here too
 */
class ProductOrder extends Component
{
    #[Url(as: 'list')] public string $list = 'all';   // 'all' | category id
    public ?int $addId = null;

    public function updatedList(): void { $this->reset('addId'); unset($this->rows, $this->category, $this->addable); }

    #[Computed] public function category(): ?Category { return $this->list === 'all' ? null : Category::find((int) $this->list); }

    #[Computed] public function categories() { return Category::orderBy('name')->withCount('products')->get(['id', 'name']); }

    /** the list being ordered, in its current order */
    #[Computed]
    public function rows()
    {
        $cols = ['ops_products.id', 'name', 'code', 'slug', 'price', 'sale_price', 'status', 'featured_image', 'ops_products.sort_order'];
        if ($c = $this->category) return $c->products()->orderByPivot('sort_order')->orderBy('ops_products.sort_order')->orderByDesc('ops_products.id')->get($cols);
        return Product::storefront()->orderBy('sort_order')->orderByDesc('id')->get($cols);   // what Home / Shop show
    }

    /** products not in this category yet (add picker) */
    #[Computed]
    public function addable(): array
    {
        if (! $this->category) return [];
        return Product::where('status', '!=', 'archived')->whereNotIn('id', $this->rows->pluck('id'))->orderBy('name')->get(['id', 'name', 'code'])
            ->mapWithKeys(fn ($p) => [$p->id => $p->name.' · '.$p->code])->all();
    }

    /** drag & drop (sortableTree): [{id}, …] top to bottom */
    public function reorder(array $tree): void
    {
        $this->saveOrder(collect($tree)->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    /** typed position (1 = first) */
    public function moveTo(int $id, $position): void
    {
        $ids = $this->rows->pluck('id')->reject(fn ($x) => $x === $id)->values()->all();
        $pos = max(1, min(count($ids) + 1, (int) $position));
        array_splice($ids, $pos - 1, 0, [$id]);
        $this->saveOrder($ids);
    }

    public function updatedAddId($id): void
    {
        $c = $this->category;
        if (! $c || ! $id || ! Product::whereKey($id)->exists()) return;
        $c->products()->syncWithoutDetaching([(int) $id => ['sort_order' => (int) $c->products()->max('ops_category_product.sort_order') + 1]]);   // added at the end
        $name = Product::whereKey($id)->value('name');
        ops_log("Added $name to {$c->name}");
        $this->reset('addId'); unset($this->rows, $this->addable, $this->categories);
        $this->dispatch('toast', message: "$name added to {$c->name}");
    }

    public function remove(int $id): void
    {
        $c = $this->category; if (! $c) return;
        $c->products()->detach($id);
        ops_log('Removed '.Product::whereKey($id)->value('name')." from {$c->name}");
        unset($this->rows, $this->addable, $this->categories);
        $this->dispatch('toast', message: 'Removed from '.$c->name);
    }

    /** 1, 2, 3… in the given order; ids that are not in this list are ignored */
    protected function saveOrder(array $ids): void
    {
        $own = $this->rows->pluck('id')->all();
        $ids = array_values(array_intersect(array_unique($ids), $own));
        $ids = array_merge($ids, array_values(array_diff($own, $ids)));   // anything the browser didn't send keeps its place at the end
        DB::transaction(function () use ($ids) {
            foreach ($ids as $i => $id) {
                $this->category
                    ? DB::table('ops_category_product')->where('category_id', $this->category->id)->where('product_id', $id)->update(['sort_order' => $i + 1])
                    : Product::whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });
        unset($this->rows);
        $this->dispatch('toast', message: 'Order saved');
    }

    public function render() { return view('livewire.ops.storefront.product-order'); }
}
