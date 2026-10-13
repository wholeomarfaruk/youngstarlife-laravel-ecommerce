<div class="flex flex-col gap-3">
    @php($cs = $this->campaigns) @php($spend = $cs->sum('spend')) @php($rev = $cs->sum('revenue'))
    <x-ops.kpis><x-ops.kpi label="Campaigns" :value="$cs->count()" /><x-ops.kpi label="Campaign orders" :value="$cs->sum('n')" tone="info" /><x-ops.kpi label="Ad spend" :value="tk($spend)" tone="warn" /><x-ops.kpi label="Delivered revenue" :value="tk($rev)" tone="ok" /><x-ops.kpi label="ROAS" :value="$spend ? number_format($rev / $spend, 1).'×' : '—'" tone="ok" hint="delivered ÷ spend" /><x-ops.kpi label="Cost per order" :value="$cs->sum('n') ? tk($spend / $cs->sum('n')) : '—'" /></x-ops.kpis>
    <section class="card"><x-ops.toolbar><x-ops.search placeholder="Search campaign…" /><select wire:model.live="platform" class="input w-auto"><option value="">All platforms</option><option>Facebook</option><option>Instagram</option><option>Google</option></select><x-ops.date-range /><select wire:model.live="sort" class="input w-auto"><option value="orders">Most orders</option><option value="rev">Highest revenue</option><option value="roas">Best ROAS</option><option value="spend">Highest spend</option></select></x-ops.toolbar></section>
    @foreach ($cs as $c)
        <section class="card overflow-hidden" wire:key="c{{ $c->id }}">
            <button wire:click="toggle({{ $c->id }})" class="flex w-full flex-wrap items-center gap-x-4 gap-y-2 px-3.5 py-3 text-left hover:bg-zebra">
                <span class="flex-[1_1_220px]"><span class="font-semibold">{{ $c->name }}</span> <x-ops.badge :tone="['active' => 'ok', 'paused' => 'warn', 'ended' => 'mute'][$c->status] ?? 'mute'">{{ ucfirst($c->status) }}</x-ops.badge><div class="text-xs text-ink-muted">{{ $c->platform }} · {{ $c->starts_at?->format('d M') }} → {{ $c->ends_at?->format('d M') ?? 'ongoing' }}</div></span>
                @foreach (['Orders' => $c->n, 'Booked' => tk($c->booked), 'Delivered' => tk($c->revenue), 'Spend' => tk($c->spend), 'ROAS' => $c->spend ? number_format($c->revenue / $c->spend, 1).'×' : '—', 'Delivery rate' => ($c->delivered + $c->returned) ? round($c->delivered / ($c->delivered + $c->returned) * 100).'%' : '—'] as $l => $v)<span class="flex min-w-[64px] flex-col"><span class="text-2xs text-ink-muted">{{ $l }}</span><span class="font-semibold tabular-nums">{{ $v }}</span></span>@endforeach
            </button>
            @if ($open[$c->id] ?? false)
                <x-ops.table min-width="640px"><x-slot:head><th class="th">Order</th><th class="th">Date</th><th class="th">Customer</th><th class="th text-right">Amount</th><th class="th">Status</th></x-slot:head>
                    @foreach ($this->ordersFor($c->id) as $o)<tr class="tr-hover"><td class="td"><a href="{{ route('ops.orders.show', $o) }}" wire:navigate class="mono font-semibold">{{ $o->number }}</a></td><td class="td">{{ $o->placed_at->format('d M H:i') }}</td><td class="td">{{ $o->customer->name }}</td><td class="td text-right font-semibold">{{ tk($o->total) }}</td><td class="td"><x-ops.badge map="order_status" :status="$o->status" /></td></tr>@endforeach
                </x-ops.table>
            @endif
        </section>
    @endforeach
</div>
