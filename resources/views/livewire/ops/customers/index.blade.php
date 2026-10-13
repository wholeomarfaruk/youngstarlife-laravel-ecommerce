<div class="flex flex-col gap-3">
    @php($k = $this->kpis)
    <x-ops.kpis>
        <x-ops.kpi label="Customers" :value="ops_num($k['customers'])" /><x-ops.kpi label="New this month" :value="$k['new']" tone="info" />
        <x-ops.kpi label="Repeat rate" :value="$k['repeat'].'%'" tone="ok" /><x-ops.kpi label="Avg order value" :value="tk($k['aov'])" />
        <x-ops.kpi label="Lifetime revenue" :value="tk($k['lifetime'])" /><x-ops.kpi label="Customer due" :value="tk($k['due'])" tone="warn" />
        <x-ops.kpi label="Risky customers" :value="$k['risky']" tone="bad" hint="delivery < 75%" /><x-ops.kpi label="Avg delivery rate" :value="$k['rate'].'%'" tone="ok" />
    </x-ops.kpis>
    <section class="card">
        @php($c = $this->counts)
        <div class="border-b border-soft px-3 py-2"><x-ops.chips wire:model.live="status" :options="['all' => ['All', $c['all']], 'vip' => ['VIP', $c['vip']], 'repeat' => ['Repeat', $c['repeat']], 'new' => ['New', $c['new']], 'risky' => ['Risky', $c['risky']], 'due' => ['Has due', $c['due']]]" /></div>
        <x-ops.toolbar>
            <x-ops.search placeholder="Name, phone, area…" />
            <select class="input w-auto" wire:model.live="filters.zone"><option value="">All zones</option><option value="inside">Inside Dhaka</option><option value="outside">Outside Dhaka</option></select>
            <select class="input w-auto" wire:model.live="filters.source"><option value="">All sources</option>@foreach (config('ops.sources') as $key => $l)<option value="{{ $key }}">{{ $l }}</option>@endforeach</select>
            <select class="input w-auto" wire:model.live="sort"><option value="spent">Most spent</option><option value="orders">Most orders</option><option value="last">Last order</option><option value="rate">Lowest delivery rate</option><option value="due">Highest due</option></select>
            <span class="ml-auto text-sm text-ink-muted">{{ ops_num($this->rows->total()) }} customers</span>
            <a href="{{ route('ops.users.index', ['new' => 'customer']) }}" wire:navigate class="btn-primary">+ Add customer</a>
        </x-ops.toolbar>
        <x-ops.bulk-bar :count="count($selected)">
            <button wire:click="openSms" class="btn-sm rounded bg-white text-ink">Send SMS</button>
            <button x-data @click="$dispatch('open-modal','tag')" class="btn-sm rounded bg-side-hover">Add tag</button>
            <button wire:click="export" class="btn-sm rounded bg-side-hover">Export</button>
            <button wire:click="block" wire:confirm="Blacklist / unblock {{ count($selected) }} customers?" class="btn-sm rounded bg-side-hover">Block / unblock</button>
        </x-ops.bulk-bar>
        <x-ops.table min-width="1080px">
            <x-slot:head><th class="th w-7"></th><th class="th">Customer</th><th class="th">Segments</th><th class="th text-right">Orders</th><th class="th">Delivery rate</th><th class="th text-right">Total spent</th><th class="th text-right">Due</th><th class="th">Last order</th><th class="th">Source</th></x-slot:head>
            @forelse ($this->rows as $r)
                @php($rate = $r->delivery_rate) @php($closed = $r->delivered_count + $r->returned_count)
                <tr wire:key="r{{ $r->id }}" @class(['tr-hover cursor-pointer', 'bg-brand-tint' => $expanded === $r->id || in_array((string) $r->id, $selected)])>
                    <td class="td" @click.stop><input type="checkbox" value="{{ $r->id }}" wire:model.live="selected" class="h-4 w-4 accent-ink"></td>
                    <td class="td" wire:click="toggle({{ $r->id }})"><div class="flex items-center gap-2.5"><span class="grid h-8 w-8 flex-none place-items-center rounded-full bg-soft text-sm font-semibold text-ink-3">{{ mb_substr($r->name, 0, 1) }}</span><div><div class="font-semibold">{{ $r->name }} @if ($r->is_blocked)<x-ops.badge tone="bad">Blocked</x-ops.badge>@endif</div><div class="text-xs text-ink-muted">{{ $r->phone }} · {{ $r->area ?: '—' }}</div></div></div></td>
                    <td class="td" wire:click="toggle({{ $r->id }})"><div class="flex flex-wrap gap-1">@foreach ($r->segments as $s)<x-ops.badge :tone="config('ops.segments.'.$s.'.1')">{{ config('ops.segments.'.$s.'.0') }}</x-ops.badge>@endforeach @foreach ($r->tags ?? [] as $t)<span class="chip py-0 text-xs">{{ $t }}</span>@endforeach</div></td>
                    <td class="td text-right" wire:click="toggle({{ $r->id }})">{{ $r->orders_count }}</td>
                    <td class="td" wire:click="toggle({{ $r->id }})">@if ($rate !== null)<span @class(['font-semibold', 'text-bad' => $rate < .75, 'text-ok' => $rate >= .9])>{{ round($rate * 100) }}%</span> <span class="text-xs text-ink-muted">{{ $r->delivered_count }}/{{ $closed }}</span><div class="mt-1 h-1 w-24 rounded bg-bad-bg"><div class="h-1 rounded bg-brand" style="width: {{ $rate * 100 }}%"></div></div>@else<span class="text-ink-muted">—</span>@endif</td>
                    <td class="td text-right font-medium" wire:click="toggle({{ $r->id }})">{{ tk($r->total_spent) }}</td>
                    <td @class(['td text-right', 'font-semibold text-warn' => $r->due]) wire:click="toggle({{ $r->id }})">{{ $r->due ? tk($r->due) : '—' }}</td>
                    <td class="td text-sm text-ink-muted" wire:click="toggle({{ $r->id }})">{{ $r->last_order_at?->format('d M') ?? '—' }}</td>
                    <td class="td text-sm" wire:click="toggle({{ $r->id }})">{{ config('ops.sources.'.$r->source, $r->source ?: '—') }}</td>
                </tr>
                @if ($expanded === $r->id && ($d = $this->detail))
                    <tr wire:key="rx{{ $r->id }}"><td colspan="9" class="border-b border-soft bg-zebra p-3.5">
                        <div class="grid gap-3 lg:grid-cols-[240px_minmax(0,1fr)_minmax(0,1fr)]">
                            <x-ops.card title="Profile"><dl class="grid grid-cols-[80px_1fr] gap-y-1 text-sm"><dt class="text-ink-muted">Phone</dt><dd class="mono">{{ $d->phone }}</dd><dt class="text-ink-muted">Email</dt><dd>{{ $d->email ?: '—' }}</dd><dt class="text-ink-muted">Area</dt><dd>{{ $d->area ?: '—' }}</dd><dt class="text-ink-muted">Address</dt><dd>{{ $d->address ?: '—' }}</dd><dt class="text-ink-muted">User</dt><dd>@if ($d->user)<a href="{{ route('ops.users.show', [$d->user_id, 'tab' => 'customer']) }}" wire:navigate>{{ $d->user->name }}</a>@else — @endif</dd><dt class="text-ink-muted">Since</dt><dd>{{ $d->created_at->format('d M Y') }}</dd><dt class="text-ink-muted">Spent</dt><dd class="font-semibold">{{ tk($d->total_spent) }}</dd></dl>
                                <div class="mt-3 grid grid-cols-2 gap-1.5">
                                    <a href="{{ route('ops.orders.drafts', ['new' => 1, 'phone' => $d->phone, 'name' => $d->name]) }}" wire:navigate class="btn-primary btn-sm">New order</a>
                                    <a href="tel:{{ $d->phone }}" class="btn-secondary btn-sm">Call</a>
                                    <a href="https://wa.me/88{{ preg_replace('/\D/', '', $d->phone) }}" target="_blank" class="btn-secondary btn-sm">WhatsApp</a>
                                    <button wire:click="openSms({{ $d->id }})" class="btn-secondary btn-sm">SMS</button>
                                    <button wire:click="openLedger({{ $d->id }})" class="btn-secondary btn-sm">Ledger</button>
                                    <button wire:click="block({{ $d->id }})" wire:confirm="{{ $d->is_blocked ? 'Remove '.$d->name.' from the blacklist?' : 'Blacklist '.$d->name.' (phone + their website devices)?' }}" class="btn-danger btn-sm">{{ $d->is_blocked ? 'Unblock' : 'Block' }}</button>
                                    <a href="{{ route('ops.blacklist.index', ['search' => $d->phone, 'status' => 'any']) }}" wire:navigate class="btn-secondary btn-sm col-span-2">Blacklist history</a>
                                </div></x-ops.card>
                            <x-ops.card title="Courier fraud check">
                                @forelse ($this->fraud as $f)
                                    @php($fr = $f->total ? $f->delivered / $f->total : 0)
                                    <div class="mb-2.5"><div class="flex justify-between text-sm"><span class="font-medium">{{ $f->name }}</span><span class="text-ink-muted">{{ $f->delivered }} delivered · {{ $f->returned }} returned</span></div><div class="mt-1 flex h-2 overflow-hidden rounded bg-soft"><span class="bg-brand" style="width: {{ $fr * 100 }}%"></span><span class="bg-bad" style="width: {{ $f->total ? $f->returned / $f->total * 100 : 0 }}%"></span></div></div>
                                @empty<span class="text-sm text-ink-muted">No parcels yet for this customer.</span>@endforelse
                                <x-ops.field label="Note" class="mt-2"><textarea wire:model="note" rows="2" class="input"></textarea></x-ops.field>
                                <button wire:click="saveNote" class="btn-secondary btn-sm mt-1.5">Save note</button>
                            </x-ops.card>
                            <x-ops.card title="Recent orders" flush>
                                @forelse ($d->orders as $o)<a href="{{ route('ops.orders.show', $o) }}" wire:navigate class="flex items-center gap-2 border-t border-soft px-3 py-1.5 text-sm text-ink first:border-0 hover:bg-white"><span class="mono font-semibold">{{ $o->number }}</span><span class="text-ink-muted">{{ $o->placed_at->format('d M') }}</span><span class="ml-auto font-semibold">{{ tk($o->total) }}</span><x-ops.badge map="order_status" :status="$o->status" /></a>
                                @empty<div class="p-3 text-ink-muted">No orders.</div>@endforelse
                            </x-ops.card>
                        </div>
                    </td></tr>
                @endif
            @empty <x-ops.empty colspan="9"><button wire:click="clearFilters" class="btn-secondary btn-sm">Clear filters</button></x-ops.empty> @endforelse
        </x-ops.table>
        <x-ops.pagination :paginator="$this->rows" />
    </section>

    <x-ops.modal name="tag" title="Add tag">
        <x-ops.field label="Tag" required :error="$errors->first('tag')"><input wire:model="tag" class="input" placeholder="Wholesale, Influencer…"></x-ops.field>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="addTag" class="btn-primary">Add to {{ count($selected) }}</button></x-slot:footer>
    </x-ops.modal>
    <x-ops.modal name="sms" title="Send SMS">
        <p class="mb-2 text-sm text-ink-muted">{{ count($smsTo) }} recipient(s). Variables: {name}. SMS gateway not connected yet — the message is recorded (demo).</p>
        <x-ops.field label="Message" required :error="$errors->first('sms')" :hint="strlen($sms).' / 160 per SMS'"><textarea wire:model.live.debounce.300ms="sms" rows="4" class="input"></textarea></x-ops.field>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="sendSms" class="btn-primary">Send</button></x-slot:footer>
    </x-ops.modal>
    <x-ops.modal name="ledger" title="Customer ledger" width="max-w-2xl">
        <x-ops.table min-width="560px"><x-slot:head><th class="th">Date</th><th class="th">Order</th><th class="th">Status</th><th class="th text-right">Total</th><th class="th text-right">Paid</th><th class="th text-right">Refund</th></x-slot:head>
            @forelse ($this->ledger as $o)<tr><td class="td text-sm">{{ $o->placed_at->format('d M Y') }}</td><td class="td mono">{{ $o->number }}</td><td class="td"><x-ops.badge map="order_status" :status="$o->status" /></td><td class="td text-right">{{ tk($o->total) }}</td><td class="td text-right">{{ tk($o->advance + ($o->payment_status === 'paid' ? $o->cod : 0)) }}</td><td class="td text-right text-bad">{{ ($rf = $o->returns->whereNotNull('refunded_at')->sum('refund_amount')) ? tk($rf) : '—' }}</td></tr>
            @empty <x-ops.empty colspan="6" title="No orders" hint="" /> @endforelse
        </x-ops.table>
    </x-ops.modal>
</div>
