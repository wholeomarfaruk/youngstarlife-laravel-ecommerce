<?php

namespace App\Livewire\Admin\Orders;

use App\Models\Order;
use App\Services\BatchAllocator;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Orders & Packing', 'title' => 'All orders', 'tabs' => 'orders'])]
#[Title('Orders')]
class Index extends Component
{
    use WithTable;

    #[Computed]
    public function counts(): array
    {
        return $this->baseQuery(false)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all();
    }

    protected function baseQuery(bool $withStatus = true)
    {
        $q = Order::live()->select(['id', 'number', 'customer_id', 'source', 'status', 'payment_status', 'total', 'cod', 'advance', 'placed_at', 'tags']);
        $this->dateRange($q, 'placed_at');
        if ($s = trim($this->search)) {
            $s = ltrim($s, '#');
            $q->where(fn ($q) => $q->where('number', 'like', "%$s%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))->orWhereHas('shipment', fn ($c) => $c->where('tracking', $s)));
        }
        foreach (['source', 'payment_status'] as $f) if ($v = $this->filters[$f] ?? null) $q->where($f, $v);
        if ($withStatus && $this->status !== 'all') $q->where('status', $this->status);
        return $q;
    }

    #[Computed]
    public function orders()
    {
        return $this->baseQuery()->with(['customer:id,name,phone,area,delivered_count,returned_count', 'items:id,order_id,variant_id,qty', 'items.variant:id,product_id,color,size', 'items.variant.product:id,name', 'shipment:id,order_id,courier_id,tracking,courier_status', 'shipment.courier:id,name'])
            ->orderBy($this->sortField === 'id' ? 'placed_at' : $this->sortField, $this->sortDir)->paginate($this->perPage);
    }

    public function setStatus(int $id, string $status): void
    {
        $o = Order::findOrFail($id);
        if ($status === 'confirmed') app(BatchAllocator::class)->allocate($o);
        $o->update(['status' => $status]); $o->log('status', 'Status → '.$status);
        $this->toast("Order {$o->number} · ".config("admin.order_status.$status.0"));
    }

    public function bulkStatus(string $status): void { foreach ($this->selected as $id) $this->setStatus((int) $id, $status); $this->selected = []; }

    public function render() { return view('livewire.admin.orders.index'); }
}
