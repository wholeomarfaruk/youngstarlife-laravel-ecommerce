<div class="flex flex-wrap items-start gap-3">
    <div class="card max-h-[calc(100vh-90px)] flex-[1_1_210px] overflow-y-auto lg:sticky lg:top-[72px] lg:max-w-[300px]">
        <div class="border-b border-soft p-2.5"><x-admin.date-range /></div>
        @foreach ($this->groups as $g)
            <button wire:click="$set('day','{{ $g->d }}')" @class(['flex w-full flex-col gap-1.5 border-b border-l-[3px] border-soft px-3 py-2.5 text-left hover:bg-zebra', 'border-l-brand bg-brand-tint' => ($day ?? $this->groups->first()->d) === $g->d, 'border-l-transparent' => ($day ?? $this->groups->first()->d) !== $g->d])>
                <div class="flex items-baseline justify-between"><span class="font-semibold">{{ \Carbon\Carbon::parse($g->d)->format('D, d M') }}</span><span class="text-lg font-semibold">{{ $g->n }}</span></div>
                <div class="flex justify-between text-xs text-ink-muted"><span>{{ tk($g->v) }}</span><span><span class="text-ok">{{ $g->delivered }}</span> · <span class="text-bad">{{ $g->returned }}</span> · {{ $g->cancelled }}</span></div>
            </button>
        @endforeach
    </div>
    <section class="card min-w-0 flex-[3_1_480px]">
        <x-admin.toolbar><span class="font-semibold">{{ $this->orders->count() }} orders</span><x-admin.search placeholder="Search order, customer, phone…" />
            <select class="input w-auto" wire:model.live="status"><option value="all">All statuses</option>@foreach (config('admin.order_status') as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></x-admin.toolbar>
        <x-admin.table min-width="760px">
            <x-slot:head><th class="th">Order</th><th class="th">Time</th><th class="th">Customer</th><th class="th text-right">Amount</th><th class="th">Status</th><th class="th">Courier</th><th class="th"></th></x-slot:head>
            @forelse ($this->orders as $o)<tr class="tr-hover" wire:key="d{{ $o->id }}"><td class="td"><a href="{{ route('admin.orders.show', $o) }}" wire:navigate class="mono font-semibold">{{ $o->number }}</a></td><td class="td text-ink-muted">{{ $o->placed_at->format('H:i') }}</td><td class="td">{{ $o->customer->name }}<div class="text-xs text-ink-muted">{{ $o->customer->area }}</div></td><td class="td text-right font-semibold">{{ tk($o->total) }}</td><td class="td"><x-admin.badge map="order_status" :status="$o->status" /></td><td class="td text-sm">{{ $o->shipment?->courier->name ?? '—' }}</td><td class="td"><livewire:admin.orders.row-actions :order="$o" :key="'ra'.$o->id" lazy /></td></tr>
            @empty <x-admin.empty colspan="7" title="No orders match" /> @endforelse
        </x-admin.table>
    </section>
</div>
