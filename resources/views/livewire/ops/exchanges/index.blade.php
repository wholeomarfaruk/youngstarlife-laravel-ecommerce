<div class="flex flex-col gap-3">
    <x-ops.card title="Create exchange" subtitle="The courier delivers the new item and brings the old one back. Steadfast / Pathao get a written instruction to collect it; the old item is checked in at Return receive.">
        <div class="flex flex-wrap items-end gap-2">
            <x-ops.field label="Delivered order"><input wire:model.live.debounce.400ms="order" class="input mono w-40" placeholder="#1001"></x-ops.field>
            <x-ops.field label="Exchange charge (৳)" :hint="'Default from Settings · '.tk(ops_setting('orders.exchange_charge', 100))" :error="$errors->first('charge')"><input type="number" min="0" wire:model.live.debounce.300ms="charge" class="input w-32"></x-ops.field>
        </div>
        @if ($order && ! $this->original)<p class="mt-2 text-sm text-bad">No order {{ $order }}.</p>@endif
        @if ($o = $this->original)
            @php($left = $this->remaining)
            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm"><a href="{{ route('ops.orders.show', $o) }}" wire:navigate class="mono font-semibold">{{ $o->number }}</a> · {{ $o->customer->name }} · {{ $o->customer->phone }} <x-ops.badge map="order_status" :status="$o->status" />
                @foreach ($o->exchanges as $prev)<a href="{{ route('ops.orders.show', $prev) }}" wire:navigate><x-ops.badge :tone="config('ops.exchange_state.'.$prev->exchange_state.'.1', 'mute')">{{ $prev->number }} · {{ config('ops.exchange_state.'.$prev->exchange_state.'.2', $prev->exchange_state) }}</x-ops.badge></a>@endforeach</div>
            @unless (in_array($o->status, ['delivered', 'partially_delivered']))<p class="mt-2 rounded bg-warn-bg px-3 py-2 text-sm text-warn">Only delivered orders can be exchanged — this one is {{ strtolower(config('ops.order_status.'.$o->status.'.0')) }}.</p>@endunless
            @error('lines')<p class="mt-2 text-sm text-bad">{{ $message }}</p>@enderror
            <div class="mt-2 overflow-x-auto rounded-md border border-line">
                @foreach ($o->items as $i)
                    @php($can = $left[$i->id] ?? 0)
                    <div @class(['grid min-w-[800px] grid-cols-[24px_minmax(160px,1fr)_minmax(220px,1fr)_70px_160px] items-center gap-2.5 border-b border-soft px-3 py-2 last:border-0', 'bg-brand-tint' => $lines[$i->id]['on'] ?? false, 'opacity-50' => ! $can])>
                        <input type="checkbox" wire:model.live="lines.{{ $i->id }}.on" class="h-5 w-5 accent-brand" @disabled(! $can)>
                        <div><div class="font-medium">{{ $i->variant->label }}</div><div class="text-xs text-ink-muted">old · {{ tk($i->unit_price) }} × {{ $i->qty }}{{ $can < $i->qty ? ' · '.$can.' left to exchange' : '' }}</div></div>
                        <div>@php($vs = $this->variants->where('product_id', $i->variant->product_id)->merge($this->variants->where('product_id', '!=', $i->variant->product_id)))
                            <x-ops.searchable-select field="lines.{{ $i->id }}.new_variant_id" wire:key="xv-{{ $i->id }}" placeholder="New item…" searchPlaceholder="Name, size, SKU…" :disabled="! $can"
                                :options="$vs->mapWithKeys(fn ($v) => [$v->id => '→ '.$v->label.' · '.tk($v->effective_price).' · '.($v->stock - $v->reserved).' free'])->all()"
                                :images="$vs->mapWithKeys(fn ($v) => [$v->id => $v->product->thumb_url])->all()" />@error('lines.'.$i->id.'.new_variant_id')<span class="text-xs text-bad">{{ $message }}</span>@enderror</div>
                        <div><input type="number" min="1" max="{{ $can }}" wire:model.live.debounce.300ms="lines.{{ $i->id }}.qty" class="input py-1" @disabled(! $can)>@error('lines.'.$i->id.'.qty')<span class="text-xs text-bad">{{ $message }}</span>@enderror</div>
                        <select wire:model="lines.{{ $i->id }}.reason" class="input py-1" @disabled(! $can)>@foreach (ops_setting('returns.reasons', config('ops.return_reasons')) as $r)<option>{{ $r }}</option>@endforeach</select>
                    </div>
                @endforeach
            </div>
            <x-ops.field label="Note for the team / rider" class="mt-3"><input wire:model="note" class="input" placeholder="e.g. Customer wants XL, will hand back the L with tag"></x-ops.field>
            @if ($q = $this->quote)
                <div class="mt-3 flex flex-wrap items-center justify-end gap-x-4 gap-y-1 text-sm">
                    <span class="text-ink-muted">Charge {{ tk($q['charge']) }}@if ($q['up']) + price up {{ tk($q['up']) }}@endif @if ($q['discount']) − cheaper item {{ tk($q['discount']) }}@endif = COD <b class="text-ink">{{ tk($q['cod']) }}</b></span>
                    @if ($q['refund'])<x-ops.badge tone="warn">Refund {{ tk($q['refund']) }} after the old item is back</x-ops.badge>@endif
                    <button wire:click="create" wire:loading.attr="disabled" class="btn-primary">Create exchange →</button>
                </div>
            @endif
        @endif
    </x-ops.card>

    @php($c = $this->counts)
    <section class="card">
        <div class="border-b border-soft px-3 py-2"><x-ops.chips wire:model.live="state" :options="['open' => ['Open', $c['open']], 'overdue' => ['Old item overdue', $c['overdue']], 'awaiting' => ['To send', $c['awaiting'] ?? 0], 'out' => ['With courier', $c['out'] ?? 0], 'collecting' => ['Collecting old item', $c['collecting'] ?? 0], 'refused' => ['Refused', $c['refused'] ?? 0], 'completed' => ['Done', $c['completed'] ?? 0], 'old_missing' => ['Old missing', $c['old_missing'] ?? 0], 'failed' => ['Failed', $c['failed'] ?? 0], 'cancelled' => ['Cancelled', $c['cancelled'] ?? 0], 'all' => ['All', $c['all']]]" /></div>
        <x-ops.toolbar><x-ops.search placeholder="Search exchange, original order, customer, phone…" /><span class="ml-auto text-sm text-ink-muted">{{ $this->exchanges->total() }} exchanges</span></x-ops.toolbar>
        <x-ops.table min-width="1000px">
            <x-slot:head><th class="th">Exchange</th><th class="th">Original</th><th class="th">Customer</th><th class="th">New → old item</th><th class="th text-right">COD</th><th class="th">Parcel</th><th class="th">Exchange</th></x-slot:head>
            @forelse ($this->exchanges as $x)
                @php($line = $x->exchangeLine) @php($s = $x->shipment)
                <tr class="tr-hover" wire:key="x{{ $x->id }}">
                    <td class="td"><a href="{{ route('ops.orders.show', $x) }}" wire:navigate class="mono font-semibold">{{ $x->number }}</a><div class="text-xs text-ink-muted">{{ $x->placed_at->format('d M H:i') }}</div></td>
                    <td class="td"><a href="{{ route('ops.orders.show', $x->exchange_of_id) }}" wire:navigate class="mono">{{ $x->exchangeOf?->number }}</a></td>
                    <td class="td">{{ $x->customer->name }}<div class="text-xs text-ink-muted">{{ $x->customer->phone }}</div></td>
                    <td class="td text-sm"><div>↗ {{ $x->items->map(fn ($i) => $i->variant->label.' ×'.$i->qty)->join(', ') }}</div>@if ($line)<div class="text-ink-muted">↙ {{ $line->items->map(fn ($i) => $i->orderItem->variant->label.' ×'.$i->qty)->join(', ') }} <span class="mono text-xs">{{ $line->code }}</span></div>@endif</td>
                    <td class="td text-right">{{ tk($x->total) }}@if ($line?->refund_amount)<div class="text-xs text-warn">refund {{ tk($line->refund_amount) }}</div>@endif</td>
                    <td class="td text-sm">@if ($s)<span class="mono">{{ $s->code }}</span> · {{ $s->courier?->name }}<div><x-ops.badge map="courier_status" :status="$s->courier_status" /></div>@else<x-ops.badge map="order_status" :status="$x->status" />@endif</td>
                    <td class="td"><x-ops.badge :tone="config('ops.exchange_state.'.$x->exchange_state.'.1', 'mute')">{{ config('ops.exchange_state.'.$x->exchange_state.'.0', $x->exchange_state ?? '—') }}</x-ops.badge>
                        @if ($x->exchange_state === 'collecting' && $s?->delivered_at)@php($d = (int) $s->delivered_at->diffInDays(now()))<div @class(['text-xs', 'text-bad font-semibold' => $d >= (int) ops_setting('orders.exchange_overdue_days', 5), 'text-ink-muted' => $d < (int) ops_setting('orders.exchange_overdue_days', 5)])>delivered {{ $d }} day{{ $d === 1 ? '' : 's' }} ago</div>@endif</td>
                </tr>
            @empty <x-ops.empty colspan="7" title="No exchanges here" hint="Use ⋯ → Create exchange on a delivered order, or type its number above." /> @endforelse
        </x-ops.table>
        <x-ops.pagination :paginator="$this->exchanges" />
    </section>
</div>
