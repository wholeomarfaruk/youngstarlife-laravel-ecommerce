<?php

namespace App\Livewire\Ops\Orders;

use App\Models\Ops\{Customer, DeliveryZone, Order, ProductVariant};
use App\Livewire\Ops\Concerns\WithTable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Orders & Packing', 'title' => 'Drafts', 'tabs' => 'orders'])]
#[Title('Drafts')]
class Drafts extends Component
{
    use WithTable;
    public ?int $editing = null;
    public array $d = [];          // name, phone, zone, address, discount, advance, note, lines[[variant_id, qty]]
    public ?int $pick = null;

    #[Computed] public function drafts() { return Order::drafts()->with('customer:id,name,phone', 'items.variant.product:id,name')->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('ship_name', 'like', "%$s%")->orWhere('ship_phone', 'like', "%$s%")))->latest('updated_at')->paginate($this->perPage); }
    #[Computed] public function zones() { return cache()->remember('zones', 600, fn () => DeliveryZone::where('is_active', true)->get(['id', 'name', 'charge'])); }
    #[Computed] public function variants() { return ProductVariant::with('product:id,name,price,sale_price,featured_image')->where('is_active', true)->get(); }

    /** ?new=1 (Dashboard "New order") opens an empty editor · ?edit=ID (Duplicate) opens that draft */
    public function mount(): void
    {
        if (request()->boolean('new')) {
            $this->newDraft();
            if ($phone = request()->query('phone')) {   // from Customers → New order
                $c = Customer::real()->where('phone', $phone)->first();
                $this->d['phone'] = $phone; $this->d['name'] = $c?->name ?? (string) request()->query('name'); $this->d['address'] = (string) $c?->address;
            }
        }
        elseif ($id = (int) request()->query('edit')) $this->edit($id);
    }

    public function newDraft(): void { $this->edit(null); }

    public function edit(?int $id): void
    {
        $o = $id ? Order::with('items')->find($id) : null; $this->editing = $id ?? 0;
        $this->d = ['name' => $o?->ship_name, 'phone' => $o?->ship_phone, 'zone' => $o?->delivery_zone_id, 'address' => $o?->ship_address, 'discount' => $o?->discount ?? 0, 'advance' => $o?->advance ?? 0, 'note' => $o?->note,
            'lines' => $o?->items->map(fn ($i) => ['variant_id' => $i->variant_id, 'qty' => $i->qty])->all() ?? []];
    }

    /** Auto-save: every field change persists the draft (wire:model.live.debounce). */
    public function updatedD(): void { $this->persist(); }

    public function removeLine(int $k): void { unset($this->d['lines'][$k]); $this->d['lines'] = array_values($this->d['lines']); $this->persist(); }

    public function addLine(): void { if ($this->pick) { $this->d['lines'][] = ['variant_id' => $this->pick, 'qty' => 1]; $this->pick = null; $this->persist(); } }

    protected function persist(bool $live = false): Order
    {
        // qty 0 removes a line; blank qty while typing counts as 1
        $lines = collect($this->d['lines'] ?? [])->filter(fn ($l) => $l['variant_id'] && $l['qty'] !== '0' && $l['qty'] !== 0)->map(fn ($l) => ['variant_id' => (int) $l['variant_id'], 'qty' => max(1, (int) $l['qty'])]);
        $this->d['lines'] = $lines->values()->all();
        $sub = $lines->sum(fn ($l) => ($this->variants->firstWhere('id', $l['variant_id'])?->effective_price ?? 0) * $l['qty']);
        $charge = $this->zones->firstWhere('id', $this->d['zone'])?->charge ?? 0; $total = max(0, $sub - (int) $this->d['discount'] + $charge);
        $existing = $this->editing ? Order::with('customer')->find($this->editing) : null;
        $o = Order::updateOrCreate(['id' => $existing?->id], [
            'number' => $existing?->number ?? 'D-'.(Order::max('id') + 2001), 'is_draft' => ! $live,
            'customer_id' => $this->draftCustomer($existing)->id,
            'ship_name' => (string) $this->d['name'], 'ship_phone' => (string) $this->d['phone'], 'ship_address' => (string) $this->d['address'], 'delivery_zone_id' => $this->d['zone'] ?: null,
            'subtotal' => $sub, 'discount' => (int) $this->d['discount'], 'delivery_charge' => $charge, 'total' => $total, 'advance' => (int) $this->d['advance'], 'cod' => max(0, $total - (int) $this->d['advance']),
            'note' => $this->d['note'], 'placed_at' => now(), 'created_by' => auth()->id(),
        ]);
        if ($existing && $existing->customer_id !== $o->customer_id) $this->dropPlaceholder($existing->customer);
        $o->items()->delete();
        foreach ($lines as $l) $o->items()->create(['variant_id' => $l['variant_id'], 'qty' => $l['qty'], 'unit_price' => $this->variants->firstWhere('id', $l['variant_id'])->effective_price]);
        if (! $existing) cache()->forget('drafts.count');
        $this->editing = $o->id;
        return $o;
    }

    /** Real customer once the phone is a valid BD number; until then one placeholder per draft (not one per keystroke). */
    protected function draftCustomer(?Order $existing): Customer
    {
        $phone = trim((string) $this->d['phone']);
        if (preg_match('/^01\d{9}$/', $phone)) return Customer::firstOrCreate(['phone' => $phone], ['name' => $this->d['name'] ?: 'Unnamed']);
        if ($existing && str_starts_with((string) $existing->customer?->phone, 'draft-')) return $existing->customer;
        return Customer::create(['phone' => 'draft-'.uniqid(), 'name' => $this->d['name'] ?: 'Unnamed']);
    }

    protected function dropPlaceholder(?Customer $c): void
    {
        if ($c && str_starts_with($c->phone, 'draft-') && ! $c->orders()->exists()) $c->delete();
    }

    public function convert()
    {
        $this->validate(['d.name' => 'required', 'd.phone' => ['required', 'regex:/^01\d{9}$/'], 'd.zone' => 'required', 'd.address' => 'required|min:6', 'd.lines' => 'required|array|min:1'],
            ['d.phone.regex' => 'Enter an 11-digit number starting with 01.', 'd.lines.required' => 'Add at least one product.']);
        $draft = $this->editing ? Order::find($this->editing) : null;   // abandoned website checkouts keep their device + IP
        if ($e = app(\App\Services\Ops\Blacklist::class)->blocking('draft', ['phone' => $this->d['phone'], 'name' => $this->d['name'], 'device_id' => $draft?->device_id, 'ip' => $draft?->ip_address])) {
            $this->addError('d.phone', 'This customer is blacklisted ('.config('ops.blacklist_reasons.'.$e->reason, 'blocked').') — remove them in Users → Blacklist to create the order.');
            return;
        }
        $o = DB::transaction(function () {
            $o = $this->persist(true);
            $o->update(['number' => Order::nextNumber(), 'status' => 'pending']); $o->log('created', 'Created from draft');
            $c = $o->customer;
            $c->update(['name' => $c->name === 'Unnamed' || ! $c->orders_count ? $this->d['name'] : $c->name, 'address' => $c->address ?: $this->d['address'],
                'area' => $c->area ?: $this->zones->firstWhere('id', $this->d['zone'])?->name, 'orders_count' => $c->orders_count + 1,
                'total_spent' => $c->total_spent + $o->total, 'last_order_at' => now()]);
            return $o;
        });
        cache()->forget('drafts.count');
        app(\App\Services\Ops\OrderWorkflow::class)->placed($o->fresh());   // automations: risky flag, courier, auto-confirm, notify
        ops_log('Created order '.$o->number.' from draft', $o);
        return $this->redirectRoute('ops.orders.show', $o, navigate: true);
    }

    public function discard(int $id): void
    {
        if ($o = Order::drafts()->with('customer')->find($id)) { $c = $o->customer; $o->delete(); $this->dropPlaceholder($c); }
        cache()->forget('drafts.count'); $this->editing = null;
    }

    public function render() { return view('livewire.ops.orders.drafts'); }
}
