<?php

namespace App\Livewire\Admin\Orders;

use App\Models\Order;
use App\Services\BatchAllocator;
use Livewire\Component;

/** Reusable ⋯ menu for any order list (By date, Campaign, In courier). Lazy-loaded → no cost until visible. */
class RowActions extends Component
{
    public Order $order;
    public function placeholder() { return '<span class="inline-block h-7 w-7 rounded border border-line"></span>'; }
    public function setStatus(int $id, string $s): void
    {
        if ($s === 'confirmed') app(BatchAllocator::class)->allocate($this->order);
        $this->order->update(['status' => $s]); $this->order->log('status', 'Status → '.$s);
        $this->dispatch('toast', message: $this->order->number.' · '.config("admin.order_status.$s.0"));
    }
    public function render() { return view('livewire.admin.orders.partials.row-menu', ['o' => $this->order]); }
}
