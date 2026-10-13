<div class="flex flex-col gap-3">
@php($t = $this->totals) @php($c = $this->counts) @php($bal = $this->balances)

{{-- money held by each courier --}}
<section class="card">
    <header class="card-head">
        <div class="flex min-w-0 flex-col"><span class="card-title">Money with couriers</span><span class="text-sm text-ink-muted">COD collected − delivery charge − COD fee − payments received. A payment lowers the balance.</span></div>
        <div class="text-right"><div class="text-xs text-ink-muted">Total held</div><div class="text-xl font-semibold tabular-nums text-ok">{{ tk($bal->sum('balance')) }}</div></div>
    </header>
    <div class="grid gap-3 p-3.5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($bal as $id => $b)
            <div wire:key="cb{{ $id }}" class="flex flex-col gap-2 rounded-lg border border-line p-3">
                <div class="flex items-center justify-between gap-2"><span class="font-semibold">{{ $b['courier']->name }}</span>@unless ($b['courier']->is_active)<x-ops.badge tone="mute">Inactive</x-ops.badge>@endunless
                    <span @class(['ml-auto text-xl font-semibold tabular-nums', 'text-ok' => $b['balance'] > 0, 'text-bad' => $b['balance'] < 0])>{{ tk($b['balance']) }}</span></div>
                <dl class="grid grid-cols-[1fr_auto] gap-y-0.5 text-sm">
                    <dt class="text-ink-muted">COD collected · {{ $b['parcels'] }} parcels</dt><dd class="text-right tabular-nums">{{ tk($b['collected']) }}</dd>
                    <dt class="text-ink-muted">Delivery charge + COD fee</dt><dd class="text-right tabular-nums text-warn">− {{ tk($b['charges']) }}</dd>
                    <dt class="text-ink-muted">Payments received</dt><dd class="text-right tabular-nums text-ok">− {{ tk($b['received']) }}</dd>
                </dl>
                <div class="text-xs text-ink-muted">{{ $b['unsettled'] }} delivered parcel(s) not settled · {{ tk($b['unsettled_cod']) }} COD
                    @if ($b['last_payment']) · last payment {{ tk($b['last_payment']->received) }} on {{ $b['last_payment']->received_on->format('d M') }}@endif
                    @if ($b['balance'] < 0)<span class="text-bad"> · paid more than estimated (advance / charges lower)</span>@endif</div>
                <div class="flex gap-1.5"><button wire:click="receivePayment({{ $id }})" class="btn-primary btn-sm">Receive payment</button><button wire:click="openLedger({{ $id }})" class="btn-secondary btn-sm">Ledger</button>
                    <span class="ml-auto self-center text-xs text-ink-muted">{{ tk($b['courier']->charge) }} + {{ rtrim(rtrim(number_format($b['courier']->cod_fee_pct, 2), '0'), '.') }}% COD</span></div>
            </div>
        @empty <div class="text-sm text-ink-muted">No couriers yet — add them in Settings → Couriers.</div> @endforelse
    </div>
</section>

<x-ops.kpis>
    <x-ops.kpi label="Settlements" :value="ops_num($t['n'])" hint="matching filters" />
    <x-ops.kpi label="COD settled" :value="tk($t['cod'])" />
    <x-ops.kpi label="Courier charges" :value="tk($t['charges'])" tone="warn" />
    <x-ops.kpi label="Received" :value="tk($t['received'])" tone="ok" />
    <x-ops.kpi label="Short paid" :value="tk($t['short'])" tone="bad" />
    <x-ops.kpi label="Waiting to settle" :value="tk($t['pending'])" :hint="$t['pending_n'].' delivered parcels'" tone="info" />
</x-ops.kpis>
<section class="card">
    <div class="border-b border-soft px-3 py-2"><x-ops.chips wire:model.live="status" :options="['all' => ['All', $c['all']], 'full' => ['Fully paid', $c['full']], 'short' => ['Short paid', $c['short']]]" /></div>
    <x-ops.toolbar>
        <x-ops.search placeholder="Reference, parcel or order…" />
        <select wire:model.live="courier" class="input w-auto"><option value="">All couriers</option>@foreach ($this->couriers as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select>
        <x-ops.date-range />
        @if ($search || $courier || $from || $to || $status !== 'all')<button type="button" wire:click="clearFilters" class="text-sm text-ink-muted hover:text-ink">Clear</button>@endif
        <span class="ml-auto text-sm text-ink-muted">{{ ops_num($this->settlements->total()) }} settlements</span>
        <button type="button" wire:click="newSettlement" class="btn-primary">+ Record settlement</button>
    </x-ops.toolbar>
    <x-ops.table min-width="980px">
        <x-slot:head><th class="th">Date</th><th class="th">Courier</th><th class="th">Reference</th><th class="th text-right">Parcels</th><th class="th text-right">COD</th><th class="th text-right">Delivery charge</th><th class="th text-right">COD fee</th><th class="th text-right">Received</th><th class="th text-right">Short</th><th class="th">Status</th></x-slot:head>
        @forelse ($this->settlements as $s)
            <tr wire:key="s{{ $s->id }}" class="tr-hover">
                <td class="td">{{ $s->received_on->format('d M Y') }}</td><td class="td font-medium">{{ $s->courier->name }}</td>
                <td class="td text-sm"><span class="mono">{{ $s->reference ?? '—' }}</span>@if ($s->account)<div class="text-xs text-ink-muted">into {{ $s->account }}</div>@endif @if ($s->note)<div class="max-w-[200px] truncate text-xs text-ink-muted" title="{{ $s->note }}">{{ $s->note }}</div>@endif</td>
                <td class="td text-right">{{ $s->shipments_count ?: '—' }}</td><td class="td text-right tabular-nums">{{ $s->cod_total ? tk($s->cod_total) : '—' }}</td>
                <td class="td text-right tabular-nums text-ink-muted">{{ tk($s->delivery_charges ?: ($s->charges - $s->cod_fee)) }}</td><td class="td text-right tabular-nums text-ink-muted">{{ tk($s->cod_fee) }}</td>
                <td class="td text-right font-semibold tabular-nums text-ok">{{ tk($s->received) }}</td><td @class(['td text-right tabular-nums', 'font-semibold text-bad' => $s->short])>{{ $s->short ? tk($s->short) : '—' }}</td>
                <td class="td">@if (! $s->shipments_count)<x-ops.badge tone="info">On account</x-ops.badge>@else<x-ops.badge :tone="$s->short ? 'bad' : 'ok'">{{ $s->short ? 'Short paid' : 'Paid' }}</x-ops.badge>@endif</td>
            </tr>
        @empty <x-ops.empty colspan="10" title="No settlements found" hint="Record a payment when Steadfast / Pathao sends money." /> @endforelse
    </x-ops.table>
    <x-ops.pagination :paginator="$this->settlements" />
</section>

<x-ops.modal name="settle" title="Receive courier payment" width="max-w-2xl">
    @php($b = $settleId ? ($bal[$settleId] ?? null) : null)
    <div class="mb-3 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
        <x-ops.field label="Courier"><select wire:model.live="settleId" class="input">@foreach ($bal as $id => $x)<option value="{{ $id }}">{{ $x['courier']->name }} — holds {{ tk($x['balance']) }} · {{ $x['unsettled'] }} parcels waiting</option>@endforeach</select></x-ops.field>
        @if ($b)<div class="text-right text-sm"><div class="text-ink-muted">Balance now</div><div class="font-semibold tabular-nums">{{ tk($b['balance']) }}</div></div>@endif
    </div>
    <div class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
        <x-ops.field label="Amount received (৳)" required :error="$errors->first('received')"><input type="number" min="0" wire:model.live.debounce.400ms="received" class="input text-lg font-semibold" placeholder="What Steadfast / Pathao sent"></x-ops.field>
        <button type="button" wire:click="autoMatch" class="btn-secondary">Auto-match oldest parcels</button>
    </div>
    @php($sel = $this->settleParcels->whereIn('id', array_map('intval', $settlePick))) @php($cod = (int) $sel->sum('cod_amount')) @php($charges = (int) $deliveryCharges + (int) $codFee) @php($net = $cod - $charges) @php($diff = $settlePick ? $net - (int) $received : 0)
    <div class="mt-3 flex items-center justify-between text-sm"><span class="font-medium">Parcels this payment covers <span class="text-ink-muted">({{ count($settlePick) }} of {{ $this->settleParcels->count() }} ticked — none = payment on account)</span></span>
        <span class="flex gap-2"><button type="button" wire:click="$set('settlePick', {{ json_encode($this->settleParcels->pluck('id')->map(fn ($i) => (string) $i)->values()) }})" class="text-ink-muted underline">All</button><button type="button" wire:click="$set('settlePick', [])" class="text-ink-muted underline">None</button></span></div>
    <div class="mt-1 max-h-56 overflow-y-auto rounded-md border border-line">
        @forelse ($this->settleParcels as $p)@php([$dc, $cf] = \App\Services\Ops\CourierLedger::estParts($p, $b['courier'] ?? null))<label wire:key="sp{{ $p->id }}" class="flex items-center gap-3 border-b border-soft px-3 py-1.5 text-sm last:border-0"><input type="checkbox" value="{{ $p->id }}" wire:model.live="settlePick" class="accent-brand"><span class="mono font-semibold">{{ $p->code }}</span><span class="mono text-ink-muted">{{ $p->order->number }}</span><span class="text-xs text-ink-muted">delivered {{ ($p->delivered_at ?? $p->status_updated_at)?->format('d M') }}</span><span class="ml-auto text-xs text-ink-muted">− {{ tk($dc) }} − {{ tk($cf) }}</span><span class="w-20 text-right font-semibold">{{ tk($p->cod_amount) }}</span></label>
        @empty<div class="p-3 text-ink-muted">No delivered parcels waiting — the amount is saved as a payment on account.</div>@endforelse
    </div>
    <div class="mt-3 grid gap-3 sm:grid-cols-3">
        <x-ops.field label="COD of ticked parcels"><span class="input bg-zebra font-semibold">{{ tk($cod) }}</span></x-ops.field>
        <x-ops.field label="Delivery charges (৳)" :hint="$settlePick ? 'estimated — edit to match the invoice' : ''" :error="$errors->first('deliveryCharges')"><input type="number" min="0" wire:model.live.debounce.400ms="deliveryCharges" class="input" @disabled(! $settlePick)></x-ops.field>
        <x-ops.field label="COD fee (৳)" :hint="$b ? rtrim(rtrim(number_format($b['courier']->cod_fee_pct, 2), '0'), '.').'% of COD' : ''" :error="$errors->first('codFee')"><input type="number" min="0" wire:model.live.debounce.400ms="codFee" class="input" @disabled(! $settlePick)></x-ops.field>
        <x-ops.field label="Into account"><select wire:model="settleAccount" class="input">@foreach (config('ops.accounts') as $a)<option>{{ $a }}</option>@endforeach</select></x-ops.field>
        <x-ops.field label="Received on"><x-ops.date wire:model="receivedOn" :max="today()->toDateString()" /></x-ops.field>
        <x-ops.field label="Reference"><input wire:model="reference" class="input mono" placeholder="Invoice / TXN id"></x-ops.field>
        <x-ops.field label="Note" class="sm:col-span-3"><input wire:model="settleNote" class="input" placeholder="Optional"></x-ops.field>
    </div>
    <div class="mt-3 flex flex-col gap-1 rounded-md bg-zebra px-3 py-2 text-sm">
        @if ($settlePick)<div class="flex justify-between"><span class="text-ink-muted">Expected = COD {{ tk($cod) }} − charges {{ tk($charges) }}</span><b>{{ tk($net) }}</b></div>@endif
        @if ($b && is_numeric($received))<div class="flex justify-between"><span class="text-ink-muted">{{ $b['courier']->name }} balance after this payment</span><b class="tabular-nums">{{ tk($b['balance'] - (int) $received - ($settlePick ? $charges - $sel->sum(fn ($p) => \App\Services\Ops\CourierLedger::estCharge($p, $b['courier'])) : 0)) }}</b></div>@endif
    </div>
    @if ($settlePick && is_numeric($received) && $diff > 0)<div class="mt-2 rounded-md bg-bad-bg px-3 py-2 text-sm text-bad">Short paid by {{ tk($diff) }} — it stays on the courier balance and is flagged on the settlement.</div>
    @elseif ($settlePick && is_numeric($received) && $diff < 0)<div class="mt-2 rounded-md bg-info-bg px-3 py-2 text-sm text-info">{{ tk(-$diff) }} more than these parcels — it lowers the courier balance (covers later parcels).</div>@endif
    <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="settle" wire:loading.attr="disabled" class="btn-primary">Save payment</button></x-slot:footer>
</x-ops.modal>

<x-ops.modal name="courier-ledger" :title="($ledgerFor ? ($bal[$ledgerFor]['courier']->name ?? 'Courier') : 'Courier').' ledger'" width="max-w-3xl">
    <x-ops.table min-width="640px">
        <x-slot:head><th class="th">Date</th><th class="th">Entry</th><th class="th text-right">COD in</th><th class="th text-right">Charges</th><th class="th text-right">Paid to us</th><th class="th text-right">Balance</th></x-slot:head>
        @forelse ($this->statement as $l)
            <tr @class(['bg-ok-bg/40' => $l['kind'] === 'payment'])>
                <td class="td text-sm text-ink-muted">{{ $l['date']->format('d M Y') }}</td>
                <td class="td text-sm">{{ $l['text'] }}@if ($l['account'])<span class="text-xs text-ink-muted"> · {{ $l['account'] }}</span>@endif</td>
                <td class="td text-right tabular-nums">{{ $l['cod'] ? '+ '.tk($l['cod']) : '' }}</td>
                <td class="td text-right tabular-nums text-warn">{{ $l['charges'] ? '− '.tk($l['charges']) : '' }}</td>
                <td class="td text-right tabular-nums text-ok">{{ $l['paid'] ? '− '.tk($l['paid']) : '' }}</td>
                <td @class(['td text-right font-semibold tabular-nums', 'text-bad' => $l['balance'] < 0])>{{ tk($l['balance']) }}</td>
            </tr>
        @empty <x-ops.empty colspan="6" title="Nothing yet" hint="Delivered parcels and payments show up here." /> @endforelse
    </x-ops.table>
    <x-slot:footer>@if ($ledgerFor)<button x-on:click="$dispatch('close-modal'); $wire.receivePayment({{ $ledgerFor }})" class="btn-primary">Receive payment</button>@endif</x-slot:footer>
</x-ops.modal>
</div>
