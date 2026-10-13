<section class="card">
    <x-ops.toolbar><x-ops.search placeholder="Search product or SKU…" /><select class="input w-auto" wire:model.live="status"><option value="all">All statuses</option>@foreach (config('ops.order_status') as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach</select><x-ops.date-range /></x-ops.toolbar>
    <x-ops.table min-width="820px">
        <x-slot:head><th class="th">Product</th><th class="th text-right">Units</th><th class="th text-right">Orders</th><th class="th text-right">To pack</th><th class="th text-right">Returned</th><th class="th text-right">Revenue</th><th class="th text-right">Stock</th></x-slot:head>
        @forelse ($this->rows as $r)<tr class="tr-hover"><td class="td"><div class="font-medium">{{ $r->name }} · {{ $r->color }} {{ $r->size }}</div><div class="mono text-xs text-ink-muted">{{ $r->sku }}</div></td><td class="td text-right font-semibold">{{ $r->units }}</td><td class="td text-right">{{ $r->orders }}</td><td class="td text-right text-info">{{ $r->to_pack }}</td><td class="td text-right text-bad">{{ $r->returned ?: '—' }}</td><td class="td text-right tabular-nums">{{ tk($r->revenue) }}</td><td @class(['td text-right font-semibold', 'text-bad' => $r->stock < $r->to_pack])>{{ $r->stock }}</td></tr>
        @empty <x-ops.empty colspan="7" /> @endforelse
    </x-ops.table>
    <x-ops.pagination :paginator="$this->rows" />
</section>
