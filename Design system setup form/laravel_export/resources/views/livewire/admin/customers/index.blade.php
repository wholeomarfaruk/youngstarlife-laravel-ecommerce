<section class="card">
    <x-admin.toolbar><x-admin.search />{{ $toolbar ?? '' }}<span class="ml-auto text-sm text-ink-muted">{{ $this->rows->total() }} records</span></x-admin.toolbar>
    <x-admin.table min-width="820px">
        <x-slot:head><th class="th">Customer</th><th class="th">Area</th><th class="th text-right">Orders</th><th class="th text-right">Delivery rate</th><th class="th text-right">Total spent</th><th class="th text-right">Due</th><th class="th">Last order</th></x-slot:head>
        @forelse ($this->rows as $r)<tr class="tr-hover" wire:key="r{{ $r->id }}"><td class="td"><div class="font-semibold">{{ $r->name }}</div><div class="text-xs text-ink-muted">{{ $r->phone }}</div></td><td class="td">{{ $r->area }}</td><td class="td text-right">{{ $r->orders_count }}</td><td @class(['td text-right font-semibold', 'text-bad' => ($r->delivery_rate ?? 1) < .75, 'text-ok' => ($r->delivery_rate ?? 1) >= .9])>{{ $r->delivery_rate !== null ? round($r->delivery_rate * 100).'%' : '—' }}</td><td class="td text-right font-medium">{{ tk($r->total_spent) }}</td><td @class(['td text-right', 'font-semibold text-warn' => $r->due])>{{ $r->due ? tk($r->due) : '—' }}</td><td class="td text-sm text-ink-muted">{{ $r->last_order_at?->format('d M') }}</td></tr>
        @empty <x-admin.empty colspan="7" /> @endforelse
    </x-admin.table>
    <x-admin.pagination :paginator="$this->rows" />
</section>
