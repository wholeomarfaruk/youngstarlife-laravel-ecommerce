<div class="flex flex-col gap-3">
    <section class="card flex flex-wrap items-center justify-between gap-2.5 px-3.5 py-3">
        <div class="flex flex-wrap items-center gap-1.5 text-sm">@foreach (['Create PO', 'Send to supplier', 'Receive & create batches', 'Print labels'] as $i => $s)<span class="flex items-center gap-1.5"><span class="grid h-5 w-5 place-items-center rounded-full bg-soft text-xs text-ink-muted">{{ $i + 1 }}</span>{{ $s }}@if (! $loop->last)<span class="mx-1 text-ink-faint">→</span>@endif</span>@endforeach</div>
        <button wire:click="newOrder" class="btn-primary">+ New purchase order</button>
    </section>
    @php($s = $this->stats)
    <x-ops.kpis>
        <x-ops.kpi label="Open POs" :value="$s['open']" :hint="ops_num($s['to_arrive']).' pcs to arrive'" tone="info" />
        <x-ops.kpi label="Overdue" :value="$s['overdue']" :tone="$s['overdue'] ? 'bad' : 'ink'" hint="past expected date" />
        <x-ops.kpi label="On order value" :value="tk($s['on_order'])" hint="still to arrive" />
        <x-ops.kpi label="Received this month" :value="tk($s['received_month'])" tone="ok" />
        <x-ops.kpi label="Labels to print" :value="$s['labels']" tone="warn" :href="route('ops.batches.index', ['filters' => ['labels' => 1]])" />
    </x-ops.kpis>
    <section class="card">
        @php($c = $this->counts)
        <div class="border-b border-soft px-3 py-2"><x-ops.chips wire:model.live="status" :options="['all' => ['All', $c['all']], 'draft' => ['Draft', $c['draft']], 'open' => ['Waiting for stock', $c['open']], 'received' => ['Received', $c['received']], 'cancelled' => ['Cancelled', $c['cancelled']]]" /></div>
        <x-ops.toolbar><x-ops.search placeholder="Search PO or supplier…" /><select class="input w-auto" wire:model.live="filters.supplier"><option value="">All suppliers</option>@foreach ($suppliers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select><x-ops.date-range /></x-ops.toolbar>
        <x-ops.table min-width="1060px">
            <x-slot:head><th class="th">PO</th><th class="th">Supplier · items</th><th class="th">Ordered</th><th class="th">Expected</th><th class="th">Received</th><th class="th text-right">Total</th><th class="th text-right">Due</th><th class="th">Status</th><th class="th">Actions</th></x-slot:head>
            @forelse ($this->orders as $po)
                @php($overdue = in_array($po->status, ['sent', 'partial']) && $po->expected_at?->lt(today()))
                <tr class="tr-hover" wire:key="po{{ $po->id }}">
                    <td class="td"><a href="{{ route('ops.purchases.edit', $po) }}" wire:navigate class="mono font-semibold">{{ $po->number }}</a></td>
                    <td class="td"><span class="font-medium">{{ $po->supplier->name }}</span><div class="max-w-[260px] truncate text-xs text-ink-muted">{{ $po->items->map(fn ($i) => $i->variant->label.' ×'.$i->qty)->join(', ') ?: 'No items yet' }}</div></td>
                    <td class="td">{{ $po->ordered_at->format('d M') }}</td>
                    <td @class(['td', 'font-medium text-bad' => $overdue])>{{ $po->expected_at?->format('d M') ?? '—' }}@if ($overdue)<div class="text-xs">overdue</div>@endif</td>
                    <td class="td">{{ (int) $po->qty_received }} / {{ (int) $po->qty_total }} pcs<div class="mt-1 h-1 rounded bg-soft"><div class="h-1 rounded bg-brand" style="width: {{ $po->qty_total ? min(100, $po->qty_received / $po->qty_total * 100) : 0 }}%"></div></div></td>
                    <td class="td text-right font-semibold tabular-nums">{{ tk($po->total) }}</td>
                    <td @class(['td text-right tabular-nums', 'font-semibold text-warn' => $po->received_value - $po->paid > 0])>{{ $po->received_value - $po->paid > 0 ? tk($po->received_value - $po->paid) : '—' }}</td>
                    <td class="td"><x-ops.badge map="po_status" :status="$po->status" />@if ($po->closed_short)<div class="text-xs text-ink-muted">closed short</div>@endif</td>
                    <td class="td"><div class="flex gap-1">
                        <a href="{{ route('ops.purchases.edit', $po) }}" wire:navigate class="btn-secondary btn-sm">{{ in_array($po->status, ['draft', 'sent']) ? 'Edit' : 'View' }}</a>
                        @if (in_array($po->status, ['sent', 'partial']))<a href="{{ route('ops.purchases.edit', ['po' => $po, 'step' => 'receive']) }}" wire:navigate class="btn-primary btn-sm">Receive</a>@endif
                        @if ($po->labels_due)<a href="{{ route('ops.purchases.edit', ['po' => $po, 'step' => 'labels']) }}" wire:navigate class="btn-secondary btn-sm">Labels <span class="text-warn">{{ $po->labels_due }}</span></a>@endif
                    </div></td>
                </tr>
            @empty <x-ops.empty colspan="9"><button wire:click="newOrder" class="btn-primary btn-sm">+ New purchase order</button></x-ops.empty> @endforelse
        </x-ops.table>
        <x-ops.pagination :paginator="$this->orders" />
    </section>
</div>
