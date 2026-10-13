<?php

namespace App\Livewire\Admin\Reports;

use App\Models\{Order, OrderItem, Shipment};
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Reports', 'title' => 'Reports'])]
#[Title('Reports')]
class Index extends Component
{
    use WithTable;
    #[Url] public string $report = 'sales_by_day'; // sales_by_day | sales_by_product | courier_performance | profit
    public function mount(): void { $this->from = $this->from ?: now()->subDays(29)->toDateString(); $this->to = $this->to ?: today()->toDateString(); }

    #[Computed]
    public function rows()
    {
        $o = fn () => $this->dateRange(Order::live(), 'placed_at');
        return match ($this->report) {
            'sales_by_product' => OrderItem::joinSub($o()->select('id'), 'o', 'o.id', '=', 'order_items.order_id')->join('product_variants as v', 'v.id', 'order_items.variant_id')->join('products as p', 'p.id', 'v.product_id')->selectRaw('p.name label, SUM(order_items.qty) a, SUM(order_items.qty*order_items.unit_price) b')->groupBy('p.name')->orderByDesc('b')->get(),
            'courier_performance' => Shipment::join('couriers as c', 'c.id', 'shipments.courier_id')->whereBetween('shipments.created_at', [$this->from, $this->to.' 23:59:59'])->selectRaw("c.name label, SUM(courier_status='delivered') a, SUM(courier_status IN ('returned','returning')) b")->groupBy('c.name')->get(),
            'profit' => $o()->where('status', 'delivered')->selectRaw('DATE(placed_at) label, SUM(total) a, SUM(total - cost - delivery_charge) b')->groupBy('label')->orderBy('label')->get(),
            default => $o()->selectRaw('DATE(placed_at) label, COUNT(*) a, SUM(total) b')->groupBy('label')->orderBy('label')->get(),
        };
    }
    public function render() { return view('livewire.admin.reports.index'); }
}
