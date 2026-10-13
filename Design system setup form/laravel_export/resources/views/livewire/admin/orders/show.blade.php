@php($o = $order)
<div class="flex flex-col gap-3">
    <a href="{{ route('admin.orders.index') }}" wire:navigate class="self-start text-sm">← All orders</a>
    <x-admin.page-header :title="'Order '.$o->number" :subtitle="$o->placed_at->format('d M Y, H:i').' · '.ucfirst($o->source)">
        <x-admin.badge map="order_status" :status="$o->status" />
        <a href="{{ route('admin.orders.print', ['ids' => $o->id]) }}" target="_blank" class="btn-secondary">Print invoice</a>
        @if ($o->status === 'processing')
            <a href="{{ route('admin.packing.scan', ['order' => $o->number]) }}" wire:navigate class="btn-primary">Pack & scan</a>
        @elseif (in_array($o->status, ['pending', 'confirmed', 'packed']))
            <button wire:click="advance" wire:loading.attr="disabled" class="btn-primary">{{ ['pending' => 'Confirm order', 'confirmed' => 'Start processing', 'packed' => 'Mark shipped'][$o->status] }}</button>
        @endif
    </x-admin.page-header>

    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="flex min-w-0 flex-col gap-3">
            <x-admin.card title="Items · batch traceability" flush>
                <x-admin.table min-width="640px">
                    <x-slot:head><th class="th">Product</th><th class="th">Batch → supplier</th><th class="th text-right">Qty</th><th class="th text-right">Price</th><th class="th text-right">Cost (at pack)</th></x-slot:head>
                    @foreach ($o->items as $i)
                        <tr><td class="td"><div class="font-medium">{{ $i->variant->label }}</div><div class="mono text-xs text-ink-muted">{{ $i->variant->sku }}</div></td>
                            <td class="td">@forelse ($i->allocations as $a)<div><span class="mono font-semibold">{{ $a->batch->code }}</span> <span class="text-xs text-ink-muted">→ {{ $a->batch->supplier->name }} × {{ $a->qty }}</span></div>@empty<span class="text-ink-muted">Not allocated</span>@endforelse</td>
                            <td class="td text-right">{{ $i->qty }}</td><td class="td text-right tabular-nums">{{ tk($i->unit_price) }}</td>
                            <td class="td text-right tabular-nums text-ink-muted">{{ $i->allocations->whereNotNull('unit_cost')->isNotEmpty() ? tk($i->allocations->sum(fn ($a) => $a->qty * $a->unit_cost)) : '—' }}</td></tr>
                    @endforeach
                </x-admin.table>
                <dl class="ml-auto grid w-full max-w-[280px] grid-cols-[1fr_auto] gap-x-6 gap-y-1 p-3.5 tabular-nums">
                    <dt class="text-ink-muted">Subtotal</dt><dd class="text-right">{{ tk($o->subtotal) }}</dd>
                    <dt class="text-ink-muted">Discount</dt><dd class="text-right">− {{ tk($o->discount) }}</dd>
                    <dt class="text-ink-muted">Delivery</dt><dd class="text-right">{{ tk($o->delivery_charge) }}</dd>
                    <dt class="border-t border-soft pt-1 font-semibold">Total</dt><dd class="border-t border-soft pt-1 text-right font-bold">{{ tk($o->total) }}</dd>
                    <dt class="text-ink-muted">Advance</dt><dd class="text-right">{{ tk($o->advance) }}</dd>
                    <dt class="font-semibold">COD to collect</dt><dd class="text-right font-semibold text-brand">{{ tk($o->cod) }}</dd>
                </dl>
            </x-admin.card>
            <x-admin.card title="Timeline">
                @foreach ($o->events as $e)
                    <div class="grid grid-cols-[100px_minmax(0,1fr)] gap-2.5 border-b border-soft py-2 text-sm last:border-0"><span class="mono text-xs text-ink-muted">{{ $e->created_at->format('d M H:i') }}</span><span>{{ $e->message }} <span class="text-ink-muted">· {{ $e->user?->name ?? 'System' }}</span></span></div>
                @endforeach
                <form wire:submit="addNote" class="mt-2 flex gap-2"><input wire:model="note" class="input" placeholder="Add an internal note…"><button class="btn-secondary">Add</button></form>
            </x-admin.card>
        </div>
        <div class="flex flex-col gap-3">
            <x-admin.card title="Customer">
                <a href="{{ route('admin.customers.index', ['search' => $o->customer->phone]) }}" wire:navigate class="font-semibold">{{ $o->customer->name }}</a>
                <div class="text-sm text-ink-muted">{{ $o->customer->orders_count }} orders · {{ $o->customer->delivery_rate !== null ? round($o->customer->delivery_rate * 100).'% delivered' : 'new' }}</div>
                <div class="mt-2 text-sm">{{ $o->ship_phone }}<br>{{ $o->ship_address }}<br><span class="text-ink-muted">{{ $o->zone?->name }} · {{ tk($o->delivery_charge) }}</span></div>
            </x-admin.card>
            <x-admin.card title="Shipment">
                @if ($o->shipment)
                    <div class="flex flex-col gap-1 text-sm"><span>{{ $o->shipment->courier->name }} · <span class="mono">{{ $o->shipment->code }}</span></span><span class="mono text-ink-muted">{{ $o->shipment->tracking }}</span><x-admin.badge map="courier_status" :status="$o->shipment->courier_status" class="self-start" /></div>
                @else <span class="text-ink-muted">Not booked yet</span> @endif
            </x-admin.card>
        </div>
    </div>
</div>
