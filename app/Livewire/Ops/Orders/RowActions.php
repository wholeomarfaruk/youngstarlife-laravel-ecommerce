<?php

namespace App\Livewire\Ops\Orders;

use App\Livewire\Ops\Concerns\OrderActions;
use App\Models\Ops\Order;
use Livewire\Component;

/** Reusable ⋯ menu for any order list (By date, Campaign, In courier). Lazy-loaded → no cost until visible. */
class RowActions extends Component
{
    use OrderActions;

    public Order $order;
    public function placeholder() { return '<span class="inline-block h-7 w-7 rounded border border-line"></span>'; }
    public function hydrate(): void { $this->order->refresh(); }   // status changes elsewhere → menu stays current
    public function render() { return view('livewire.ops.orders.partials.row-menu', ['o' => $this->order->loadMissing('shipment:id,order_id,tracking,courier_status,handed_over_at', 'customer:id,phone')]); }
}
