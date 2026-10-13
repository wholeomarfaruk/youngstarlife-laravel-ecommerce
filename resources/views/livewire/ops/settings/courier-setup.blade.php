<div class="flex flex-col gap-3">
    <div class="flex items-center justify-between gap-2"><span class="text-sm text-ink-muted">Courier accounts, API connection, webhook and logs. Steadfast and Pathao connect through their API; others are booked in their own panel.</span><button type="button" wire:click="edit" class="btn-primary btn-sm">+ Add courier</button></div>

    @foreach ($this->rows as $r)
        @php($meta = \App\Services\Couriers\CourierGateway::meta($r->code)) @php($h = $this->health[$r->id] ?? []) @php($t = $tested[$r->id] ?? null)
        <div wire:key="cs{{ $r->id }}" class="rounded-lg border border-line">
            <div class="flex flex-wrap items-center gap-3 border-b border-soft px-3 py-2.5">
                <div class="min-w-0 flex-1"><div class="font-semibold">{{ $r->name }} <span class="mono text-xs font-normal text-ink-muted">{{ $r->code }} · {{ $r->total }} parcels</span></div>
                    <div class="text-xs text-ink-muted">{{ $r->zone?->name ? 'Default for '.$r->zone->name.' · ' : '' }}{{ $r->charge ? tk($r->charge).' / parcel' : 'no charge set' }}@if ((float) $r->cod_fee_pct) + {{ rtrim(rtrim(number_format($r->cod_fee_pct, 2), '0'), '.') }}% COD @endif</div></div>
                <button type="button" wire:click="toggle({{ $r->id }})" title="Click to turn {{ $r->is_active ? 'off' : 'on' }}"><x-ops.badge :tone="$r->is_active ? 'ok' : 'mute'">{{ $r->is_active ? 'Active' : 'Off' }}</x-ops.badge></button>
                @if ($meta)
                    <x-ops.badge :tone="$r->isConnected() ? 'info' : 'warn'">{{ $r->isConnected() ? 'API set up' : 'API not set up' }}</x-ops.badge>
                @else<x-ops.badge>No API — manual tracking</x-ops.badge>@endif
                <div class="flex gap-1">
                    @if ($meta)<button type="button" wire:click="test({{ $r->id }})" wire:loading.attr="disabled" wire:target="test({{ $r->id }})" class="btn-secondary btn-sm" @disabled(! $r->isConnected())><span wire:loading.remove wire:target="test({{ $r->id }})">Test connection</span><span wire:loading wire:target="test({{ $r->id }})">Testing…</span></button>@endif
                    <button type="button" wire:click="edit({{ $r->id }})" class="btn-secondary btn-sm">Edit</button>
                </div>
            </div>
            @if ($t)
                <div @class(['border-b border-soft px-3 py-2 text-sm', 'bg-ok-bg text-ok' => $t['ok'], 'bg-bad-bg text-bad' => ! $t['ok']])>{{ $t['ok'] ? '✓' : '✕' }} {{ $t['message'] }}</div>
                @if ($t['stores'])
                    <div class="flex flex-wrap gap-1.5 border-b border-soft px-3 py-2 text-sm"><span class="text-xs text-ink-muted">Pickup stores:</span>
                        @foreach ($t['stores'] as $st)<button type="button" wire:click="useStore({{ $r->id }}, {{ $st['id'] }})" @class(['chip py-0.5 text-xs', 'chip-on' => (string) ($r->credentials['store_id'] ?? '') === (string) $st['id']]) title="{{ $st['address'] }}">#{{ $st['id'] }} {{ $st['name'] }}{{ $st['active'] ? '' : ' (inactive)' }}</button>@endforeach</div>
                @endif
            @endif
            @if ($meta)
                <div class="grid gap-3 px-3 py-3 lg:grid-cols-[minmax(0,1fr)_260px]">
                    <div class="flex flex-col gap-2">
                        <div class="text-sm font-semibold">Webhook — status updates pushed by {{ $r->name }}</div>
                        <p class="text-xs text-ink-muted">{{ $meta['webhook_help'] }}</p>
                        <x-ops.field label="Callback URL"><div class="flex gap-1.5"><input readonly value="{{ $r->webhookUrl() }}" class="input mono bg-zebra text-xs"><button type="button" onclick="opsCopy(@js($r->webhookUrl()))" class="btn-secondary btn-sm">Copy</button></div></x-ops.field>
                        <x-ops.field :label="$meta['webhook_secret_label'] ?? 'Secret'"><div class="flex gap-1.5" x-data="{ show: false }"><input readonly :type="show ? 'text' : 'password'" value="{{ $r->webhook_secret }}" class="input mono bg-zebra text-xs"><button type="button" @click="show = !show" class="btn-secondary btn-sm" x-text="show ? 'Hide' : 'Show'"></button><button type="button" onclick="opsCopy(@js((string) $r->webhook_secret))" class="btn-secondary btn-sm">Copy</button><button type="button" wire:click="rotateSecret({{ $r->id }})" wire:confirm="Make a new secret? Paste it in the {{ $r->name }} panel too, or its updates will be refused." class="btn-secondary btn-sm">Rotate</button></div></x-ops.field>
                        @if ($r->code === 'steadfast' && config('couriers.steadfast.legacy_webhook_token'))<p class="text-xs text-ink-muted">The old URL <span class="mono">{{ url('/api/steadfast/webhook') }}</span> with its old token also still updates parcels here.</p>@endif
                    </div>
                    <dl class="grid grid-cols-[1fr_auto] content-start gap-y-1 rounded-md bg-zebra p-2.5 text-sm">
                        <dt class="text-ink-muted">API calls (24 h)</dt><dd>{{ $h['calls'] ?? 0 }}@if (($h['failed'] ?? 0) > 0) <span class="text-bad">· {{ $h['failed'] }} failed</span>@endif</dd>
                        <dt class="text-ink-muted">Webhooks (24 h)</dt><dd>{{ $h['hooks'] ?? 0 }}</dd>
                        <dt class="text-ink-muted">Last webhook</dt><dd>{{ ($h['last_hook'] ?? null) ? \Carbon\Carbon::parse($h['last_hook'])->diffForHumans() : 'never' }}</dd>
                        <dt class="text-ink-muted">Last connected</dt><dd>{{ $r->last_synced_at?->diffForHumans() ?? 'never' }}</dd>
                        <dt class="text-ink-muted">Packed, not sent</dt><dd>{{ $h['pending'] ?? 0 }}</dd>
                    </dl>
                </div>
            @endif
        </div>
    @endforeach
    @if ($this->rows->isEmpty())<div class="rounded-lg border border-line px-3 py-6 text-center text-ink-muted">No couriers yet — add Steadfast (code steadfast) or Pathao (code pathao).</div>@endif

    {{-- logs --}}
    <div class="rounded-lg border border-line">
        <div class="flex flex-wrap items-center gap-2 border-b border-soft px-3 py-2">
            @foreach (['api' => 'API calls', 'webhooks' => 'Incoming webhooks'] as $k => $l)<button type="button" wire:click="$set('logTab','{{ $k }}')" @class(['mtab py-1', 'mtab-on' => $logTab === $k])>{{ $l }}</button>@endforeach
            <select wire:model.live="logCourier" class="input ml-auto w-auto py-1 text-sm"><option value="">All couriers</option>@foreach ($this->rows as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>
            <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" wire:model.live="logFailedOnly" class="accent-brand"> Problems only</label>
        </div>
        @if ($logTab === 'api')
            @php($logs = $this->apiLogs)
            <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-sm">
                <tr><th class="th">When</th><th class="th">Courier</th><th class="th">Action</th><th class="th">Parcel</th><th class="th">Result</th><th class="th text-right">Time</th><th class="th"></th></tr>
                @forelse ($logs as $l)
                    <tr class="border-b border-soft last:border-0">
                        <td class="whitespace-nowrap px-3 py-1.5 text-ink-muted">{{ $l->created_at->format('d M H:i:s') }}</td><td class="px-3 py-1.5">{{ $l->courier?->name ?? '—' }}</td>
                        <td class="mono px-3 py-1.5">{{ $l->action }}</td>
                        <td class="px-3 py-1.5">@if ($l->shipment)<a href="{{ route('ops.orders.show', $l->shipment->order_id) }}" wire:navigate class="mono">{{ $l->shipment->code }}</a>@else — @endif</td>
                        <td class="px-3 py-1.5">@if ($l->success)<x-ops.badge tone="ok">OK{{ $l->http_status ? ' '.$l->http_status : '' }}</x-ops.badge>@else<x-ops.badge tone="bad">{{ $l->http_status ?: 'Failed' }}</x-ops.badge> <span class="text-xs text-bad">{{ \Illuminate\Support\Str::limit($l->error, 70) }}</span>@endif</td>
                        <td class="px-3 py-1.5 text-right text-ink-muted">{{ $l->ms }} ms</td><td class="px-3 py-1.5 text-right"><button type="button" wire:click="showApi({{ $l->id }})" class="btn-ghost btn-sm">View</button></td>
                    </tr>
                @empty<tr><td colspan="7" class="p-3 text-ink-muted">No API calls yet.</td></tr>@endforelse
            </table></div>
            @if ($logs->hasPages())<x-ops.pagination :paginator="$logs" />@endif
        @else
            @php($logs = $this->hookLogs)
            <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-sm">
                <tr><th class="th">When</th><th class="th">Courier</th><th class="th">Event</th><th class="th">Parcel</th><th class="th">Result</th><th class="th text-right">Time</th><th class="th"></th></tr>
                @forelse ($logs as $w)
                    <tr class="border-b border-soft last:border-0">
                        <td class="whitespace-nowrap px-3 py-1.5 text-ink-muted">{{ $w->created_at->format('d M H:i:s') }}</td><td class="px-3 py-1.5">{{ $w->courier?->name ?? '—' }}</td>
                        <td class="mono px-3 py-1.5">{{ $w->event }}</td>
                        <td class="px-3 py-1.5">@if ($w->shipment)<a href="{{ route('ops.orders.show', $w->shipment->order_id) }}" wire:navigate class="mono">{{ $w->shipment->code }}</a>@else — @endif</td>
                        <td class="px-3 py-1.5"><x-ops.badge :tone="match ($w->result) { 'processed' => 'ok', 'no_change', 'handshake', 'ignored' => 'mute', 'unmatched' => 'warn', default => $w->status_code >= 400 ? 'bad' : 'mute' }">{{ $w->result ?? $w->status_code }}</x-ops.badge> @if ($w->error)<span class="text-xs text-ink-muted">{{ \Illuminate\Support\Str::limit($w->error, 60) }}</span>@endif</td>
                        <td class="px-3 py-1.5 text-right text-ink-muted">{{ $w->ms }} ms</td><td class="px-3 py-1.5 text-right"><button type="button" wire:click="showHook({{ $w->id }})" class="btn-ghost btn-sm">View</button></td>
                    </tr>
                @empty<tr><td colspan="7" class="p-3 text-ink-muted">No webhook calls received yet.</td></tr>@endforelse
            </table></div>
            @if ($logs->hasPages())<x-ops.pagination :paginator="$logs" />@endif
        @endif
    </div>

    <x-ops.modal name="log-detail" title="Log details" width="max-w-2xl">
        @if ($detail)
            <div class="flex flex-col gap-2 text-sm">
                <div class="text-ink-muted">{{ $detail->courier?->name }} · {{ $detail->created_at->format('d M Y H:i:s') }}@if ($detail->error) · <span class="text-bad">{{ $detail->error }}</span>@endif</div>
                @if ($detail instanceof \App\Models\Ops\CourierApiLog)
                    <div class="text-xs font-semibold">Request</div><pre class="mono max-h-56 overflow-auto rounded bg-zebra p-2 text-xs">{{ json_encode($detail->request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    <div class="text-xs font-semibold">Response</div><pre class="mono max-h-72 overflow-auto rounded bg-zebra p-2 text-xs">{{ json_encode($detail->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                @else
                    <div class="text-xs">From {{ $detail->ip ?? '—' }} · HTTP {{ $detail->status_code }} · {{ $detail->result }}</div>
                    <div class="text-xs font-semibold">Payload</div><pre class="mono max-h-80 overflow-auto rounded bg-zebra p-2 text-xs">{{ json_encode($detail->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif
            </div>
        @endif
    </x-ops.modal>

    <x-ops.modal name="courier" :title="$editId ? 'Edit courier' : 'Add courier'" width="max-w-lg">
        <div class="grid gap-3 sm:grid-cols-2">
            <x-ops.field label="Name" required :error="$errors->first('c.name')"><input wire:model="c.name" class="input"></x-ops.field>
            <x-ops.field label="Code" required :error="$errors->first('c.code')" hint="steadfast · pathao connect by API"><input wire:model.live.debounce.400ms="c.code" class="input mono" @readonly($editId)></x-ops.field>
            @if ($this->fields)
                <div class="flex flex-col gap-3 rounded-md border border-line p-3 sm:col-span-2">
                    <span class="text-sm font-semibold">API credentials <span class="font-normal text-ink-muted">· saved encrypted@if ($editId) · leave a password blank to keep it@endif</span></span>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($this->fields as $f)
                            @if ($f['type'] === 'checkbox')
                                <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" wire:model="cred.{{ $f['key'] }}" class="accent-brand"> {{ $f['label'] }}</label>
                            @else
                                <x-ops.field :label="$f['label']" :required="$f['required'] && ! $editId" :hint="$f['hint'] ?? null"><input type="{{ $f['type'] === 'password' ? 'password' : 'text' }}" wire:model="cred.{{ $f['key'] }}" class="input mono" autocomplete="off" @if ($f['type'] === 'password' && $editId) placeholder="•••••• saved" @endif></x-ops.field>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
            <x-ops.field label="Default for zone"><select wire:model="c.default_zone_id" class="input"><option value="">—</option>@foreach ($zones as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-ops.field>
            <x-ops.field label="Delivery charge per parcel (৳)" hint="Used until the courier reports the real charge" :error="$errors->first('c.charge')"><input type="number" min="0" wire:model="c.charge" class="input"></x-ops.field>
            <x-ops.field label="COD fee (%)" hint="Share of the collected cash the courier keeps — usually 1%" :error="$errors->first('c.cod_fee_pct')"><input type="number" min="0" max="10" step="0.01" wire:model="c.cod_fee_pct" class="input"></x-ops.field>
            <x-ops.field label="Pickup note"><input wire:model="c.pickup_store" class="input" placeholder="Mirpur WH"></x-ops.field>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="c.is_active" class="accent-brand"> Active</label>
        </div>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="save" class="btn-primary">Save courier</button></x-slot:footer>
    </x-ops.modal>
</div>
