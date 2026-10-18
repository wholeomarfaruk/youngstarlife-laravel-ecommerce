<div class="flex flex-col gap-3">
    @php($c = $this->counts) @php($k = $this->kpis)
    <x-ops.kpis class="max-[479px]:grid-cols-2 max-[479px]:gap-1.5">
        <x-ops.kpi label="Orders" :value="ops_num($k['n'])" :hint="$preset === 'all' && ! $from ? 'all time' : 'in range'" />
        <x-ops.kpi label="Order value" :value="tk($k['v'])" :hint="'avg '.tk($k['avg'])" />
        <x-ops.kpi label="COD to collect" :value="tk($k['cod'])" hint="packed · shipped · on hold" tone="warn" />
        <x-ops.kpi label="Awaiting action" :value="$k['waiting']" hint="to confirm · pack · hand over" tone="info" />
    </x-ops.kpis>
    <section class="card">
        <div class="border-b border-soft px-3 py-2 max-[479px]:overflow-x-auto max-[479px]:[scrollbar-width:none] max-[479px]:[&>div]:flex-nowrap max-[479px]:[&_button]:flex-none">
            <x-ops.chips wire:model.live="status" :options="collect(config('ops.order_tabs') + ['exchange' => 'Exchanges'])->mapWithKeys(fn ($l, $key) => [$key => [$l, $key === 'all' ? array_sum(\Illuminate\Support\Arr::except($c, 'exchange')) : ($c[$key] ?? 0)]])->all()" />
        </div>
        @if (str_starts_with($status, 'group:') && ($grp = \App\Livewire\Ops\Dashboard::GROUPS[substr($status, 6)] ?? null))
            <div class="flex items-center gap-2 border-b border-soft bg-brand-tint px-3 py-2 text-sm">Showing <b>{{ $grp[0] }}</b> <span class="text-ink-muted">({{ collect($grp[1])->map(fn ($s) => config("ops.order_status.$s.0"))->join(', ') }})</span><button wire:click="$set('status','all')" class="ml-auto text-brand">Clear</button></div>
        @endif
        @php($activeFilters = collect(['source', 'courier', 'payment_status', 'risky'])->filter(fn ($f) => filled($filters[$f] ?? null))->count())
        <div x-data="{ more: false }">
        <x-ops.toolbar>
            <x-ops.search placeholder="Order no., customer, phone, tracking, area…" />
            <button type="button" @click="more = ! more" class="btn-secondary btn-sm min-[480px]:hidden">Filters @if ($activeFilters)<b class="rounded-full bg-ink px-1.5 text-2xs text-white">{{ $activeFilters }}</b>@endif <span x-text="more ? '▴' : '▾'">▾</span></button>
            <div class="contents max-[479px]:hidden" :class="more && 'max-[479px]:!grid w-full grid-cols-2 gap-2'">
            <select class="input w-auto max-[479px]:w-full" wire:model.live="filters.source"><option value="">All channels</option>@foreach (config('ops.sources') as $key => $l)<option value="{{ $key }}">{{ $l }}</option>@endforeach</select>
            <select class="input w-auto max-[479px]:w-full" wire:model.live="filters.courier"><option value="">All couriers</option><option value="none">Not booked</option>@foreach ($couriers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
            <select class="input w-auto max-[479px]:w-full" wire:model.live="filters.payment_status"><option value="">Any payment</option>@foreach (config('ops.payment_status') as $key => [$l]) <option value="{{ $key }}">{{ $l }}</option> @endforeach</select>
            <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" wire:model.live="filters.risky" value="1" class="accent-bad"> Risky only</label>
            @if ($activeFilters)<button type="button" wire:click="$set('filters', [])" class="text-sm text-brand min-[480px]:hidden">Clear filters</button>@endif
            </div>
        </x-ops.toolbar>
        </div>
        <div class="flex flex-wrap items-center gap-1.5 border-b border-soft px-3 py-2 max-[479px]:flex-nowrap max-[479px]:overflow-x-auto max-[479px]:[scrollbar-width:none] max-[479px]:[&>*]:flex-none">
            @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', 'month' => 'This month', 'last_month' => 'Last month', 'all' => 'All time'] as $key => $l)<button wire:click="$set('preset','{{ $key }}')" @class(['chip', 'chip-on' => $preset === $key])>{{ $l }}</button>@endforeach
            <x-ops.date-range />
            <span class="ml-auto text-sm text-ink-muted">{{ ops_num($this->orders->total()) }} orders</span>
        </div>
        <div class="max-[479px]:sticky max-[479px]:bottom-[calc(64px+env(safe-area-inset-bottom))] max-[479px]:z-30 max-[479px]:shadow-pop">
        <x-ops.bulk-bar :count="count($selected)">
            <button wire:click="bulkStatus('confirmed')" class="btn-sm rounded bg-white text-ink">Confirm</button>
            <a href="{{ route('ops.orders.print', ['ids' => implode(',', $selected)]) }}" target="_blank" class="btn-sm rounded bg-side-hover text-white hover:text-white">Print invoices</a>
            <button x-data @click="$dispatch('open-modal','book-courier')" class="btn-sm rounded bg-side-hover">Book courier</button>
            <a href="{{ route('ops.orders.bulk-courier', ['add' => implode(',', $selected)]) }}" wire:navigate class="btn-sm rounded bg-side-hover text-white hover:text-white">Courier sheet</a>
            <button wire:click="export" class="btn-sm rounded bg-side-hover">Export</button>
        </x-ops.bulk-bar>
        </div>

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
                        <td class="td"><div class="font-medium">{{ $o->shipment?->courier->name ?? '—' }}</div>@if ($o->shipment)<x-ops.parcel-ref :s="$o->shipment" empty="" class="text-xs text-ink-muted" />@endif</td>
                        <td class="td"><div class="flex max-w-[200px] flex-wrap gap-1">@foreach ($o->tags ?? [] as $t)<x-ops.badge :tone="$t === 'Advance required' ? 'bad' : ($t === 'Auto-confirmed' ? 'ok' : 'info')">{{ $t }}</x-ops.badge>@endforeach</div></td>
                        <td class="td">@include('livewire.ops.orders.partials.row-menu', ['o' => $o])</td>
                    </tr>
                @empty
                    <x-ops.empty colspan="9"><button wire:click="clearFilters" class="btn-secondary btn-sm">Clear filters</button></x-ops.empty>
                @endforelse
            </x-ops.table>
        </div>

        {{-- Phone cards: select · ⋯ actions · call / WhatsApp · courier + tracking --}}
        <div class="min-[480px]:hidden">
            @php($sortNow = ($sortField === 'id' ? 'placed_at' : $sortField).':'.$sortDir)
            <div class="flex items-center gap-2 border-b border-soft px-3 py-2">
                <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" class="h-5 w-5 accent-ink" aria-label="Select all on this page"
                    x-on:change="$wire.set('selected', $event.target.checked ? @js($this->orders->pluck('id')->map(fn ($i) => (string) $i)->all()) : [])"> Select page</label>
                <select class="input ml-auto w-auto py-1 text-sm" aria-label="Sort" x-on:change="$wire.sortList($event.target.value)">
                    @foreach (['placed_at:desc' => 'Newest first', 'placed_at:asc' => 'Oldest first', 'total:desc' => 'Highest amount', 'total:asc' => 'Lowest amount'] as $v => $l)<option value="{{ $v }}" @selected($sortNow === $v)>{{ $l }}</option>@endforeach
                </select>
            </div>
            @forelse ($this->orders as $o)
                @php($rate = $o->customer->delivery_rate) @php($wa = preg_replace('/\D/', '', (string) \App\Support\Phone::toE164($o->customer->phone)))
                <div wire:key="m{{ $o->id }}" @class(['flex gap-2.5 border-b border-soft p-3', 'bg-brand-tint' => in_array((string) $o->id, $selected)])>
                    <input type="checkbox" value="{{ $o->id }}" wire:model.live="selected" class="mt-1 h-5 w-5 flex-none accent-ink" aria-label="Select {{ $o->number }}">
                    <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('ops.orders.show', $o) }}" wire:navigate class="mono text-md font-semibold text-brand">{{ $o->number }}</a>
                            <span class="min-w-0 flex-1 truncate text-xs text-ink-muted">{{ $o->placed_at->format('d M · H:i') }} · {{ config('ops.sources.'.$o->source, ucfirst($o->source)) }}</span>
                            @include('livewire.ops.orders.partials.row-menu', ['o' => $o])
                        </div>
                        <a href="{{ route('ops.orders.show', $o) }}" wire:navigate class="flex justify-between gap-2 text-ink">
                            <span class="min-w-0"><span class="block truncate text-md font-semibold">{{ $o->customer->name }}</span>
                                <span class="block truncate text-xs text-ink-muted">{{ $o->customer->area ?: '—' }}@if ($rate !== null) · <span @class(['font-semibold', 'text-bad' => $rate < .75, 'text-ok' => $rate >= .9])>{{ round($rate * 100) }}% delivered</span>@endif</span></span>
                            <span class="text-right"><span class="block text-lg font-bold tabular-nums">{{ tk($o->total) }}</span><span class="block text-xs text-ink-muted">COD {{ tk($o->cod) }}</span></span>
                        </a>
                        @if ($o->customer->phone)
                            <div class="flex items-center gap-1.5 text-sm">
                                <span class="mono min-w-0 flex-1 truncate text-ink-3">{{ $o->customer->phone }}</span>
                                <a href="tel:{{ $o->customer->phone }}" class="btn-secondary btn-sm">Call</a>
                                @if ($wa)<a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="btn-secondary btn-sm">WhatsApp</a>@endif
                            </div>
                        @endif
                        <div class="flex flex-wrap gap-1.5"><x-ops.badge map="order_status" :status="$o->status" />@include('livewire.ops.orders.partials.exchange-stages', ['o' => $o])<x-ops.badge map="payment_status" :status="$o->payment_status" />@foreach ($o->tags ?? [] as $t)<x-ops.badge :tone="$t === 'Advance required' ? 'bad' : ($t === 'Auto-confirmed' ? 'ok' : 'info')">{{ $t }}</x-ops.badge>@endforeach</div>
                        @if ($o->shipment)
                            <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-ink-muted">
                                <span class="font-medium text-ink-3">{{ $o->shipment->courier?->name }}</span>
                                <span class="mono">{{ $o->shipment->code }}</span>
                                <x-ops.parcel-ref :s="$o->shipment" inline empty="" />
                                <x-ops.badge map="courier_status" :status="$o->shipment->courier_status" />
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-sm text-ink-muted">No orders match. <button wire:click="clearFilters" class="text-brand">Clear filters</button></div>
            @endforelse
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
