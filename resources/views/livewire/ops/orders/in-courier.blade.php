<div class="flex flex-col gap-3">
    @php($c = $this->counts)
    <x-ops.kpis>@foreach (config('ops.courier_status') as $k => [$l, $t])<button wire:click="$set('status','{{ $status === $k ? 'all' : $k }}')" @class(['kpi text-left', 'ring-2 ring-ink' => $status === $k])><span class="text-xs text-ink-muted">{{ $l }}</span><span class="text-xl font-semibold text-{{ $t }}">{{ $c[$k] ?? 0 }}</span><span class="mono text-2xs text-ink-muted">{{ $k }}</span></button>@endforeach</x-ops.kpis>
    <section class="card">
        <x-ops.toolbar><x-ops.search placeholder="Parcel, tracking, order…" /><select wire:model.live="courier" class="input w-auto"><option value="">All couriers</option>@foreach ($couriers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select><select wire:model.live="status" class="input w-auto"><option value="all">All statuses</option>@foreach (config('ops.courier_status') as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach</select><x-ops.date-range /></x-ops.toolbar>
        <x-ops.table min-width="980px">
            <x-slot:head><th class="th">Parcel</th><th class="th">Order · customer</th><th class="th">Courier · tracking</th><th class="th text-right">COD</th><th class="th">Status</th><th class="th">Last update</th><th class="th">Actions</th></x-slot:head>
            @forelse ($this->parcels as $p)<tr class="tr-hover" wire:key="p{{ $p->id }}"><td class="td mono font-semibold">{{ $p->code }}@if ($p->complaint)<div class="text-xs font-normal text-bad">complaint</div>@endif</td><td class="td"><a href="{{ route('ops.orders.show', $p->order_id) }}" wire:navigate class="mono">{{ $p->order->number }}</a> · {{ $p->order->customer->name }}<div class="text-xs text-ink-muted">{{ $p->order->customer->area }}</div></td><td class="td">{{ $p->courier->name }}<div class="mono text-xs text-ink-muted">{{ $p->tracking ?: '—' }}</div></td><td class="td text-right font-semibold">{{ tk($p->cod_amount) }}</td><td class="td"><x-ops.badge map="courier_status" :status="$p->courier_status" /></td><td @class(['td text-sm', 'text-bad' => in_array($p->courier_status, ['pending', 'picked_up', 'in_transit']) && $p->status_updated_at?->lt(now()->subDays(3)), 'text-ink-muted' => ! (in_array($p->courier_status, ['pending', 'picked_up', 'in_transit']) && $p->status_updated_at?->lt(now()->subDays(3)))])>{{ $p->status_updated_at?->diffForHumans() }}</td>
                <td class="td"><div class="flex gap-1"><a href="{{ route('ops.orders.show', $p->order_id) }}" wire:navigate class="btn-secondary btn-sm">Open order</a>@if ($p->tracking)<button type="button" onclick="opsCopy('{{ $p->tracking }}')" class="btn-secondary btn-sm">Copy tracking</button>@endif<button wire:click="openComplaint({{ $p->id }})" class="btn-secondary btn-sm">Complaint</button></div></td></tr>
            @empty <x-ops.empty colspan="7" /> @endforelse
        </x-ops.table>
        <x-ops.pagination :paginator="$this->parcels" />
    </section>
    <x-ops.modal name="complaint" title="Raise complaint with courier">
        <x-ops.field label="What happened?" required :error="$errors->first('complaint')"><textarea wire:model="complaint" rows="4" class="input" placeholder="Parcel stuck in transit for 5 days, customer says rider never called…"></textarea></x-ops.field>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="raiseComplaint" class="btn-primary">Save complaint</button></x-slot:footer>
    </x-ops.modal>
</div>
