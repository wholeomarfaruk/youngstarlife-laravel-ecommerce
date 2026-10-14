<?php

namespace App\Livewire\Ops\Orders;

use App\Models\Ops\Order;
use App\Livewire\Ops\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, On, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Orders & Packing', 'title' => 'By date', 'tabs' => 'orders'])]
#[Title('By date')]
class ByDate extends Component
{
    use WithTable;
    #[Url] public ?string $day = null;

    /** A row's ⋯ menu (RowActions) changed an order → re-render so badges and day totals are current. */
    #[On('ops-order-updated')]
    public function refreshList(): void { unset($this->groups, $this->orders); }

    #[Computed]
    public function groups()
    {
        $q = Order::live()->whereNull('exchange_of_id')->selectRaw("DATE(placed_at) d, COUNT(*) n, SUM(total) v, SUM(status='delivered') delivered, SUM(status IN ('returning','returned')) returned, SUM(status='cancelled') cancelled, SUM(cod) cod")->groupBy('d')->orderByDesc('d');
        return $this->dateRange($q, 'placed_at')->limit(90)->get();
    }

    public function currentDay(): ?string { return $this->day ?? $this->groups->first()?->d; }

    #[Computed]
    public function orders()
    {
        return Order::live()->whereNull('exchange_of_id')->whereDate('placed_at', $this->currentDay())->with('customer:id,name,phone,area', 'shipment.courier:id,name')
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', '%'.ltrim($s, '#').'%')->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))))
            ->latest('placed_at')->get();
    }

    public function render() { return view('livewire.ops.orders.by-date', ['summary' => $this->groups->firstWhere('d', $this->currentDay())]); }
}
