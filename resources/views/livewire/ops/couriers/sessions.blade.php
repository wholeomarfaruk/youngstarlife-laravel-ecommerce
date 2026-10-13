<div class="flex flex-col gap-3">
<section class="card">
    @php($c = $this->counts)
    <div class="border-b border-soft px-3 py-2"><x-ops.chips wire:model.live="status" :options="['all' => ['All', $c['all']], 'open' => ['Open', $c['open']], 'sent' => ['With parcels handed over', $c['sent']], 'returned' => ['With returns', $c['returned']], 'rejected' => ['With damaged / not received', $c['rejected']], 'closed' => ['Closed', $c['closed']]]" /></div>
    <x-ops.toolbar>
        <x-ops.search placeholder="Session, rider, phone, parcel or order…" />
        <select wire:model.live="courier" class="input w-auto"><option value="">All couriers</option>@foreach (\App\Models\Ops\Courier::orderBy('id')->pluck('name', 'id') as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
        <x-ops.date-range />
        @if ($search || $courier || $from || $to || $status !== 'all')<button type="button" wire:click="clearFilters" class="text-sm text-ink-muted hover:text-ink">Clear</button>@endif
        <span class="ml-auto text-sm text-ink-muted">{{ ops_num($this->sessions->total()) }} sessions</span>
        <button type="button" wire:click="startNew" class="btn-primary">+ New entry session</button>
    </x-ops.toolbar>
    <x-ops.table min-width="1000px">
        <x-slot:head><th class="th">Session</th><th class="th">Courier · pickup man</th><th class="th">Started</th><th class="th text-right">Handed over</th><th class="th text-right">COD handed over</th><th class="th text-right">Returns received</th><th class="th text-right">Damaged · not received</th><th class="th">By</th><th class="th">Status</th><th class="th"></th></x-slot:head>
        @forelse ($this->sessions as $r)
            <tr wire:key="s{{ $r->id }}" class="tr-hover cursor-pointer" onclick="Livewire.navigate('{{ route('ops.couriers.session', $r) }}')">
                <td class="td mono font-semibold text-brand">{{ $r->code }}</td>
                <td class="td">{{ $r->courier->name }}<div class="text-xs text-ink-muted">{{ $r->rider ?: 'Pickup man not noted' }}{{ $r->rider_phone ? ' · '.$r->rider_phone : '' }}</div></td>
                <td class="td text-sm">{{ $r->created_at->format('d M Y H:i') }}</td>
                <td @class(['td text-right', 'font-semibold text-info' => $r->sent])>{{ $r->sent ?: '—' }}</td>
                <td class="td text-right tabular-nums">{{ $r->cod_sent ? tk($r->cod_sent) : '—' }}</td>
                <td @class(['td text-right', 'font-semibold text-ok' => $r->returned])>{{ $r->returned ?: '—' }}</td>
                <td @class(['td text-right', 'font-semibold text-bad' => $r->rejected])>{{ $r->rejected ?: '—' }}</td>
                <td class="td text-sm text-ink-muted">{{ $r->opener?->name ?? '—' }}</td>
                <td class="td"><x-ops.badge :tone="$r->is_open ? 'warn' : 'mute'">{{ $r->is_open ? 'Open' : 'Closed' }}</x-ops.badge></td>
                <td class="td text-right"><a href="{{ route('ops.couriers.session.print', $r) }}" target="_blank" onclick="event.stopPropagation()" class="btn-secondary btn-sm">Receipt ↗</a></td>
            </tr>
        @empty <x-ops.empty colspan="10" title="No entry sessions" hint="Start a session when the courier's pickup man arrives." /> @endforelse
    </x-ops.table>
    <x-ops.pagination :paginator="$this->sessions" />
</section>

<x-ops.modal name="new-session" title="New entry session">
    <div class="flex flex-col gap-3">
        <x-ops.field label="Courier" required :error="$errors->first('new.courier_id')"><select wire:model="new.courier_id" class="input"><option value="">Choose…</option>@foreach ($this->couriers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-ops.field>
        <div class="grid gap-3 sm:grid-cols-2">
            <x-ops.field label="Pickup man"><input wire:model="new.rider" class="input" placeholder="Name"></x-ops.field>
            <x-ops.field label="Phone"><input wire:model="new.rider_phone" class="input" placeholder="01XXXXXXXXX"></x-ops.field>
        </div>
        <p class="text-xs text-ink-muted">In one session you can hand parcels over, receive returned parcels and mark damaged parcels you refuse to take back.</p>
    </div>
    <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="create" class="btn-primary">Start session →</button></x-slot:footer>
</x-ops.modal>
</div>
