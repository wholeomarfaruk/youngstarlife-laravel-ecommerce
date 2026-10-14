<div class="flex flex-col gap-3">
    @php($s = $this->stats)
    <x-ops.kpis>
        <x-ops.kpi label="Active batches" :value="ops_num($s['active'])" /><x-ops.kpi label="Units in batches" :value="ops_num($s['units'])" />
        <x-ops.kpi label="Batch stock value" :value="tk($s['value'])" hint="at purchase cost" /><x-ops.kpi label="Avg sell-through" :value="$s['sell'].'%'" tone="ok" />
        <x-ops.kpi label="Low batches" :value="$s['low']" tone="warn" hint="under 30% left" /><x-ops.kpi label="Oldest active" :value="$s['oldest'].' days'" :tone="$s['oldest'] > 30 ? 'bad' : 'ink'" />
        <x-ops.kpi label="Customer returns traced" :value="$s['returns']" tone="info" :href="route('ops.returns.receive')" /><x-ops.kpi label="Damaged" :value="$s['damaged']" tone="bad" :href="route('ops.supplier-returns.index', ['view' => 'pool'])" />
    </x-ops.kpis>
    <section class="card">
        @php($c = $this->counts)
        <div class="border-b border-soft px-3 py-2"><x-ops.chips wire:model.live="status" :options="['all' => ['All', $c['all']], 'active' => ['Active', $c['active']], 'queued' => ['Next in queue', $c['queued']], 'low' => ['Low', $c['low']], 'depleted' => ['Depleted', $c['depleted']]]" /></div>
        <x-ops.toolbar>
            <x-ops.search placeholder="Search batch, product, SKU, PO…" />
            <x-ops.searchable-select field="filters.supplier" :options="$suppliers" placeholder="All suppliers" searchPlaceholder="Search suppliers…" class="w-52" />
            <x-ops.searchable-select field="filters.product" :options="$products->pluck('name', 'id')->all()" :images="$products->mapWithKeys(fn ($p) => [$p->id => $p->thumb_url])->all()" placeholder="All products" searchPlaceholder="Search products…" class="w-60" />
            <select class="input w-auto" wire:model.live="filters.warehouse"><option value="">All warehouses</option>@foreach ($warehouses as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
            <x-ops.date-range />
            <select class="input w-auto" wire:model.live="sort"><option value="newest">Newest first</option><option value="fifo">Oldest first (FIFO)</option><option value="remaining">Most remaining</option><option value="value">Highest value</option><option value="sellthrough">Best sell-through</option><option value="returns">Most returns</option></select>
        </x-ops.toolbar>
        <x-ops.bulk-bar :count="count($selected)">
            <a href="{{ route('ops.labels.print', ['batches' => implode(',', $selected)]) }}" target="_blank" class="btn-sm rounded bg-white text-ink hover:text-ink">Print labels</a>
            <button wire:click="prepare('batch-transfer')" class="btn-sm rounded bg-side-hover">Transfer</button>
            <button wire:click="prepare('batch-adjust')" class="btn-sm rounded bg-side-hover">Adjust</button>
            <button wire:click="markDepleted" wire:confirm="Write off the remaining stock of {{ count($selected) }} batches?" class="btn-sm rounded bg-side-hover">Mark depleted</button>
            <button wire:click="export" class="btn-sm rounded bg-side-hover">Export</button>
        </x-ops.bulk-bar>
        <x-ops.table min-width="1140px">
            <x-slot:head><th class="th w-7"></th><x-ops.th sort="code">Batch</x-ops.th><th class="th">Product</th><th class="th">Supplier · PO</th><x-ops.th sort="received_at">Received</x-ops.th><x-ops.th sort="remaining" align="right">Remaining / qty</x-ops.th><th class="th text-right">Sell-thru</th><th class="th text-right">Returns</th><th class="th text-right">Damaged</th><th class="th text-right">Value</th><th class="th">Status</th><th class="th w-8"></th></x-slot:head>
            @forelse ($this->batches as $b)
                <tr wire:key="b{{ $b->id }}" @class(['tr-hover cursor-pointer', 'bg-brand-tint' => $expanded === $b->id || in_array((string) $b->id, $selected)])>
                    <td class="td" @click.stop><input type="checkbox" value="{{ $b->id }}" wire:model.live="selected" class="h-4 w-4 accent-ink"></td>
                    <td class="td" wire:click="toggle({{ $b->id }})"><span class="mono font-semibold">{{ $b->code }}</span><div class="text-xs {{ $b->queue === 1 ? 'text-ok' : 'text-info' }}">{{ $b->queue === 1 ? 'Allocating now' : ($b->queue > 1 ? 'Queue #'.$b->queue : '') }}</div></td>
                    <td class="td" wire:click="toggle({{ $b->id }})"><span class="font-medium text-ink">{{ $b->variant->label }}</span><div class="text-xs text-ink-muted">{{ $b->warehouse?->name ?? '—' }} · cost {{ tk($b->unit_cost) }}</div></td>
                    <td class="td" wire:click="toggle({{ $b->id }})">{{ $b->supplier->name }}<div class="mono text-xs text-ink-muted">{{ $b->purchaseOrder?->number ?? '—' }}</div></td>
                    <td class="td" wire:click="toggle({{ $b->id }})">{{ $b->received_at->format('d M') }}<div @class(['text-xs', 'text-bad font-medium' => $b->age_days > 30 && $b->remaining > 0, 'text-ink-muted' => ! ($b->age_days > 30 && $b->remaining > 0)])>{{ $b->age_days }} days old</div></td>
                    <td class="td text-right" wire:click="toggle({{ $b->id }})"><b>{{ $b->remaining }}</b> <span class="text-ink-muted">/ {{ $b->qty }}</span><div class="mt-1 h-1 rounded bg-soft"><div class="h-1 rounded {{ $b->status === 'low' ? 'bg-warn-bar' : 'bg-brand' }}" style="width: {{ $b->qty ? $b->remaining / $b->qty * 100 : 0 }}%"></div></div></td>
                    <td class="td text-right" wire:click="toggle({{ $b->id }})">{{ $b->sell_through }}%</td>
                    <td class="td text-right" wire:click="toggle({{ $b->id }})">{{ $b->returns ?: '—' }}</td>
                    <td class="td text-right text-bad" wire:click="toggle({{ $b->id }})">{{ $b->damaged ?: '—' }}</td>
                    <td class="td text-right font-medium tabular-nums" wire:click="toggle({{ $b->id }})">{{ tk($b->remaining * $b->unit_cost) }}</td>
                    <td class="td" wire:click="toggle({{ $b->id }})">@if ($b->queue > 1)<x-ops.badge tone="info">Next in queue</x-ops.badge>@else<x-ops.badge map="batch_status" :status="$b->status" />@endif</td>
                    <td class="td"><x-ops.row-menu>
                        <x-ops.menu-item wire:click="toggle({{ $b->id }})">Open <span class="text-xs text-ink-muted">↵</span></x-ops.menu-item>
                        <x-ops.menu-item wire:click="prepare('batch-adjust', {{ $b->id }})">Adjust stock</x-ops.menu-item>
                        <x-ops.menu-item wire:click="prepare('batch-transfer', {{ $b->id }})">Transfer</x-ops.menu-item>
                        <x-ops.menu-item wire:click="prepare('batch-sr', {{ $b->id }})">Supplier return</x-ops.menu-item>
                        <div class="my-1 border-t border-soft"></div>
                        <a href="{{ route('ops.labels.print', ['batches' => $b->id]) }}" target="_blank" class="rounded px-2 py-1.5 text-ink hover:bg-soft">Print labels</a>
                        <a href="{{ route('ops.trace', ['q' => $b->code]) }}" wire:navigate class="rounded px-2 py-1.5 text-ink hover:bg-soft">Trace</a>
                        @if ($b->remaining > 0)<div class="my-1 border-t border-soft"></div><x-ops.menu-item danger wire:click="markDepleted({{ $b->id }})" wire:confirm="Write off {{ $b->remaining }} pcs of {{ $b->code }}?">Mark depleted</x-ops.menu-item>@endif
                    </x-ops.row-menu></td>
                </tr>
                @if ($expanded === $b->id && ($d = $this->detail))
                    <tr wire:key="bx{{ $b->id }}"><td colspan="12" class="border-b border-soft bg-zebra p-3.5">
                        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_220px]">
                            <x-ops.card title="Batch ledger" flush>
                                @forelse ($d->movements as $m)<div class="grid grid-cols-[90px_minmax(0,1fr)_60px] gap-2 border-t border-soft px-3 py-1.5 text-sm first:border-0"><span class="text-ink-muted">{{ $m->created_at->format('d M H:i') }}</span><span>{{ config('ops.movement_types.'.$m->type, $m->type) }}@if ($m->note) <span class="text-xs text-ink-muted">· {{ $m->note }}</span>@endif</span><span @class(['text-right font-semibold', 'text-ok' => $m->qty > 0, 'text-bad' => $m->qty < 0])>{{ $m->qty > 0 ? '+' : '' }}{{ $m->qty }}</span></div>
                                @empty<div class="p-3 text-ink-muted">No movements.</div>@endforelse
                            </x-ops.card>
                            <x-ops.card title="Orders taken from this batch" flush>
                                @forelse ($this->detailOrders as $a)
                                    @php($o = $a->item->order) @php($tone = in_array($o->status, ['delivered']) ? 'ok' : (in_array($o->status, ['returned', 'returning']) ? 'bad' : (in_array($o->status, ['cancelled']) ? 'mute' : 'info')))
                                    <a href="{{ route('ops.orders.show', $o) }}" wire:navigate class="flex items-center gap-2 border-t border-soft px-3 py-1.5 text-sm text-ink first:border-0 hover:bg-white"><span class="h-2 w-2 rounded-full bg-{{ $tone === 'mute' ? 'ink-faint' : $tone }}"></span><span class="mono font-semibold">{{ $o->number }}</span><span class="flex-1 truncate">{{ $o->customer?->name }}</span><span>× {{ $a->qty }}</span><x-ops.badge map="order_status" :status="$o->status" /></a>
                                @empty<div class="p-3 text-ink-muted">No orders yet.</div>@endforelse
                            </x-ops.card>
                            <div class="flex flex-col gap-2">
                                <div class="rounded-md border border-line bg-white p-2.5"><div class="flex justify-between text-2xs font-bold"><span>{{ mb_strtoupper(site()->storeName()) }}</span><span>{{ tk($d->variant->effective_price) }}</span></div><div class="truncate text-xs font-semibold">{{ $d->variant->product->name }} · {{ $d->variant->color }} {{ $d->variant->size }}</div><div class="my-1 h-7 bg-[repeating-linear-gradient(90deg,#1A1A17_0_1px,transparent_1px_3px,#1A1A17_3px_5px,transparent_5px_6px)]"></div><div class="mono text-center text-2xs">{{ $d->label_code }}</div></div>
                                <a href="{{ route('ops.products.edit', ['product' => $d->variant->product_id, 'tab' => 'stock']) }}" wire:navigate class="btn-secondary btn-sm">Open product</a>
                                <button wire:click="prepare('batch-adjust', {{ $d->id }})" class="btn-secondary btn-sm">Adjust</button>
                                <button wire:click="prepare('batch-transfer', {{ $d->id }})" class="btn-secondary btn-sm">Transfer</button>
                                <button wire:click="prepare('batch-sr', {{ $d->id }})" class="btn-secondary btn-sm">Supplier return</button>
                                <a href="{{ route('ops.trace', ['q' => $d->code]) }}" wire:navigate class="btn-dark btn-sm">Trace</a>
                            </div>
                        </div>
                    </td></tr>
                @endif
            @empty <x-ops.empty colspan="12"><button wire:click="clearFilters" class="btn-secondary btn-sm">Clear filters</button></x-ops.empty> @endforelse
        </x-ops.table>
        <x-ops.pagination :paginator="$this->batches" />
    </section>

    <x-ops.modal name="batch-adjust" title="Adjust stock">
        <div class="grid gap-3"><p class="text-sm text-ink-muted">{{ count($target) }} batch(es). Use + to add found units, − to remove lost / written-off units.</p>
            <x-ops.field label="Units (+ / −)" required :error="$errors->first('adjustQty')"><input type="number" wire:model="adjustQty" class="input" placeholder="-2"></x-ops.field>
            <x-ops.field label="Reason" required :error="$errors->first('adjustNote')"><input wire:model="adjustNote" class="input" placeholder="Stock count difference"></x-ops.field></div>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="adjust" class="btn-primary">Adjust</button></x-slot:footer>
    </x-ops.modal>
    <x-ops.modal name="batch-transfer" title="Transfer batch">
        <x-ops.field label="To warehouse" required :error="$errors->first('toWarehouse')"><select wire:model="toWarehouse" class="input"><option value="">Choose…</option>@foreach ($warehouses as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-ops.field>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="transfer" class="btn-primary">Transfer {{ count($target) }}</button></x-slot:footer>
    </x-ops.modal>
    <x-ops.modal name="batch-sr" title="Supplier return from batch">
        <div class="grid gap-3"><x-ops.field label="Units to return" required :error="$errors->first('srQty')"><input type="number" min="1" wire:model="srQty" class="input"></x-ops.field>
            <x-ops.field label="Reason" required><select wire:model="srReason" class="input">@foreach (ops_setting('returns.reasons', config('ops.return_reasons')) as $r)<option>{{ $r }}</option>@endforeach</select></x-ops.field></div>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="supplierReturn" class="btn-primary">Create supplier return</button></x-slot:footer>
    </x-ops.modal>
</div>
