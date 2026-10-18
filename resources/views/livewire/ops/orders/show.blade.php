@php($o = $order)
<div class="flex flex-col gap-3">
    <a href="{{ route('ops.orders.index') }}" wire:navigate class="self-start text-sm">← Back to orders</a>
    <x-ops.page-header :title="'Order '.$o->number" :subtitle="$o->placed_at->format('d M Y, H:i').' · '.config('ops.sources.'.$o->source, ucfirst($o->source)).($o->campaign ? ' · '.$o->campaign->name : '').($o->creator ? ' · by '.$o->creator->name : '')">
        <x-ops.badge map="order_status" :status="$o->status" />@if ($pr = $o->partialReturnStage())<x-ops.badge :tone="$pr[1]">{{ $pr[0] }}</x-ops.badge>@endif<x-ops.badge map="payment_status" :status="$o->payment_status" />
        <a href="{{ route('ops.orders.print', ['ids' => $o->id]) }}" target="_blank" class="btn-secondary">Print invoice</a>
        @if ($this->editable() && ! $edit)<button wire:click="$set('edit', true)" class="btn-secondary">Edit order</button>@endif
        @if ($o->status === 'confirmed')
            <a href="{{ route('ops.packing.scan', ['order' => $o->number]) }}" wire:navigate class="btn-primary">Pack & scan</a>
        @elseif (in_array($o->status, ['pending', 'packed']))
            <button wire:click="advance" wire:loading.attr="disabled" class="btn-primary">{{ ['pending' => 'Confirm order', 'packed' => 'Mark shipped'][$o->status] }}</button>
        @elseif ($o->status === 'on_hold')
            <button wire:click="resume({{ $o->id }})" class="btn-primary">Resume order</button>
        @elseif ($o->status === 'cancelled')
            <button x-on:click="opsConfirm({ title: @js('Restore '.$o->number.'?'), text: 'It goes back to Pending — confirm and pack it again as usual.', confirm: 'Restore' }).then(ok => ok && $wire.restore({{ $o->id }}))" class="btn-primary">Restore order</button>
        @endif
        @if ($this->canResend())<button type="button" x-data @click="$dispatch('open-modal','resend')" class="btn-secondary">Send again…</button>@endif
        @include('livewire.ops.orders.partials.row-menu', ['o' => $o])
    </x-ops.page-header>
    @if ($o->tags)<div class="flex flex-wrap gap-1.5">@foreach ($o->tags as $t)<x-ops.badge :tone="in_array($t, ['Advance required', 'Blacklist review']) ? 'bad' : ($t === 'Auto-confirmed' ? 'ok' : 'info')">{{ $t }}</x-ops.badge>@endforeach</div>@endif
    @if (($bl = $this->blacklisted)->isNotEmpty())
        @php($b = $bl->first())
        <div @class(['flex flex-wrap items-center gap-2 rounded-md px-3 py-2.5 text-sm', 'bg-bad-bg text-bad' => $b->action === 'block', 'bg-warn-bg text-warn' => $b->action !== 'block'])>
            <b>On the blacklist</b> · {{ $bl->map(fn ($e) => config('ops.blacklist_types.'.$e->type).' '.($e->type === 'device' ? '#'.$e->value : $e->value).' ('.config('ops.blacklist_actions.'.$e->action.'.0').')')->join(', ') }}
            · {{ config('ops.blacklist_reasons.'.$b->reason, '') }}@if ($b->note) · “{{ \Illuminate\Support\Str::limit($b->note, 80) }}”@endif
            <a href="{{ route('ops.blacklist.index', ['search' => $b->value, 'status' => 'any']) }}" wire:navigate class="ml-auto underline">Open blacklist</a>
        </div>
    @endif
    @if ($o->exchangeOf)
        {{-- exchange lifecycle --}}
        @php($xl = $this->exchangeLine) @php($st = $o->exchange_state) @php($sh = $o->shipment)
        @php($steps = ['awaiting' => 'Created', 'out' => 'With courier', 'collecting' => 'Delivered', 'completed' => 'Old item back'])
        @php($reach = ['awaiting' => 0, 'out' => 1, 'collecting' => 2, 'completed' => 3, 'old_missing' => 3][$st] ?? null)
        <section class="card border-info">
            <div class="card-head"><div class="flex min-w-0 flex-col"><span class="card-title">Exchange for <a href="{{ route('ops.orders.show', $o->exchange_of_id) }}" wire:navigate class="mono">{{ $o->exchangeOf->number }}</a></span><span class="text-sm text-ink-muted">New item goes out, the rider brings the old one back.</span></div>
                <x-ops.badge :tone="config('ops.exchange_state.'.$st.'.1', 'mute')">{{ config('ops.exchange_state.'.$st.'.0', $st ?? '—') }}</x-ops.badge></div>
            <div class="flex flex-col gap-3 p-3.5">
                @if ($reach !== null)
                    <ol class="grid grid-cols-4 gap-1 text-xs">@foreach (array_values($steps) as $k => $label)<li class="flex flex-col gap-1"><span @class(['h-1.5 rounded-full', 'bg-brand' => $k <= $reach && $st !== 'old_missing', 'bg-bad' => $st === 'old_missing' && $k === 3, 'bg-soft' => $k > $reach])></span><span @class(['font-semibold' => $k === $reach, 'text-ink-muted' => $k !== $reach])>{{ $st === 'old_missing' && $k === 3 ? 'Old item missing' : $label }}</span></li>@endforeach</ol>
                @endif
                <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><div class="text-xs text-ink-muted">New item (this order)</div>{{ $o->items->map(fn ($i) => $i->variant->label.' ×'.$i->qty)->join(', ') }}</div>
                    <div><div class="text-xs text-ink-muted">Old item to collect @if ($xl)<span class="mono">{{ $xl->code }}</span> <x-ops.badge :tone="config('ops.return_status.'.$xl->status.'.1', 'mute')">{{ config('ops.return_status.'.$xl->status.'.0', $xl->status) }}</x-ops.badge>@endif</div>
                        @foreach ($xl?->items ?? [] as $it)<div>{{ $it->orderItem->variant->label }} ×{{ $it->qty }} <span class="text-xs text-ink-muted">· {{ $it->reason }}</span>@if ($it->condition) @php($cfg = config('ops.return_conditions.'.$it->condition))<x-ops.badge :tone="$cfg[3] ?? 'mute'">{{ $cfg[2] ?? $it->condition }}</x-ops.badge>@endif</div>@endforeach
                        @if ($xl?->refund_amount)<div class="mt-1 text-xs text-warn">Price difference refund {{ tk($xl->refund_amount) }}{{ $xl->refunded_at ? ' · refunded' : ' · due after the old item is back' }}</div>@endif
                    </div>
                </div>
                @if (in_array($st, ['awaiting', 'out']))
                    @php($cn = app(\App\Services\Ops\ExchangeService::class)->courierNote($o))
                    <div class="flex items-start gap-2 rounded-md bg-zebra px-3 py-2 text-xs"><span class="flex-1"><b>Rider instruction</b> (sent with the parcel through the API — paste it in the courier panel when booking by hand): {{ $cn }}</span><button type="button" onclick="opsCopy(@js($cn))" class="btn-secondary btn-sm">Copy</button></div>
                @endif
                @if ($st === 'refused')<div class="rounded-md bg-bad-bg px-3 py-2 text-sm text-bad">The customer refused the exchange. Receive the new item at <a href="{{ route('ops.returns.receive') }}" wire:navigate>Return receive</a> — the old item stays with the customer.</div>@endif
                <div class="flex flex-wrap gap-1.5">
                    @if (in_array($st, \App\Services\Ops\ExchangeService::RECEIVABLE) && $xl?->status === 'expected')
                        <a href="{{ route('ops.returns.receive', ['exchange' => $o->number]) }}" wire:navigate class="btn-primary btn-sm">Receive old item</a>
                        <button type="button" x-data @click="$dispatch('open-modal','old-missing')" class="btn-danger btn-sm">Old item not returned</button>
                    @endif
                    @if ($st === 'awaiting')<button type="button" wire:click="cancelExchange" wire:confirm="Cancel exchange {{ $o->number }}? The new items go back to stock and nothing is collected.{{ $sh?->booked_at ? ' It was already sent to '.$sh->courier->name.' — cancel it in their panel too.' : '' }}" class="btn-secondary btn-sm">Cancel exchange</button>@endif
                </div>
            </div>
        </section>
        <x-ops.modal name="old-missing" title="Old item not returned">
            <p class="mb-2 text-sm text-ink-muted">The new item was delivered but the rider did not bring the old one back. The old-item line is closed as <b>missing → courier claim</b>.</p>
            <x-ops.field label="What happened" required :error="$errors->first('missingNote')"><textarea wire:model="missingNote" rows="3" class="input" placeholder="e.g. Rider says customer will send it later / rider forgot to collect"></textarea></x-ops.field>
            <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="markOldMissing" class="btn-danger">Close as missing</button></x-slot:footer>
        </x-ops.modal>
    @endif
    @if ($this->exchangesOf->isNotEmpty())
        {{-- exchanges are part of this order: each one = new items out in its own parcel, old items back --}}
        <section class="card">
            <div class="card-head"><span class="card-title">Exchanges <span class="text-sm font-normal text-ink-muted">{{ $this->exchangesOf->count() }}</span></span>
                @if (in_array($o->status, ['delivered', 'partially_delivered']))<a href="{{ route('ops.exchanges.index', ['order' => $o->number]) }}" wire:navigate class="btn-secondary btn-sm">+ New exchange</a>@endif</div>
            @foreach ($this->exchangesOf->sortBy('id') as $ex)
                @php([$label, $tone] = $ex->exchangeStage()) @php($xs = $ex->shipment)
                <div wire:key="ex{{ $ex->id }}" class="flex flex-col gap-2 border-t border-soft px-3.5 py-3 first:border-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">Exchange {{ \Illuminate\Support\Str::afterLast($ex->number, '-EX') }}</span><span class="mono text-xs text-ink-muted">{{ $ex->number }}</span>
                        <x-ops.badge :tone="$tone">{{ $label }}</x-ops.badge>
                        <span class="ml-auto text-sm">COD <b>{{ tk($ex->cod) }}</b></span>
                    </div>
                    <div class="grid gap-2 text-sm sm:grid-cols-2">
                        <div><div class="text-xs text-ink-muted">Old item (comes back) @if ($ex->exchangeLine)<x-ops.badge :tone="config('ops.return_status.'.$ex->exchangeLine->status.'.1', 'mute')">{{ config('ops.return_status.'.$ex->exchangeLine->status.'.0', $ex->exchangeLine->status) }}</x-ops.badge>@endif</div>
                            @foreach ($ex->exchangeLine?->items ?? [] as $it)<div class="mt-1 flex items-center gap-2">@if ($p = $it->orderItem->variant->product)<img src="{{ $p->thumb_url }}" alt="" loading="lazy" class="h-8 w-8 rounded bg-soft object-cover">@endif<span>{{ $it->orderItem->variant->label }} ×{{ $it->qty }} <span class="text-xs text-ink-muted">· {{ $it->reason }}</span></span></div>@endforeach</div>
                        <div><div class="text-xs text-ink-muted">New item (goes out)</div>
                            @foreach ($ex->items as $it)<div class="mt-1 flex items-center gap-2">@if ($p = $it->variant->product)<img src="{{ $p->thumb_url }}" alt="" loading="lazy" class="h-8 w-8 rounded bg-soft object-cover">@endif<span>{{ $it->variant->label }} ×{{ $it->qty }}</span></div>@endforeach</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 text-sm">
                        @if ($xs)<span class="text-ink-muted">Parcel <span class="mono text-ink">{{ $xs->code }}</span> · {{ $xs->courier?->name }}@if ($xs->tracking || $xs->consignment_id) · <x-ops.parcel-ref :s="$xs" inline />@endif</span><x-ops.badge map="courier_status" :status="$xs->courier_status" />@endif
                        <span class="ml-auto flex flex-wrap gap-1.5">
                            @if ($ex->status === 'pending')<button wire:click="setStatus({{ $ex->id }}, 'confirmed')" class="btn-secondary btn-sm">Confirm</button>@endif
                            @if (in_array($ex->status, ['pending', 'confirmed']))<a href="{{ route('ops.packing.scan', ['order' => $ex->number]) }}" wire:navigate class="btn-secondary btn-sm">Pack & scan</a>@endif
                            @if (in_array($ex->status, \App\Services\Ops\CourierBooking::OPEN) && ! $xs?->booked_at)<button x-data @click="$dispatch('open-modal', 'book-courier-one'); $dispatch('book-courier', { id: {{ $ex->id }} })" class="btn-secondary btn-sm">Book courier…</button>@endif
                            @if (in_array($ex->exchange_state, \App\Services\Ops\ExchangeService::RECEIVABLE) && $ex->exchangeLine?->status === 'expected')<a href="{{ route('ops.returns.receive', ['exchange' => $ex->number]) }}" wire:navigate class="btn-secondary btn-sm">Receive old item</a>@endif
                            <a href="{{ route('ops.orders.show', $ex) }}" wire:navigate class="btn-ghost btn-sm">Details →</a>
                        </span>
                    </div>
                </div>
            @endforeach
        </section>
    @endif
    @if ($errors->any())<div class="rounded-md bg-bad-bg px-3 py-2.5 text-bad">{{ $errors->first() }}</div>@endif

    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="flex min-w-0 flex-col gap-3">
            @if ($edit)
                <x-ops.card title="Edit order" subtitle="Only before packing. Stock is re-reserved from batches when items change.">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <x-ops.field label="Customer name" required><input wire:model="e.name" class="input"></x-ops.field>
                        <x-ops.field label="Phone" required><input wire:model="e.phone" class="input" inputmode="tel"></x-ops.field>
                        <x-ops.field label="Delivery area" required><select wire:model.live="e.zone" class="input">@foreach ($this->zones as $z)<option value="{{ $z->id }}">{{ $z->name }} · {{ tk($z->charge) }}</option>@endforeach</select></x-ops.field>
                        <x-ops.field label="Address" required class="sm:col-span-3"><input wire:model="e.address" class="input"></x-ops.field>
                    </div>
                    <div class="mt-3 rounded-md border border-line">
                        @foreach ($e['lines'] as $k => $l)<div class="flex flex-wrap items-center gap-2 border-b border-soft px-2.5 py-2"><span class="flex-1">{{ $l['label'] }}</span><input type="number" min="1" wire:model.live.debounce.300ms="e.lines.{{ $k }}.qty" class="input w-16 py-1"><span class="text-ink-muted">×</span><input type="number" min="0" wire:model.live.debounce.300ms="e.lines.{{ $k }}.unit_price" class="input w-24 py-1"><button type="button" wire:click="removeLine({{ $k }})" class="px-1 text-bad">×</button></div>@endforeach
                        <div class="flex gap-2 p-2.5"><x-ops.searchable-select field="pick" class="flex-1" placeholder="Add product…" searchPlaceholder="Name, size, SKU or barcode…"
                        :options="$this->variants->mapWithKeys(fn ($v) => [$v->id => $v->label.' · '.tk($v->effective_price).' · '.$v->stock.' in stock · '.$v->sku.' · '.$v->barcode])->all()"
                        :images="$this->variants->mapWithKeys(fn ($v) => [$v->id => $v->product->thumb_url])->all()" /><button type="button" wire:click="addLine" class="btn-secondary">+ Add</button></div>
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-[160px_1fr]">
                        <x-ops.field label="Discount (৳)"><input type="number" min="0" wire:model.live.debounce.300ms="e.discount" class="input"></x-ops.field>
                        <x-ops.field label="Note"><input wire:model="e.note" class="input"></x-ops.field>
                    </div>
                    @php($sub = collect($e['lines'])->sum(fn ($l) => (int) $l['qty'] * (int) $l['unit_price'])) @php($ch = (int) ($this->zones->firstWhere('id', $e['zone'])?->charge ?? 0))
                    <div class="mt-3 flex flex-wrap items-center justify-end gap-3"><span class="text-sm text-ink-muted">Subtotal {{ tk($sub) }} − {{ tk((int) $e['discount']) }} + delivery {{ tk($ch) }} =</span><b class="text-lg">{{ tk(max(0, $sub - (int) $e['discount'] + $ch)) }}</b><button wire:click="$set('edit', false)" class="btn-secondary">Cancel</button><button wire:click="saveEdit" class="btn-primary">Save changes</button></div>
                </x-ops.card>
            @endif
            <x-ops.card title="Items · batch traceability" flush>
                <x-ops.table min-width="680px">
                    <x-slot:head><th class="th">Product</th><th class="th">Batch → supplier</th><th class="th text-right">Qty</th><th class="th text-right">Price</th><th class="th text-right">Cost (at pack)</th></x-slot:head>
                    @foreach ($o->items as $i)
                        <tr><td class="td"><div class="flex items-start gap-2.5">
                            @if ($p = $i->variant->product)<a href="{{ $p->image_url }}" target="_blank" class="shrink-0"><img src="{{ $p->thumb_url }}" alt="" loading="lazy" class="h-12 w-12 rounded-md bg-soft object-cover"></a>@endif
                            <div class="min-w-0"><div class="font-medium">{{ $i->variant->label }}</div>@if ($i->combo_text)<div class="text-xs font-semibold text-info">Combo: {{ $i->combo_text }}</div>@endif<div class="mono text-xs text-ink-muted">{{ $i->variant->sku }}@if ($i->returned_qty) · <span class="text-bad">{{ $i->returned_qty }} returned</span>@endif</div></div>
                        </div></td>
                            <td class="td">@forelse ($i->allocations as $a)<div><a href="{{ route('ops.trace', ['q' => $a->batch->code]) }}" wire:navigate class="mono font-semibold">{{ $a->batch->code }}</a> <span class="text-xs text-ink-muted">→ {{ $a->batch->supplier->name }}{{ $a->batch->purchaseOrder ? ' · '.$a->batch->purchaseOrder->number : '' }} × {{ $a->qty }}</span></div>@empty<span class="text-ink-muted">Not allocated</span>@endforelse</td>
                            <td class="td text-right">{{ $i->qty }}</td><td class="td text-right tabular-nums">{{ tk($i->unit_price) }}</td>
                            <td class="td text-right tabular-nums text-ink-muted">{{ $i->allocations->whereNotNull('unit_cost')->isNotEmpty() ? tk($i->allocations->sum(fn ($a) => $a->qty * $a->unit_cost)) : '—' }}</td></tr>
                    @endforeach
                </x-ops.table>
                <dl class="ml-auto grid w-full max-w-[280px] grid-cols-[1fr_auto] gap-x-6 gap-y-1 p-3.5 tabular-nums">
                    <dt class="text-ink-muted">Subtotal</dt><dd class="text-right">{{ tk($o->subtotal) }}</dd>
                    <dt class="text-ink-muted">Discount</dt><dd class="text-right">− {{ tk($o->discount) }}</dd>
                    <dt class="text-ink-muted">Delivery</dt><dd class="text-right">{{ tk($o->delivery_charge) }}</dd>
                    <dt class="border-t border-soft pt-1 font-semibold">Total</dt><dd class="border-t border-soft pt-1 text-right font-bold">{{ tk($o->total) }}</dd>
                    <dt class="text-ink-muted">Advance</dt><dd class="text-right">{{ tk($o->advance) }}</dd>
                    <dt class="font-semibold">COD to collect</dt><dd class="text-right font-semibold text-brand">{{ tk($o->cod) }}</dd>
                    @if ($o->cost)<dt class="text-ink-muted">Cost (batches)</dt><dd class="text-right text-ink-muted">{{ tk($o->cost) }}</dd>@endif
                </dl>
            </x-ops.card>
            <x-ops.card title="Timeline" id="timeline">
                @foreach ($o->events as $ev)
                    <div class="grid grid-cols-[100px_minmax(0,1fr)] gap-2.5 border-b border-soft py-2 text-sm last:border-0"><span class="mono text-xs text-ink-muted">{{ $ev->created_at->format('d M H:i') }}</span><span>@if ($ev->type === 'note')<span class="badge-warn mr-1">Note</span>@endif{{ $ev->message }} <span class="text-ink-muted">· {{ $ev->user?->name ?? 'System' }}</span></span></div>
                @endforeach
                <form wire:submit="addNote" class="mt-2 flex gap-2"><input wire:model="note" class="input" placeholder="Add an internal note…"><button class="btn-secondary">Add</button></form>
            </x-ops.card>
        </div>
        <div class="flex flex-col gap-3">
            @php($rate = $o->customer->delivery_rate)
            <x-ops.card title="Customer">
                <a href="{{ route('ops.customers.index', ['search' => $o->customer->phone]) }}" wire:navigate class="font-semibold">{{ $o->customer->name }}</a>
                <div class="text-sm text-ink-muted">{{ $o->customer->orders_count }} orders · <span @class(['font-semibold', 'text-bad' => $rate !== null && $rate < .75, 'text-ok' => $rate !== null && $rate >= .9])>{{ $rate !== null ? round($rate * 100).'% delivered' : 'new customer' }}</span></div>
                <div class="mt-2 text-sm"><a href="tel:{{ $o->ship_phone }}" class="mono">{{ $o->ship_phone }}</a><br>{{ $o->ship_address }}<br><span class="text-ink-muted">{{ $o->zone?->name }} · {{ tk($o->delivery_charge) }}</span></div>
            </x-ops.card>
            <x-ops.card title="Payment">
                <dl class="grid grid-cols-[1fr_auto] gap-y-1 text-sm tabular-nums"><dt class="text-ink-muted">Status</dt><dd><x-ops.badge map="payment_status" :status="$o->payment_status" /></dd><dt class="text-ink-muted">Advance paid</dt><dd>{{ tk($o->advance) }}</dd><dt class="text-ink-muted">COD to collect</dt><dd class="font-semibold">{{ tk($o->cod) }}</dd></dl>
                @if ($o->cod > 0 && ! in_array($o->status, ['delivered', 'cancelled', 'returned']))<button x-data @click="$dispatch('open-modal','order-pay')" class="btn-secondary btn-sm mt-2 w-full">Record advance payment</button>@endif
            </x-ops.card>
            <x-ops.card title="Shipment">
                @if ($s = $o->shipment)
                    @php($api = $s->courier->hasApi()) @php($connected = $s->courier->isConnected())
                    <div class="flex flex-col gap-1 text-sm"><span>{{ $s->courier->name }} · <span class="mono">{{ $s->code }}</span></span>
                        @if ($s->consignment_id)<span class="text-ink-muted">Consignment <span class="mono text-ink">{{ $s->consignment_id }}</span> <button type="button" onclick="opsCopy('{{ $s->consignment_id }}')" class="text-xs text-brand">copy</button></span>@endif
                        @if ($s->tracking && $s->tracking !== $s->consignment_id)<span class="mono text-ink-muted">{{ $s->tracking }} <button type="button" onclick="opsCopy('{{ $s->tracking }}')" class="text-xs text-brand">copy</button></span>@endif
                        <div class="flex flex-wrap items-center gap-1.5"><x-ops.badge map="courier_status" :status="$s->courier_status" />@if ($s->tracking_url)<a href="{{ $s->tracking_url }}" target="_blank" rel="noopener" class="text-xs">track ↗</a>@endif</div>
                        <span class="text-xs text-ink-muted">Packed {{ $s->packed_at?->format('d M H:i') ?? '—' }}{{ $s->booked_at ? ' · sent '.$s->booked_at->format('d M H:i') : '' }}{{ $s->handed_over_at ? ' · handed over '.$s->handed_over_at->format('d M H:i') : '' }}{{ $s->delivered_at ? ' · delivered '.$s->delivered_at->format('d M') : '' }}</span>
                        @if ($s->charge)<span class="text-xs text-ink-muted">Delivery charge {{ tk($s->charge) }}@if (isset($s->courier_meta['collected'])) · collected {{ tk($s->courier_meta['collected']) }}@endif</span>@endif
                    </div>

                    {{-- needs attention: stuck / hold / failed attempts / booking failed / complaint (Shipment::attentionReasons) --}}
                    @if ($why = $s->attentionReasons())
                        <div class="mt-2 rounded-md bg-warn-bg px-2.5 py-1.5 text-xs text-warn"><b>Needs attention:</b> {{ implode(' · ', $why) }}@if ($s->complaint)<div class="mt-0.5 text-ink-3">“{{ $s->complaint }}”</div>@endif</div>
                    @endif
                    @if ($s->return_flag || $s->return_note)<div class="mt-2 rounded-md bg-zebra px-2.5 py-1.5 text-xs"><b>Return note</b>{{ $s->return_flag ? ' · '.$s->return_flag : '' }}{{ $s->return_note ? ' — '.$s->return_note : '' }}</div>@endif

                    {{-- what is inside this parcel (packed units → batches) --}}
                    @if ($s->packed_at)
                        @php($inside = $s->contents())
                        <div class="mt-3 border-t border-soft pt-2">
                            <div class="mb-1 flex items-center justify-between text-xs text-ink-muted"><span>Inside the parcel · {{ collect($inside)->sum('qty') }} unit(s)</span><span>packed by {{ $s->packer?->name ?? '—' }}</span></div>
                            @foreach ($inside as $c)
                                <div class="flex items-center gap-2 py-1 text-xs">
                                    @if ($c['image'])<img src="{{ $c['image'] }}" alt="" class="h-8 w-8 flex-none rounded object-cover" loading="lazy">@endif
                                    <span class="min-w-0 flex-1"><span class="block truncate">{{ $c['label'] }} × {{ $c['qty'] }}</span>
                                        @if ($c['batches'])<span class="mono text-ink-muted">@foreach ($c['batches'] as $b){{ $loop->first ? '' : ', ' }}<a href="{{ route('ops.trace', ['q' => $b['code']]) }}" wire:navigate class="text-ink-muted hover:text-ink">{{ $b['code'] }}</a>{{ count($c['batches']) > 1 ? ' ×'.$b['qty'] : '' }}{{ $b['confirmed'] ? ' ✓' : '' }}@endforeach</span>@endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <a href="{{ route('ops.orders.parcel-labels', ['ids' => $o->id]) }}" target="_blank" class="btn-secondary btn-sm mt-2 w-full">Print parcel label</a>

                    {{-- courier API --}}
                    @if ($api)
                        @if (! $s->booked_at)
                            @if ($s->booking_error)<div class="mt-2 rounded bg-bad-bg px-2.5 py-1.5 text-xs text-bad">{{ $s->booking_error }}</div>@endif
                            @if ($connected && ! in_array($o->status, ['cancelled', 'returned', 'delivered']))
                                <button type="button" wire:click="openBook" class="btn-primary btn-sm mt-2 w-full">Send to {{ $s->courier->name }}</button>
                            @elseif (! $connected)
                                <a href="{{ route('ops.settings.index', ['tab' => 'couriers']) }}" wire:navigate class="mt-2 text-xs">Connect the {{ $s->courier->name }} API to send parcels from here →</a>
                            @endif
                        @elseif ($connected && ! $s->isFinal())
                            <button type="button" wire:click="syncParcel" wire:loading.attr="disabled" class="btn-secondary btn-sm mt-2 w-full"><span wire:loading.remove wire:target="syncParcel">Sync status from {{ $s->courier->name }}</span><span wire:loading wire:target="syncParcel">Asking {{ $s->courier->name }}…</span></button>
                        @endif
                    @endif

                    {{-- tracking timeline --}}
                    @php($events = $s->events()->limit(12)->get())
                    @if ($events->isNotEmpty())
                        <ol class="mt-3 flex flex-col gap-1.5 border-t border-soft pt-3 text-xs">
                            @foreach ($events as $ev)
                                <li class="flex gap-2"><span class="mt-1 h-1.5 w-1.5 flex-none rounded-full {{ $ev->status ? 'bg-brand' : 'bg-line-2' }}"></span>
                                    <span class="flex-1">{{ $ev->message ?: $ev->raw_status }}@if ($ev->status) <span class="text-ink-muted">→ {{ config('ops.courier_status.'.$ev->status.'.0') }}</span>@endif</span>
                                    <span class="whitespace-nowrap text-ink-muted" title="{{ ucfirst($ev->source) }}">{{ $ev->created_at->format('d M H:i') }}</span></li>
                            @endforeach
                        </ol>
                    @endif

                    {{-- before it is sent: courier, COD, weight --}}
                    @if ($this->parcelEditable())
                        <details class="mt-3 border-t border-soft pt-2 text-sm">
                            <summary class="cursor-pointer text-xs text-ink-muted">Edit parcel (courier · COD · weight)</summary>
                            <form wire:submit="saveParcel" class="mt-2 flex flex-col gap-2">
                                <x-ops.field label="Courier" :error="$errors->first('parcel.courier_id')"><select wire:model="parcel.courier_id" class="input">@foreach ($this->couriers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-ops.field>
                                <div class="grid grid-cols-2 gap-2">
                                    <x-ops.field label="COD (৳)" :error="$errors->first('parcel.cod')" :hint="'Order COD '.tk($o->cod)"><input type="number" min="0" wire:model="parcel.cod" class="input"></x-ops.field>
                                    <x-ops.field label="Weight (kg)" :error="$errors->first('parcel.weight')"><input type="number" step="0.1" min="0.1" wire:model="parcel.weight" class="input" placeholder="0.5"></x-ops.field>
                                </div>
                                <button class="btn-secondary">Save parcel</button>
                            </form>
                        </details>
                    @endif

                    {{-- complaint to the courier (also In courier → Complaint) --}}
                    @if ($s->booked_at || $s->handed_over_at || $s->complaint)
                        <details class="mt-3 border-t border-soft pt-2 text-sm" @if ($s->complaint) open @endif>
                            <summary class="cursor-pointer text-xs text-ink-muted">Complaint to {{ $s->courier->name }}{{ $s->complaint ? ' · open' : '' }}</summary>
                            <form wire:submit="saveComplaint" class="mt-2 flex flex-col gap-2">
                                <textarea wire:model="complaintText" rows="2" class="input" placeholder="e.g. Stuck in transit 5 days, rider never called the customer"></textarea>
                                <div class="flex gap-2"><button class="btn-secondary flex-1">Save complaint</button>@if ($s->complaint)<button type="button" wire:click="solveComplaint" class="btn-ghost">Mark solved</button>@endif</div>
                            </form>
                        </details>
                    @endif

                    <details class="mt-3 border-t border-soft pt-2 text-sm" @if (! $api) open @endif>
                        <summary class="cursor-pointer text-xs text-ink-muted">Manual update</summary>
                        <form wire:submit="updateShipment" class="mt-2 flex flex-col gap-2">
                            <input wire:model="tracking" class="input mono" placeholder="Courier tracking no.">
                            <div class="flex gap-2"><select wire:model="parcelStatus" class="input">@foreach (config('ops.courier_status') as $key => [$l])<option value="{{ $key }}">{{ $l }}</option>@endforeach</select><button class="btn-secondary">Update</button></div>
                        </form>
                    </details>

                    @if ($api && ! $s->booked_at)
                        <x-ops.modal name="send-courier" :title="'Send '.$s->code.' to '.$s->courier->name" width="max-w-lg">
                            <div class="flex flex-col gap-3">
                                <div class="rounded-md bg-zebra px-3 py-2 text-sm"><div class="font-semibold">{{ $o->ship_name }} · {{ $o->ship_phone }}</div><div class="text-ink-3">{{ $o->ship_address }}</div><div class="mt-1">COD <b>{{ tk($s->cod_amount) }}</b> · {{ $o->items->sum('qty') }} item(s)</div></div>
                                @error('book')<div class="rounded bg-bad-bg px-3 py-2 text-sm text-bad">{{ $message }}</div>@enderror
                                @if ($s->courier->code === 'pathao')
                                    @php($pl = $this->pathaoLists)
                                    @if (! empty($pl['error']))<div class="rounded bg-bad-bg px-3 py-2 text-sm text-bad">{{ $pl['error'] }}</div>@endif
                                    @if ($locationHint)<div class="text-xs text-ink-muted">{{ $locationHint }}</div>@endif
                                    <div class="grid gap-2 sm:grid-cols-3">
                                        <x-ops.field label="City" :error="$errors->first('book.city_id')"><select wire:model.live="book.city_id" class="input"><option value="">From address</option>@foreach ($pl['cities'] as $c)<option value="{{ $c['id'] }}">{{ $c['name'] }}</option>@endforeach</select></x-ops.field>
                                        <x-ops.field label="Zone" :error="$errors->first('book.zone_id')"><select wire:model.live="book.zone_id" class="input" @disabled(! $book['city_id'])><option value="">From address</option>@foreach ($pl['zones'] as $z)<option value="{{ $z['id'] }}">{{ $z['name'] }}</option>@endforeach</select></x-ops.field>
                                        <x-ops.field label="Area"><select wire:model.live="book.area_id" class="input" @disabled(! $book['zone_id'])><option value="">Optional</option>@foreach ($pl['areas'] as $a)<option value="{{ $a['id'] }}">{{ $a['name'] }}</option>@endforeach</select></x-ops.field>
                                    </div>
                                    @if ($pl['quote'])<div class="text-sm">Pathao charge for this zone: <b>{{ tk((int) round($pl['quote']['price'])) }}</b></div>@endif
                                @else
                                    <x-ops.field label="Delivery"><select wire:model="book.delivery_type" class="input"><option value="0">Home delivery</option><option value="1">Point delivery (customer collects)</option></select></x-ops.field>
                                @endif
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <x-ops.field label="Weight (kg)" :error="$errors->first('book.weight')"><input type="number" step="0.1" min="0.1" wire:model.live.debounce.500ms="book.weight" class="input"></x-ops.field>
                                    <x-ops.field label="Second phone"><input wire:model="book.alt_phone" inputmode="tel" class="input" placeholder="Optional"></x-ops.field>
                                    <x-ops.field label="Note for the rider" class="sm:col-span-2" :error="$errors->first('book.note')"><input wire:model="book.note" class="input" placeholder="e.g. Call before delivery"></x-ops.field>
                                </div>
                            </div>
                            <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="sendToCourier" wire:loading.attr="disabled" class="btn-primary"><span wire:loading.remove wire:target="sendToCourier">Send</span><span wire:loading wire:target="sendToCourier">Sending…</span></button></x-slot:footer>
                        </x-ops.modal>
                    @endif
                @else <span class="text-ink-muted">Not booked yet — the parcel is created at packing.</span> @endif
            </x-ops.card>
            @if ($this->parcels->count() > 1 || $this->parcels->contains(fn ($p) => $p->isSuperseded()))
                {{-- one order, many parcels: first delivery, re-sends, exchanges --}}
                <x-ops.card title="Parcels" flush>
                    @foreach ($this->parcels as $p)
                        <a href="{{ route('ops.trace', ['q' => $p->code]) }}" wire:navigate class="flex flex-col gap-0.5 border-t border-soft px-3.5 py-2 text-sm text-ink first:border-0 hover:bg-zebra">
                            <span class="flex items-center gap-2"><span class="mono font-semibold">{{ $p->code }}</span>
                                <span class="text-xs text-ink-muted">{{ $p->order->exchange_of_id ? 'Exchange '.\Illuminate\Support\Str::afterLast($p->order->number, '-EX') : ($p->kind === 'resend' ? 'Re-send' : 'Delivery') }}</span>
                                <span class="ml-auto"><x-ops.badge map="courier_status" :status="$p->courier_status" /></span></span>
                            <span class="text-xs text-ink-muted">{{ $p->courier?->name }}@if ($p->tracking || $p->consignment_id) · <x-ops.parcel-ref :s="$p" inline />@endif · {{ $p->created_at->format('d M') }}@if ($p->isSuperseded()) · <span class="font-semibold text-warn">replaced {{ $p->superseded_at->format('d M') }}</span>@endif</span>
                        </a>
                    @endforeach
                </x-ops.card>
            @endif
            @if ($o->returns->isNotEmpty())
                <x-ops.card title="Returns" flush>@foreach ($o->returns as $r)<a href="{{ route('ops.trace', ['q' => $r->code]) }}" wire:navigate class="flex justify-between border-t border-soft px-3.5 py-2 text-ink first:border-0 hover:bg-zebra"><span class="mono font-semibold">{{ $r->code }}</span><span class="text-sm capitalize">{{ $r->type }}</span></a>@endforeach</x-ops.card>
            @endif
        </div>
    </div>

    <x-ops.modal name="order-pay" title="Record advance payment">
        <div class="grid gap-3 sm:grid-cols-2">
            <x-ops.field label="Amount (৳)" required :error="$errors->first('payAmount')" :hint="'COD left '.tk($o->cod)"><input type="number" min="1" wire:model="payAmount" class="input"></x-ops.field>
            <x-ops.field label="Received in" required><select wire:model="payAccount" class="input">@foreach (config('ops.accounts') as $a)<option>{{ $a }}</option>@endforeach</select></x-ops.field>
        </div>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="recordPayment" class="btn-primary">Save payment</button></x-slot:footer>
    </x-ops.modal>
    <livewire:ops.orders.book-courier />
    @if ($this->canResend())
        <x-ops.modal name="resend" title="Send again in a new parcel">
            <div x-data x-init="@if ($resend) $nextTick(() => $dispatch('open-modal', 'resend')) @endif" class="flex flex-col gap-3 text-sm">
                <p class="text-ink-muted">Parcel <b class="mono">{{ $o->shipment?->code ?? '—' }}</b> is marked <b>replaced</b> — it stays tracked, and when it comes back Return receive restocks it without touching this order.
                    The order goes back to <b>Confirmed</b> (stock reserved again) → Pack &amp; scan → courier, in a new parcel.</p>
                @if ($o->shipment && ! in_array($o->shipment->courier_status, ['returning', 'returned']))
                    <p class="rounded-md bg-warn-bg px-3 py-2 text-warn">The old parcel is still with {{ $o->shipment->courier?->name ?? 'the courier' }} ({{ config('ops.courier_status.'.$o->shipment->courier_status.'.0') }}). Ask them to <b>return it</b> in their merchant panel — if it gets delivered anyway you'll get an alert to stop the new one.</p>
                @endif
                <x-ops.field label="Why" required :error="$errors->first('resendReason')">
                    <select wire:model="resendReason" class="input">@foreach (['Customer found a fault and sent it back', 'Customer refused, then asked for it again', 'Wrong item sent', 'Parcel lost / damaged by the courier', 'Other'] as $r)<option>{{ $r }}</option>@endforeach</select>
                </x-ops.field>
                <x-ops.field label="COD for the new parcel (৳)" :error="$errors->first('resendCod')" :hint="'Empty = same as now ('.tk($o->cod).'). Put 0 when the customer already paid / you cover it.'"><input type="number" min="0" wire:model="resendCod" class="input" placeholder="{{ $o->cod }}"></x-ops.field>
            </div>
            <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="sendAgain" wire:loading.attr="disabled" class="btn-primary">Send again</button></x-slot:footer>
        </x-ops.modal>
    @endif
</div>
