<div class="flex flex-col gap-3">
    @php($c = $this->counts)
    <section class="card">
        <div class="border-b border-soft px-3 py-2">
            <x-admin.chips wire:model.live="status" :options="['all' => ['All', array_sum($c)]] + collect(config('admin.order_status'))->mapWithKeys(fn ($v, $k) => [$k => [$v[0], $c[$k] ?? 0]])->all()" />
        </div>
        <x-admin.toolbar>
            <x-admin.search placeholder="Order no., customer, phone, tracking…" />
            <select class="input w-auto" wire:model.live="filters.source"><option value="">All channels</option><option value="facebook">Facebook</option><option value="website">Website</option><option value="instagram">Instagram</option></select>
            <select class="input w-auto" wire:model.live="filters.payment_status"><option value="">Any payment</option>@foreach (config('admin.payment_status') as $k => [$l]) <option value="{{ $k }}">{{ $l }}</option> @endforeach</select>
            <x-admin.date-range />
            <span class="ml-auto text-sm text-ink-muted">{{ $this->orders->total() }} orders</span>
        </x-admin.toolbar>
        <x-admin.bulk-bar :count="count($selected)">
            <button wire:click="bulkStatus('confirmed')" class="btn-sm rounded bg-white text-ink">Confirm</button>
            <button wire:click="bulkStatus('processing')" class="btn-sm rounded bg-side-hover">Processing</button>
            <a href="{{ route('admin.orders.print', ['ids' => implode(',', $selected)]) }}" target="_blank" class="btn-sm rounded bg-side-hover text-white">Print invoices</a>
        </x-admin.bulk-bar>

        {{-- Desktop table --}}
        <div class="max-[479px]:hidden">
            <x-admin.table min-width="900px">
                <x-slot:head>
                    <th class="th w-7"></th><x-admin.th sort="placed_at">Order</x-admin.th><x-admin.th>Customer</x-admin.th><x-admin.th sort="total" align="right">Total</x-admin.th>
                    <x-admin.th>Payment</x-admin.th><x-admin.th>Status</x-admin.th><x-admin.th>Courier</x-admin.th><th class="th w-8"></th>
                </x-slot:head>
                @forelse ($this->orders as $o)
                    <tr wire:key="o{{ $o->id }}" @class(['tr-hover', 'bg-brand-tint' => in_array((string) $o->id, $selected)])>
                        <td class="td"><input type="checkbox" value="{{ $o->id }}" wire:model.live="selected" class="h-4 w-4 accent-ink"></td>
                        <td class="td"><a href="{{ route('admin.orders.show', $o) }}" wire:navigate class="mono font-semibold">{{ $o->number }}</a><div class="text-xs text-ink-muted">{{ $o->placed_at->format('d M · H:i') }} · {{ ucfirst($o->source) }}</div></td>
                        <td class="td"><div class="font-medium">{{ $o->customer->name }}</div><div class="text-xs text-ink-muted">{{ $o->customer->phone }} · {{ $o->customer->area }}</div></td>
                        <td class="td text-right tabular-nums"><div class="font-semibold">{{ tk($o->total) }}</div><div class="text-xs text-ink-muted">COD {{ tk($o->cod) }}</div></td>
                        <td class="td"><x-admin.badge map="payment_status" :status="$o->payment_status" /></td>
                        <td class="td"><x-admin.badge map="order_status" :status="$o->status" /></td>
                        <td class="td"><div class="font-medium">{{ $o->shipment?->courier->name ?? '—' }}</div><div class="mono text-xs text-ink-muted">{{ $o->shipment?->tracking }}</div></td>
                        <td class="td">@include('livewire.admin.orders.partials.row-menu', ['o' => $o])</td>
                    </tr>
                @empty
                    <x-admin.empty colspan="8"><button wire:click="clearFilters" class="btn-secondary btn-sm">Clear filters</button></x-admin.empty>
                @endforelse
            </x-admin.table>
        </div>

        {{-- Phone cards --}}
        <div class="min-[480px]:hidden">
            @foreach ($this->orders as $o)
                <a href="{{ route('admin.orders.show', $o) }}" wire:navigate wire:key="m{{ $o->id }}" class="flex flex-col gap-2 border-b border-soft p-3 text-ink">
                    <div class="flex items-center gap-2"><span class="mono text-md font-semibold text-brand">{{ $o->number }}</span><span class="flex-1 truncate text-xs text-ink-muted">{{ $o->placed_at->format('d M · H:i') }} · {{ ucfirst($o->source) }}</span></div>
                    <div class="flex justify-between gap-2"><div class="min-w-0"><div class="text-md font-semibold">{{ $o->customer->name }}</div><div class="text-sm text-ink-muted">{{ $o->customer->phone }}</div></div><div class="text-right"><div class="text-lg font-bold">{{ tk($o->total) }}</div><div class="text-xs text-ink-muted">COD {{ tk($o->cod) }}</div></div></div>
                    <div class="flex flex-wrap gap-1.5"><x-admin.badge map="order_status" :status="$o->status" /><x-admin.badge map="payment_status" :status="$o->payment_status" /></div>
                </a>
            @endforeach
        </div>
        <x-admin.pagination :paginator="$this->orders" />
    </section>
</div>
