<?php

namespace App\Livewire\Ops\Orders;

use App\Models\Ops\OrderItem;
use App\Livewire\Ops\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Orders & Packing', 'title' => 'Ordered products', 'tabs' => 'orders'])]
#[Title('Ordered products')]
class OrderedProducts extends Component
{
    use WithTable;
    #[Computed]
    public function rows()
    {
        $q = OrderItem::query()->join('ops_orders as orders', 'orders.id', '=', 'ops_order_items.order_id')->where('orders.is_draft', false)
            ->join('ops_product_variants as v', 'v.id', '=', 'ops_order_items.variant_id')->join('ops_products as p', 'p.id', '=', 'v.product_id')
            ->when($this->status !== 'all', fn ($q) => $q->where('orders.status', $this->status))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('p.name', 'like', "%$s%")->orWhere('v.sku', 'like', "%$s%")))
            ->selectRaw("v.id, p.name, v.color, v.size, v.sku, v.stock, SUM(ops_order_items.qty) units, COUNT(DISTINCT orders.id) orders, SUM(CASE WHEN orders.exchange_of_id IS NULL THEN ops_order_items.qty*ops_order_items.unit_price ELSE 0 END) revenue, SUM(ops_order_items.returned_qty) returned, SUM(CASE WHEN orders.status IN ('pending','confirmed') THEN ops_order_items.qty ELSE 0 END) to_pack")
            ->groupBy('v.id', 'p.name', 'v.color', 'v.size', 'v.sku', 'v.stock')->orderByDesc('units');
        return $this->dateRange($q, 'orders.placed_at')->paginate($this->perPage);
    }
    public function render() { return view('livewire.ops.orders.ordered-products'); }
}
