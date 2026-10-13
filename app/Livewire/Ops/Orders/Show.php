<?php

namespace App\Livewire\Ops\Orders;

use App\Models\Ops\{Account, DeliveryZone, Order, ProductVariant, Transaction};
use App\Services\Couriers\{CourierException, CourierGateway};
use App\Services\Ops\{BatchAllocator, OrderWorkflow};
use App\Livewire\Ops\Concerns\OrderActions;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Layout, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Orders & Packing', 'title' => 'Order details', 'tabs' => 'orders'])]
class Show extends Component
{
    use OrderActions;

    public Order $order;
    #[Url(except: false)] public bool $edit = false;
    public string $note = '';
    public string $tracking = ''; public string $parcelStatus = '';   // manual parcel update (when the courier has no webhook)
    public array $e = [];                                              // edit form
    public ?int $pick = null;
    public $payAmount = null; public string $payAccount = 'bKash';

    public function mount(Order $order): void
    {
        $this->order = $order->load(['customer', 'zone', 'campaign', 'creator:id,name', 'items.variant.product:id,name', 'items.allocations.batch:id,code,supplier_id,purchase_order_id', 'items.allocations.batch.supplier:id,name', 'items.allocations.batch.purchaseOrder:id,number', 'shipment.courier', 'events.user:id,name', 'returns:id,order_id,code,type']);
        $this->tracking = (string) $this->order->shipment?->tracking; $this->parcelStatus = (string) $this->order->shipment?->courier_status;
        if ($this->edit && ! $this->editable()) $this->edit = false;
        $o = $this->order;
        $this->e = ['name' => $o->ship_name, 'phone' => $o->ship_phone, 'address' => $o->ship_address, 'zone' => $o->delivery_zone_id, 'discount' => $o->discount, 'note' => $o->note,
            'lines' => $o->items->map(fn ($i) => ['id' => $i->id, 'variant_id' => $i->variant_id, 'label' => $i->variant->label, 'qty' => $i->qty, 'unit_price' => $i->unit_price])->all()];
    }

    /** live blacklist entries matching this order's phone / device / IP (warning banner) */
    #[Computed] public function blacklisted() { $o = $this->order; return app(\App\Services\Ops\Blacklist::class)->matches(['phone' => $o->ship_phone, 'device_id' => $o->device_id, 'ip' => $o->ip_address, 'email' => $o->customer?->email]); }

    public function editable(): bool { return in_array($this->order->status, ['pending', 'confirmed', 'processing', 'on_hold']); }
    #[Computed] public function zones() { return DeliveryZone::where('is_active', true)->get(['id', 'name', 'charge']); }
    #[Computed] public function variants() { return ProductVariant::with('product:id,name,price,sale_price,featured_image')->where('is_active', true)->get(); }

    public function advance(): void
    {
        $next = ['pending' => 'confirmed', 'confirmed' => 'processing', 'packed' => 'shipped'][$this->order->status] ?? null;
        if (! $next) return;
        app(OrderWorkflow::class)->setStatus($this->order, $next);
        $this->mount($this->order->fresh());
        $this->dispatch('toast', message: 'Order '.$this->order->number.' · '.config("ops.order_status.$next.0"));
    }

    public function addNote(): void
    {
        $this->validate(['note' => 'required|string|max:500']);
        $this->order->log('note', $this->note); $this->note = ''; $this->mount($this->order->fresh());
    }

    // ── edit order (before packing)
    public function addLine(): void { if ($this->pick && ($v = $this->variants->firstWhere('id', $this->pick))) { $this->e['lines'][] = ['id' => null, 'variant_id' => $v->id, 'label' => $v->label, 'qty' => 1, 'unit_price' => $v->effective_price]; $this->pick = null; } }
    public function removeLine(int $k): void { unset($this->e['lines'][$k]); $this->e['lines'] = array_values($this->e['lines']); }

    public function saveEdit(): void
    {
        abort_unless($this->editable(), 403);
        $this->validate(['e.name' => 'required|max:120', 'e.phone' => ['required', 'regex:/^01\d{9}$/'], 'e.address' => 'required|min:6', 'e.zone' => 'required|exists:ops_delivery_zones,id',
            'e.discount' => 'nullable|integer|min:0', 'e.lines' => 'required|array|min:1', 'e.lines.*.qty' => 'required|integer|min:1', 'e.lines.*.unit_price' => 'required|integer|min:0'],
            ['e.phone.regex' => 'Enter an 11-digit number starting with 01.', 'e.lines.required' => 'An order needs at least one product.']);
        DB::transaction(function () {
            $o = $this->order; $alloc = app(BatchAllocator::class); $hadStock = $o->items()->whereHas('allocations')->exists();
            if ($hadStock) $alloc->release($o);   // items may change → re-reserve from scratch
            $keep = [];
            foreach ($this->e['lines'] as $l) $keep[] = $o->items()->updateOrCreate(['id' => $l['id']], ['variant_id' => $l['variant_id'], 'qty' => (int) $l['qty'], 'unit_price' => (int) $l['unit_price']])->id;
            $o->items()->whereNotIn('id', $keep)->delete();
            $sub = collect($this->e['lines'])->sum(fn ($l) => (int) $l['qty'] * (int) $l['unit_price']);
            $charge = (int) ($this->zones->firstWhere('id', $this->e['zone'])?->charge ?? $o->delivery_charge);
            $total = max(0, $sub - (int) $this->e['discount'] + $charge);
            $o->update(['ship_name' => $this->e['name'], 'ship_phone' => $this->e['phone'], 'ship_address' => $this->e['address'], 'delivery_zone_id' => $this->e['zone'], 'note' => $this->e['note'] ?: null,
                'subtotal' => $sub, 'discount' => (int) $this->e['discount'], 'delivery_charge' => $charge, 'total' => $total, 'cod' => max(0, $total - $o->advance)]);
            if ($hadStock) $alloc->allocate($o->fresh());
            $o->log('edit', 'Order edited · total '.tk($total));
            ops_log("Edited order {$o->number}", $o);
        });
        $this->edit = false; $this->mount($this->order->fresh());
        $this->dispatch('toast', message: 'Order updated');
    }

    // ── payment
    public function recordPayment(): void
    {
        $this->validate(['payAmount' => 'required|integer|min:1|max:'.max(1, $this->order->cod), 'payAccount' => 'required|in:'.implode(',', config('ops.accounts'))], ['payAmount.max' => 'More than the COD left to collect.']);
        $o = $this->order;
        DB::transaction(function () use ($o) {
            Transaction::create(['account_id' => Account::where('name', $this->payAccount)->value('id'), 'direction' => 'in', 'category' => 'Sales', 'amount' => (int) $this->payAmount, 'description' => 'Advance · '.$o->number.' · '.$o->ship_name, 'date' => today(), 'ref_type' => $o->getMorphClass(), 'ref_id' => $o->id, 'user_id' => auth()->id()]);
            $advance = $o->advance + (int) $this->payAmount; $cod = max(0, $o->total - $advance);
            $o->update(['advance' => $advance, 'cod' => $cod, 'payment_status' => $cod === 0 ? 'paid' : 'partial']);
            $o->shipment?->update(['cod_amount' => $cod]);
            $o->log('payment', tk($this->payAmount).' received via '.$this->payAccount);
            ops_log("Payment ".tk($this->payAmount)." for {$o->number} via {$this->payAccount}", $o);
        });
        $this->reset('payAmount'); $this->dispatch('close-modal'); $this->mount($o->fresh()); $this->dispatch('toast', message: 'Payment recorded');
    }

    // ── Exchange (App\Services\Ops\ExchangeService)

    public string $missingNote = '';

    #[Computed] public function exchangeLine() { return $this->order->exchange_of_id ? app(\App\Services\Ops\ExchangeService::class)->line($this->order) : null; }
    #[Computed] public function exchangesOf() { return $this->order->exchanges()->with('exchangeLine.items.orderItem.variant.product:id,name', 'shipment:id,order_id,code,courier_status,delivered_at')->get(); }

    public function cancelExchange(): void
    {
        try { app(\App\Services\Ops\ExchangeService::class)->cancel($this->order, 'by '.auth()->user()->name); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->dispatch('toast', message: collect($e->errors())->flatten()->first(), type: 'bad'); return; }
        $this->mount($this->order->fresh()); unset($this->exchangeLine);
        $this->dispatch('toast', message: 'Exchange cancelled · new items released');
    }

    public function markOldMissing(): void
    {
        try { app(\App\Services\Ops\ExchangeService::class)->markOldMissing($this->order, $this->missingNote); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->setErrorBag($e->validator->errors()); return; }
        $this->reset('missingNote'); $this->dispatch('close-modal'); $this->mount($this->order->fresh()); unset($this->exchangeLine);
        $this->dispatch('toast', message: 'Old item marked not returned · courier claim');
    }

    // ── Courier API (App\Services\Couriers): send the parcel, sync its status, Pathao location

    public array $book = ['note' => '', 'weight' => 0.5, 'delivery_type' => 0, 'alt_phone' => '', 'city_id' => null, 'zone_id' => null, 'area_id' => null];
    public ?string $locationHint = null;

    public function openBook(CourierGateway $gw): void
    {
        $s = $this->order->shipment; abort_unless($s, 404);
        $this->resetErrorBag();
        $loc = $s->courier_meta['pathao'] ?? null; $this->locationHint = $loc ? 'Saved on this parcel' : null;
        if (! $loc && $s->courier->code === 'pathao') {
            if ($loc = $gw->rememberedLocation($s)) $this->locationHint = 'Same as this customer’s last Pathao parcel';
            else { try { $g = $gw->guessLocation($s->courier, $s); if ($g['city_id']) { $loc = $g; $this->locationHint = $g['zone_id'] ? 'Guessed from the address — please check' : 'City guessed from the delivery area — pick the zone'; } } catch (CourierException $e) { $this->addError('book', $e->getMessage()); } }
        }
        $this->book = ['note' => '', 'weight' => (float) ($s->weight_kg ?: 0.5), 'delivery_type' => 0, 'alt_phone' => '', 'city_id' => $loc['city_id'] ?? null, 'zone_id' => $loc['zone_id'] ?? null, 'area_id' => $loc['area_id'] ?? null];
        $this->dispatch('open-modal', name: 'send-courier');
    }

    public function updatedBook($value, ?string $key = null): void
    {
        if ($key === 'city_id') { $this->book['zone_id'] = null; $this->book['area_id'] = null; }
        if ($key === 'zone_id') $this->book['area_id'] = null;
    }

    /** Pathao city / zone / area lists (cached a week by the driver) */
    #[Computed]
    public function pathaoLists(): array
    {
        $s = $this->order->shipment;
        if (! $s || $s->courier->code !== 'pathao' || ! $s->courier->isConnected()) return ['cities' => [], 'zones' => [], 'areas' => [], 'quote' => null];
        try {
            /** @var \App\Services\Couriers\Drivers\PathaoDriver $d */
            $d = app(CourierGateway::class)->driver($s->courier, $s);
            $out = ['cities' => $d->cities(), 'zones' => $this->book['city_id'] ? $d->zones((int) $this->book['city_id']) : [], 'areas' => $this->book['zone_id'] ? $d->areas((int) $this->book['zone_id']) : [], 'quote' => null];
            if ($this->book['city_id'] && $this->book['zone_id']) { try { $out['quote'] = $d->quote((int) $this->book['city_id'], (int) $this->book['zone_id'], (float) $this->book['weight']); } catch (CourierException) {} }
            return $out;
        } catch (CourierException $e) {
            return ['cities' => [], 'zones' => [], 'areas' => [], 'quote' => null, 'error' => $e->getMessage()];
        }
    }

    public function sendToCourier(CourierGateway $gw): void
    {
        $s = $this->order->shipment; abort_unless($s, 404);
        $pathao = $s->courier->code === 'pathao';
        $this->validate(['book.weight' => 'required|numeric|min:0.1|max:30', 'book.note' => 'nullable|max:200', 'book.alt_phone' => 'nullable|max:20', 'book.delivery_type' => 'in:0,1',
            'book.city_id' => $pathao ? 'required|integer' : 'nullable', 'book.zone_id' => $pathao ? 'required|integer' : 'nullable', 'book.area_id' => 'nullable|integer'],
            ['book.city_id.required' => 'Choose the Pathao city.', 'book.zone_id.required' => 'Choose the Pathao zone.']);
        if ((float) $this->book['weight'] !== (float) $s->weight_kg) $s->update(['weight_kg' => $this->book['weight']]);
        try {
            $gw->book($s, ['note' => $this->book['note'] ?: null, 'weight' => $this->book['weight'], 'delivery_type' => (int) $this->book['delivery_type'], 'alt_phone' => $this->book['alt_phone'] ?: null,
                'pathao' => $pathao ? ['city_id' => (int) $this->book['city_id'], 'zone_id' => (int) $this->book['zone_id'], 'area_id' => $this->book['area_id'] ? (int) $this->book['area_id'] : null] : null]);
        } catch (CourierException $e) {
            $this->addError('book', $e->getMessage()); return;
        }
        $this->dispatch('close-modal'); $this->mount($this->order->fresh());
        $this->dispatch('toast', message: 'Parcel '.$s->code.' sent to '.$s->courier->name);
    }

    public function syncParcel(CourierGateway $gw): void
    {
        $s = $this->order->shipment; abort_unless($s, 404);
        try { $changed = $gw->sync($s); } catch (CourierException $e) { $this->dispatch('toast', message: $s->courier->name.': '.$e->getMessage(), type: 'bad'); return; }
        $this->mount($this->order->fresh());
        $this->dispatch('toast', message: $changed ? 'Status updated → '.config('ops.courier_status.'.$this->order->shipment->courier_status.'.0') : 'No change from '.$s->courier->name);
    }

    public function updateShipment(): void
    {
        $s = $this->order->shipment; abort_unless($s, 404);
        $this->validate(['tracking' => 'nullable|max:40', 'parcelStatus' => 'required|in:'.implode(',', array_keys(config('ops.courier_status')))]);
        if ($this->tracking !== (string) $s->tracking) { $s->update(['tracking' => $this->tracking ?: null]); $this->order->log('courier', 'Tracking → '.($this->tracking ?: '—')); }
        if (app(OrderWorkflow::class)->courierStatus($s, $this->parcelStatus, auth()->user()->name))
            app(CourierGateway::class)->event($s, 'manual', $this->parcelStatus, null, 'Set by '.auth()->user()->name);
        $this->mount($this->order->fresh());
        $this->dispatch('toast', message: 'Parcel '.$s->code.' updated');
    }

    public function render() { return view('livewire.ops.orders.show')->title('Order '.$this->order->number); }
}
