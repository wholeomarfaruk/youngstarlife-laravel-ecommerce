<?php

namespace App\Livewire\Admin\Orders;

use App\Models\OrderItem;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Orders & Packing', 'title' => 'Ordered products', 'tabs' => 'orders'])]
#[Title('Ordered products')]
class OrderedProducts extends Component
{
    use WithTable;
    #[Computed]
    public function rows()
    {
        $q = OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.is_draft', false)
            ->join('product_variants as v', 'v.id', '=', 'order_items.variant_id')->join('products as p', 'p.id', '=', 'v.product_id')
            ->when($this->status !== 'all', fn ($q) => $q->where('orders.status', $this->status))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('p.name', 'like', "%$s%")->orWhere('v.sku', 'like', "%$s%")))
            ->selectRaw("v.id, p.name, v.color, v.size, v.sku, v.stock, SUM(order_items.qty) units, COUNT(DISTINCT orders.id) orders, SUM(order_items.qty*order_items.unit_price) revenue, SUM(order_items.returned_qty) returned, SUM(CASE WHEN orders.status IN ('pending','confirmed','processing') THEN order_items.qty ELSE 0 END) to_pack")
            ->groupBy('v.id', 'p.name', 'v.color', 'v.size', 'v.sku', 'v.stock')->orderByDesc('units');
        return $this->dateRange($q, 'orders.placed_at')->paginate($this->perPage);
    }
    public function render() { return view('livewire.admin.orders.ordered-products'); }
}
