<?php

namespace App\Livewire\Admin\Orders;

use App\Models\Order;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Orders & Packing', 'title' => 'By date', 'tabs' => 'orders'])]
#[Title('By date')]
class ByDate extends Component
{
    use WithTable;
    #[Url] public ?string $day = null;

    #[Computed]
    public function groups()
    {
        $q = Order::live()->selectRaw("DATE(placed_at) d, COUNT(*) n, SUM(total) v, SUM(status='delivered') delivered, SUM(status IN ('returning','returned')) returned, SUM(status='cancelled') cancelled")->groupBy('d')->orderByDesc('d');
        return $this->dateRange($q, 'placed_at')->limit(90)->get();
    }

    #[Computed]
    public function orders()
    {
        $d = $this->day ?? $this->groups->first()?->d;
        return Order::live()->whereDate('placed_at', $d)->with('customer:id,name,phone,area', 'shipment.courier:id,name')
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', '%'.ltrim($s, '#').'%')->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))))
            ->latest('placed_at')->get();
    }

    public function render() { return view('livewire.admin.orders.by-date'); }
}
