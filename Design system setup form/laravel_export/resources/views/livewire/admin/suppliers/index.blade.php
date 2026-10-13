<section class="card">
    <x-admin.toolbar><x-admin.search />{{ $toolbar ?? '' }}<span class="ml-auto text-sm text-ink-muted">{{ $this->rows->total() }} records</span></x-admin.toolbar>
    <x-admin.table min-width="820px">
        <x-slot:head><th class="th">Supplier</th><th class="th">Categories</th><th class="th text-right">POs</th><th class="th text-right">Batches</th><th class="th text-right">Stock</th><th class="th text-right">Due</th><th class="th text-right">Credit</th><th class="th"></th></x-slot:head>
        @forelse ($this->rows as $r)<tr class="tr-hover" wire:key="r{{ $r->id }}"><td class="td"><div class="font-semibold">{{ $r->name }}</div><div class="text-xs text-ink-muted">{{ $r->contact_name }} · {{ $r->phone }}</div></td><td class="td text-sm">{{ $r->categories }}</td><td class="td text-right">{{ $r->purchase_orders_count }}</td><td class="td text-right">{{ $r->batches_count }}</td><td class="td text-right">{{ (int) $r->stock }}</td><td @class(['td text-right font-semibold', 'text-warn' => $r->due])>{{ $r->due ? tk($r->due) : '—' }}</td><td class="td text-right text-ok">{{ $r->credit ? tk($r->credit) : '—' }}</td><td class="td"><a href="{{ route('admin.purchases.index', ['search' => $r->name]) }}" wire:navigate class="btn-secondary btn-sm">POs</a></td></tr>
        @empty <x-admin.empty colspan="8" /> @endforelse
    </x-admin.table>
    <x-admin.pagination :paginator="$this->rows" />
</section>
