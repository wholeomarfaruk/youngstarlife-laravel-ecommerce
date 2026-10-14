<div class="flex flex-col gap-3" x-data="{
        booking: false, stop: false, current: null, done: 0, total: 0, ok: 0, failed: 0, finished: false,
        async bookAll(risky) {
            if (this.booking) return;
            const ids = await $wire.bookableIds();
            if (!ids.length) return;
            const ok = await opsConfirm({ title: `Book ${ids.length} parcel(s)?`, text: 'Each row goes to the courier picked on it.' + (risky ? ` ${risky} row(s) are risky customers (under 75% delivered).` : ''), confirm: 'Book all', danger: !!risky });
            if (ok) this.run(ids);
        },
        bookOne(id) { if (!this.booking) this.run([id]); },
        async run(ids) {
            Object.assign(this, { booking: true, stop: false, finished: false, done: 0, ok: 0, failed: 0, total: ids.length });
            for (const id of ids) {
                if (this.stop) break;
                this.current = id;
                try { const r = await $wire.bookItem(id); r && r.ok ? this.ok++ : this.failed++; } catch (e) { this.failed++; }
                this.done++;
            }
            Object.assign(this, { booking: false, current: null, finished: true });
        },
    }">

    @if ($couriers->isEmpty())
        <div class="rounded-md border border-warn/30 bg-warn-bg px-3 py-2 text-sm text-warn">No active courier can book through its API yet — set one up in Settings → Couriers.</div>
    @endif

    {{-- ══════════ Ready to ship ══════════ --}}
    <section class="card">
        @php($addable = $orders->getCollection()->reject(fn ($o) => in_array($o->id, $sheetIds) || $lockedByOthers->has($o->id))->pluck('id'))
        <div class="flex flex-wrap items-center gap-2 border-b border-soft px-3 py-2">
            <span class="mr-auto font-semibold">Ready to ship <span class="text-sm font-normal text-ink-muted">{{ ops_num($orders->total()) }} not with a courier</span></span>
            <input wire:model.live.debounce.400ms="search" class="input w-56" placeholder="Order no., name, phone…">
            <select wire:model.live="filterStatus" class="input w-auto"><option value="">All statuses</option>@foreach (['pending', 'confirmed', 'processing', 'packed'] as $st)<option value="{{ $st }}">{{ config("ops.order_status.$st.0") }}</option>@endforeach</select>
            <select wire:model.live="filterSource" class="input w-auto"><option value="">All channels</option>@foreach (config('ops.sources') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            @if ($search !== '' || $filterStatus !== '' || $filterSource !== '')<button wire:click="clearFilters" class="text-sm text-brand">Clear</button>@endif
            <button wire:click="addPage('{{ $addable->implode(',') }}')" @disabled($addable->isEmpty()) class="btn-secondary btn-sm">+ Add all on this page ({{ $addable->count() }})</button>
        </div>
        <div class="max-h-[420px] overflow-auto">
            <x-ops.table min-width="900px">
                <x-slot:head><th class="th w-12"></th><x-ops.th>Order</x-ops.th><x-ops.th>Customer</x-ops.th><x-ops.th>Address</x-ops.th><x-ops.th align="right">Items</x-ops.th><x-ops.th align="right">COD</x-ops.th><x-ops.th>Status</x-ops.th></x-slot:head>
                @forelse ($orders as $o)
                    @php($in = in_array($o->id, $sheetIds)) @php($by = $lockedByOthers->get($o->id)) @php($rate = $o->customer?->delivery_rate)
                    <tr wire:key="ready-{{ $o->id }}" @class(['tr-hover', 'bg-brand-tint' => $in])>
                        <td class="td">
                            @if ($in)<span class="grid h-7 w-7 place-items-center rounded-full bg-brand-tint text-brand" title="On the sheet">✓</span>
                            @elseif ($by)<span class="grid h-7 w-7 place-items-center rounded-full bg-soft text-ink-muted" title="On {{ $by }}’s sheet">🔒</span>
                            @else<button wire:click="addOrder({{ $o->id }})" wire:loading.attr="disabled" wire:target="addOrder({{ $o->id }})" class="grid h-7 w-7 place-items-center rounded-full bg-brand text-lg leading-none text-white" title="Add to sheet">+</button>@endif
                        </td>
                        <td class="td whitespace-nowrap"><a href="{{ route('ops.orders.show', $o) }}" wire:navigate class="mono font-semibold">{{ $o->number }}</a><div class="text-xs text-ink-muted">{{ $o->placed_at->format('d M · H:i') }} · {{ config('ops.sources.'.$o->source, ucfirst($o->source)) }}</div></td>
                        <td class="td"><div class="font-medium">{{ $o->ship_name }}</div><div class="text-xs text-ink-muted">{{ $o->ship_phone }}@if ($rate !== null) · <span @class(['font-semibold', 'text-bad' => $rate < .75, 'text-ok' => $rate >= .9])>{{ round($rate * 100) }}%</span>@endif</div></td>
                        <td class="td max-w-xs"><div class="line-clamp-2 text-xs text-ink-3">{{ $o->ship_address }}</div></td>
                        <td class="td text-right tabular-nums">{{ (int) $o->qty }}</td>
                        <td class="td text-right font-semibold tabular-nums">{{ tk($o->cod) }}</td>
                        <td class="td"><x-ops.badge map="order_status" :status="$o->status" />@if ($by)<div class="text-[11px] text-ink-muted">on {{ $by }}’s sheet</div>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="td py-10 text-center text-ink-muted">No orders waiting for a courier.</td></tr>
                @endforelse
            </x-ops.table>
        </div>
        <x-ops.pagination :paginator="$orders" />
    </section>

    {{-- ══════════ Courier sheet ══════════ --}}
    <section class="card">
        <div class="flex flex-col gap-2 border-b border-soft px-3 py-2.5">
            <div class="flex flex-wrap items-center gap-2">
                <span class="mr-auto font-semibold">Courier sheet <x-ops.badge tone="warn">Draft · auto-saved</x-ops.badge></span>
                <select wire:model="bulkCourierId" class="input w-auto"><option value="">Courier for all rows…</option>@foreach ($couriers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                <button wire:click="applyCourierToAll" class="btn-secondary btn-sm">Apply</button>
                @if ($stats['booked'])<button x-on:click="opsConfirm({ title: 'Complete this sheet?', text: 'Booked rows go to history; anything not booked stays on a new sheet.', confirm: 'Complete' }).then(ok => ok && $wire.completeSheet())" class="btn-secondary btn-sm">Complete sheet</button>@endif
                @if ($stats['open'])<button x-on:click="opsConfirm({ title: 'Clear the sheet?', text: 'Every unbooked row is removed from the sheet (the orders are not touched).', confirm: 'Clear', danger: true }).then(ok => ok && $wire.clearSheet())" class="btn-sm text-ink-muted hover:text-bad">Clear</button>@endif
                <button x-show="!booking" x-on:click="bookAll({{ $stats['risky'] }})" @disabled(! $stats['open'] || $couriers->isEmpty()) class="btn-primary">Book all ({{ $stats['open'] }})</button>
                <button x-show="booking" x-cloak x-on:click="stop = true" class="btn-secondary text-bad"><span x-text="stop ? 'Stopping…' : 'Stop'"></span></button>
            </div>
            <div class="flex flex-wrap gap-1.5 text-xs">
                <span class="rounded bg-zebra px-2 py-1"><b>{{ $stats['open'] }}</b> to book</span>
                <span class="rounded bg-zebra px-2 py-1">COD <b>{{ tk($stats['cod']) }}</b></span>
                @if ($stats['booked'])<span class="rounded bg-ok-bg px-2 py-1 text-ok"><b>{{ $stats['booked'] }}</b> booked</span>@endif
                @if ($stats['failed'])<span class="rounded bg-bad-bg px-2 py-1 text-bad"><b>{{ $stats['failed'] }}</b> failed</span>@endif
                @if ($stats['risky'])<span class="rounded bg-bad-bg px-2 py-1 text-bad"><b>{{ $stats['risky'] }}</b> risky customer(s)</span>@endif
                @foreach ($stats['byCourier'] as $name => $n)<span class="rounded bg-brand-tint px-2 py-1 text-brand">{{ $name }}: <b>{{ $n }}</b></span>@endforeach
            </div>
            <div x-show="booking || finished" x-cloak class="rounded-md border px-3 py-2" :class="booking ? 'border-line bg-zebra' : (failed ? 'border-warn/30 bg-warn-bg' : 'border-ok/30 bg-ok-bg')">
                <div class="flex justify-between text-xs"><span class="font-medium" x-text="booking ? `Booking ${done + 1} of ${total}…` : `Done — ${ok} booked, ${failed} failed`"></span><button x-show="!booking" x-on:click="finished = false; $wire.$refresh()" class="text-ink-muted">Dismiss</button></div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white"><div class="h-full rounded-full bg-brand transition-all" :style="`width: ${total ? Math.round(done / total * 100) : 0}%`"></div></div>
            </div>
        </div>

        {{-- wide grid: scrolls sideways; # + Order pinned left, actions right --}}
        <div class="courier-sheet max-h-[70vh] overflow-auto">
            <table class="table-fixed border-separate border-spacing-0 text-sm" style="width: 1760px">
                <colgroup><col style="width:40px"><col style="width:96px"><col style="width:160px"><col style="width:130px"><col style="width:290px"><col style="width:90px"><col style="width:70px"><col style="width:60px"><col style="width:140px"><col style="width:250px"><col style="width:180px"><col style="width:120px"><col style="width:134px"></colgroup>
                <thead class="sticky top-0 z-20"><tr class="whitespace-nowrap text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                    <th class="sticky left-0 z-30 border-b border-line bg-zebra px-2 py-2">#</th>
                    <th class="sticky z-30 border-b border-r border-line bg-zebra px-2 py-2" style="left:40px">Order</th>
                    <th class="border-b border-line bg-zebra px-2 py-2">Name <span class="text-bad">*</span></th>
                    <th class="border-b border-line bg-zebra px-2 py-2">Phone <span class="text-bad">*</span></th>
                    <th class="border-b border-line bg-zebra px-2 py-2">Address <span class="text-bad">*</span></th>
                    <th class="border-b border-line bg-zebra px-2 py-2 text-right">COD</th>
                    <th class="border-b border-line bg-zebra px-2 py-2 text-right">Kg</th>
                    <th class="border-b border-line bg-zebra px-2 py-2 text-right">Qty</th>
                    <th class="border-b border-line bg-zebra px-2 py-2">Courier <span class="text-bad">*</span></th>
                    <th class="border-b border-line bg-zebra px-2 py-2">Items</th>
                    <th class="border-b border-line bg-zebra px-2 py-2">Note for rider</th>
                    <th class="border-b border-line bg-zebra px-2 py-2">Result</th>
                    <th class="sticky right-0 z-30 border-b border-l border-line bg-zebra px-2 py-2"></th>
                </tr></thead>
                <tbody>
                    @forelse ($items as $i)
                        @php($k = 'r'.$i->id) @php($booked = $i->isBooked()) @php($failed = $i->status === 'failed')
                        @php($bg = $booked ? 'bg-ok-bg' : ($failed ? 'bg-bad-bg' : 'bg-white')) @php($td = 'border-b border-soft px-1 py-1')
                        <tr wire:key="sheet-{{ $i->id }}">
                            <td class="sticky left-0 z-10 border-b border-soft px-2 text-xs text-ink-muted {{ $bg }}" :class="current === {{ $i->id }} && '!bg-brand-tint'">{{ $loop->iteration }}</td>
                            <td class="sticky z-10 whitespace-nowrap border-b border-r border-soft px-2 py-1 {{ $bg }}" style="left:40px" :class="current === {{ $i->id }} && '!bg-brand-tint'">
                                <a href="{{ route('ops.orders.show', $i->order_id) }}" wire:navigate class="mono font-semibold">{{ $i->order?->number }}</a>
                                <div class="text-[11px] leading-tight text-ink-muted">{{ config('ops.order_status.'.$i->order?->status.'.0') }}@if ($risky($i->order)) · <span class="font-semibold text-bad">risky</span>@endif</div>
                            </td>
                            <td class="{{ $td }} {{ $bg }}"><input wire:model.blur="rows.{{ $k }}.name" @disabled($booked) class="cell"></td>
                            <td class="{{ $td }} {{ $bg }}"><input wire:model.blur="rows.{{ $k }}.phone" inputmode="tel" placeholder="01XXXXXXXXX" @disabled($booked) class="cell mono"></td>
                            <td class="{{ $td }} {{ $bg }}"><input wire:model.blur="rows.{{ $k }}.address" title="{{ $i->address }}" @disabled($booked) class="cell"></td>
                            <td class="{{ $td }} {{ $bg }}"><input wire:model.blur="rows.{{ $k }}.cod" type="number" min="0" @disabled($booked) class="cell text-right tabular-nums"></td>
                            <td class="{{ $td }} {{ $bg }}"><input wire:model.blur="rows.{{ $k }}.weight" type="number" min="0.1" step="0.1" @disabled($booked) class="cell text-right tabular-nums"></td>
                            <td class="{{ $td }} {{ $bg }}"><input wire:model.blur="rows.{{ $k }}.qty" type="number" min="1" @disabled($booked) class="cell text-right tabular-nums"></td>
                            <td class="{{ $td }} {{ $bg }}">
                                <select wire:model.live="rows.{{ $k }}.courier_id" @disabled($booked) @class(['cell', '!border-warn' => ! $i->courier_id])>
                                    <option value="">— Pick —</option>
                                    @foreach ($couriers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                    @if ($i->courier && ! $couriers->contains('id', $i->courier_id))<option value="{{ $i->courier_id }}">{{ $i->courier->name }} (no API)</option>@endif
                                </select>
                            </td>
                            <td class="{{ $td }} {{ $bg }}"><input wire:model.blur="rows.{{ $k }}.description" title="{{ $i->description }}" @disabled($booked) class="cell text-xs"></td>
                            <td class="{{ $td }} {{ $bg }}"><input wire:model.blur="rows.{{ $k }}.note" placeholder="Optional" @disabled($booked) class="cell text-xs"></td>
                            <td class="border-b border-soft px-2 py-1 {{ $bg }}">
                                <span x-show="current === {{ $i->id }}" x-cloak class="text-xs text-brand">Booking…</span>
                                <div x-show="current !== {{ $i->id }}" class="min-w-0">
                                    @if ($booked)
                                        <div class="mono truncate text-xs font-semibold text-ok select-all" title="{{ $i->tracking }}">✓ {{ $i->tracking }}</div>
                                        <div class="truncate text-[11px] text-ink-muted">{{ $i->courier?->name }} · {{ $i->booked_at?->format('H:i') }}</div>
                                    @elseif ($failed)
                                        <x-ops.badge tone="bad">Failed</x-ops.badge>
                                        <div class="truncate text-[11px] text-bad" title="{{ $i->error }}">{{ $i->error }}</div>
                                    @else<x-ops.badge>Ready</x-ops.badge>@endif
                                </div>
                            </td>
                            <td class="sticky right-0 z-10 border-b border-l border-soft px-1.5 py-1 {{ $bg }}" :class="current === {{ $i->id }} && '!bg-brand-tint'">
                                @unless ($booked)
                                    <div class="flex items-center justify-end gap-1">
                                        <button x-on:click="bookOne({{ $i->id }})" :disabled="booking" class="btn-primary btn-sm">{{ $failed ? 'Retry' : 'Book' }}</button>
                                        <button x-on:click="opsConfirm({ title: 'Re-fill this row from the order?', text: 'Your edits on this row are lost.', confirm: 'Re-fill' }).then(ok => ok && $wire.resetItem({{ $i->id }}))" :disabled="booking" class="grid h-7 w-7 place-items-center rounded text-ink-muted hover:bg-soft" title="Re-fill from the order">↺</button>
                                        <button wire:click="removeItem({{ $i->id }})" :disabled="booking" class="grid h-7 w-7 place-items-center rounded text-ink-muted hover:bg-bad-bg hover:text-bad" title="Remove from sheet">✕</button>
                                    </div>
                                @else
                                    <a href="{{ route('ops.orders.show', $i->order_id) }}" wire:navigate class="block text-right text-xs font-medium text-ok">View order →</a>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="px-4 py-12 text-center text-sm text-ink-muted">The sheet is empty — click <b class="text-brand">+</b> on an order above to add it here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ══════════ Completed sheets ══════════ --}}
    @if ($history->isNotEmpty())
        <section class="card">
            <div class="border-b border-soft px-3 py-2 font-semibold">Completed sheets</div>
            <div class="divide-y divide-soft">
                @foreach ($history as $b)
                    <div wire:key="hist-{{ $b->id }}">
                        <button wire:click="showHistory({{ $historyId === $b->id ? 'null' : $b->id }})" class="flex w-full flex-wrap items-center gap-x-6 gap-y-1 px-3 py-2.5 text-left text-sm hover:bg-zebra">
                            <span class="font-medium">Sheet #{{ $b->id }}</span><span class="text-ink-muted">{{ $b->booked_count }} booked</span><span class="text-ink-muted">COD {{ tk((int) $b->booked_cod) }}</span>
                            <span class="ml-auto text-xs text-ink-muted">{{ $b->creator?->name }} · {{ $b->completed_at?->format('d M Y, H:i') }}</span>
                        </button>
                        @if ($historyId === $b->id)
                            <div class="overflow-x-auto px-3 pb-3">
                                <table class="min-w-full text-xs">
                                    <thead><tr class="text-left uppercase text-ink-muted"><th class="py-1.5 pr-4">Order</th><th class="py-1.5 pr-4">Recipient</th><th class="py-1.5 pr-4">Courier</th><th class="py-1.5 pr-4">Tracking</th><th class="py-1.5 text-right">COD</th></tr></thead>
                                    <tbody class="divide-y divide-soft">
                                        @foreach ($historyItems as $h)
                                            <tr><td class="py-1.5 pr-4"><a href="{{ route('ops.orders.show', $h->order_id) }}" wire:navigate class="mono">{{ $h->order?->number ?? '#'.$h->order_id }}</a></td><td class="py-1.5 pr-4">{{ $h->name }} <span class="text-ink-muted">{{ $h->phone }}</span></td><td class="py-1.5 pr-4">{{ $h->courier?->name }}</td><td class="mono py-1.5 pr-4">{{ $h->tracking ?? '—' }}</td><td class="py-1.5 text-right tabular-nums">{{ tk($h->cod) }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <style>
        .courier-sheet .cell { display: block; width: 100%; height: 2.1rem; border: 1px solid transparent; border-radius: .375rem; background: transparent; padding: .375rem .5rem; font-size: .8125rem; text-overflow: ellipsis; white-space: nowrap; }
        .courier-sheet .cell:hover { border-color: #E4E2DC; background: #fff; }
        .courier-sheet .cell:focus { outline: none; border-color: #1F5F4A; background: #fff; box-shadow: 0 0 0 1px #1F5F4A; }
        .courier-sheet .cell:disabled { color: inherit; cursor: default; }
        .courier-sheet .cell::placeholder { color: #c4c8cf; }
    </style>
</div>
