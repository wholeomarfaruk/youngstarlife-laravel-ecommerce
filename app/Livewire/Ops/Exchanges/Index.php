<?php

namespace App\Livewire\Ops\Exchanges;

use App\Models\Ops\{Order, ProductVariant};
use App\Services\Ops\ExchangeService;
use App\Livewire\Ops\Concerns\WithTable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

/** Orders → Exchanges: create an exchange for a delivered order, and follow every exchange until its old item is back (App\Services\Ops\ExchangeService). */
#[Layout('layouts.ops', ['module' => 'Orders & Packing', 'title' => 'Exchanges', 'tabs' => 'orders'])]
#[Title('Exchanges')]
class Index extends Component
{
    use WithTable;

    #[Url(except: '')] public string $order = '';    // ?order=#1001 from the ⋯ menu "Create exchange"
    #[Url(except: 'open')] public string $state = 'open';   // open | overdue | <exchange_state> | all
    public array $lines = [];                        // original order_item_id => [on, new_variant_id, qty, reason]
    public $charge = 0;
    public string $note = '';

    public function mount(): void { $this->charge = (int) ops_setting('orders.exchange_charge', 100); if ($this->order) $this->load(); }
    public function updatedOrder(): void { $this->load(); }
    public function updatedState(): void { $this->resetPage(); }

    #[Computed] public function original(): ?Order { return $this->order ? Order::live()->numbered($this->order)->with('items.variant.product:id,name,price,sale_price', 'customer:id,name,phone', 'zone', 'exchanges:id,number,exchange_of_id,exchange_state,status')->first() : null; }
    #[Computed] public function variants() { return ProductVariant::with('product:id,name,price,sale_price,featured_image')->where('is_active', true)->get(); }
    #[Computed] public function remaining(): array { return $this->original ? app(ExchangeService::class)->remaining($this->original) : []; }

    protected function load(): void
    {
        unset($this->original, $this->remaining);
        $left = $this->remaining;
        $this->lines = $this->original?->items->mapWithKeys(fn ($i) => [$i->id => ['on' => false, 'new_variant_id' => $i->variant_id, 'qty' => max(1, min($i->qty, $left[$i->id] ?? 0)), 'reason' => 'Wrong Size']])->all() ?? [];
    }

    /** live money preview */
    #[Computed]
    public function quote(): ?array
    {
        $o = $this->original; if (! $o) return null;
        $picked = collect($this->lines)->filter(fn ($l) => $l['on'])->map(fn ($l, $id) => ['old' => $o->items->firstWhere('id', $id), 'variant' => $this->variants->firstWhere('id', $l['new_variant_id']), 'qty' => (int) $l['qty']])
            ->filter(fn ($l) => $l['old'] && $l['variant'] && $l['qty'] > 0)->values()->all();
        return $picked ? app(ExchangeService::class)->quote($picked, (int) $this->charge) : null;
    }

    public function create(ExchangeService $svc)
    {
        $o = $this->original; abort_unless($o, 404);
        $this->validate(['charge' => 'required|integer|min:0', 'note' => 'nullable|max:300']);
        try {
            $x = $svc->create($o, collect($this->lines)->filter(fn ($l) => $l['on'])->map(fn ($l) => ['variant_id' => (int) $l['new_variant_id'], 'qty' => (int) $l['qty'], 'reason' => $l['reason']])->all(), (int) $this->charge, $this->note ?: null);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors()); $this->dispatch('toast', message: collect($e->errors())->flatten()->first(), type: 'bad'); return null;
        }
        $this->dispatch('toast', message: 'Exchange '.$x->number.' created');
        return $this->redirectRoute('ops.orders.show', $x, navigate: true);
    }

    #[Computed]
    public function counts(): array
    {
        $by = Order::live()->whereNotNull('exchange_of_id')->selectRaw('exchange_state s, COUNT(*) n')->groupBy('exchange_state')->pluck('n', 's');
        return ['open' => (int) $by->only(['awaiting', 'out', 'collecting', 'refused'])->sum(), 'overdue' => app(ExchangeService::class)->overdueQuery()->count(), 'all' => (int) $by->sum()] + $by->map(fn ($n) => (int) $n)->all();
    }

    #[Computed]
    public function exchanges()
    {
        return Order::live()->whereNotNull('exchange_of_id')->with('exchangeOf:id,number', 'customer:id,name,phone', 'items.variant.product:id,name', 'shipment:id,order_id,code,courier_id,courier_status,delivered_at', 'shipment.courier:id,name', 'exchangeLine.items.orderItem.variant.product:id,name')
            ->when($this->state === 'open', fn ($q) => $q->whereIn('exchange_state', ['awaiting', 'out', 'collecting', 'refused']))
            ->when($this->state === 'overdue', fn ($q) => $q->whereIn('id', app(ExchangeService::class)->overdueQuery()->select('id')))
            ->when(! in_array($this->state, ['open', 'overdue', 'all']), fn ($q) => $q->where('exchange_state', $this->state))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', '%'.ltrim($s, '#').'%')->orWhereHas('exchangeOf', fn ($o) => $o->where('number', 'like', '%'.ltrim($s, '#').'%'))->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))))
            ->latest('placed_at')->paginate($this->perPage);
    }

    public function render() { return view('livewire.ops.exchanges.index'); }
}
