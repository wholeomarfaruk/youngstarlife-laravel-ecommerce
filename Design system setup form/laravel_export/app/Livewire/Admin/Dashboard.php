<?php

namespace App\Livewire\Admin;

use App\Models\{Customer, Order, ProductVariant, Shipment, Supplier, SupplierReturn};
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Overview', 'title' => 'Dashboard'])]
#[Title('Dashboard')]
class Dashboard extends Component
{
    #[Url] public string $range = 'today'; // today | 7d | 30d

    protected function since() { return match ($this->range) { '7d' => now()->subDays(6)->startOfDay(), '30d' => now()->subDays(29)->startOfDay(), default => today() }; }

    /** One grouped query per widget, cached 60s → dashboard renders in a few ms. */
    #[Computed]
    public function kpis(): array
    {
        return cache()->remember("dash.kpis.$this->range", 60, function () {
            $o = Order::live()->where('placed_at', '>=', $this->since());
            return [
                'sales' => (clone $o)->sum('total'), 'orders' => (clone $o)->count(),
                'revenue' => (clone $o)->where('status', 'delivered')->sum('total'),
                'aov' => (int) (clone $o)->avg('total'),
                'cod_in_courier' => Shipment::whereNotIn('courier_status', ['delivered', 'returned', 'cancelled', 'lost'])->whereNull('settlement_id')->sum('cod_amount'),
                'supplier_due' => Supplier::sum('due'), 'customer_due' => Customer::sum('due'),
                'pending_supplier_returns' => SupplierReturn::whereNotIn('status', ['resolved', 'rejected', 'cancelled'])->count(),
            ];
        });
    }

    #[Computed]
    public function orderStatus(): array { return cache()->remember('dash.ost', 60, fn () => Order::live()->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all()); }

    #[Computed]
    public function courierStatus(): array { return cache()->remember('dash.cst', 60, fn () => Shipment::selectRaw('courier_status, count(*) n')->groupBy('courier_status')->pluck('n', 'courier_status')->all()); }

    #[Computed]
    public function chart(): array
    {
        return cache()->remember('dash.chart', 300, fn () => Order::live()->where('placed_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw("DATE(placed_at) d, SUM(CASE WHEN status='delivered' THEN total ELSE 0 END) delivered, SUM(CASE WHEN status NOT IN ('delivered','cancelled') THEN total ELSE 0 END) booked")
            ->groupBy('d')->orderBy('d')->get()->all());
    }

    #[Computed]
    public function recent() { return Order::live()->with(['customer:id,name,area', 'shipment:id,order_id,courier_status,courier_id', 'shipment.courier:id,name'])->latest('placed_at')->limit(8)->get(['id', 'number', 'customer_id', 'total', 'status', 'payment_status', 'placed_at']); }

    #[Computed]
    public function lowStock() { return ProductVariant::with('product:id,name,low_stock_threshold')->whereColumn('stock', '<', DB::raw('(select low_stock_threshold from products where products.id = product_variants.product_id)'))->orderBy('stock')->limit(6)->get(); }

    public function render() { return view('livewire.admin.dashboard'); }
}
