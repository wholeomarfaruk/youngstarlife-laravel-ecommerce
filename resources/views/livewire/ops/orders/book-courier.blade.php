<div>
    @php($o = $this->order) @php($s = $this->parcel()) @php($c = $this->courier)
    <x-ops.modal name="book-courier-one" :title="$o ? 'Book courier · '.$o->number : 'Book courier'" width="max-w-2xl">
        {{-- opens at once from the ⋯ menu; the order loads in the background --}}
        <div wire:loading.flex wire:target="open" class="items-center justify-center gap-2 py-16 text-sm text-ink-muted"><span class="h-4 w-4 animate-spin rounded-full border-2 border-line border-t-brand"></span> Loading order…</div>
        @if ($o)
            <div class="flex flex-col gap-3" wire:key="bc{{ $o->id }}" wire:loading.remove wire:target="open">
                @error('book')<div class="rounded bg-bad-bg px-3 py-2 text-sm text-bad">{{ $message }}</div>@enderror

                {{-- order summary --}}
                <div class="rounded-md border border-line text-sm">
                    <div class="flex flex-wrap items-center gap-2 border-b border-soft px-3 py-2">
                        <x-ops.badge map="order_status" :status="$o->status" /><x-ops.badge map="payment_status" :status="$o->payment_status" />
                        <span class="text-ink-muted">{{ $o->placed_at->format('d M · H:i') }} · {{ config('ops.sources.'.$o->source, ucfirst($o->source)) }}@if ($o->zone) · {{ $o->zone->name }}@endif</span>
                        @if (($rate = $o->customer?->delivery_rate) !== null)<span @class(['ml-auto font-semibold', 'text-bad' => $rate < .75, 'text-ok' => $rate >= .9])>{{ round($rate * 100) }}% delivered</span>@endif
                    </div>
                    <div class="divide-y divide-soft">
                        @foreach ($o->items as $i)
                            <div class="flex justify-between gap-3 px-3 py-1.5"><span class="min-w-0 truncate">{{ $i->variant?->label ?? 'Item' }} <span class="text-ink-muted">×{{ $i->qty }}</span></span><span class="tabular-nums">{{ tk($i->qty * $i->unit_price) }}</span></div>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap justify-end gap-x-4 gap-y-1 border-t border-soft bg-zebra px-3 py-2 tabular-nums">
                        @if ($o->discount)<span>Discount −{{ tk($o->discount) }}</span>@endif
                        <span>Delivery {{ tk($o->delivery_charge) }}</span>
                        @if ($o->advance)<span>Advance −{{ tk($o->advance) }}</span>@endif
                        <span class="font-semibold">Total {{ tk($o->total) }}</span>
                    </div>
                </div>

                {{-- delivery details (editable) --}}
                <div class="grid gap-2 sm:grid-cols-2">
                    <x-ops.field label="Customer name" required :error="$errors->first('f.name')"><input wire:model="f.name" class="input"></x-ops.field>
                    <x-ops.field label="Phone" required :error="$errors->first('f.phone')"><input wire:model="f.phone" inputmode="tel" class="input mono"></x-ops.field>
                    <x-ops.field label="Address" required class="sm:col-span-2" :error="$errors->first('f.address')"><textarea wire:model="f.address" rows="2" class="input"></textarea></x-ops.field>
                    <x-ops.field label="Courier" required :error="$errors->first('f.courier_id')">
                        <select wire:model.live="f.courier_id" class="input"><option value="">Choose…</option>@foreach ($this->couriers as $co)<option value="{{ $co->id }}">{{ $co->name }}{{ $co->isConnected() ? '' : ' (no API)' }}</option>@endforeach</select>
                    </x-ops.field>
                    <x-ops.field label="COD to collect (৳)" required :error="$errors->first('f.cod')" :hint="'Order total '.tk($o->total).($o->advance ? ' · advance '.tk($o->advance) : '')"><input type="number" min="0" wire:model="f.cod" class="input tabular-nums"></x-ops.field>
                    <x-ops.field label="Item description" required class="sm:col-span-2" :error="$errors->first('f.description')" hint="What the courier sees as the parcel contents — prefilled from the items, edit if needed."><textarea wire:model="f.description" rows="2" maxlength="250" class="input"></textarea></x-ops.field>
                    <x-ops.field label="Note for the rider" class="sm:col-span-2" :error="$errors->first('f.note')"><input wire:model="f.note" class="input" placeholder="e.g. Call before delivery"></x-ops.field>
                </div>

                {{-- parcel: the order's unsent one, or a new one made when it is sent --}}
                @if ($s)
                    <div class="flex flex-col gap-2 rounded-md border border-line p-3">
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="font-semibold">Parcel @if ($s->exists)<span class="mono">{{ $s->code }}</span>@endif</span>
                            <span class="text-ink-muted">{{ $s->packed_at ? 'packed '.$s->packed_at->format('d M · H:i') : ($s->exists ? 'not packed yet' : 'created when sent · pack it later with Pack & scan') }}</span>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <x-ops.field label="Weight (kg)" :error="$errors->first('f.weight')"><input type="number" step="0.1" min="0.1" wire:model.live.debounce.500ms="f.weight" class="input"></x-ops.field>
                            <x-ops.field label="Second phone"><input wire:model="f.alt_phone" inputmode="tel" class="input" placeholder="Optional"></x-ops.field>
                            @if ($c?->code !== 'pathao')<x-ops.field label="Delivery"><select wire:model="f.delivery_type" class="input"><option value="0">Home delivery</option><option value="1">Point delivery</option></select></x-ops.field>@endif
                        </div>
                        @if ($c?->code === 'pathao' && $c->isConnected())
                            @if (! $pathaoReady)
                                <div x-init="$wire.loadPathao()" class="flex items-center gap-2 text-sm text-ink-muted"><span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-line border-t-brand"></span> Loading Pathao cities &amp; zones…</div>
                            @endif
                            @php($pl = $this->pathaoLists)
                            @if (! empty($pl['error']))<div class="rounded bg-bad-bg px-3 py-2 text-sm text-bad">{{ $pl['error'] }}</div>@endif
                            @if ($locationHint)<div class="text-xs text-ink-muted">{{ $locationHint }}</div>@endif
                            <div class="grid gap-2 sm:grid-cols-3">
                                <x-ops.field label="City" required :error="$errors->first('f.city_id')"><select wire:model.live="f.city_id" class="input"><option value="">Choose…</option>@foreach ($pl['cities'] as $ci)<option value="{{ $ci['id'] }}">{{ $ci['name'] }}</option>@endforeach</select></x-ops.field>
                                <x-ops.field label="Zone" required :error="$errors->first('f.zone_id')"><select wire:model.live="f.zone_id" class="input" @disabled(! $f['city_id'])><option value="">Choose…</option>@foreach ($pl['zones'] as $z)<option value="{{ $z['id'] }}">{{ $z['name'] }}</option>@endforeach</select></x-ops.field>
                                <x-ops.field label="Area"><select wire:model.live="f.area_id" class="input" @disabled(! $f['zone_id'])><option value="">Optional</option>@foreach ($pl['areas'] as $a)<option value="{{ $a['id'] }}">{{ $a['name'] }}</option>@endforeach</select></x-ops.field>
                            </div>
                            @if ($pl['quote'])<div class="text-sm">Pathao charge for this zone: <b>{{ tk((int) round($pl['quote']['price'])) }}</b></div>@endif
                        @endif
                        @if ($c?->can('book'))
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="f.send" class="accent-brand"> Send the parcel to the {{ $c->name }} API now @if ($o->status === 'pending')<span class="text-ink-muted">(also confirms the order)</span>@endif</label>
                        @elseif ($c)
                            <p class="text-xs text-ink-muted">{{ $c->name }} has no API connection (Settings → Couriers) — book it in their panel and type the tracking on the order page.</p>
                        @endif
                    </div>
                @endif
            </div>
        @endif
        <x-slot:footer>
            <button x-on:click="$dispatch('close-modal', 'book-courier-one')" class="btn-secondary">Cancel</button>
            <button wire:click="save" wire:loading.attr="disabled" wire:target="open,save,loadPathao" class="btn-primary" @disabled(! $o)>
                <span wire:loading.remove wire:target="save">{{ $s && ($f['send'] ?? false) && $c?->can('book') ? 'Send to '.$c->name : 'Save courier' }}</span><span wire:loading wire:target="save">Saving…</span>
            </button>
        </x-slot:footer>
    </x-ops.modal>
</div>
