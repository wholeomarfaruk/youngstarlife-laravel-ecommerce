<div class="flex flex-wrap items-start gap-3">
    <div class="card max-h-[calc(100vh-90px)] flex-[1_1_210px] overflow-y-auto lg:sticky lg:top-[72px] lg:max-w-[300px]">
        <div class="border-b border-soft p-2.5"><x-ops.date-range /></div>
        @forelse ($this->groups as $g)
            <button wire:click="$set('day','{{ $g->d }}')" @class(['flex w-full flex-col gap-1.5 border-b border-l-[3px] border-soft px-3 py-2.5 text-left hover:bg-zebra', 'border-l-brand bg-brand-tint' => $this->currentDay() === $g->d, 'border-l-transparent' => $this->currentDay() !== $g->d])>
                <div class="flex items-baseline justify-between"><span class="font-semibold">{{ \Carbon\Carbon::parse($g->d)->format('D, d M') }}</span><span class="text-lg font-semibold">{{ $g->n }}</span></div>
                <div class="flex justify-between text-xs text-ink-muted"><span>{{ tk($g->v) }}</span><span><span class="text-ok">{{ $g->delivered }}</span> · <span class="text-bad">{{ $g->returned }}</span> · {{ $g->cancelled }}</span></div>
            </button>
        @empty <div class="p-4 text-ink-muted">No orders in this range.</div> @endforelse
    </div>
    <div class="flex min-w-0 flex-[3_1_480px] flex-col gap-3">
        @if ($summary)
            <x-ops.kpis>
                <x-ops.kpi :label="\Carbon\Carbon::parse($summary->d)->format('l, d M')" :value="$summary->n.' orders'" />
                <x-ops.kpi label="Value" :value="tk($summary->v)" :hint="'COD '.tk($summary->cod)" />
                <x-ops.kpi label="Delivered" :value="$summary->delivered" tone="ok" />
                <x-ops.kpi label="Returned" :value="$summary->returned" tone="bad" />
                <x-ops.kpi label="Cancelled" :value="$summary->cancelled" tone="mute" />
            </x-ops.kpis>
        @endif
        <section class="card min-w-0">
            <x-ops.toolbar><span class="font-semibold">{{ $this->orders->count() }} orders</span><x-ops.search placeholder="Search order, customer, phone…" />
                <select class="input w-auto" wire:model.live="status"><option value="all">All statuses</option>@foreach (config('ops.order_status') as $key => [$l])<option value="{{ $key }}">{{ $l }}</option>@endforeach</select></x-ops.toolbar>
            <x-ops.table min-width="760px">
                <x-slot:head><th class="th">Order</th><th class="th">Time</th><th class="th">Customer</th><th class="th text-right">Amount</th><th class="th">Status</th><th class="th">Courier</th><th class="th"></th></x-slot:head>
                @forelse ($this->orders as $o)<tr class="tr-hover" wire:key="d{{ $o->id }}"><td class="td"><a href="{{ route('ops.orders.show', $o) }}" wire:navigate class="mono font-semibold">{{ $o->number }}</a></td><td class="td text-ink-muted">{{ $o->placed_at->format('H:i') }}</td><td class="td">{{ $o->customer->name }}<div class="text-xs text-ink-muted">{{ $o->customer->area }}</div></td><td class="td text-right font-semibold">{{ tk($o->total) }}</td><td class="td"><x-ops.badge map="order_status" :status="$o->status" /></td><td class="td text-sm">{{ $o->shipment?->courier->name ?? '—' }}</td><td class="td"><livewire:ops.orders.row-actions :order="$o" :key="'ra'.$o->id.$o->status" lazy /></td></tr>
                @empty <x-ops.empty colspan="7" title="No orders match" /> @endforelse
            </x-ops.table>
        </section>
    </div>
</div>
