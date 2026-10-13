<?php

namespace App\Livewire\Admin\Orders;

use App\Models\{Campaign, Order};
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Orders & Packing', 'title' => 'Campaign summary', 'tabs' => 'orders'])]
#[Title('Campaign summary')]
class Campaigns extends Component
{
    use WithTable;
    #[Url] public string $platform = '';
    #[Url] public string $sort = 'orders';
    public array $open = [];

    #[Computed]
    public function campaigns()
    {
        $range = fn ($q) => $this->dateRange($q->where('is_draft', false), 'placed_at');
        return Campaign::query()->when($this->platform, fn ($q, $p) => $q->where('platform', $p))->when($this->search, fn ($q, $s) => $q->where('name', 'like', "%$s%"))
            ->withCount(['orders as n' => $range, 'orders as delivered' => fn ($q) => $range($q)->where('status', 'delivered'), 'orders as returned' => fn ($q) => $range($q)->whereIn('status', ['returning', 'returned'])])
            ->withSum(['orders as booked' => $range], 'total')->withSum(['orders as revenue' => fn ($q) => $range($q)->where('status', 'delivered')], 'total')
            ->get()->filter(fn ($c) => $c->n > 0)->sortByDesc(fn ($c) => match ($this->sort) { 'rev' => $c->revenue, 'roas' => $c->spend ? $c->revenue / $c->spend : 0, 'spend' => $c->spend, default => $c->n });
    }

    public function ordersFor(int $id) { return Order::live()->where('campaign_id', $id)->with('customer:id,name,area')->latest('placed_at')->limit(50)->get(); }
    public function toggle(int $id): void { $this->open[$id] = ! ($this->open[$id] ?? false); }
    public function render() { return view('livewire.admin.orders.campaigns'); }
}
