<div class="flex flex-col gap-3">
    @php($c = $this->counts) @php($k = $this->kpis)
    <x-ops.kpis>
        <x-ops.kpi label="Orders" :value="ops_num($k['n'])" :hint="$preset === 'all' && ! $from ? 'all time' : 'in range'" />
        <x-ops.kpi label="Order value" :value="tk($k['v'])" :hint="'avg '.tk($k['avg'])" />
        <x-ops.kpi label="COD to collect" :value="tk($k['cod'])" hint="packed · shipped · on hold" tone="warn" />
        <x-ops.kpi label="Awaiting action" :value="$k['waiting']" hint="to confirm · pack · hand over" tone="info" />
    </x-ops.kpis>
    <section class="card">
        <div class="border-b border-soft px-3 py-2">
            <x-ops.chips wire:model.live="status" :options="collect(config('ops.order_tabs') + ['exchange' => 'Exchanges'])->mapWithKeys(fn ($l, $key) => [$key => [$l, $key === 'all' ? array_sum(\Illuminate\Support\Arr::except($c, 'exchange')) : ($c[$key] ?? 0)]])->all()" />
        </div>
        @if (str_starts_with($status, 'group:') && ($grp = \App\Livewire\Ops\Dashboard::GROUPS[substr($status, 6)] ?? null))
            <div class="flex items-center gap-2 border-b border-soft bg-brand-tint px-3 py-2 text-sm">Showing <b>{{ $grp[0] }}</b> <span class="text-ink-muted">({{ collect($grp[1])->map(fn ($s) => config("ops.order_status.$s.0"))->join(', ') }})</span><button wire:click="$set('status','all')" class="ml-auto text-brand">Clear</button></div>
        @endif
        <x-ops.toolbar>
            <x-ops.search placeholder="Order no., customer, phone, tracking, area…" />
            <select class="input w-auto" wire:model.live="filters.source"><option value="">All channels</option>@foreach (config('ops.sources') as $key => $l)<option value="{{ $key }}">{{ $l }}</option>@endforeach</select>
            <select class="input w-auto" wire:model.live="filters.courier"><option value="">All couriers</option><option value="none">Not booked</option>@foreach ($couriers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
            <select class="input w-auto" wire:model.live="filters.payment_status"><option value="">Any payment</option>@foreach (config('ops.payment_status') as $key => [$l]) <option value="{{ $key }}">{{ $l }}</option> @endforeach</select>
            <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" wire:model.live="filters.risky" value="1" class="accent-bad"> Risky only</label>
        </x-ops.toolbar>
        <div class="flex flex-wrap items-center gap-1.5 border-b border-soft px-3 py-2">
            @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', 'month' => 'This month', 'last_month' => 'Last month', 'all' => 'All time'] as $key => $l)<button wire:click="$set('preset','{{ $key }}')" @class(['chip', 'chip-on' => $preset === $key])>{{ $l }}</button>@endforeach
            <x-ops.date-range />
            <span class="ml-auto text-sm text-ink-muted">{{ ops_num($this->orders->total()) }} orders</span>
        </div>
        <x-ops.bulk-bar :count="count($selected)">
            <button wire:click="bulkStatus('confirmed')" class="btn-sm rounded bg-white text-ink">Confirm</button>
            <button wire:click="bulkStatus('processing')" class="btn-sm rounded bg-side-hover">Processing</button>
            <a href="{{ route('ops.orders.print', ['ids' => implode(',', $selected)]) }}" target="_blank" class="btn-sm rounded bg-side-hover text-white hover:text-white">Print invoices</a>
            <button x-data @click="$dispatch('open-modal','book-courier')" class="btn-sm rounded bg-side-hover">Book courier</button>
            <a href="{{ route('ops.orders.bulk-courier', ['add' => implode(',', $selected)]) }}" wire:navigate class="btn-sm rounded bg-side-hover text-white hover:text-white">Courier sheet</a>
            <button wire:click="export" class="btn-sm rounded bg-side-hover">Export</button>
        </x-ops.bulk-bar>

        {{-- Desktop table --}}
        <div class="max-[479px]:hidden">
            <x-ops.table min-width="1060px">
                <x-slot:head>
                    <th class="th w-7"></th><x-ops.th sort="placed_at">Order</x-ops.th><x-ops.th>Customer</x-ops.th><x-ops.th sort="total" align="right">Amount</x-ops.th>
                    <x-ops.th>Payment</x-ops.th><x-ops.th>Status</x-ops.th><x-ops.th>Courier</x-ops.th><x-ops.th>Tags</x-ops.th><th class="th w-8"></th>
                </x-slot:head>
                @forelse ($this->orders as $o)
                    @php($rate = $o->customer->delivery_rate)
                    <tr wire:key="o{{ $o->id }}" @class(['tr-hover', 'bg-brand-tint' => in_array((string) $o->id, $selected)])>
                        <td class="td"><input type="checkbox" value="{{ $o->id }}" wire:model.live="selected" class="h-4 w-4 accent-ink"></td>
                        <td class="td"><a href="{{ route('ops.orders.show', $o) }}" wire:navigate class="mono font-semibold">{{ $o->number }}</a><div class="text-xs text-ink-muted">{{ $o->placed_at->format('d M · H:i') }} · {{ config('ops.sources.'.$o->source, ucfirst($o->source)) }}</div></td>
                        <td class="td"><div class="font-medium">{{ $o->customer->name }}</div><div class="text-xs text-ink-muted">{{ $o->customer->phone }} · {{ $o->customer->area }}@if ($rate !== null) · <span @class(['font-semibold', 'text-bad' => $rate < .75, 'text-ok' => $rate >= .9])>{{ round($rate * 100) }}%</span>@endif</div></td>
                        <td class="td text-right tabular-nums"><div class="font-semibold">{{ tk($o->total) }}</div><div class="text-xs text-ink-muted">COD {{ tk($o->cod) }}</div></td>
                        <td class="td"><x-ops.badge map="payment_status" :status="$o->payment_status" /></td>
                        <td class="td"><x-ops.badge map="order_status" :status="$o->status" />@include('livewire.ops.orders.partials.exchange-stages', ['o' => $o])</td>
                        <td class="td"><div class="font-medium">{{ $o->shipment?->courier->name ?? '—' }}</div><div class="mono text-xs text-ink-muted">{{ $o->shipment?->tracking }}</div></td>
                        <td class="td"><div class="flex max-w-[200px] flex-wrap gap-1">@foreach ($o->tags ?? [] as $t)<x-ops.badge :tone="$t === 'Advance required' ? 'bad' : ($t === 'Auto-confirmed' ? 'ok' : 'info')">{{ $t }}</x-ops.badge>@endforeach</div></td>
                        <td class="td">@include('livewire.ops.orders.partials.row-menu', ['o' => $o])</td>
                    </tr>
                @empty
                    <x-ops.empty colspan="9"><button wire:click="clearFilters" class="btn-secondary btn-sm">Clear filters</button></x-ops.empty>
                @endforelse
            </x-ops.table>
        </div>

        {{-- Phone cards --}}
        <div class="min-[480px]:hidden">
            @foreach ($this->orders as $o)
                <a href="{{ route('ops.orders.show', $o) }}" wire:navigate wire:key="m{{ $o->id }}" class="flex flex-col gap-2 border-b border-soft p-3 text-ink">
                    <div class="flex items-center gap-2"><span class="mono text-md font-semibold text-brand">{{ $o->number }}</span><span class="flex-1 truncate text-xs text-ink-muted">{{ $o->placed_at->format('d M · H:i') }} · {{ ucfirst($o->source) }}</span></div>
                    <div class="flex justify-between gap-2"><div class="min-w-0"><div class="text-md font-semibold">{{ $o->customer->name }}</div><div class="text-sm text-ink-muted">{{ $o->customer->phone }}</div></div><div class="text-right"><div class="text-lg font-bold">{{ tk($o->total) }}</div><div class="text-xs text-ink-muted">COD {{ tk($o->cod) }}</div></div></div>
                    <div class="flex flex-wrap gap-1.5"><x-ops.badge map="order_status" :status="$o->status" />@include('livewire.ops.orders.partials.exchange-stages', ['o' => $o])<x-ops.badge map="payment_status" :status="$o->payment_status" />@foreach ($o->tags ?? [] as $t)<x-ops.badge tone="info">{{ $t }}</x-ops.badge>@endforeach</div>
                </a>
            @endforeach
        </div>
        <x-ops.pagination :paginator="$this->orders" />
    </section>

    <livewire:ops.orders.book-courier />
    <x-ops.modal name="book-courier" title="Book courier">
        <p class="mb-3 text-sm text-ink-muted">{{ count($selected) }} orders. Packed parcels move to this courier; other orders are booked with it when packed.</p>
        <x-ops.field label="Courier" required :error="$errors->first('bookCourierId')"><select wire:model="bookCourierId" class="input"><option value="">Choose…</option>@foreach ($couriers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-ops.field>
        <label class="mt-3 flex items-start gap-2 text-sm"><input type="checkbox" wire:model="bookSend" class="mt-0.5 accent-brand"> <span>Send packed parcels to the courier now <span class="block text-xs text-ink-muted">Through the Steadfast / Pathao API (Settings → Couriers). Pathao parcels need a known city & zone — the customer's last one or a clear match in the address; the rest stay for the order page.</span></span></label>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="bookCourier" wire:loading.attr="disabled" class="btn-primary"><span wire:loading.remove wire:target="bookCourier">Book</span><span wire:loading wire:target="bookCourier">Sending…</span></button></x-slot:footer>
    </x-ops.modal>
    <x-ops.modal name="book-failed" title="Not sent to the courier" width="max-w-lg">
        <p class="mb-2 text-sm text-ink-muted">Fix these on the order page (Shipment → Send) — the reason is also in Settings → Couriers → API log.</p>
        <div class="flex flex-col divide-y divide-soft rounded-md border border-line text-sm">
            @foreach ($bookFailed as $code => $why)<a href="{{ route('ops.trace', ['q' => $code]) }}" wire:navigate class="flex gap-3 px-3 py-2 text-ink hover:bg-zebra"><span class="mono font-semibold">{{ $code }}</span><span class="flex-1 text-bad">{{ $why }}</span></a>@endforeach
        </div>
    </x-ops.modal>
</div>
