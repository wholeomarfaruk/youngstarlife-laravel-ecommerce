<div class="flex flex-col gap-3">
    {{-- Session bar --}}
    <section class="card flex flex-wrap items-end gap-3 px-3 py-2.5">
        <div class="flex flex-col gap-0.5"><span class="text-xs text-ink-muted">Receiving from</span>
            <div class="flex overflow-hidden rounded border border-line-2">@foreach ($this->couriers as $c)<button wire:click="$set('courier', {{ $c->id }})" @class(['border-r border-line px-3 py-1.5 last:border-0', 'bg-ink text-white' => $courier === $c->id, 'bg-white' => $courier !== $c->id])>{{ $c->name }} <span class="text-xs opacity-75">{{ $c->returning }}</span></button>@endforeach</div></div>
        <div class="flex flex-col gap-0.5"><span class="text-xs text-ink-muted">Session</span><span class="mono rounded border border-line bg-zebra px-2 py-1.5 font-semibold">{{ $this->session }}</span></div>
        <x-ops.field label="Rider / handover"><input wire:model.blur="rider" class="input w-44" placeholder="Rider name, phone"></x-ops.field>
        <div class="flex flex-col gap-0.5"><span class="text-xs text-ink-muted">Received by</span><span class="px-1 py-1.5 font-medium">{{ auth()->user()->name }}</span></div>
        <label class="ml-auto flex cursor-pointer items-center gap-2 rounded border border-line-2 px-2.5 py-1.5"><input type="checkbox" wire:model.live="fast" class="accent-brand"> <span class="flex flex-col"><span class="font-medium">Fast mode</span><span class="text-xs text-ink-muted">Parcel scan = all items good</span></span></label>
    </section>

    <div class="flex flex-wrap items-start gap-3">
        <div class="flex min-w-0 flex-[999_1_480px] flex-col gap-2.5">
            <section class="card flex flex-col gap-2.5 p-3.5" x-data="scanner('scan')">
                <form @submit.prevent="submit" class="flex flex-wrap gap-2">
                    <div class="flex min-w-0 flex-[1_1_260px] items-center gap-2.5 rounded-md border-2 border-ink bg-white px-3"><span class="mono rounded-sm border border-line px-1 text-xs text-ink-muted">SCAN</span><input x-ref="input" x-model="code" autocomplete="off" class="mono min-w-0 flex-1 border-0 bg-transparent py-3 text-lg outline-none" placeholder="Parcel ID, tracking, order / exchange no. or product barcode"></div>
                    <button class="btn-dark min-h-[44px] px-4">Enter</button>
                </form>
                <div x-data="camera('scan')" class="flex flex-col gap-2">
                    <button type="button" x-show="!on" @click="start" class="btn-secondary btn-sm self-start">📷 Phone camera</button>
                    <div x-cloak x-show="on" class="relative overflow-hidden rounded-md bg-ink"><video x-ref="video" playsinline muted class="max-h-60 w-full object-cover"></video><button type="button" @click="stop" class="btn-secondary btn-sm absolute right-2 top-2">Stop camera</button></div>
                    <span x-cloak x-show="error" x-text="error" class="text-xs text-bad"></span>
                </div>
                @if ($flash)<div @class(['rounded-md px-3 py-2.5', 'bg-ok-bg text-ok' => $flash['tone'] === 'ok', 'bg-bad-bg text-bad' => $flash['tone'] === 'bad', 'bg-info-bg text-info' => $flash['tone'] === 'info', 'bg-warn-bg text-warn' => $flash['tone'] === 'warn'])><div class="font-semibold">{{ $flash['title'] }}</div><div class="text-sm">{{ $flash['sub'] }}</div></div>@endif
            </section>

            @if ($x = $this->activeExchange)
                @php($line = $x->exchangeLine)
                <section class="card border-ink">
                    <div class="card-head"><span class="font-semibold">Exchange old item · <a href="{{ route('ops.orders.show', $x) }}" wire:navigate class="mono">{{ $x->number }}</a> · original <span class="mono">{{ $x->exchangeOf?->number }}</span> · {{ $x->customer?->name }}</span>
                        <div class="flex gap-1.5"><button wire:click="cancelActive" class="btn-ghost btn-sm">Cancel</button><button wire:click="markAllGood" class="btn-secondary btn-sm">Mark all good</button><button wire:click="confirmExchange" wire:loading.attr="disabled" class="btn-primary">Confirm old item received</button></div></div>
                    <p class="px-3.5 pt-2 text-xs text-ink-muted">The customer already has the new item ({{ $x->shipment?->courier?->name }} · {{ config('ops.exchange_state.'.$x->exchange_state.'.0') }}). Tick what the rider handed back; unticked = missing → courier claim. No refund{{ $line?->refund_amount ? ' — except the price difference '.tk($line->refund_amount).' (Refund due after confirming)' : '' }}.</p>
                    <div class="overflow-x-auto">@foreach ($line?->items ?? [] as $it)
                        @php($l = $lines[$it->order_item_id] ?? ['got' => false, 'condition' => 'good']) @php($bp = $this->batchPreview($it->orderItem, (int) $it->qty)) @php($codes = collect($bp)->pluck('code')->filter()->join(', '))
                        @php($act = $l['got'] ? config('ops.return_conditions.'.($l['condition'] ?: 'none')) : config('ops.return_conditions.missing'))
                        <div @class(['grid min-w-[760px] grid-cols-[24px_minmax(180px,1fr)_150px_150px_150px] items-center gap-2.5 border-b border-soft px-3 py-2', 'bg-brand-tint' => $l['got']])>
                            <input type="checkbox" wire:model.live="lines.{{ $it->order_item_id }}.got" class="h-5 w-5 accent-brand">
                            <div><div class="font-medium">{{ $it->orderItem->variant->label }} × {{ $it->qty }}</div><div class="text-xs text-ink-muted">{{ $it->reason }} · {{ $it->orderItem->variant->barcode ?: $it->orderItem->variant->sku }}</div></div>
                            <div class="text-sm">@include('livewire.ops.returns.partials.batch-preview', ['bp' => $bp])</div>
                            <select wire:model.live="lines.{{ $it->order_item_id }}.condition" class="input py-1" @disabled(! $l['got'])><option value="">Not inspected</option>@foreach (config('ops.return_conditions') as $k => [$lbl]) @continue($k === 'missing')<option value="{{ $k }}">{{ $lbl }}</option>@endforeach</select>
                            <span>@if ($act)<x-ops.badge :tone="$act[3]">{{ $act[2] }}{{ $act[1] === 'restock' && $codes ? ' → '.$codes : '' }}</x-ops.badge>@else<x-ops.badge tone="mute">Not inspected</x-ops.badge>@endif</span>
                        </div>@endforeach</div>
                    <div class="grid gap-3 p-3 sm:grid-cols-[1fr_220px]">
                        <textarea wire:model="note" rows="2" class="input" placeholder="Note, e.g. Old pant came back with tag, slightly used."></textarea>
                        <label class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed border-line-2 p-2 text-sm text-ink-muted hover:bg-zebra">📸 Photos ({{ count($photos) }})<input type="file" wire:model="photos" accept="image/*" capture="environment" multiple class="sr-only"><span wire:loading wire:target="photos" class="text-xs">Uploading…</span></label>
                    </div>
                </section>
            @endif

            @if ($o = $this->activeOrder)
                @php($p = $this->active) @php($kept = $this->keptMode())
                <section class="card border-ink">
                    <div class="card-head"><span class="font-semibold">{{ $p ? 'Checking' : 'Customer return' }} @if ($p)<span class="mono">{{ $p->code }}</span> · @endif{{ $o->number }} · {{ $o->customer->name }}</span>
                        <div class="flex gap-1.5"><button wire:click="cancelActive" class="btn-ghost btn-sm">Cancel</button><button wire:click="markAllGood" class="btn-secondary btn-sm">Mark all good</button><button wire:click="confirm" wire:loading.attr="disabled" class="btn-primary">Confirm parcel</button></div></div>
                    @php($refund = $this->refundFor($o, collect($lines)->filter(fn ($l) => $l['got'])->keys()->map(fn ($k) => (int) $k)->all()))
                    @if ($o->advance > 0 || $refund > 0)<div class="mx-3.5 mt-3 rounded-md bg-warn-bg px-3 py-2 text-sm text-warn">Customer paid {{ tk($o->advance + ($o->payment_status === 'paid' ? $o->cod : 0)) }} in advance. Delivery charge {{ tk($o->delivery_charge) }} is still billed → refund {{ tk($refund) }} after confirming.</div>@endif
                    <p class="px-3.5 pt-2 text-xs text-ink-muted">{{ $kept ? 'Tick the items that came back. Unticked items stay with the customer (partial).' : 'Tick each item found in the parcel. Unticked items are recorded as Missing → courier claim.' }}</p>
                    <div class="overflow-x-auto">@foreach ($o->items as $i)
                        @php($l = $lines[$i->id] ?? ['got' => false, 'condition' => 'good', 'reason' => '']) @php($bp = $this->batchPreview($i, (int) $i->qty, $p?->id)) @php($codes = collect($bp)->pluck('code')->filter()->join(', '))
                        @php($act = $l['got'] ? config('ops.return_conditions.'.($l['condition'] ?: 'none')) : ($kept ? null : config('ops.return_conditions.missing')))
                        <div @class(['grid min-w-[860px] grid-cols-[24px_minmax(160px,1fr)_170px_150px_150px_130px] items-center gap-2.5 border-b border-soft px-3 py-2', 'bg-brand-tint' => $l['got']])>
                            <input type="checkbox" wire:model.live="lines.{{ $i->id }}.got" class="h-5 w-5 accent-brand">
                            <div><div class="font-medium">{{ $i->variant->label }} × {{ $i->qty }}</div><div class="mono text-xs text-ink-muted">{{ $i->variant->barcode ?: $i->variant->sku }}</div></div>
                            <div class="text-sm">@include('livewire.ops.returns.partials.batch-preview', ['bp' => $bp])</div>
                            <select wire:model="lines.{{ $i->id }}.reason" class="input py-1">@foreach (ops_setting('returns.reasons', config('ops.return_reasons')) as $r)<option>{{ $r }}</option>@endforeach</select>
                            <select wire:model.live="lines.{{ $i->id }}.condition" class="input py-1"><option value="">Not inspected</option>@foreach (config('ops.return_conditions') as $k => [$lbl]) @continue($k === 'missing')<option value="{{ $k }}">{{ $lbl }}</option>@endforeach</select>
                            <span>@if ($act)<x-ops.badge :tone="$act[3]">{{ $act[2] }}{{ $act[1] === 'restock' && $codes ? ' → '.$codes : '' }}</x-ops.badge>@elseif ($l['got'])<x-ops.badge tone="mute">Not inspected</x-ops.badge>@else<span class="text-xs text-ink-muted">{{ $kept ? 'kept by customer' : '' }}</span>@endif</span>
                        </div>@endforeach</div>
                    <div class="grid gap-3 p-3 sm:grid-cols-[1fr_220px]">
                        <textarea wire:model="note" rows="2" class="input" placeholder="Note, e.g. Customer received 2 items, Blue XL was damaged, returned only XL."></textarea>
                        <label class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed border-line-2 p-2 text-sm text-ink-muted hover:bg-zebra">📸 Photos ({{ count($photos) }})<input type="file" wire:model="photos" accept="image/*" capture="environment" multiple class="sr-only"><span wire:loading wire:target="photos" class="text-xs">Uploading…</span></label>
                    </div>
                </section>
            @endif

            <x-ops.card :title="'Courier return list · '.$this->expected->count().' parcels'" flush>
                <x-ops.table min-width="760px"><x-slot:head><th class="th">Parcel</th><th class="th">Tracking</th><th class="th">Order · customer</th><th class="th">Type</th><th class="th text-right">Items</th><th class="th text-right">Charge</th><th class="th">Status</th></x-slot:head>
                @forelse ($this->expected as $s)
                    <tr class="tr-hover cursor-pointer" wire:click="scan('{{ $s->code }}')" wire:key="ex{{ $s->id }}"><td class="td mono font-semibold">{{ $s->code }}</td><td class="td mono text-xs">{{ $s->tracking ?: '—' }}</td><td class="td">{{ $s->order->number }} · {{ $s->order->customer->name }}</td>
                        <td class="td">{{ $s->order->exchange_of_id ? 'Exchange' : ($s->courier_status === 'partial_delivered' ? 'Partial' : 'Full') }}</td><td class="td text-right">{{ $s->order->items->sum('qty') }}</td><td class="td text-right">{{ tk($s->charge) }}</td>
                        <td class="td">@if ($activeId === $s->id)<x-ops.badge tone="info">Checking</x-ops.badge>@else<x-ops.badge map="courier_status" :status="$s->courier_status" />@endif</td></tr>
                @empty <x-ops.empty colspan="7" title="No parcels coming back from this courier" hint="" /> @endforelse
                </x-ops.table>
            </x-ops.card>
            @if ($this->exchangeDue->isNotEmpty())
                <x-ops.card :title="'Exchange old items · '.$this->exchangeDue->count()" subtitle="New item delivered — the rider must hand back the old one" flush>
                    <x-ops.table min-width="760px"><x-slot:head><th class="th">Exchange</th><th class="th">Original</th><th class="th">Customer</th><th class="th">Old item(s) to collect</th><th class="th">Delivered</th><th class="th">State</th></x-slot:head>
                    @foreach ($this->exchangeDue as $ex)
                        @php($days = $ex->shipment?->delivered_at?->diffInDays(now()))
                        <tr class="tr-hover cursor-pointer" wire:click="openExchangeById({{ $ex->id }})" wire:key="xd{{ $ex->id }}"><td class="td mono font-semibold">{{ $ex->number }}<div class="text-xs font-normal text-ink-muted">{{ $ex->shipment?->code }}</div></td><td class="td mono">{{ $ex->exchangeOf?->number }}</td><td class="td">{{ $ex->customer?->name }}</td>
                            <td class="td text-sm">{{ $ex->exchangeLine?->items->map(fn ($i) => $i->orderItem->variant->label.' ×'.$i->qty)->join(', ') }}</td>
                            <td class="td text-sm">{{ $ex->shipment?->delivered_at?->format('d M') ?? '—' }}@if ($days !== null && $days >= (int) ops_setting('orders.exchange_overdue_days', 5)) <x-ops.badge tone="bad">{{ (int) $days }} days</x-ops.badge>@endif</td>
                            <td class="td">@if ($exchangeId === $ex->id)<x-ops.badge tone="info">Checking</x-ops.badge>@else<x-ops.badge :tone="config('ops.exchange_state.'.$ex->exchange_state.'.1')">{{ config('ops.exchange_state.'.$ex->exchange_state.'.2') }}</x-ops.badge>@endif</td></tr>
                    @endforeach
                    </x-ops.table>
                </x-ops.card>
            @endif
            @if ($unexpected)<x-ops.card title="Unexpected scans" flush>@foreach ($unexpected as $u)<div class="mono border-t border-soft px-3.5 py-1.5 text-sm text-bad first:border-0">{{ $u }}</div>@endforeach</x-ops.card>@endif

            @php($rc = $this->receivedCounts)
            <x-ops.card title="Received returns" flush>
                <x-slot:actions><x-ops.search placeholder="Search return or order…" class="!flex-none w-56" /></x-slot:actions>
                <div class="border-b border-soft px-3 py-2"><x-ops.chips wire:model.live="view" :options="['all' => ['All', $rc['all']], 'today' => ['Today', $rc['today']], 'inspect' => ['To inspect', $rc['inspect']], 'refund' => ['Refund due', $rc['refund']], 'damaged' => ['Damaged', $rc['damaged']]]" /></div>
                @forelse ($this->received as $r)
                    <div class="flex flex-col gap-1.5 border-t border-soft px-3 py-2.5 first:border-0" wire:key="r{{ $r->id }}">
                        <div class="flex flex-wrap items-center gap-x-3.5 gap-y-1">
                            <a href="{{ route('ops.trace', ['q' => $r->code]) }}" wire:navigate class="mono min-w-[70px] font-semibold">{{ $r->code }}</a>
                            <span class="flex-[1_1_160px]">{{ $r->order->customer->name }} <a href="{{ route('ops.orders.show', $r->order_id) }}" wire:navigate class="mono text-xs">{{ $r->order->number }}</a> <span class="text-xs capitalize text-ink-muted">· {{ $r->type }}@if ($r->exchangeOrder) old item of <a href="{{ route('ops.orders.show', $r->exchange_order_id) }}" wire:navigate class="mono normal-case">{{ $r->exchangeOrder->number }}</a>@endif · {{ ($r->received_at ?? $r->created_at)->format('d M H:i') }}</span></span>
                            @if ($r->refund_amount && ! $r->refunded_at)
                                <div x-data="{ via: 'bKash' }" class="flex gap-1.5"><select x-model="via" class="input w-auto py-1">@foreach (config('ops.accounts') as $a)<option>{{ $a }}</option>@endforeach</select><button @click="$wire.refund({{ $r->id }}, via)" wire:confirm="Refund {{ tk($r->refund_amount) }} for {{ $r->code }}? This posts money out once." class="btn-primary btn-sm">Refund {{ tk($r->refund_amount) }}</button></div>
                            @elseif ($r->refunded_at) <span class="text-sm font-semibold text-ok">Refunded {{ tk($r->refund_amount) }} · {{ $r->refund_via }}</span> @endif
                            @if ($r->items->whereIn('condition', ['damaged', 'used', 'broken'])->isNotEmpty())<a href="{{ route('ops.supplier-returns.index', ['view' => 'pool']) }}" wire:navigate class="btn-secondary btn-sm">Create supplier return</a>@endif
                        </div>
                        <div class="flex flex-wrap gap-1.5 pl-[86px] max-sm:pl-0">@foreach ($r->items as $it)
                            @php($cfg = config('ops.return_conditions.'.$it->condition))
                            <span class="inline-flex items-center gap-1.5 rounded border border-line px-2 py-1 text-sm">{{ $it->orderItem->variant->label }} <span class="text-xs text-ink-muted">· {{ $it->reason }}</span>
                                @if ($cfg)<x-ops.badge :tone="$cfg[3]">{{ $cfg[2] }}{{ $cfg[1] === 'restock' && $it->batch ? ' → '.$it->batch->code : '' }}</x-ops.badge>
                                @else <x-ops.badge tone="mute">Not inspected</x-ops.badge><select x-data @change="$wire.setCondition({{ $it->id }}, $event.target.value)" class="input w-auto py-0.5 text-xs"><option value="">Set condition…</option>@foreach (config('ops.return_conditions') as $k => [$lbl]) @continue($k === 'missing')<option value="{{ $k }}">{{ $lbl }}</option>@endforeach</select>@endif</span>
                        @endforeach</div>
                    </div>
                @empty <div class="p-4 text-ink-muted">No returns match.</div> @endforelse
            </x-ops.card>
        </div>

        {{-- Session summary + scan log --}}
        @php($sm = $this->summary)
        <div class="flex flex-[1_1_260px] flex-col gap-3 lg:sticky lg:top-[72px] lg:max-w-[320px]">
            <x-ops.card title="Session summary" flush>
                @foreach ([['Expected', $sm['expected'], 'ink'], ['Exchange old items due', $sm['exchange'], 'warn'], ['Received', $sm['received'], 'ok'], ['Checking', $sm['checking'], 'info'], ['Not scanned', $sm['not_scanned'], 'warn'], ['To restock', $sm['restock'], 'ok'], ['To damaged', $sm['damaged'], 'bad'], ['Unexpected', $sm['unexpected'], 'bad']] as [$l, $v, $tone])
                    <div class="flex justify-between border-t border-soft px-3.5 py-1.5 text-sm first:border-0"><span>{{ $l }}</span><span class="font-semibold tabular-nums text-{{ $tone }}">{{ $v }}</span></div>
                @endforeach
                <div class="border-t border-soft p-3"><button wire:click="finishSession" wire:confirm="Finish {{ $this->session }}? Parcels not scanned ({{ $sm['not_scanned'] }}) are recorded as missing → courier claim." class="btn-dark w-full">Finish session → receipt</button><p class="mt-1.5 text-xs text-ink-muted">Stock is added only after a physical scan. Delivery charge is still billed.</p></div>
            </x-ops.card>
            <x-ops.card title="Scan log" flush>
                @forelse ($log as [$t, $code, $res, $tone])<div class="grid grid-cols-[60px_1fr] gap-2 border-t border-soft px-3.5 py-1.5 text-xs first:border-0"><span class="mono text-ink-muted">{{ $t }}</span><span><span class="mono">{{ $code }}</span> · <span class="text-{{ $tone }}">{{ $res }}</span></span></div>
                @empty<div class="px-3.5 py-3 text-sm text-ink-muted">Scans appear here.</div>@endforelse
            </x-ops.card>
        </div>
    </div>
</div>
