<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Models\Order_Item;
use App\Models\products;
use Carbon\Carbon;
use Livewire\Component;

class OrderProducts extends Component
{
    public $start_date;
    public $end_date;
    public $order_status = '';
    public $product_search = '';
    public $order_search = '';
    public $split_size = true;

    protected $queryString = [
        'start_date' => ['except' => ''],
        'end_date' => ['except' => ''],
        'order_status' => ['except' => ''],
        'product_search' => ['except' => ''],
        'order_search' => ['except' => ''],
    ];

    public function mount()
    {
        $today = now()->toDateString();
        $this->start_date = $this->start_date ?: $today;
        $this->end_date = $this->end_date ?: $today;
    }

    public function setRange($range)
    {
        $today = now();
        [$start, $end] = match ($range) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'last7' => [$today->copy()->subDays(6), $today],
            'last30' => [$today->copy()->subDays(29), $today],
            'this_month' => [$today->copy()->startOfMonth(), $today],
            default => [$today, $today],
        };
        $this->start_date = $start->toDateString();
        $this->end_date = $end->toDateString();
    }

    public function resetFilters()
    {
        $this->reset(['order_status', 'product_search', 'order_search']);
        $this->setRange('today');
    }

    public function render()
    {
        $start = $this->parseDate($this->start_date) ?? now()->startOfDay();
        $end = $this->parseDate($this->end_date) ?? now();
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $items = Order_Item::query()
            ->join('orders', 'orders.id', '=', 'order__items.order_id')
            ->join('products', 'products.id', '=', 'order__items.product_id')
            ->where('orders.status', '!=', 'deleted')
            ->whereBetween('orders.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when($this->order_status, fn($q) => $q->where('orders.status', $this->order_status))
            ->when(trim($this->product_search) !== '', function ($q) {
                $search = trim($this->product_search);
                $q->where(function ($q) use ($search) {
                    $q->where('products.name', 'like', "%{$search}%")
                        ->orWhere('products.sku', 'like', "%{$search}%")
                        ->orWhere('products.id', $search);
                });
            })
            ->when(trim($this->order_search) !== '', function ($q) {
                // "#123" = order number only; phone matching needs 6+ chars so short order numbers don't hit every phone
                $raw = trim($this->order_search);
                $search = ltrim($raw, '#');
                $q->where(function ($q) use ($raw, $search) {
                    $q->where('orders.id', $search);
                    if (!str_starts_with($raw, '#') && strlen($search) >= 6) {
                        $q->orWhere('orders.phone', 'like', "%{$search}%");
                    }
                });
            })
            ->orderBy('orders.created_at', 'desc')
            ->get([
                'order__items.order_id',
                'order__items.product_id',
                'order__items.quantity',
                'order__items.returned_quantity',
                'order__items.price',
                'order__items.options',
                'orders.created_at as order_date',
                'orders.name as customer_name',
                'orders.phone',
                'orders.status as order_status',
                'products.name as product_name',
                'products.sku',
            ]);

        $days = [];
        $productTotals = [];
        foreach ($items as $item) {
            $date = Carbon::parse($item->order_date)->toDateString();
            $size = $this->split_size ? ($item->options['size'] ?? null) : null;
            $key = $item->product_id . '|' . $size;

            $days[$date]['rows'][$key] = $this->addToRow($days[$date]['rows'][$key] ?? null, $item, $size);
            $productTotals[$key] = $this->addToRow($productTotals[$key] ?? null, $item, $size);

            $days[$date]['qty'] = ($days[$date]['qty'] ?? 0) + $item->quantity;
            $days[$date]['order_ids'][$item->order_id] = true;
        }

        $byQty = fn($a, $b) => $b['qty'] <=> $a['qty'];
        foreach ($days as &$day) {
            uasort($day['rows'], $byQty);
        }
        unset($day);
        krsort($days);
        uasort($productTotals, $byQty);

        $productImages = products::with('media')
            ->whereIn('id', $items->pluck('product_id')->unique())
            ->get()
            ->mapWithKeys(fn($p) => [$p->id => $p->featured_image]);

        $summary = [
            'qty' => $items->sum('quantity'),
            'orders' => $items->pluck('order_id')->unique()->count(),
            'products' => $items->pluck('product_id')->unique()->count(),
            'amount' => $items->sum(fn($i) => $i->quantity * $i->price),
        ];

        $status_group = Order::whereNot('status', 'deleted')
            ->select('status')
            ->groupBy('status')
            ->pluck('status');

        return view('livewire.admin.order-products', compact(
            'days', 'productTotals', 'productImages', 'summary', 'status_group'
        ));
    }

    private function addToRow($row, $item, $size)
    {
        $row ??= [
            'product_id' => $item->product_id,
            'name' => $item->product_name,
            'sku' => $item->sku,
            'size' => $size,
            'qty' => 0,
            'returned' => 0,
            'amount' => 0,
            'orders' => [],
        ];
        $row['qty'] += $item->quantity;
        $row['returned'] += (int) $item->returned_quantity;
        $row['amount'] += $item->quantity * $item->price;
        $row['orders'][] = [
            'id' => $item->order_id,
            'name' => $item->customer_name,
            'phone' => $item->phone,
            'status' => $item->order_status,
            'qty' => $item->quantity,
            'time' => Carbon::parse($item->order_date)->format('d M, h:i A'),
        ];

        return $row;
    }

    private function parseDate($value)
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
