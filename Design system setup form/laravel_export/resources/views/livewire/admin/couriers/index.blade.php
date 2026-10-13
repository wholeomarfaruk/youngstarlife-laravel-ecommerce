<section class="card">
    <x-admin.toolbar><x-admin.search />{{ $toolbar ?? '' }}<span class="ml-auto text-sm text-ink-muted">{{ $this->rows->total() }} records</span></x-admin.toolbar>
    <x-admin.table min-width="820px">
        <x-slot:head><th class="th">Courier</th><th class="th text-right">Parcels</th><th class="th text-right">Delivered</th><th class="th text-right">Returned</th><th class="th text-right">Delivery rate</th><th class="th text-right">Receivable</th><th class="th">Status</th></x-slot:head>
        @forelse ($this->rows as $r)<tr class="tr-hover" wire:key="r{{ $r->id }}"><td class="td font-semibold">{{ $r->name }}</td><td class="td text-right">{{ $r->total }}</td><td class="td text-right text-ok">{{ $r->delivered }}</td><td class="td text-right text-bad">{{ $r->returned }}</td><td class="td text-right font-semibold">{{ ($r->delivered + $r->returned) ? round($r->delivered / ($r->delivered + $r->returned) * 100).'%' : '—' }}</td><td class="td text-right font-semibold">{{ tk($r->receivable) }}</td><td class="td"><x-admin.badge :tone="$r->is_active ? 'ok' : 'mute'">{{ $r->is_active ? 'Active' : 'Off' }}</x-admin.badge></td></tr>
        @empty <x-admin.empty colspan="7" /> @endforelse
    </x-admin.table>
    <x-admin.pagination :paginator="$this->rows" />
</section>
