<?php

namespace App\Livewire\Admin;

use App\Models\{Batch, Customer, Order, ProductVariant, Shipment};
use Livewire\Component;

/** Topbar "Scan or search anything". Barcode → exact match first; text → top 8 across entities. */
class GlobalSearch extends Component
{
    public string $q = '';

    public function getResultsProperty(): array
    {
        $q = trim($this->q); if (strlen($q) < 2) return [];
        $out = [];
        if ($o = Order::live()->where('number', '#'.ltrim($q, '#'))->first()) $out[] = ['Order', $o->number, route('admin.orders.show', $o)];
        if ($s = Shipment::where('code', $q)->orWhere('tracking', $q)->first()) $out[] = ['Parcel', $s->code, route('admin.orders.show', $s->order_id)];
        if ($b = Batch::where('code', strtoupper($q))->first()) $out[] = ['Batch', $b->code, route('admin.batches.index', ['search' => $b->code])];
        foreach (ProductVariant::with('product:id,name')->where('barcode', $q)->orWhere('sku', 'like', "$q%")->limit(3)->get() as $v) $out[] = ['Product', $v->label, route('admin.products.edit', $v->product_id)];
        foreach (Customer::where('phone', 'like', "%$q%")->orWhere('name', 'like', "%$q%")->limit(3)->get(['id', 'name', 'phone']) as $c) $out[] = ['Customer', $c->name.' · '.$c->phone, route('admin.customers.index', ['search' => $c->phone])];
        return array_slice($out, 0, 8);
    }

    public function go() { if ($r = $this->results[0] ?? null) return $this->redirect($r[2], navigate: true); }

    public function render()
    {
        return <<<'BLADE'
        <div class="relative order-5 w-full flex-none sm:order-none sm:w-[min(460px,42vw)]" x-data="{ open: false }" @click.outside="open=false">
            <form wire:submit="go" class="flex items-center gap-1.5 rounded border border-line-2 bg-white px-2 py-1.5">
                <span class="mono rounded-sm border border-line px-1 text-xs text-ink-muted">SCAN</span>
                <input id="global-search" wire:model.live.debounce.250ms="q" @focus="open=true" placeholder="Scan or search anything…  ( / )" class="min-w-0 flex-1 border-0 bg-transparent outline-none" autocomplete="off">
                <span class="mono hidden text-2xs text-ink-muted sm:inline">⌘K</span>
            </form>
            <div x-show="open && $wire.q.length > 1" x-cloak class="absolute inset-x-0 top-[calc(100%+6px)] z-50 overflow-hidden rounded-lg border border-line-2 bg-white shadow-search">
                @forelse ($this->results as [$type, $label, $url])
                    <a href="{{ $url }}" wire:navigate class="flex items-center gap-2.5 border-t border-soft px-3 py-2 text-ink first:border-0 hover:bg-zebra"><span class="w-16 text-xs text-ink-muted">{{ $type }}</span><span class="truncate">{{ $label }}</span></a>
                @empty <div class="px-3 py-3 text-ink-muted">No matches</div> @endforelse
            </div>
        </div>
        BLADE;
    }
}
