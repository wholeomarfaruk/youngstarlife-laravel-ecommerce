<div class="flex flex-col gap-3">
    <section class="card flex flex-wrap items-center gap-3 px-3 py-2.5">
        <div class="flex flex-col gap-0.5"><span class="text-xs text-ink-muted">Receiving from</span>
            <div class="flex overflow-hidden rounded border border-line-2">@foreach ($this->couriers as $c)<button wire:click="$set('courier', {{ $c->id }})" @class(['border-r border-line px-3 py-1.5 last:border-0', 'bg-ink text-white' => $courier === $c->id, 'bg-white' => $courier !== $c->id])>{{ $c->name }} <span class="text-xs opacity-75">{{ $c->returning }}</span></button>@endforeach</div></div>
        <label class="ml-auto flex cursor-pointer items-center gap-2 rounded border border-line-2 px-2.5 py-1.5"><input type="checkbox" wire:model.live="fast" class="accent-brand"> <span class="flex flex-col"><span class="font-medium">Fast mode</span><span class="text-xs text-ink-muted">Parcel scan = all items good</span></span></label>
    </section>

    <div class="flex flex-wrap items-start gap-3">
        <div class="flex min-w-0 flex-[999_1_480px] flex-col gap-2.5">
            <section class="card flex flex-col gap-2.5 p-3.5" x-data="scanner('scan')">
                <form @submit.prevent="submit" class="flex flex-wrap gap-2">
                    <div class="flex min-w-0 flex-[1_1_260px] items-center gap-2.5 rounded-md border-2 border-ink bg-white px-3"><span class="mono rounded-sm border border-line px-1 text-xs text-ink-muted">SCAN</span><input x-ref="input" x-model="code" autocomplete="off" class="mono min-w-0 flex-1 border-0 bg-transparent py-3 text-lg outline-none" placeholder="Parcel ID, tracking, order no. or product barcode"></div>
                    <button class="btn-dark min-h-[44px] px-4">Enter</button>
                </form>
                @if ($flash)<div @class(['rounded-md px-3 py-2.5', 'bg-ok-bg text-ok' => $flash['tone'] === 'ok', 'bg-bad-bg text-bad' => $flash['tone'] === 'bad', 'bg-info-bg text-info' => $flash['tone'] === 'info'])><div class="font-semibold">{{ $flash['title'] }}</div><div class="text-sm">{{ $flash['sub'] }}</div></div>@endif
            </section>

            @if ($p = $this->active)
                <section class="card border-ink">
                    <div class="card-head"><span class="font-semibold">Checking <span class="mono">{{ $p->code }}</span> · {{ $p->order->number }} · {{ $p->order->customer->name }}</span><button wire:click="confirm" class="btn-primary">Confirm parcel</button></div>
                    <div class="overflow-x-auto">@foreach ($p->order->items as $i)
                        <div class="grid min-w-[700px] grid-cols-[24px_minmax(140px,1fr)_160px_140px] items-center gap-2.5 border-b border-soft px-3 py-2">
                            <input type="checkbox" wire:model="lines.{{ $i->id }}.got" class="h-5 w-5 accent-brand">
                            <div><div class="font-medium">{{ $i->variant->label }}</div><div class="mono text-xs text-ink-muted">{{ $i->variant->barcode }}</div></div>
                            <select wire:model="lines.{{ $i->id }}.reason" class="input py-1">@foreach (config('admin.return_reasons') as $r)<option>{{ $r }}</option>@endforeach</select>
                            <select wire:model="lines.{{ $i->id }}.condition" class="input py-1">@foreach (config('admin.return_conditions') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                        </div>@endforeach</div>
                    <div class="p-3"><textarea wire:model="note" rows="2" class="input" placeholder="Note, e.g. Customer received 2 items, Blue XL was damaged, returned only XL."></textarea></div>
                </section>
            @endif

            <x-admin.card :title="'Return list · '.$this->expected->count().' parcels'" flush>
                @forelse ($this->expected as $s)
                    <button wire:click="scan('{{ $s->code }}')" class="grid w-full grid-cols-[90px_minmax(0,1fr)_120px] items-center gap-2.5 border-t border-soft px-3 py-2.5 text-left first:border-0 hover:bg-zebra">
                        <span class="mono font-semibold">{{ $s->code }}</span><span>{{ $s->order->number }} · {{ $s->order->customer->name }}</span><x-admin.badge map="courier_status" :status="$s->courier_status" />
                    </button>
                @empty <div class="p-4 text-ink-muted">No parcels coming back from this courier.</div> @endforelse
            </x-admin.card>

            <x-admin.card title="Received returns" flush>
                <x-slot:actions><x-admin.search placeholder="Search return or order…" class="!flex-none w-56" /></x-slot:actions>
                @foreach ($this->received as $r)
                    <div class="flex flex-wrap items-center gap-x-3.5 gap-y-2 border-t border-soft px-3 py-2.5 first:border-0" wire:key="r{{ $r->id }}">
                        <span class="mono min-w-[70px] font-semibold">{{ $r->code }}</span>
                        <span class="flex-[1_1_160px]">{{ $r->order->customer->name }} <span class="mono text-xs text-brand">{{ $r->order->number }}</span></span>
                        <span class="flex-[2_1_220px] text-sm">@foreach ($r->items as $it){{ $it->orderItem->variant->label }} <span class="mono text-ink-muted">{{ $it->batch?->code }}</span> · {{ config('admin.return_conditions.'.$it->condition) }}@if (! $loop->last), @endif @endforeach</span>
                        @if ($r->refund_amount && ! $r->refunded_at)
                            <div x-data="{ via: 'bKash' }" class="flex gap-1.5"><select x-model="via" class="input w-auto py-1"><option>bKash</option><option>Nagad</option><option>Cash</option><option>Bank</option></select><button @click="$wire.refund({{ $r->id }}, via)" class="btn-primary btn-sm">Refund {{ tk($r->refund_amount) }}</button></div>
                        @elseif ($r->refunded_at) <span class="text-sm font-semibold text-ok">Refunded · {{ $r->refund_via }}</span> @endif
                    </div>
                @endforeach
            </x-admin.card>
        </div>
    </div>
</div>
