<div class="flex flex-col gap-3">
    @php($k = $this->kpis)
    <x-ops.kpis>
        <x-ops.kpi label="Active entries" :value="ops_num($k['active'])" tone="bad" /><x-ops.kpi label="Blocked phones" :value="ops_num($k['phones'])" />
        <x-ops.kpi label="Devices & IPs" :value="ops_num($k['devices'])" /><x-ops.kpi label="Flag for review" :value="$k['flag']" tone="warn" hint="order accepted, tagged" />
        <x-ops.kpi label="Attempts today" :value="$k['today']" :tone="$k['today'] ? 'bad' : 'ink'" /><x-ops.kpi label="Blocked · 30 days" :value="ops_num($k['blocked30'])" tone="bad" hint="orders refused" />
        <x-ops.kpi label="Flagged · 30 days" :value="ops_num($k['flagged30'])" tone="warn" /><x-ops.kpi label="Expiring in 7 days" :value="$k['expiring']" tone="info" />
    </x-ops.kpis>

    <div class="flex flex-wrap items-center gap-2">
        <div class="flex gap-1 rounded-md border border-line bg-white p-0.5">
            @foreach (['entries' => 'Blacklist', 'attempts' => 'Attempts'] as $v => $l)
                <button type="button" wire:click="$set('view', '{{ $v }}')" @class(['rounded px-3 py-1 text-sm', 'bg-ink text-white' => $view === $v, 'text-ink-3' => $view !== $v])>{{ $l }}</button>
            @endforeach
        </div>
        <span class="text-sm text-ink-muted">Blocked phones, devices, IPs and emails can’t order on the website, the bulk / AI sheet or from drafts. “Flag” entries still order but get tagged for review.</span>
        <div class="ml-auto flex flex-wrap gap-1.5">
            <button type="button" x-data @click="$dispatch('open-modal', 'check')" class="btn-secondary">Check</button>
            <button type="button" x-data @click="$wire.set('impResult', null); $dispatch('open-modal', 'import')" class="btn-secondary">Import list</button>
            <button type="button" x-data @click="$dispatch('open-modal', 'bl-settings')" class="btn-secondary">Settings</button>
            <button type="button" x-data @click="$wire.edit().then(() => $dispatch('open-modal', 'entry'))" class="btn-primary">+ Add to blacklist</button>
        </div>
    </div>

    @if ($view === 'entries')
        <section class="card">
            @php($c = $this->counts)
            <div class="border-b border-soft px-3 py-2"><x-ops.chips wire:model.live="status" :options="['all' => ['Active', $c['all']], 'block' => ['Block', $c['block']], 'flag' => ['Flag', $c['flag']], 'hit' => ['Had attempts', $c['hit']], 'expired' => ['Expired', $c['expired']], 'removed' => ['Removed', $c['removed']], 'any' => ['Everything', $c['any']]]" /></div>
            <x-ops.toolbar>
                <x-ops.search placeholder="Phone, IP, email, name, note…" />
                <select class="input w-auto" wire:model.live="filters.type"><option value="">All types</option>@foreach (config('ops.blacklist_types') as $key => $l)<option value="{{ $key }}">{{ $l }}</option>@endforeach</select>
                <select class="input w-auto" wire:model.live="filters.reason"><option value="">All reasons</option>@foreach (config('ops.blacklist_reasons') as $key => $l)<option value="{{ $key }}">{{ $l }}</option>@endforeach</select>
                <select class="input w-auto" wire:model.live="filters.source"><option value="">Added from</option>@foreach (['manual' => 'Manual', 'order' => 'Order', 'customer' => 'Customer page', 'import' => 'Import', 'auto' => 'Automation', 'legacy' => 'Old website'] as $key => $l)<option value="{{ $key }}">{{ $l }}</option>@endforeach</select>
                <select class="input w-auto" wire:model.live="sort"><option value="new">Newest</option><option value="hits">Most attempts</option><option value="last">Last attempt</option></select>
                <span class="ml-auto text-sm text-ink-muted">{{ ops_num($this->rows->total()) }} entries</span>
                <button wire:click="export" class="btn-secondary">Export</button>
            </x-ops.toolbar>
            <x-ops.bulk-bar :count="count($selected)">
                <button wire:click="bulk('block')" class="btn-sm rounded bg-side-hover">Set Block</button>
                <button wire:click="bulk('flag')" class="btn-sm rounded bg-side-hover">Set Flag</button>
                <button wire:click="bulk('remove')" wire:confirm="Remove {{ count($selected) }} entries from the blacklist?" class="btn-sm rounded bg-side-hover">Remove</button>
                <button wire:click="bulk('restore')" class="btn-sm rounded bg-side-hover">Restore</button>
                <button wire:click="export" class="btn-sm rounded bg-side-hover">Export</button>
                <button wire:click="bulk('delete')" wire:confirm="Delete {{ count($selected) }} entries and their attempt history for good?" class="btn-sm rounded bg-side-hover text-bad">Delete</button>
            </x-ops.bulk-bar>
            <x-ops.table min-width="1100px">
                <x-slot:head><th class="th w-7"></th><th class="th">Blacklisted</th><th class="th">Customer</th><th class="th">Action</th><th class="th">Reason</th><th class="th text-right">Attempts</th><th class="th">Expires</th><th class="th">Added</th><th class="th">Status</th><th class="th"></th></x-slot:head>
                @forelse ($this->rows as $r)
                    @php($cu = $r->customer)
                    <tr wire:key="b{{ $r->id }}" @class(['tr-hover', 'bg-brand-tint' => in_array((string) $r->id, $selected), 'opacity-60' => ! $r->is_live])>
                        <td class="td"><input type="checkbox" value="{{ $r->id }}" wire:model.live="selected" class="h-4 w-4 accent-ink"></td>
                        <td class="td"><div class="flex items-center gap-2"><span class="chip py-0 text-xs">{{ config('ops.blacklist_types.'.$r->type) }}</span><span class="mono font-semibold">{{ $r->type === 'device' ? '#'.$r->value : $r->value }}</span></div>
                            @if ($r->type === 'device')<div class="text-xs text-ink-muted">{{ $r->device?->name ?? 'Device deleted' }}@if ($r->device?->ip_address) · {{ $r->device->ip_address }}@endif @if ($r->device?->last_seen) · seen {{ $r->device->last_seen->diffForHumans() }}@endif</div>@endif
                            @if ($r->note)<div class="max-w-xs truncate text-xs text-ink-muted" title="{{ $r->note }}">{{ $r->note }}</div>@endif</td>
                        <td class="td text-sm">@if ($cu)<a href="{{ $cu->user_id ? route('ops.users.show', [$cu->user_id, 'tab' => 'customer']) : route('ops.customers.index', ['search' => $cu->phone]) }}" wire:navigate class="font-medium">{{ $cu->name }}</a><div class="text-xs text-ink-muted">{{ $cu->orders_count }} orders · {{ $cu->delivered_count }} delivered · {{ $cu->returned_count }} returned</div>@else<span class="text-ink-muted">—</span>@endif
                            @if ($r->order)<div class="text-xs">from <a href="{{ route('ops.orders.show', $r->order_id) }}" wire:navigate class="mono">{{ $r->order->number }}</a></div>@endif</td>
                        <td class="td"><x-ops.badge map="blacklist_actions" :status="$r->action" /></td>
                        <td class="td text-sm">{{ config('ops.blacklist_reasons.'.$r->reason, '—') }}</td>
                        <td class="td text-right">@if ($r->hits)<button wire:click="attemptsOf({{ $r->id }})" class="font-semibold text-bad">{{ $r->hits }}</button><div class="text-xs text-ink-muted">{{ $r->last_hit_at?->diffForHumans() }}</div>@else<span class="text-ink-muted">0</span>@endif</td>
                        <td class="td text-sm">{{ $r->expires_at ? $r->expires_at->format('d M Y') : 'Never' }}</td>
                        <td class="td text-sm">{{ $r->created_at->format('d M Y') }}<div class="text-xs text-ink-muted">{{ $r->creator?->name ?? ($r->source === 'auto' ? 'Automation' : ($r->source === 'legacy' ? 'Old website' : 'System')) }}</div></td>
                        <td class="td"><x-ops.badge map="blacklist_state" :status="$r->state" />@if ($r->removed_at)<div class="text-xs text-ink-muted">{{ $r->remover?->name }} · {{ $r->removed_at->format('d M') }}</div>@endif</td>
                        <td class="td"><x-ops.row-menu>
                            <x-ops.menu-item x-on:click="$wire.edit({{ $r->id }}).then(() => $dispatch('open-modal', 'entry'))">Edit</x-ops.menu-item>
                            <x-ops.menu-item wire:click="attemptsOf({{ $r->id }})">Attempts <span class="text-xs text-ink-muted">{{ $r->hits }}</span></x-ops.menu-item>
                            @if ($r->type === 'phone')
                                <a href="{{ route('ops.orders.index', ['search' => $r->value]) }}" wire:navigate class="rounded px-2 py-1.5 text-ink hover:bg-soft">Orders of this phone</a>
                                <a href="tel:{{ $r->value }}" class="rounded px-2 py-1.5 text-ink hover:bg-soft">Call</a>
                            @endif
                            <div class="my-1 border-t border-soft"></div>
                            @if ($r->is_live)<x-ops.menu-item wire:click="remove({{ $r->id }})" wire:confirm="Remove {{ $r->type === 'device' ? 'device #'.$r->value : $r->value }} from the blacklist?">Remove from blacklist</x-ops.menu-item>
                            @else<x-ops.menu-item wire:click="restore({{ $r->id }})">Restore</x-ops.menu-item>@endif
                            <x-ops.menu-item danger wire:click="destroy({{ $r->id }})" wire:confirm="Delete this entry and its attempt history for good?">Delete</x-ops.menu-item>
                        </x-ops.row-menu></td>
                    </tr>
                @empty <x-ops.empty colspan="10" title="Nobody on this list" hint="Add a phone, device, IP or email — or blacklist straight from an order’s ⋯ menu."><button wire:click="clearFilters" class="btn-secondary btn-sm">Clear filters</button></x-ops.empty> @endforelse
            </x-ops.table>
            <x-ops.pagination :paginator="$this->rows" />
        </section>
    @else
        <section class="card">
            @php($c = $this->hitCounts)
            @if ($en = $this->entryRow)
                <div class="flex items-center gap-2 border-b border-soft bg-bad-bg px-3 py-2 text-sm text-bad">Attempts of {{ config('ops.blacklist_types.'.$en->type) }} <b class="mono">{{ $en->label }}</b><button wire:click="$set('entry', 0)" class="ml-auto underline">Show all attempts</button></div>
            @endif
            <div class="border-b border-soft px-3 py-2"><x-ops.chips wire:model.live="status" :options="['all' => ['All', $c['all']], 'blocked' => ['Refused', $c['blocked']], 'flagged' => ['Flagged', $c['flagged']]]" /></div>
            <x-ops.toolbar>
                <x-ops.search placeholder="Phone, name, IP…" />
                <select class="input w-auto" wire:model.live="filters.channel"><option value="">All channels</option>@foreach (config('ops.blacklist_channels') as $key => $l)<option value="{{ $key }}">{{ $l }}</option>@endforeach</select>
                <x-ops.date-range />
                <span class="ml-auto text-sm text-ink-muted">{{ ops_num($this->hits->total()) }} attempts</span>
            </x-ops.toolbar>
            <x-ops.table min-width="960px">
                <x-slot:head><th class="th">When</th><th class="th">Result</th><th class="th">Channel</th><th class="th">Who tried</th><th class="th">Matched</th><th class="th">Device / IP</th><th class="th">Order</th></x-slot:head>
                @forelse ($this->hits as $h)
                    <tr wire:key="h{{ $h->id }}" class="tr-hover">
                        <td class="td text-sm">{{ $h->created_at->format('d M Y H:i') }}<div class="text-xs text-ink-muted">{{ $h->created_at->diffForHumans() }}</div></td>
                        <td class="td">@if ($h->blocked)<x-ops.badge tone="bad">Refused</x-ops.badge>@else<x-ops.badge tone="warn">Flagged</x-ops.badge>@endif</td>
                        <td class="td text-sm">{{ config('ops.blacklist_channels.'.$h->channel, $h->channel) }}@if ($h->user)<div class="text-xs text-ink-muted">by {{ $h->user->name }}</div>@endif</td>
                        <td class="td text-sm"><div class="font-medium">{{ $h->name ?: '—' }}</div><div class="mono text-xs text-ink-muted">{{ $h->phone ?: '—' }}</div></td>
                        <td class="td text-sm">@if ($h->entry)<span class="chip py-0 text-xs">{{ config('ops.blacklist_types.'.$h->entry->type) }}</span> <span class="mono">{{ $h->entry->type === 'device' ? '#'.$h->entry->value : $h->entry->value }}</span>@endif</td>
                        <td class="td text-xs text-ink-muted">{{ $h->device?->name ?? '—' }}@if ($h->device_id) #{{ $h->device_id }}@endif<div class="mono">{{ $h->ip_address }}</div></td>
                        <td class="td text-sm">@if ($h->order)<a href="{{ route('ops.orders.show', $h->order_id) }}" wire:navigate class="mono">{{ $h->order->number }}</a> <x-ops.badge map="order_status" :status="$h->order->status" />@else<span class="text-ink-muted">not placed</span>@endif</td>
                    </tr>
                @empty <x-ops.empty colspan="7" title="No attempts" hint="Every order attempt that matches the blacklist shows up here." /> @endforelse
            </x-ops.table>
            <x-ops.pagination :paginator="$this->hits" />
        </section>
    @endif

    {{-- add / edit --}}
    <x-ops.modal name="entry" :title="$orderId ? 'Blacklist customer of an order' : ($editingId ? 'Edit blacklist entry' : 'Add to blacklist')" width="max-w-lg">
        <div class="grid gap-3 sm:grid-cols-2">
            @if ($orderId && ($so = $this->sourceOrder))
                <div class="rounded-md bg-soft px-3 py-2 text-sm sm:col-span-2">Order <b class="mono">{{ $so->number }}</b> · {{ $so->ship_name }}@if ($so->customer) · {{ $so->customer->orders_count }} orders, {{ $so->customer->returned_count }} returned @endif</div>
                <div class="flex flex-col gap-1.5 sm:col-span-2">
                    <span class="text-sm font-medium">Blacklist</span>
                    <label class="flex items-center gap-2"><input type="checkbox" value="phone" wire:model="fromOrder" class="h-4 w-4 accent-brand"> Phone <span class="mono">{{ $so->ship_phone }}</span></label>
                    <label @class(['flex items-center gap-2', 'opacity-50' => ! $so->device_id])><input type="checkbox" value="device" wire:model="fromOrder" @disabled(! $so->device_id) class="h-4 w-4 accent-brand"> Device {{ $so->device_id ? '#'.$so->device_id.' · '.($so->device?->name ?? '') : '— not a website order' }}</label>
                    <label @class(['flex items-center gap-2', 'opacity-50' => ! $so->ip_address])><input type="checkbox" value="ip" wire:model="fromOrder" @disabled(! $so->ip_address) class="h-4 w-4 accent-brand"> IP address <span class="mono">{{ $so->ip_address ?: '—' }}</span> <span class="text-xs text-ink-muted">(shared networks — use with care)</span></label>
                    @error('fromOrder')<span class="text-xs text-bad">{{ $message }}</span>@enderror
                </div>
            @else
                <x-ops.field label="Type" required><select wire:model.live="f.type" class="input" @disabled($editingId)>@foreach (config('ops.blacklist_types') as $key => $l)<option value="{{ $key }}">{{ $l }}</option>@endforeach</select></x-ops.field>
                <x-ops.field :label="config('ops.blacklist_types.'.$f['type'])" required :error="$errors->first('f.value')" :hint="['phone' => '01XXXXXXXXX — any format is fine', 'device' => 'Device id (see Attempts or an order)', 'ip' => 'IPv4 or IPv6', 'email' => ''][$f['type']] ?? ''">
                    <input wire:model="f.value" class="input mono" @disabled($editingId) @if ($f['type'] === 'phone') inputmode="tel" @endif></x-ops.field>
            @endif
            <div class="flex flex-col gap-1.5 sm:col-span-2"><span class="text-sm font-medium">What happens</span>
                <div class="grid gap-2 sm:grid-cols-2">@foreach (['block' => 'Order is refused with your message (Settings)', 'flag' => 'Order goes through, tagged “Blacklist review” + notification'] as $a => $d)
                    <label @class(['cursor-pointer rounded-md border p-2.5', 'border-brand bg-brand-tint' => $f['action'] === $a, 'border-line-2' => $f['action'] !== $a])><input type="radio" wire:model.live="f.action" value="{{ $a }}" class="sr-only"><div class="font-semibold">{{ config("ops.blacklist_actions.$a.0") }}</div><div class="text-xs text-ink-muted">{{ $d }}</div></label>@endforeach</div></div>
            <x-ops.field label="Reason" required :error="$errors->first('f.reason')"><select wire:model="f.reason" class="input">@foreach (config('ops.blacklist_reasons') as $key => $l)<option value="{{ $key }}">{{ $l }}</option>@endforeach</select></x-ops.field>
            <x-ops.field label="Expires" :error="$errors->first('f.expires_at')"><select wire:model.live="f.expiry" class="input"><option value="never">Never</option><option value="7">In 7 days</option><option value="30">In 30 days</option><option value="90">In 90 days</option><option value="365">In 1 year</option><option value="date">On a date…</option></select>
                @if ($f['expiry'] === 'date')<x-ops.date wire:model="f.expires_at" class="mt-1" />@endif</x-ops.field>
            <x-ops.field label="Note" class="sm:col-span-2" :error="$errors->first('f.note')" hint="Only staff see this"><textarea wire:model="f.note" rows="2" class="input" placeholder="What happened? e.g. refused 3 parcels, abusive on call…"></textarea></x-ops.field>
            @unless ($editingId)
                @if ($orderId || $f['type'] === 'phone')
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        @unless ($orderId)<label class="flex items-center gap-2"><input type="checkbox" wire:model="f.devices" class="h-4 w-4 accent-brand"> Also blacklist every website device this customer ordered from</label>@endunless
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="f.cancel" class="h-4 w-4 accent-brand"> Cancel this phone’s open orders (pending, confirmed, on hold) — only with Block</label>
                    </div>
                @endif
            @endunless
        </div>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="save" wire:loading.attr="disabled" class="btn-primary">{{ $editingId ? 'Save changes' : 'Add to blacklist' }}</button></x-slot:footer>
    </x-ops.modal>

    {{-- import --}}
    <x-ops.modal name="import" title="Import a list" width="max-w-lg">
        <div class="grid gap-3 sm:grid-cols-2">
            <x-ops.field label="Type"><select wire:model="imp.type" class="input">@foreach (config('ops.blacklist_types') as $key => $l)<option value="{{ $key }}">{{ $l }}</option>@endforeach</select></x-ops.field>
            <x-ops.field label="Action"><select wire:model="imp.action" class="input">@foreach (config('ops.blacklist_actions') as $key => [$l])<option value="{{ $key }}">{{ $l }}</option>@endforeach</select></x-ops.field>
            <x-ops.field label="List" required class="sm:col-span-2" :error="$errors->first('imp.list')" hint="One per line or comma separated · up to 1,000 · already listed ones are updated"><textarea wire:model="imp.list" rows="7" class="input mono" placeholder="01712345678&#10;01812345678"></textarea></x-ops.field>
            <x-ops.field label="Reason"><select wire:model="imp.reason" class="input">@foreach (config('ops.blacklist_reasons') as $key => $l)<option value="{{ $key }}">{{ $l }}</option>@endforeach</select></x-ops.field>
            <x-ops.field label="Note"><input wire:model="imp.note" class="input" placeholder="e.g. Courier fraud list, Oct 2026"></x-ops.field>
            @if ($impResult)<div @class(['rounded-md px-3 py-2 text-sm sm:col-span-2', 'bg-ok-bg text-ok' => ! $impResult['bad'], 'bg-bad-bg text-bad' => $impResult['bad']])>{{ $impResult['added'] }} added.@if ($impResult['bad']) Not valid ({{ count($impResult['bad']) }}): <span class="mono">{{ implode(', ', array_slice($impResult['bad'], 0, 20)) }}{{ count($impResult['bad']) > 20 ? '…' : '' }}</span>@endif</div>@endif
        </div>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Close</button><button wire:click="import" wire:loading.attr="disabled" class="btn-primary">Import</button></x-slot:footer>
    </x-ops.modal>

    {{-- check --}}
    <x-ops.modal name="check" title="Check the blacklist" width="max-w-lg">
        <x-ops.field label="Phone, IP, email or device id"><input wire:model.live.debounce.400ms="check" class="input mono" placeholder="01712345678" autofocus></x-ops.field>
        @if ($r = $this->checkResult)
            <div class="mt-3 flex flex-col gap-2 text-sm">
                @if (! $r['valid'])<div class="text-ink-muted">Not a valid phone, IP, email or device id.</div>
                @elseif ($r['live']->isEmpty())<div class="rounded-md bg-ok-bg px-3 py-2 text-ok">Not blacklisted — orders go through.</div>
                @else @foreach ($r['live'] as $e)<div @class(['rounded-md px-3 py-2', 'bg-bad-bg text-bad' => $e->action === 'block', 'bg-warn-bg text-warn' => $e->action === 'flag'])><b>{{ config("ops.blacklist_actions.{$e->action}.0") }}</b> · {{ config('ops.blacklist_types.'.$e->type) }} {{ $e->label }} · {{ config('ops.blacklist_reasons.'.$e->reason, '') }} · {{ $e->hits }} attempts @if ($e->expires_at)· until {{ $e->expires_at->format('d M Y') }}@endif @if ($e->note)<div class="text-xs">{{ $e->note }}</div>@endif</div>@endforeach @endif
                @foreach ($r['other'] as $e)<div class="rounded-md bg-soft px-3 py-2 text-ink-muted">Was listed ({{ $e->state }}) · {{ config('ops.blacklist_reasons.'.$e->reason, '') }} · {{ $e->hits }} attempts <button wire:click="restore({{ $e->id }})" class="ml-1 underline">Restore</button></div>@endforeach
                @if ($cu = $r['customer'])<div class="rounded-md border border-line px-3 py-2"><b>{{ $cu->name }}</b> · {{ $cu->orders_count }} orders · {{ $cu->delivered_count }} delivered · {{ $cu->returned_count }} returned · {{ tk($cu->total_spent) }}@if ($cu->delivery_rate !== null) · <span @class(['font-semibold', 'text-bad' => $cu->delivery_rate < .75, 'text-ok' => $cu->delivery_rate >= .9])>{{ round($cu->delivery_rate * 100) }}% delivered</span>@endif</div>@endif
                @if ($d = $r['device'])<div class="rounded-md border border-line px-3 py-2">Device #{{ $d->id }} · {{ $d->name }} · {{ $d->ip_address }} · last seen {{ $d->last_seen?->diffForHumans() ?? '—' }}</div>@endif
            </div>
        @endif
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Close</button>@if ($this->checkResult && $this->checkResult['valid'] && $this->checkResult['live']->isEmpty())<button x-on:click="$wire.edit().then(() => { $wire.set('f.value', $wire.check); $dispatch('close-modal'); $dispatch('open-modal', 'entry') })" class="btn-primary">Add to blacklist</button>@endif</x-slot:footer>
    </x-ops.modal>

    {{-- settings --}}
    <x-ops.modal name="bl-settings" title="Blacklist settings" width="max-w-lg">
        <div class="flex flex-col gap-3">
            <x-ops.field label="Message shown to a blocked customer" required :error="$errors->first('cfg.message')" hint="Website checkout. Staff see “This customer is blacklisted” instead."><textarea wire:model="cfg.message" rows="3" class="input"></textarea></x-ops.field>
            <label class="flex items-start gap-2"><input type="checkbox" wire:model="cfg.match_fingerprint" class="mt-0.5 h-4 w-4 accent-brand"><span>Also block a new browser with the same fingerprint as a blocked device <span class="block text-xs text-ink-muted">Catches a blocked visitor who cleared cookies (same phone model, screen, graphics chip and browser). Only browsers first seen after the block are checked — customers who already shopped here are never affected.</span></span></label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="cfg.skip_autosave" class="h-4 w-4 accent-brand"> Don’t save abandoned checkouts (drafts) of blocked visitors</label>
            <div class="rounded-lg border border-line">
                <label class="flex items-center gap-2 border-b border-soft px-3 py-2 text-sm font-semibold"><input type="checkbox" wire:model.live="cfg.auto.on" class="h-4 w-4 accent-brand"> Auto-blacklist customers who keep refusing parcels</label>
                <div @class(['grid gap-3 p-3 sm:grid-cols-2', 'opacity-50' => ! $cfg['auto']['on']])>
                    <x-ops.field label="After at least … returned parcels" :error="$errors->first('cfg.auto.returns')"><input type="number" min="1" wire:model="cfg.auto.returns" class="input"></x-ops.field>
                    <x-ops.field label="… and delivery rate below (%)" :error="$errors->first('cfg.auto.rate')"><input type="number" min="1" max="100" wire:model="cfg.auto.rate" class="input"></x-ops.field>
                    <x-ops.field label="Then"><select wire:model="cfg.auto.action" class="input"><option value="flag">Flag for review</option><option value="block">Block</option></select></x-ops.field>
                    <x-ops.field label="For (days)" hint="0 = until removed" :error="$errors->first('cfg.auto.days')"><input type="number" min="0" wire:model="cfg.auto.days" class="input"></x-ops.field>
                    <p class="text-xs text-ink-muted sm:col-span-2">Checked when a returned parcel is received. A phone you removed by hand is never re-added automatically.</p>
                </div>
            </div>
        </div>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="saveSettings" class="btn-primary">Save settings</button></x-slot:footer>
    </x-ops.modal>

    @if ($autoOpen)<div x-data x-init="setTimeout(() => $dispatch('open-modal', 'entry'))"></div>@endif
</div>
