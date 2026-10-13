<div class="flex flex-col gap-3">
    @php($k = $this->kpis)
    @php($greet = now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening'))
    <x-ops.page-header :title="$greet.', '.str(auth()->user()->name)->before(' ')" :subtitle="now()->format('l, j F Y').' · '.(auth()->user()->warehouse?->name ?? \App\Models\Ops\Warehouse::where('is_default', true)->value('name') ?? 'Main warehouse')">
        <div class="flex flex-wrap items-center gap-1.5">
            <div class="flex gap-0.5 rounded-md bg-soft p-0.5">
                @foreach (\App\Livewire\Ops\Dashboard::BUTTONS as $key)
                    <button wire:click="$set('range','{{ $key }}')" @class(['rounded px-3 py-1', 'bg-white font-semibold shadow-seg' => $range === $key, 'text-ink-muted' => $range !== $key])>{{ \App\Livewire\Ops\Dashboard::RANGES[$key] }}</button>
                @endforeach
                @php($more = ! in_array($range, \App\Livewire\Ops\Dashboard::BUTTONS))
                <select wire:model.live="range" @class(['rounded border-0 py-1 pl-2 pr-7 text-base outline-none', 'bg-white font-semibold shadow-seg' => $more, 'bg-transparent text-ink-muted' => ! $more])>
                    @unless ($more)<option value="{{ $range }}">More…</option>@endunless
                    @foreach (\App\Livewire\Ops\Dashboard::RANGES as $key => $l) @continue(in_array($key, \App\Livewire\Ops\Dashboard::BUTTONS))<option value="{{ $key }}">{{ $l }}</option>@endforeach
                </select>
            </div>
            @if ($range === 'custom')
                <div class="flex items-center gap-1.5 text-sm text-ink-muted"><x-ops.date wire:model.live="from" :max="today()->toDateString()" class="w-32 py-1" /><span>–</span><x-ops.date wire:model.live="to" :max="today()->toDateString()" class="w-32 py-1" /></div>
            @endif
        </div>
        <a href="{{ route('ops.orders.drafts', ['new' => 1]) }}" wire:navigate class="btn-primary">New order</a>
        <a href="{{ route('ops.purchases.index') }}" wire:navigate class="btn-secondary">Stock in</a>
        <a href="{{ route('ops.returns.receive') }}" wire:navigate class="btn-secondary">Receive returns</a>
        <a href="{{ route('ops.packing.scan') }}" wire:navigate class="btn-secondary">Pack orders</a>
    </x-ops.page-header>

    {{-- 4 hero KPIs --}}
    @php($vs = ['today' => 'vs yesterday', 'yesterday' => 'vs the day before', '7d' => 'vs previous 7 days', '30d' => 'vs previous 30 days', 'custom' => 'vs the period before', 'month' => 'vs same days before', 'last_month' => 'vs the month before', '90d' => 'vs previous 90 days', 'year' => 'vs the period before', 'all' => 'since the first order'][$range] ?? '')
    @php($hero = [
        ['Total sales', tk($k['sales']), $k['sales_pct'], $k['sales_spark'], 'booked · '.$this->rangeLabel().' · '.$vs, route('ops.reports.index', ['report' => 'sales_summary'])],
        ['Orders', ops_num($k['orders']), $k['orders_pct'], $k['orders_spark'], $k['new_customers'].' new customers · '.$k['cancelled'].' cancelled', route('ops.orders.index')],
        ['Delivered revenue', tk($k['revenue']), $k['revenue_pct'], $k['revenue_spark'], 'COD collected by couriers', route('ops.orders.index', ['status' => 'delivered'])],
    ])
    <div class="grid grid-cols-[repeat(auto-fit,minmax(210px,1fr))] gap-3">
        @foreach ($hero as [$label, $value, $pct, $spark, $hint, $href])
            @php($max = max(1, max($spark)))
            <a href="{{ $href }}" wire:navigate class="card flex flex-col gap-1 p-4 text-ink hover:border-ink-faint hover:text-ink">
                <div class="flex items-center justify-between gap-2"><span class="text-xs text-ink-muted">{{ $label }}</span>
                    @if ($pct !== null)<span @class(['rounded-full px-1.5 text-2xs font-semibold', 'bg-ok-bg text-ok' => $pct >= 0, 'bg-bad-bg text-bad' => $pct < 0])>{{ $pct >= 0 ? '▲' : '▼' }} {{ abs($pct) }}%</span>@endif</div>
                <div class="flex items-end justify-between gap-3"><span class="text-3xl font-semibold tabular-nums">{{ $value }}</span>
                    <span class="flex h-7 items-end gap-px">@foreach ($spark as $v)<span class="w-1 rounded-sm bg-brand-pale" style="height: {{ max(8, $v / $max * 100) }}%"></span>@endforeach</span></div>
                <span class="text-xs text-ink-muted">{{ $hint }}</span>
            </a>
        @endforeach
        @php($t = $this->target) @php($p = $t ? min(100, (int) round($k['sales'] / $t * 100)) : 0)
        <div class="card flex flex-col gap-1 p-4" x-data="{ edit: false }">
            <div class="flex items-center justify-between"><span class="text-xs text-ink-muted">Sales target · {{ $this->rangeLabel() }}{{ $range === 'custom' ? ' (daily × '.((int) \Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to)) + 1).')' : '' }}</span><button type="button" @click="edit = !edit" class="text-xs text-brand">Edit</button></div>
            <div x-show="!edit" class="flex items-baseline gap-2"><span class="text-3xl font-semibold tabular-nums">{{ $p }}%</span><span class="text-sm text-ink-muted">of {{ tk($t) }}</span></div>
            <form x-cloak x-show="edit" @submit.prevent="$wire.saveTarget($refs.t.value); edit = false" class="flex gap-1.5"><input x-ref="t" type="number" min="0" value="{{ $t }}" class="input py-1"><button class="btn-primary btn-sm">Save</button></form>
            <div class="mt-1 h-1.5 rounded bg-soft"><div class="h-1.5 rounded bg-brand" style="width: {{ $p }}%"></div></div>
            <span class="text-xs text-ink-muted">{{ $t > $k['sales'] ? tk($t - $k['sales']).' to go' : 'Target reached 🎉' }}</span>
        </div>
    </div>

    {{-- Order groups in the selected range: count (big) + order value (small) --}}
    @php($g = $this->groups) @php([$gs, $ge] = [$this->period()[0]->toDateString(), $this->period()[1]->toDateString()])
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
        @foreach (\App\Livewire\Ops\Dashboard::GROUPS as $key => [$label, $statuses, $tone])
            <a href="{{ route('ops.orders.index', ['status' => 'group:'.$key, 'from' => $gs, 'to' => $ge, 'preset' => 'custom']) }}" wire:navigate class="kpi text-left text-ink hover:border-ink-faint hover:text-ink">
                <span class="flex items-center gap-1.5 text-xs text-ink-muted"><span class="h-2 w-2 rounded-full bg-{{ $tone === 'mute' ? 'ink-faint' : $tone }}"></span>{{ $label }}</span>
                <span class="text-xl font-semibold tabular-nums text-{{ $tone === 'mute' ? 'ink' : $tone }}">{{ ops_num($g[$key]['n']) }}</span>
                <span class="text-xs tabular-nums text-ink-muted">{{ tk($g[$key]['v']) }}@if ($key === 'courier' && $g[$key]['exchange']) · incl. {{ $g[$key]['exchange'] }} exchange @endif</span>
            </a>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-3">
        @php($chart = collect($this->chart)) @php($max = max(1, $chart->max(fn ($d) => $d['delivered'] + $d['booked']))) @php($best = $chart->sortByDesc('delivered')->first())
        <x-ops.card title="Sales · last 14 days" class="flex-[2_1_480px]">
            <x-slot:actions><span class="flex items-center gap-3 text-xs text-ink-muted"><span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-brand"></span>Delivered</span><span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-brand-pale"></span>Booked</span></span></x-slot:actions>
            <div class="mb-3 flex flex-wrap gap-6 text-sm"><span><span class="text-ink-muted">14-day delivered</span> <b class="tabular-nums">{{ tk($chart->sum('delivered')) }}</b></span><span><span class="text-ink-muted">Best day</span> <b>{{ $best['delivered'] ? \Carbon\Carbon::parse($best['d'])->format('d M').' · '.tk($best['delivered']) : '—' }}</b></span><span><span class="text-ink-muted">Daily average</span> <b class="tabular-nums">{{ tk($chart->sum('delivered') / 14) }}</b></span></div>
            <div class="flex h-[180px] items-end gap-1.5 border-b border-soft">
                @foreach ($chart as $d)
                    <div class="group relative flex h-full flex-1 flex-col justify-end gap-px">
                        <span class="rounded-t bg-brand-pale" style="height: {{ $d['booked'] / $max * 100 }}%"></span>
                        <span class="bg-brand" style="height: {{ $d['delivered'] / $max * 100 }}%"></span>
                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-ink px-2 py-1 text-xs text-white shadow-pop group-hover:block">{{ \Carbon\Carbon::parse($d['d'])->format('D, d M') }} · {{ $d['n'] }} orders<br>Delivered {{ tk($d['delivered']) }} · Booked {{ tk($d['booked']) }}</div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex gap-1.5 text-2xs text-ink-muted">@foreach ($chart as $d)<span class="flex-1 text-center">{{ \Carbon\Carbon::parse($d['d'])->format('d') }}</span>@endforeach</div>
        </x-ops.card>
        @php($acts = $this->actions)
        <x-ops.card title="Action needed" flush class="flex flex-[1_1_300px] flex-col">
            <x-slot:actions>@if (count($acts))<span class="rounded-full bg-bad px-2 py-0.5 text-xs font-semibold text-white">{{ count($acts) }}</span>@endif</x-slot:actions>
            <div class="max-h-[318px] overflow-y-auto">
                @forelse ($acts as [$tone, $label, $hint, $n, $v, $href, $cta])
                    <a href="{{ $href }}" wire:navigate class="group flex items-center gap-2.5 border-t border-soft px-3.5 py-2 text-ink first:border-0 hover:bg-zebra">
                        <span class="h-2 w-2 flex-none rounded-full bg-{{ $tone === 'mute' ? 'ink-faint' : $tone }}"></span>
                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ $label }}</span><span class="block truncate text-xs text-ink-muted">{{ $hint }}</span></span>
                        <span class="text-right"><span class="block text-lg font-semibold leading-tight tabular-nums text-{{ $tone === 'mute' ? 'ink' : $tone }}">{{ ops_num($n) }}</span>@if ($v)<span class="block text-2xs tabular-nums text-ink-muted">{{ tk($v) }}</span>@endif</span>
                        <span class="w-16 flex-none text-right text-xs font-medium text-brand opacity-70 group-hover:opacity-100">{{ $cta }} →</span>
                    </a>
                @empty
                    <div class="flex items-center gap-2.5 px-3.5 py-4 text-ink-muted"><span class="h-2 w-2 rounded-full bg-ok"></span>All clear — nothing needs your action right now</div>
                @endforelse
            </div>
        </x-ops.card>
    </div>

    <x-ops.card title="Recent orders" flush>
        <x-slot:actions><a href="{{ route('ops.orders.index') }}" wire:navigate class="text-sm">View all →</a></x-slot:actions>
        <x-ops.table min-width="860px">
            <x-slot:head><th class="th">Order</th><th class="th">Customer</th><th class="th text-right">Items</th><th class="th text-right">Amount</th><th class="th">Status</th><th class="th">Payment</th><th class="th">Courier</th></x-slot:head>
            @forelse ($this->recent as $o)
                <tr class="tr-hover cursor-pointer" onclick="Livewire.navigate('{{ route('ops.orders.show', $o) }}')">
                    <td class="td"><span class="mono font-semibold text-brand">{{ $o->number }}</span><div class="text-xs text-ink-muted">{{ $o->placed_at->format('d M H:i') }}</div></td>
                    <td class="td"><div class="flex items-center gap-2"><span class="grid h-7 w-7 flex-none place-items-center rounded-full bg-soft text-xs font-semibold text-ink-3">{{ mb_substr($o->customer->name, 0, 1) }}</span><span><span class="font-medium">{{ $o->customer->name }}</span><div class="text-xs text-ink-muted">{{ $o->customer->area }}</div></span></div></td>
                    <td class="td text-right">{{ $o->items->sum('qty') }}</td><td class="td text-right font-semibold tabular-nums">{{ tk($o->total) }}</td>
                    <td class="td"><x-ops.badge map="order_status" :status="$o->status" /></td><td class="td"><x-ops.badge map="payment_status" :status="$o->payment_status" /></td>
                    <td class="td">{{ $o->shipment?->courier->name ?? '—' }}@if ($o->shipment)<div class="mono text-xs text-ink-muted">{{ $o->shipment->courier_status }}</div>@endif</td>
                </tr>
            @empty <x-ops.empty colspan="7" title="No orders yet" hint="New orders appear here." /> @endforelse
        </x-ops.table>
    </x-ops.card>

    <div class="flex flex-wrap gap-3">
        @php($ch = collect($this->channels)) @php($chTotal = max(1, $ch->sum('v'))) @php($chColor = ['facebook' => 'bg-info', 'website' => 'bg-brand', 'instagram' => 'bg-warn-bar', 'phone' => 'bg-ink-3'])
        <x-ops.card title="Orders by channel" class="flex-[1_1_360px]">
            <div class="flex h-2.5 overflow-hidden rounded bg-soft">@foreach ($ch as $c)<span class="{{ $chColor[$c['source']] ?? 'bg-ink-faint' }}" style="width: {{ $c['v'] / $chTotal * 100 }}%"></span>@endforeach</div>
            <div class="mt-2.5 flex flex-col gap-1.5">@forelse ($ch as $c)<a href="{{ route('ops.orders.index', ['filters' => ['source' => $c['source']]]) }}" wire:navigate class="flex items-center gap-2 text-ink"><span class="h-2 w-2 rounded-sm {{ $chColor[$c['source']] ?? 'bg-ink-faint' }}"></span><span class="flex-1">{{ config('ops.sources.'.$c['source'], ucfirst($c['source'])) }} <span class="text-xs text-ink-muted">· {{ $c['n'] }} orders</span></span><span class="text-xs text-ink-muted">{{ round($c['v'] / $chTotal * 100) }}%</span><span class="w-20 text-right font-semibold tabular-nums">{{ tk($c['v']) }}</span></a>@empty<span class="text-ink-muted">No orders in this range.</span>@endforelse</div>
        </x-ops.card>
        <x-ops.card title="Money" flush class="flex-[1_1_360px]">
            @foreach ([['You will receive · couriers', $k['receivable_courier'], 'ok', route('ops.couriers.index')], ['COD still in courier', $k['cod_in_courier'], 'warn', route('ops.orders.in-courier')], ['You will receive · customers', $k['customer_due'], 'ok', route('ops.customers.index', ['status' => 'due'])], ['You have to pay · suppliers', $k['supplier_due'], 'bad', route('ops.suppliers.index', ['status' => 'due'])]] as [$l, $v, $tone, $href])
                <a href="{{ $href }}" wire:navigate class="flex items-center justify-between border-t border-soft px-3.5 py-2 text-ink first:border-0 hover:bg-zebra"><span class="text-sm">{{ $l }}</span><span class="font-semibold tabular-nums text-{{ $tone }}">{{ tk($v) }}</span></a>
            @endforeach
        </x-ops.card>
    </div>

    <div class="flex flex-wrap items-start gap-3">
        @foreach (['Order status' => ['order_status', $this->orderStatus, 'ops.orders.index'], 'Courier status' => ['courier_status', $this->courierStatus, 'ops.orders.in-courier']] as $title => [$map, $counts, $route])
            @php($max = max(1, max($counts ?: [0])))
            <x-ops.card :title="$title" class="flex-[1_1_360px]">
                <x-slot:actions><span class="text-sm text-ink-muted">{{ ops_num(array_sum($counts)) }} total</span></x-slot:actions>
                @foreach (array_filter(config("ops.$map"), fn ($c) => ($counts[$c] ?? 0) > 0, ARRAY_FILTER_USE_KEY) as $code => [$label, $tone])
                    <a href="{{ route($route, ['status' => $code]) }}" wire:navigate class="flex flex-col gap-1 rounded px-1.5 py-1 text-ink hover:bg-zebra">
                        <span class="flex items-center justify-between text-sm"><span class="truncate text-ink-3">{{ $label }}</span><span class="font-semibold tabular-nums">{{ $counts[$code] }}</span></span>
                        <span class="h-1.5 rounded-full bg-soft"><span class="block h-1.5 min-w-[10px] rounded-full bg-{{ $tone === 'mute' ? 'ink-faint' : $tone }}" style="width: {{ $counts[$code] / $max * 100 }}%"></span></span>
                    </a>
                @endforeach
            </x-ops.card>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-3">
        @php($tp = $this->topProducts) @php($tpMax = max(1, (int) $tp->max('units')))
        <x-ops.card title="Top products" class="flex-[1_1_300px]">
            <div class="flex flex-col gap-2.5">@forelse ($tp as $p)<a href="{{ route('ops.products.edit', $p->id) }}" wire:navigate class="flex flex-col gap-1 text-ink"><span class="flex justify-between text-sm"><span class="truncate font-medium">{{ $p->name }}</span><span class="tabular-nums text-ink-muted">{{ $p->units }} pcs · {{ tk($p->revenue) }}</span></span><span class="h-1.5 rounded bg-soft"><span class="block h-1.5 rounded bg-brand" style="width: {{ $p->units / $tpMax * 100 }}%"></span></span></a>@empty<span class="text-ink-muted">No sales in this range.</span>@endforelse</div>
        </x-ops.card>
        <x-ops.card title="Courier performance" flush class="flex-[1_1_300px]">
            @foreach ($this->couriers as $c)
                @php($closed = $c->delivered + $c->returned)
                <a href="{{ route('ops.couriers.index') }}" wire:navigate class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 border-t border-soft px-3.5 py-2 text-ink first:border-0 hover:bg-zebra"><span class="font-medium">{{ $c->name }}</span><span class="text-sm text-ok">{{ $closed ? round($c->delivered / $closed * 100) : 0 }}%</span><span class="text-sm text-bad">{{ $closed ? round($c->returned / $closed * 100) : 0 }}%</span><span class="w-20 text-right text-sm font-semibold tabular-nums">{{ tk($c->receivable) }}</span></a>
            @endforeach
            <div class="grid grid-cols-[1fr_auto_auto_auto] gap-3 border-t border-soft px-3.5 py-1.5 text-2xs text-ink-muted"><span></span><span>delivered</span><span>returned</span><span class="w-20 text-right">receivable</span></div>
        </x-ops.card>
        @php($s = $this->stock)
        <x-ops.card title="Stock" flush class="flex-[1_1_240px]">
            @foreach ([['Variants', $s['variants'], 'ink', route('ops.products.index')], ['Active batches', $s['batches'], 'ink', route('ops.batches.index')], ['Low stock', $s['low'], 'warn', route('ops.products.index', ['status' => 'low'])], ['Damaged', $s['damaged'], 'bad', route('ops.supplier-returns.index', ['view' => 'pool'])], ['Supplier returns open', $s['supplier_returns'], 'bad', route('ops.supplier-returns.index', ['status' => 'open'])]] as [$l, $v, $tone, $href])
                <a href="{{ $href }}" wire:navigate class="flex items-center justify-between border-t border-soft px-3.5 py-2 text-ink first:border-0 hover:bg-zebra"><span class="text-sm">{{ $l }}</span><span class="font-semibold tabular-nums text-{{ $tone }}">{{ ops_num($v) }}</span></a>
            @endforeach
        </x-ops.card>
    </div>

    <div class="flex flex-wrap gap-3">
        <x-ops.card title="Needs attention" flush class="flex-[1_1_360px]">
            @foreach ($this->attention as [$tone, $msg, $href])
                <{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" wire:navigate @endif class="flex items-center gap-2.5 border-t border-soft px-3.5 py-2.5 text-ink first:border-0 hover:bg-zebra"><span class="h-2 w-2 flex-none rounded-full bg-{{ $tone }}"></span><span class="flex-1">{{ $msg }}</span>@if ($href)<span class="text-ink-faint">→</span>@endif</{{ $href ? 'a' : 'div' }}>
            @endforeach
        </x-ops.card>
        <x-ops.card title="Recent activity" flush class="flex-[1_1_360px]">
            <x-slot:actions><a href="{{ route('ops.settings.index', ['tab' => 'activity']) }}" wire:navigate class="text-sm">All activity →</a></x-slot:actions>
            @forelse ($this->activity as $a)
                <div class="grid grid-cols-[70px_minmax(0,1fr)_auto] gap-2.5 border-t border-soft px-3.5 py-2 text-sm first:border-0"><span class="mono text-xs text-ink-muted">{{ $a->created_at->format('d M H:i') }}</span><span class="truncate">{{ $a->message }}</span><span class="text-xs text-ink-muted">{{ $a->user?->name ?? 'System' }}</span></div>
            @empty <div class="px-3.5 py-4 text-ink-muted">No activity yet.</div> @endforelse
        </x-ops.card>
    </div>
</div>
