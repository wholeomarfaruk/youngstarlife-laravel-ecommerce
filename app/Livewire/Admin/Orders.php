<?php

namespace App\Livewire\Admin;

use App\Models\AutoSaveOrder;
use App\Models\Order;
use Illuminate\Http\Request;
use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination;
    public $search = '';
    public $order_status;
    public $daterange;
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function mount(Request $request)
    {
        $this->search = request()->query('search', '');
        $this->order_status = request()->query('order_status', '');

        $pendingOrders = Order::where('status', 'pending')
            ->with('Order_Item')
            ->get();
        if ($pendingOrders->count() > 0) {
            foreach ($pendingOrders as $order) {
                $productIds = $order->Order_Item->pluck('product_id');

                AutoSaveOrder::where('phone', $order->phone)
                    ->whereHas('items', function ($q) use ($productIds) {
                        $q->whereIn('product_id', $productIds);
                    })
                    ->delete();
            }
        }
    }
    public function render()
    {


        $search = $this->search;
        $orders = Order::query()
            ->where('status', '!=', 'deleted')
            ->when($this->order_status, function ($query) {
                $query->where('status', $this->order_status);
            })
            ->when($this->search, function ($query) {
                $search = $this->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('id', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('consignment_id', 'like', '%' . $search . '%');
                });
            })
            ->when($this->daterange, function ($query) {
                $dates = explode(' to ', $this->daterange);

                if (count($dates) === 2) {
                    // range selected
                    $startDate = \Carbon\Carbon::parse(trim($dates[0]))->startOfDay();
                    $endDate = \Carbon\Carbon::parse(trim($dates[1]))->endOfDay();

                    $query->whereBetween('created_at', [$startDate, $endDate]);

                } elseif (count($dates) === 1) {
                    // single date selected
                    $date = \Carbon\Carbon::parse(trim($dates[0]));

                    $query->whereDate('created_at', $date);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);


        $status_group = Order::whereNot('status', 'deleted')->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();

        //auto save order delete

        return view('livewire.admin.orders', compact('orders', 'status_group', 'orders_count'));
    }
}
