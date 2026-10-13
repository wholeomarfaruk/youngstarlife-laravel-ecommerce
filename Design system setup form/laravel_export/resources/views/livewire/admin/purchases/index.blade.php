<div class="flex flex-col gap-3">
    <section class="card flex flex-wrap items-center justify-between gap-2.5 px-3.5 py-3">
        <div class="flex flex-wrap items-center gap-1.5 text-sm">@foreach (['Create PO', 'Send to supplier', 'Receive & create batches', 'Print labels'] as $i => $s)<span class="flex items-center gap-1.5"><span class="grid h-5 w-5 place-items-center rounded-full bg-soft text-xs text-ink-muted">{{ $i + 1 }}</span>{{ $s }}@if (! $loop->last)<span class="mx-1 text-ink-faint">→</span>@endif</span>@endforeach</div>
        <button wire:click="newOrder" class="btn-primary">+ New purchase order</button>
    </section>
    <section class="card">
        <x-admin.toolbar><x-admin.chips wire:model.live="status" :options="['all' => ['All', ''], 'draft' => ['Draft', ''], 'open' => ['Waiting for stock', ''], 'received' => ['Received', ''], 'cancelled' => ['Cancelled', '']]" /><x-admin.search placeholder="Search PO or supplier…" class="ml-auto" /></x-admin.toolbar>
        <x-admin.table min-width="900px">
            <x-slot:head><th class="th">PO</th><th class="th">Supplier</th><th class="th">Ordered</th><th class="th">Expected</th><th class="th">Received</th><th class="th text-right">Total</th><th class="th">Status</th></x-slot:head>
            @forelse ($this->orders as $po)
                <tr class="tr-hover" wire:key="po{{ $po->id }}">
                    <td class="td"><a href="{{ route('admin.purchases.edit', $po) }}" wire:navigate class="mono font-semibold">{{ $po->number }}</a></td>
                    <td class="td font-medium">{{ $po->supplier->name }}</td><td class="td">{{ $po->ordered_at->format('d M') }}</td>
                    <td @class(['td', 'text-bad' => in_array($po->status, ['sent', 'partial']) && $po->expected_at?->isPast()])>{{ $po->expected_at?->format('d M') }}</td>
                    <td class="td">{{ (int) $po->qty_received }} / {{ (int) $po->qty_total }} pcs<div class="mt-1 h-1 rounded bg-soft"><div class="h-1 rounded bg-brand" style="width: {{ $po->qty_total ? $po->qty_received / $po->qty_total * 100 : 0 }}%"></div></div></td>
                    <td class="td text-right font-semibold tabular-nums">{{ tk($po->total) }}</td><td class="td"><x-admin.badge map="po_status" :status="$po->status" /></td>
                </tr>
            @empty <x-admin.empty colspan="7" /> @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$this->orders" />
    </section>
</div>
