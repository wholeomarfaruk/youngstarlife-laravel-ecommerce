<?php

namespace App\Livewire\Admin\Orders;

use App\Models\{Customer, DeliveryZone, Order, ProductVariant};
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Orders & Packing', 'title' => 'Drafts', 'tabs' => 'orders'])]
#[Title('Drafts')]
class Drafts extends Component
{
    use WithTable;
    public ?int $editing = null;
    public array $d = [];          // name, phone, zone, address, discount, advance, note, lines[[variant_id, qty]]
    public ?int $pick = null;

    #[Computed] public function drafts() { return Order::drafts()->with('customer:id,name,phone', 'items.variant.product:id,name')->when($this->search, fn ($q, $s) => $q->where('ship_name', 'like', "%$s%")->orWhere('ship_phone', 'like', "%$s%"))->latest('updated_at')->paginate($this->perPage); }
    #[Computed] public function zones() { return cache()->remember('zones', 600, fn () => DeliveryZone::where('is_active', true)->get(['id', 'name', 'charge'])); }
    #[Computed] public function variants() { return ProductVariant::with('product:id,name,price,sale_price')->where('is_active', true)->get(); }

    public function newDraft(): void { $this->edit(null); }

    public function edit(?int $id): void
    {
        $o = $id ? Order::with('items')->find($id) : null; $this->editing = $id ?? 0;
        $this->d = ['name' => $o?->ship_name, 'phone' => $o?->ship_phone, 'zone' => $o?->delivery_zone_id, 'address' => $o?->ship_address, 'discount' => $o?->discount ?? 0, 'advance' => $o?->advance ?? 0, 'note' => $o?->note,
            'lines' => $o?->items->map(fn ($i) => ['variant_id' => $i->variant_id, 'qty' => $i->qty])->all() ?? []];
    }

    /** Auto-save: every field change persists the draft (wire:model.live.debounce). */
    public function updatedD(): void { $this->persist(); }

    public function addLine(): void { if ($this->pick) { $this->d['lines'][] = ['variant_id' => $this->pick, 'qty' => 1]; $this->pick = null; $this->persist(); } }

    protected function persist(bool $live = false): Order
    {
        $lines = collect($this->d['lines'] ?? [])->filter(fn ($l) => $l['variant_id']);
        $sub = $lines->sum(fn ($l) => $this->variants->firstWhere('id', $l['variant_id'])?->effective_price * $l['qty']);
        $charge = $this->zones->firstWhere('id', $this->d['zone'])?->charge ?? 0; $total = max(0, $sub - (int) $this->d['discount'] + $charge);
        $o = Order::updateOrCreate(['id' => $this->editing ?: null], [
            'number' => $this->editing ? Order::find($this->editing)->number : 'D-'.(Order::max('id') + 2001), 'is_draft' => ! $live,
            'customer_id' => Customer::firstOrCreate(['phone' => $this->d['phone'] ?: 'draft-'.uniqid()], ['name' => $this->d['name'] ?: 'Unnamed'])->id,
            'ship_name' => (string) $this->d['name'], 'ship_phone' => (string) $this->d['phone'], 'ship_address' => (string) $this->d['address'], 'delivery_zone_id' => $this->d['zone'] ?: null,
            'subtotal' => $sub, 'discount' => (int) $this->d['discount'], 'delivery_charge' => $charge, 'total' => $total, 'advance' => (int) $this->d['advance'], 'cod' => max(0, $total - (int) $this->d['advance']),
            'note' => $this->d['note'], 'placed_at' => now(), 'created_by' => auth()->id(),
        ]);
        $o->items()->delete();
        foreach ($lines as $l) $o->items()->create(['variant_id' => $l['variant_id'], 'qty' => $l['qty'], 'unit_price' => $this->variants->firstWhere('id', $l['variant_id'])->effective_price]);
        $this->editing = $o->id;
        return $o;
    }

    public function convert()
    {
        $this->validate(['d.name' => 'required', 'd.phone' => ['required', 'regex:/^01\d{9}$/'], 'd.zone' => 'required', 'd.address' => 'required|min:6', 'd.lines' => 'required|array|min:1']);
        $o = $this->persist(true); $o->update(['number' => '#'.(Order::live()->max('id') + 1001), 'status' => 'pending']); $o->log('created', 'Created from draft');
        cache()->forget('drafts.count');
        return $this->redirectRoute('admin.orders.show', $o, navigate: true);
    }

    public function discard(int $id): void { Order::drafts()->whereKey($id)->delete(); cache()->forget('drafts.count'); $this->editing = null; }

    public function render() { return view('livewire.admin.orders.drafts'); }
}
