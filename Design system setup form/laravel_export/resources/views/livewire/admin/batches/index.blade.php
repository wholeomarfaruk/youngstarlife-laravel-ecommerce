<div class="flex flex-col gap-3">
    @php($s = $this->stats)
    <x-admin.kpis>
        <x-admin.kpi label="Active batches" :value="$s['active']" /><x-admin.kpi label="Units in batches" :value="number_format($s['units'])" />
        <x-admin.kpi label="Batch stock value" :value="tk($s['value'])" hint="at purchase cost" /><x-admin.kpi label="Low batches" :value="$s['low']" tone="warn" hint="under 30% left" />
        <x-admin.kpi label="Damaged" :value="$s['damaged']" tone="bad" :href="route('admin.supplier-returns.index', ['view' => 'pool'])" /><x-admin.kpi label="Labels to print" :value="$s['labels']" tone="warn" />
    </x-admin.kpis>
    <section class="card">
        <div class="border-b border-soft px-3 py-2"><x-admin.chips wire:model.live="status" :options="['all' => ['All', ''], 'active' => ['Active', ''], 'low' => ['Low', ''], 'depleted' => ['Depleted', '']]" /></div>
        <x-admin.toolbar><x-admin.search placeholder="Search batch, SKU, PO…" /><select class="input w-auto" wire:model.live="filters.supplier"><option value="">All suppliers</option>@foreach ($suppliers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select><x-admin.date-range /></x-admin.toolbar>
        <x-admin.table min-width="980px">
            <x-slot:head><x-admin.th sort="code">Batch</x-admin.th><th class="th">Product</th><th class="th">Supplier · PO</th><x-admin.th sort="received_at">Received</x-admin.th><x-admin.th sort="remaining" align="right">Remaining / qty</x-admin.th><th class="th text-right">Damaged</th><th class="th text-right">Value</th><th class="th">Status</th></x-slot:head>
            @forelse ($this->batches as $b)
                <tr class="tr-hover" wire:key="b{{ $b->id }}">
                    <td class="td mono font-semibold">{{ $b->code }}</td>
                    <td class="td"><a href="{{ route('admin.products.edit', ['product' => $b->variant->product_id, 'tab' => 'stock']) }}" wire:navigate class="font-medium text-ink">{{ $b->variant->label }}</a><div class="text-xs text-ink-muted">cost {{ tk($b->unit_cost) }}</div></td>
                    <td class="td">{{ $b->supplier->name }}<div class="mono text-xs text-ink-muted">{{ $b->purchaseOrder?->number }}</div></td>
                    <td class="td">{{ $b->received_at->format('d M') }}<div class="text-xs text-ink-muted">{{ $b->received_at->diffInDays() }} days old</div></td>
                    <td class="td text-right"><b>{{ $b->remaining }}</b> <span class="text-ink-muted">/ {{ $b->qty }}</span><div class="mt-1 h-1 rounded bg-soft"><div class="h-1 rounded bg-brand" style="width: {{ $b->qty ? $b->remaining / $b->qty * 100 : 0 }}%"></div></div></td>
                    <td class="td text-right">{{ $b->damaged ?: '—' }}</td><td class="td text-right font-medium tabular-nums">{{ tk($b->remaining * $b->unit_cost) }}</td>
                    <td class="td"><x-admin.badge map="batch_status" :status="$b->status" /></td>
                </tr>
            @empty <x-admin.empty colspan="8" /> @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$this->batches" />
    </section>
</div>
