<div class="flex flex-col gap-3">
    @php($c = $this->counts)
    <x-admin.kpis>@foreach (config('admin.courier_status') as $k => [$l, $t])<button wire:click="$set('status','{{ $k }}')" class="kpi text-left"><span class="text-xs text-ink-muted">{{ $l }}</span><span class="text-xl font-semibold text-{{ $t }}">{{ $c[$k] ?? 0 }}</span><span class="mono text-2xs text-ink-muted">{{ $k }}</span></button>@endforeach</x-admin.kpis>
    <section class="card">
        <x-admin.toolbar><x-admin.search placeholder="Parcel, tracking, order…" /><select wire:model.live="courier" class="input w-auto"><option value="">All couriers</option>@foreach ($couriers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select><select wire:model.live="status" class="input w-auto"><option value="all">All statuses</option>@foreach (config('admin.courier_status') as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach</select><x-admin.date-range /></x-admin.toolbar>
        <x-admin.table min-width="900px">
            <x-slot:head><th class="th">Parcel</th><th class="th">Order · customer</th><th class="th">Courier · tracking</th><th class="th text-right">COD</th><th class="th">Status</th><th class="th">Updated</th></x-slot:head>
            @forelse ($this->parcels as $p)<tr class="tr-hover"><td class="td mono font-semibold">{{ $p->code }}</td><td class="td"><a href="{{ route('admin.orders.show', $p->order_id) }}" wire:navigate class="mono">{{ $p->order->number }}</a> · {{ $p->order->customer->name }}<div class="text-xs text-ink-muted">{{ $p->order->customer->area }}</div></td><td class="td">{{ $p->courier->name }}<div class="mono text-xs text-ink-muted">{{ $p->tracking }}</div></td><td class="td text-right font-semibold">{{ tk($p->cod_amount) }}</td><td class="td"><x-admin.badge map="courier_status" :status="$p->courier_status" /></td><td class="td text-sm text-ink-muted">{{ $p->status_updated_at?->diffForHumans() }}</td></tr>
            @empty <x-admin.empty colspan="6" /> @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$this->parcels" />
    </section>
</div>
