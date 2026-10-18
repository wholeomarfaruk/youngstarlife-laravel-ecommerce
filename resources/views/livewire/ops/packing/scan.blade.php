<div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_300px]">
    @php($o = $this->current)
    <div class="flex min-w-0 flex-col gap-3">
        {{-- scanner --}}
        <section class="card flex flex-col gap-2.5 p-3.5" x-data="scanner('scan')" @ops-refocus.window="$refs.input?.focus()">
            <div class="flex flex-wrap items-baseline justify-between gap-2"><span class="text-md font-semibold">Scan the order, then each product</span><span class="text-xs text-ink-muted">Order no. · parcel P1005 · barcode · SKU · batch label (SKU-B014) · batch code (B014)</span></div>
            <form @submit.prevent="submit" class="flex flex-wrap gap-2">
                <div class="flex min-w-0 flex-[1_1_260px] items-center gap-2.5 rounded-md border-2 border-ink bg-white px-3">
                    <span class="mono rounded-sm border border-line px-1 text-xs text-ink-muted">SCAN</span>
                    <input x-ref="input" x-model="code" class="mono min-w-0 flex-1 border-0 bg-transparent py-3 text-lg outline-none" placeholder="#1042 or product barcode" autocomplete="off">
                </div>
                <button class="btn-dark min-h-[44px] px-4">Enter</button>
            </form>
            <div x-data="camera('scan')" class="flex flex-col gap-2">
                <button type="button" x-show="!on" @click="start" class="btn-secondary btn-sm self-start">📷 Phone camera</button>
                <div x-cloak x-show="on" class="relative overflow-hidden rounded-md bg-ink"><video x-ref="video" playsinline muted class="max-h-60 w-full object-cover"></video><button type="button" @click="stop" class="btn-secondary btn-sm absolute right-2 top-2">Stop camera</button></div>
                <span x-cloak x-show="error" x-text="error" class="text-xs text-bad"></span>
            </div>
            @if ($flash)
                <div @class(['rounded-md px-3 py-2.5', 'bg-ok-bg text-ok' => $flash['tone'] === 'ok', 'bg-bad-bg text-bad' => $flash['tone'] === 'bad', 'bg-warn-bg text-warn' => $flash['tone'] === 'warn', 'bg-info-bg text-info' => $flash['tone'] === 'info'])>
                    <div class="font-semibold">{{ $flash['title'] }}</div><div class="text-sm">{{ $flash['sub'] }}</div>
                </div>
            @endif
            {{-- a product that is not on the order: add it anyway? --}}
            @if ($extraId && ($xv = $this->variants->firstWhere('id', $extraId)))
                <div class="flex flex-wrap items-center gap-3 rounded-md border-2 border-bad/40 bg-bad-bg px-3 py-2.5">
                    <img src="{{ $xv->product->thumb_url }}" alt="" class="h-12 w-12 rounded bg-white object-cover">
                    <div class="min-w-0 flex-1"><div class="font-semibold text-bad">⚠ {{ $xv->label }} is not on this order</div><div class="text-xs text-ink-3">Put it in the parcel only if the customer should get it. It is added to the order and its stock leaves at packing.</div></div>
                    <div class="flex flex-wrap gap-1.5">
                        <button wire:click="addExtra(false)" class="btn-secondary btn-sm">Add free</button>
                        <button wire:click="addExtra(true)" class="btn-danger btn-sm">Add & charge {{ tk($xv->effective_price) }}</button>
                        <button wire:click="skipExtra" class="btn-ghost btn-sm">Don’t add</button>
                    </div>
                </div>
            @endif
            @if ($choices)
                <div class="flex flex-col gap-1.5 rounded-md border-2 border-warn/40 bg-warn-bg p-2.5">
                    <span class="text-sm font-semibold text-warn">Which parcel are you packing?</span>
                    @foreach ($choices as $c)
                        <button wire:click="choose('{{ $c['number'] }}')" class="flex items-center gap-2 rounded-md border border-line bg-white px-3 py-2 text-left text-sm hover:border-brand">
                            <span class="font-semibold">{{ $c['label'] }}</span><span class="mono text-xs text-ink-muted">{{ $c['number'] }}</span>
                            <span class="ml-auto text-xs text-ink-muted">{{ $c['units'] }} item(s) · {{ $c['parcel'] ? 'parcel '.$c['parcel'] : 'new parcel' }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
            @if ($done)
                <div class="flex flex-wrap items-center gap-2 rounded-md bg-ok-bg px-3 py-2 text-sm text-ok">
                    <span class="font-semibold">✓ {{ $done['number'] }} packed{{ $done['parcel'] ? ' · parcel '.$done['parcel'] : '' }}</span>
                    <span class="ml-auto flex flex-wrap gap-1.5">
                        <a href="{{ route('ops.orders.parcel-labels', ['ids' => $done['id']]) }}" target="_blank" class="btn-secondary btn-sm">Print label</a>
                        <button x-data @click="$dispatch('open-modal', 'book-courier-one'); $dispatch('book-courier', { id: {{ $done['id'] }} })" class="btn-secondary btn-sm">Book courier…</button>
                        <a href="{{ route('ops.orders.show', $done['id']) }}" wire:navigate class="btn-ghost btn-sm">Open order</a>
                    </span>
                </div>
            @endif
        </section>

        @if ($o)
            @php($lines = $this->lines) @php($rate = $o->customer?->delivery_rate)
            <section class="card">
                <div class="card-head">
                    <div class="flex min-w-0 flex-col">
                        <span class="card-title">Packing <span class="text-brand">{{ \App\Livewire\Ops\Packing\Scan::kind($o) }}</span> of <a href="{{ route('ops.orders.show', $o->exchange_of_id ?: $o->id) }}" wire:navigate class="mono">{{ $o->exchangeOf?->number ?? $o->number }}</a> · {{ $this->progress() }}</span>
                        <span class="text-sm font-semibold">{{ $this->packingParcel() ?? 'New parcel — created when you mark it packed' }}@if ($o->exchange_of_id) <span class="mono font-normal text-ink-muted">· {{ $o->number }}</span>@endif</span>
                        <span class="text-sm text-ink-muted">{{ $o->ship_name }} · {{ $o->ship_phone }}@if ($rate !== null) · <span @class(['font-semibold', 'text-bad' => $rate < .75, 'text-ok' => $rate >= .9])>{{ round($rate * 100) }}%</span>@endif · COD {{ tk($o->cod) }}</span>
                        <span class="text-xs text-ink-muted">{{ $o->ship_address }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button wire:click="undo" @disabled(! $history) class="btn-secondary btn-sm" title="Take back the last scan">↶ Undo</button>
                        <button x-data @click="opsConfirm({ title: 'Clear every scan?', text: 'Start this order again.', confirm: 'Clear' }).then(ok => ok && $wire.resetScans())" @disabled(! $scanned) class="btn-secondary btn-sm">Reset</button>
                        <button x-data @click="opsConfirm({ title: 'Tick everything by hand?', text: 'Nothing will be checked by scan — make sure every item is in the parcel.', confirm: 'Tick all', danger: true }).then(ok => ok && $wire.tickAll())" class="btn-secondary btn-sm">Tick all</button>
                    </div>
                </div>
                @if ($this->parcels->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-1.5 border-b border-soft bg-zebra px-3.5 py-2 text-xs"><span class="text-ink-muted">Parcels of this order:</span>
                        @foreach ($this->parcels as $p)
                            <span @class(['rounded border px-1.5 py-0.5', 'border-brand bg-white font-semibold' => $p->order_id === $o->id && ! $p->superseded_at && ! $p->packed_at, 'border-line bg-white' => ! ($p->order_id === $o->id && ! $p->superseded_at && ! $p->packed_at)])>
                                <span class="mono">{{ $p->code }}</span> · {{ $p->order->exchange_of_id ? 'Exchange '.\Illuminate\Support\Str::afterLast($p->order->number, '-EX') : ($p->kind === 'resend' ? 'Re-send' : 'Delivery') }} · {{ $p->superseded_at ? 'replaced' : ($p->packed_at ? config('ops.courier_status.'.$p->courier_status.'.0', $p->courier_status) : 'not packed') }}
                            </span>
                        @endforeach
                    </div>
                @endif
                @if ($o->note || $o->courier_note)
                    <div class="flex flex-col gap-0.5 border-b border-soft bg-warn-bg px-3.5 py-2 text-sm text-warn">@if ($o->note)<div><b>Order note:</b> {{ $o->note }}</div>@endif @if ($o->courier_note)<div><b>Rider note:</b> {{ $o->courier_note }}</div>@endif</div>
                @endif
                @foreach ($lines as $key => $l)
                    @php($n = $scanned[$key] ?? 0) @php($ok = $n >= $l['need']) @php($hand = $manual[$key] ?? 0)
                    <div wire:key="ln-{{ $key }}" @class(['flex items-center gap-3 border-t border-soft px-3.5 py-2.5 first:border-0', 'bg-brand-tint' => $ok])>
                        <button type="button" wire:click="{{ $ok ? 'minus' : 'plus' }}('{{ $key }}')" title="{{ $ok ? 'Take one out' : 'Tick by hand' }}"
                            @class(['grid h-7 w-7 shrink-0 place-items-center rounded text-sm text-white', 'bg-brand' => $ok, 'border-2 border-ink-faint bg-white hover:border-brand' => ! $ok])>{{ $ok ? '✓' : '' }}</button>
                        @if ($l['image'])<img src="{{ $l['image'] }}" alt="" loading="lazy" class="h-12 w-12 shrink-0 rounded-md bg-soft object-cover">@endif
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ $l['label'] }} @if ($l['free'])<x-ops.badge tone="info">Free</x-ops.badge>@endif</div>
                            @if ($l['combo'])<div class="text-xs font-semibold text-info">Part of combo: {{ $l['combo'] }}</div>@endif
                            <div class="mono text-xs text-ink-muted">{{ $l['barcode'] ?: '—' }} · {{ $l['sku'] }}@if ($l['batches']) · batch {{ $l['batches'] }}@endif @if ($hand)<span class="font-sans font-semibold text-warn"> · {{ $hand }} ticked by hand</span>@endif</div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" wire:click="minus('{{ $key }}')" @disabled(! $n) class="grid h-7 w-7 place-items-center rounded border border-line text-ink-3 hover:bg-soft">−</button>
                            <span @class(['min-w-[48px] text-center font-semibold tabular-nums', 'text-ok' => $ok])>{{ $n }} / {{ $l['need'] }}</span>
                            <button type="button" wire:click="plus('{{ $key }}')" @disabled($ok) class="grid h-7 w-7 place-items-center rounded border border-line text-ink-3 hover:bg-soft">+</button>
                        </div>
                    </div>
                @endforeach
                <div class="flex flex-wrap items-center gap-2 border-t border-soft p-3">
                    <x-ops.searchable-select field="pick" class="min-w-[220px] flex-1" placeholder="+ Add another product to the parcel…" searchPlaceholder="Name, size, SKU or barcode…"
                        :options="$this->variants->mapWithKeys(fn ($v) => [$v->id => $v->label.' · '.$v->sku])->all()" :images="$this->variants->mapWithKeys(fn ($v) => [$v->id => $v->product->thumb_url])->all()" />
                    @php($hands = array_sum($manual))
                    <button x-data @if ($hands) @click="opsConfirm({ title: 'Mark packed?', text: '{{ $hands }} item(s) were ticked by hand, not scanned. Is everything in the parcel?', confirm: 'Mark packed', danger: true }).then(ok => ok && $wire.complete())" @else wire:click="complete" @endif
                        wire:loading.attr="disabled" @disabled(! $this->isComplete()) class="btn-primary">Mark packed</button>
                </div>
            </section>
        @endif
    </div>

    {{-- queue --}}
    <aside class="flex flex-col gap-3">
        <section class="card">
            <div class="card-head"><span class="card-title">Waiting to pack <span class="text-sm font-normal text-ink-muted">{{ $this->queueCount }}</span></span><span class="text-xs text-ink-muted">{{ $this->packedToday }} packed today</span></div>
            <label class="flex items-center gap-2 border-b border-soft px-3.5 py-2 text-sm"><input type="checkbox" wire:model.live="autoNext" class="accent-brand"> Open the next order after packing</label>
            {{-- search the whole queue (not only the 40 shown) --}}
            <div class="relative border-b border-soft px-3.5 py-2">
                <input type="search" wire:model.live.debounce.300ms="queueSearch" class="input py-1.5 pr-7" placeholder="Search order no., name or phone…" aria-label="Search waiting orders" x-on:keydown.escape="$wire.set('queueSearch', '')">
                @if ($queueSearch !== '')<span class="mt-1 block text-xs text-ink-muted">{{ $this->queueMatches }} {{ \Illuminate\Support\Str::plural('match', $this->queueMatches) }}@if ($this->queueMatches > 40) · showing the first 40@endif</span>@endif
            </div>
            <div class="max-h-[60vh] overflow-auto">
                @forelse ($this->queue as $q)
                    <button type="button" wire:key="q{{ $q->id }}" wire:click="open('{{ $q->number }}')" @class(['flex w-full items-center gap-2 border-t border-soft px-3.5 py-2 text-left text-sm first:border-0 hover:bg-zebra', 'bg-brand-tint' => $o && $o->id === $q->id])>
                        <span class="min-w-0 flex-1"><span class="mono font-semibold">{{ $q->number }}</span><span class="block truncate text-xs text-ink-muted"><b class="text-ink-3">{{ \App\Livewire\Ops\Packing\Scan::kind($q, (bool) $q->resent) }}</b> · {{ $q->ship_name }} · {{ (int) $q->units }} item(s) · {{ $q->placed_at->format('d M') }}</span></span>
                        <x-ops.badge map="order_status" :status="$q->status" />
                    </button>
                @empty
                    <div class="px-3.5 py-6 text-center text-sm text-ink-muted">{{ $queueSearch !== '' ? 'No waiting order matches “'.$queueSearch.'”' : 'Nothing waiting 🎉' }}</div>
                @endforelse
            </div>
        </section>
        <livewire:ops.orders.book-courier />
    </aside>
</div>
