<?php

namespace App\Livewire\Ops\Orders;

use App\Models\Ops\{Courier, Order};
use App\Services\Couriers\{CourierException, CourierGateway};
use Livewire\Attributes\{Computed, On};
use Livewire\Component;

/**
 * "Book courier…" from an order's ⋯ menu: one modal per page, opened with $dispatch('book-courier', { id }).
 * Staff check / fix the delivery details, pick the courier, and — when the parcel is already packed — send it to the courier API.
 * Orders not packed yet keep the courier ("Courier: X" tag) and the rider note; the parcel goes to that courier at packing.
 */
class BookCourier extends Component
{
    public const OPEN = ['pending', 'confirmed', 'processing', 'packed', 'on_hold'];

    public ?int $orderId = null;
    public array $f = [];   // form
    public ?string $locationHint = null;

    #[Computed] public function order(): ?Order
    {
        return $this->orderId ? Order::with('customer:id,name,phone,delivered_count,returned_count', 'items.variant.product:id,name', 'shipment.courier', 'zone:id,name')->find($this->orderId) : null;
    }
    #[Computed] public function couriers() { return Courier::where('is_active', true)->orderBy('name')->get(); }
    #[Computed] public function courier(): ?Courier { return $this->couriers->firstWhere('id', (int) ($this->f['courier_id'] ?? 0)); }

    /** a packed parcel not sent yet → it can go to the courier API now */
    public function parcel() { $s = $this->order?->shipment; return $s && ! $s->booked_at ? $s : null; }

    #[On('book-courier')]
    public function open(int $id): void
    {
        $this->orderId = $id; unset($this->order); $this->resetErrorBag();
        $o = $this->order;
        if (! $o) { $this->dispatch('toast', message: 'Order not found', type: 'bad'); return; }
        if ($why = $this->blocked($o)) { $this->dispatch('toast', message: $why, type: 'bad'); return; }
        $s = $o->shipment;
        $courier = $s?->courier ?? app(\App\Services\Ops\OrderWorkflow::class)->courierFor($o);
        $this->f = ['name' => $o->ship_name, 'phone' => $o->ship_phone, 'address' => $o->ship_address, 'cod' => (int) ($s?->cod_amount ?? $o->cod),
            'courier_id' => $courier?->id, 'note' => (string) $o->courier_note, 'send' => true,
            'weight' => (float) ($s?->weight_kg ?: 0.5), 'delivery_type' => 0, 'alt_phone' => '', 'city_id' => null, 'zone_id' => null, 'area_id' => null];
        $this->locationHint = null;
        if ($s) $this->pathaoLocation(app(CourierGateway::class));
        $this->dispatch('open-modal', name: 'book-courier-one');
    }

    protected function blocked(Order $o): ?string
    {
        if (! in_array($o->status, self::OPEN)) return "Order {$o->number} is ".mb_strtolower(config("ops.order_status.{$o->status}.0", $o->status)).' — it can’t be booked now.';
        if ($o->shipment?->booked_at) return "Parcel {$o->shipment->code} is already with {$o->shipment->courier->name}.";
        return null;
    }

    /** Pathao needs city / zone: saved on the parcel → this customer's last Pathao parcel → guessed from the address */
    protected function pathaoLocation(CourierGateway $gw): void
    {
        $s = $this->parcel(); $c = $this->courier; $this->locationHint = null;
        if (! $s || $c?->code !== 'pathao' || ! $c->isConnected()) return;
        $s->setRelation('courier', $c);
        $loc = $s->courier_id === $c->id ? ($s->courier_meta['pathao'] ?? null) : null;
        if ($loc) $this->locationHint = 'Saved on this parcel';
        elseif ($loc = $gw->rememberedLocation($s)) $this->locationHint = 'Same as this customer’s last Pathao parcel';
        else { try { $g = $gw->guessLocation($c, $s); if ($g['city_id']) { $loc = $g; $this->locationHint = $g['zone_id'] ? 'Guessed from the address — please check' : 'City guessed from the delivery area — pick the zone'; } } catch (CourierException $e) { $this->addError('book', $e->getMessage()); } }
        $this->f = array_merge($this->f, ['city_id' => $loc['city_id'] ?? null, 'zone_id' => $loc['zone_id'] ?? null, 'area_id' => $loc['area_id'] ?? null]);
    }

    public function updatedF($value, ?string $key = null): void
    {
        if ($key === 'courier_id') { unset($this->courier); $this->f['city_id'] = $this->f['zone_id'] = $this->f['area_id'] = null; $this->pathaoLocation(app(CourierGateway::class)); }
        if ($key === 'city_id') $this->f['zone_id'] = $this->f['area_id'] = null;
        if ($key === 'zone_id') $this->f['area_id'] = null;
    }

    /** Pathao city / zone / area lists (cached by the driver) */
    #[Computed]
    public function pathaoLists(): array
    {
        $none = ['cities' => [], 'zones' => [], 'areas' => [], 'quote' => null];
        $c = $this->courier;
        if (! $this->parcel() || $c?->code !== 'pathao' || ! $c->isConnected()) return $none;
        try {
            /** @var \App\Services\Couriers\Drivers\PathaoDriver $d */
            $d = app(CourierGateway::class)->driver($c, $this->parcel());
            $out = ['cities' => $d->cities(), 'zones' => $this->f['city_id'] ? $d->zones((int) $this->f['city_id']) : [], 'areas' => $this->f['zone_id'] ? $d->areas((int) $this->f['zone_id']) : [], 'quote' => null];
            if ($this->f['city_id'] && $this->f['zone_id']) { try { $out['quote'] = $d->quote((int) $this->f['city_id'], (int) $this->f['zone_id'], (float) $this->f['weight']); } catch (CourierException) {} }
            return $out;
        } catch (CourierException $e) {
            return $none + ['error' => $e->getMessage()];
        }
    }

    public function save(CourierGateway $gw): void
    {
        $o = $this->order; abort_unless($o, 404);
        if ($why = $this->blocked($o)) { $this->addError('book', $why); return; }
        $c = $this->courier; $s = $this->parcel();
        $send = $s && $this->f['send'] && $c?->can('book');
        $pathao = $send && $c->code === 'pathao';
        $this->validate([
            'f.name' => 'required|max:120', 'f.phone' => ['required', 'regex:/^01\d{9}$/'], 'f.address' => 'required|max:255',
            'f.cod' => 'required|integer|min:0|max:'.max($o->total, 1000000), 'f.courier_id' => 'required|exists:ops_couriers,id', 'f.note' => 'nullable|max:200',
            'f.weight' => 'required|numeric|min:0.1|max:30', 'f.alt_phone' => 'nullable|max:20', 'f.delivery_type' => 'in:0,1',
            'f.city_id' => $pathao ? 'required|integer' : 'nullable', 'f.zone_id' => $pathao ? 'required|integer' : 'nullable', 'f.area_id' => 'nullable|integer',
        ], ['f.phone.regex' => 'Enter an 11-digit mobile number (01XXXXXXXXX).', 'f.courier_id.required' => 'Choose a courier.',
            'f.city_id.required' => 'Choose the Pathao city.', 'f.zone_id.required' => 'Choose the Pathao zone.']);

        // delivery details edited here → order (and the parcel's COD)
        $edits = array_filter(['ship_name' => trim($this->f['name']), 'ship_phone' => trim($this->f['phone']), 'ship_address' => trim($this->f['address']), 'cod' => (int) $this->f['cod']],
            fn ($v, $k) => $v != $o->$k, ARRAY_FILTER_USE_BOTH);
        $tags = array_values(array_merge(array_filter($o->tags ?? [], fn ($t) => ! preg_match('/^(Auto )?[Cc]ourier: /', $t)), ['Courier: '.$c->name]));
        $o->update($edits + ['courier_note' => trim((string) $this->f['note']) ?: null, 'tags' => $tags]);
        if ($edits) $o->log('edit', 'Delivery details changed before booking: '.collect($edits)->map(fn ($v, $k) => ['ship_name' => 'name', 'ship_phone' => 'phone', 'ship_address' => 'address', 'cod' => 'COD'][$k].' → '.($k === 'cod' ? tk($v) : $v))->join(', '));
        if ($s) $s->update(['courier_id' => $c->id, 'cod_amount' => (int) $this->f['cod'], 'weight_kg' => $this->f['weight']]);
        $o->log('courier', 'Booked with '.$c->name); ops_log("Order {$o->number} booked with {$c->name}", $o);

        $msg = "Order {$o->number} booked with {$c->name}".($s ? '' : ' · it goes to them when packed');
        if ($send) {
            try {
                $gw->book($s->fresh(['courier', 'order']), ['note' => $o->courier_note, 'weight' => $this->f['weight'], 'delivery_type' => (int) $this->f['delivery_type'], 'alt_phone' => $this->f['alt_phone'] ?: null,
                    'pathao' => $pathao ? ['city_id' => (int) $this->f['city_id'], 'zone_id' => (int) $this->f['zone_id'], 'area_id' => $this->f['area_id'] ? (int) $this->f['area_id'] : null] : null]);
                $msg = "Parcel {$s->code} sent to {$c->name}";
            } catch (CourierException $e) {
                $this->addError('book', 'Details saved, but '.$c->name.' did not accept the parcel: '.$e->getMessage());
                $this->dispatch('ops-order-updated'); return;
            }
        }
        $this->dispatch('close-modal', 'book-courier-one');
        $this->dispatch('toast', message: $msg);
        $this->dispatch('ops-order-updated');
        $this->orderId = null; unset($this->order);
    }

    public function render() { return view('livewire.ops.orders.book-courier'); }
}
