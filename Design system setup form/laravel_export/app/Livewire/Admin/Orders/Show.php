<?php

namespace App\Livewire\Admin\Orders;

use App\Models\Order;
use App\Services\BatchAllocator;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Orders & Packing', 'title' => 'Order details', 'tabs' => 'orders'])]
class Show extends Component
{
    public Order $order;
    public string $note = '';

    public function mount(Order $order): void
    {
        $this->order = $order->load(['customer', 'zone', 'items.variant.product:id,name', 'items.allocations.batch:id,code,supplier_id,purchase_order_id', 'items.allocations.batch.supplier:id,name', 'shipment.courier', 'events.user:id,name']);
    }

    public function advance(): void
    {
        $next = ['pending' => 'confirmed', 'confirmed' => 'processing', 'packed' => 'shipped'][$this->order->status] ?? null;
        if (! $next) return;
        if ($next === 'confirmed') app(BatchAllocator::class)->allocate($this->order);
        $this->order->update(['status' => $next]); $this->order->log('status', 'Status → '.$next);
        $this->mount($this->order->fresh());
        $this->dispatch('toast', message: 'Order '.$this->order->number.' · '.config("admin.order_status.$next.0"));
    }

    public function addNote(): void
    {
        $this->validate(['note' => 'required|string|max:500']);
        $this->order->log('note', $this->note); $this->note = ''; $this->mount($this->order->fresh());
    }

    public function render() { return view('livewire.admin.orders.show')->title('Order '.$this->order->number); }
}
