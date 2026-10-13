<div class="flex flex-col gap-3">
    @php($items = $this->items) @php($open = $session->is_open)
    @php($sent = $items->where('type', 'sent')) @php($ret = $items->where('type', 'returned')) @php($rej = $items->where('type', 'rejected'))

    {{-- header --}}
    <section class="card flex flex-wrap items-center gap-x-6 gap-y-3 p-3.5" x-data="{ edit: false, rider: @js($session->rider ?? ''), phone: @js($session->rider_phone ?? '') }">
        <div class="flex flex-col"><span class="text-xs text-ink-muted">Session</span><span class="mono text-lg font-semibold">{{ $session->code }}</span></div>
        <div class="flex flex-col"><span class="text-xs text-ink-muted">Courier</span><span class="font-semibold">{{ $session->courier->name }}</span></div>
        <div class="flex flex-col">
            <span class="text-xs text-ink-muted">Pickup man</span>
            <span x-show="!edit" class="flex items-center gap-2"><span class="font-medium">{{ $session->rider ?: 'Not noted' }}{{ $session->rider_phone ? ' · '.$session->rider_phone : '' }}</span>@if ($open)<button type="button" @click="edit = true" class="text-xs text-brand">Edit</button>@endif</span>
            <span x-cloak x-show="edit" class="flex items-center gap-1"><input x-model="rider" class="input w-32 py-1" placeholder="Name"><input x-model="phone" class="input w-32 py-1" placeholder="Phone"><button type="button" @click="$wire.saveRider(rider, phone); edit = false" class="btn-secondary btn-sm">Save</button></span>
        </div>
        <div class="flex flex-col"><span class="text-xs text-ink-muted">Started</span><span>{{ $session->created_at->format('d M Y H:i') }}</span></div>
        <x-ops.badge :tone="$open ? 'warn' : 'mute'">{{ $open ? 'Open' : 'Closed '.$session->closed_at?->format('d M H:i') }}</x-ops.badge>
        <div class="ml-auto flex gap-2">
            <a href="{{ route('ops.couriers.session.print', $session) }}" target="_blank" class="btn-secondary">Print receipt</a>
            @if ($open)<button type="button" wire:click="close" wire:confirm="Close {{ $session->code }}? The pickup man signs the receipt." class="btn-primary">Close session</button>
            @else<button type="button" wire:click="reopen" class="btn-secondary">Reopen</button>@endif
        </div>
    </section>

    @if ($open)
        {{-- scanner --}}
        <section class="card flex flex-col gap-2.5 p-3.5" x-data="scanner('scan')">
            <div class="flex flex-wrap gap-1.5">
                @foreach (['sent' => ['Hand over parcel', 'bg-info text-white border-info'], 'returned' => ['Receive return', 'bg-ok text-white border-ok'], 'rejected' => ['Damaged · not received', 'bg-bad text-white border-bad']] as $m => [$l, $on])
                    <button type="button" wire:click="$set('mode', '{{ $m }}')" @class(['rounded-md border px-3 py-1.5 text-sm font-medium', $on => $mode === $m, 'border-line-2 bg-white text-ink-3 hover:border-ink-faint' => $mode !== $m])>{{ $l }}</button>
                @endforeach
                <span class="ml-auto self-center text-xs text-ink-muted">Scan parcel code, tracking or order number · USB / Bluetooth scanners press Enter</span>
            </div>
            <form @submit.prevent="submit" class="flex flex-wrap gap-2">
                <div @class(['flex min-w-0 flex-[1_1_260px] items-center gap-2.5 rounded-md border-2 bg-white px-3', 'border-info' => $mode === 'sent', 'border-ok' => $mode === 'returned', 'border-bad' => $mode === 'rejected'])>
                    <span class="mono rounded-sm border border-line px-1 text-xs text-ink-muted">SCAN</span>
                    <input x-ref="input" x-model="code" class="mono min-w-0 flex-1 border-0 bg-transparent py-3 text-lg outline-none" placeholder="P1005 · tracking · #1042" autocomplete="off">
                </div>
                <button class="btn-dark min-h-[44px] px-4">Enter</button>
            </form>
            @if ($mode !== 'sent')
                <div class="flex flex-wrap items-center gap-2">
                    <input wire:model="note" class="input min-w-0 flex-1" placeholder="{{ $mode === 'rejected' ? 'Damage — torn, wet, opened, crushed… (required)' : 'Note (optional)' }}">
                    @if ($mode === 'rejected' && $rejectId)<button type="button" wire:click="rejectPicked" class="btn-sm rounded-md bg-bad px-3 py-1.5 text-white">Not received</button>@endif
                </div>
            @endif
            @if ($flash)
                @php([$tone, $title, $sub] = $flash)
                <div @class(['rounded-md border px-3 py-2', 'border-info bg-info-bg' => $tone === 'info', 'border-ok bg-ok-bg' => $tone === 'ok', 'border-bad bg-bad-bg' => $tone === 'bad', 'border-warn bg-warn-bg' => $tone === 'warn'])>
                    <div class="font-semibold text-{{ $tone }}">{{ $title }}</div><div class="text-sm text-ink-3">{{ $sub }}</div>
                </div>
            @endif
        </section>

    @endif

    @php($entriesCard = true)
    <div @class(['grid items-start gap-3', 'lg:grid-cols-2' => $open])>
        {{-- left: what's recorded in this session --}}
        <x-ops.card title="Session entries" :subtitle="$items->count().' parcels'" flush>
            <div class="max-h-[460px] overflow-y-auto">
                @forelse ($items as $it)
                    @php([$label, $tone] = \App\Models\Ops\CourierSessionItem::TYPES[$it->type])
                    <div wire:key="it{{ $it->id }}" class="flex items-start gap-2.5 border-t border-soft px-3.5 py-2 text-sm first:border-0 hover:bg-zebra">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2"><span class="mono font-semibold">{{ $it->shipment->code }}</span><x-ops.badge :tone="$tone">{{ $label }}</x-ops.badge><span class="ml-auto tabular-nums">{{ tk($it->shipment->cod_amount) }}</span></div>
                            <div class="mt-0.5 truncate text-xs text-ink-muted"><a href="{{ route('ops.orders.show', $it->shipment->order_id) }}" wire:navigate class="mono">{{ $it->shipment->order?->number }}</a> {{ $it->shipment->order?->customer?->name }} · {{ $it->created_at?->format('d M H:i') }} · {{ $it->user?->name ?? '—' }}@if ($it->customerReturn) · <span class="mono text-brand">{{ $it->customerReturn->code }}</span>@endif{{ $it->note ? ' · '.$it->note : '' }}</div>
                        </div>
                        @if ($open)@php($why = \App\Services\Ops\EntrySessions::undoBlocked($it))
                            @if ($why)<span class="cursor-help pt-0.5 text-xs text-ink-faint" title="{{ $why }}">Locked</span>
                            @else<button type="button" wire:click="undo({{ $it->id }})" wire:confirm="Undo this entry?" class="pt-0.5 text-xs text-ink-muted hover:text-bad">Undo</button>@endif
                        @endif
                    </div>
                @empty <div class="px-3.5 py-6 text-center text-ink-muted">Nothing recorded yet — scan a parcel above.</div> @endforelse
            </div>
        </x-ops.card>

        {{-- right: depends on the tab --}}
        @if ($open)
            @if ($mode === 'sent')
                <x-ops.card title="Ready to hand over" :subtitle="$this->toSend->count().' packed parcels for '.$session->courier->name" flush>
                    <div class="max-h-72 overflow-y-auto">
                        @forelse ($this->toSend as $p)
                            <div wire:key="ts{{ $p->id }}" class="flex items-center gap-3 border-t border-soft px-3.5 py-2 text-sm first:border-0">
                                <span class="mono w-14 font-semibold">{{ $p->code }}</span><span class="mono text-ink-muted">{{ $p->order->number }}</span>
                                <span class="min-w-0 flex-1 truncate">{{ $p->order->customer->name }} <span class="text-xs text-ink-muted">{{ $p->order->customer->area }}</span></span>
                                <span class="tabular-nums">{{ tk($p->cod_amount) }}</span>
                                <button type="button" wire:click="act({{ $p->id }}, 'sent')" class="btn-secondary btn-sm">Hand over</button>
                            </div>
                        @empty <div class="px-3.5 py-4 text-ink-muted">Nothing packed for this courier.</div> @endforelse
                    </div>
                </x-ops.card>
            @else
                <x-ops.card title="Returning parcels" :subtitle="$this->toReceive->count().' parcels the courier must bring back · returns, cancelled, exchange, never delivered'" flush>
                    <div class="max-h-72 overflow-y-auto">
                        @forelse ($this->toReceive as $p)
                            @php($wasRejected = in_array($p->id, $this->rejectedIds))
                            <div wire:key="tr{{ $p->id }}" class="border-t border-soft first:border-0" x-data="{ edit: false, flag: @js($p->return_flag), note: @js($p->return_note) }">
<div class="flex flex-wrap items-center gap-2 px-3.5 py-2 text-sm ">
                                <span class="mono w-14 font-semibold">{{ $p->code }}</span><span class="mono text-ink-muted">{{ $p->order->number }}</span>
                                <span class="min-w-0 flex-1"><span class="block truncate">{{ $p->order->customer->name }}</span><span class="block truncate text-xs text-ink-muted" title="{{ $p->return_reason }}">{{ $p->return_reason }}</span></span>
                                @if ($p->order->exchange_of_id)<x-ops.badge tone="info">Exchange</x-ops.badge>@endif
                                <x-ops.badge map="courier_status" :status="$p->courier_status" />
                                @if ($wasRejected)<x-ops.badge tone="bad">Rejected</x-ops.badge>@endif
                                <button type="button" wire:click="act({{ $p->id }}, 'returned')" class="btn-secondary btn-sm">Receive</button>
                                <button type="button" @click="edit = !edit" class="btn-sm text-ink-muted hover:text-ink">Note</button>
                            @unless ($wasRejected)<button type="button" wire:click="pickReject({{ $p->id }})" class="btn-sm text-bad">Damaged</button>@endunless
                            </div>
                            @if ($w = \App\Services\Ops\EntrySessions::flagText($p))<div class="mx-3.5 mb-2 rounded bg-warn-bg px-2 py-1 text-xs text-warn">⚠ {{ $w }}</div>@endif
                            <div x-cloak x-show="edit" class="mx-3.5 mb-2 flex flex-wrap gap-1.5"><select x-model="flag" class="input w-auto py-1 text-sm"><option value="">No flag</option>@foreach (\App\Services\Ops\EntrySessions::FLAGS as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach</select><input x-model="note" class="input min-w-0 flex-1 py-1 text-sm" placeholder="Reason / damage seen — e.g. customer says size wrong, box wet"><button type="button" @click="$wire.saveReturnNote({{ $p->id }}, flag, note); edit = false" class="btn-secondary btn-sm">Save</button></div>
                        </div>
                        @empty <div class="px-3.5 py-4 text-ink-muted">No returning parcels for this courier.</div> @endforelse
                    </div>
                </x-ops.card>
            @endif
        @endif
    </div>
</div>
