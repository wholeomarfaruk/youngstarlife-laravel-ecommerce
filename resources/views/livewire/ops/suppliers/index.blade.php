<div class="flex flex-col gap-3">
@php($k = $this->kpis)
<x-ops.kpis>
    <x-ops.kpi label="Suppliers" :value="$k['suppliers']" /><x-ops.kpi label="Total purchased" :value="tk($k['purchased'])" hint="good units × cost" />
    <x-ops.kpi label="Paid" :value="tk($k['paid'])" tone="ok" /><x-ops.kpi label="Supplier due" :value="tk($k['due'])" tone="warn" />
    <x-ops.kpi label="Stock from suppliers" :value="ops_num($k['stock'])" hint="units in batches" /><x-ops.kpi label="Pending returns" :value="$k['returns']" tone="bad" :href="route('ops.supplier-returns.index', ['status' => 'open'])" />
    <x-ops.kpi label="Supplier credit" :value="tk($k['credit'])" tone="ok" /><x-ops.kpi label="Avg defect rate" :value="$k['defect'].'%'" :tone="$k['defect'] > 3 ? 'bad' : 'ink'" />
</x-ops.kpis>
<section class="card">
    @php($c = $this->counts)
    <div class="border-b border-soft px-3 py-2"><x-ops.chips wire:model.live="status" :options="['all' => ['All', $c['all']], 'due' => ['Has due', $c['due']], 'returns' => ['Pending returns', $c['returns']], 'active' => ['Active', $c['active']], 'inactive' => ['Inactive', $c['inactive']]]" /></div>
    <x-ops.toolbar><x-ops.search placeholder="Supplier, contact, phone…" />
        <select class="input w-auto" wire:model.live="filters.category"><option value="">All categories</option>@foreach ($this->categories as $cat)<option>{{ $cat }}</option>@endforeach</select>
        <select class="input w-auto" wire:model.live="sort"><option value="due">Highest due</option><option value="purchased">Most purchased</option><option value="defect">Highest defect rate</option><option value="name">Name A–Z</option></select>
        <span class="ml-auto text-sm text-ink-muted">{{ $this->rows->total() }} suppliers</span><a href="{{ route('ops.users.index', ['new' => 'supplier']) }}" wire:navigate class="btn-primary">+ Add supplier</a></x-ops.toolbar>
    <x-ops.table min-width="1140px">
        <x-slot:head><th class="th">Supplier</th><th class="th">Categories</th><th class="th text-right">POs</th><th class="th text-right">Batches</th><th class="th text-right">Stock</th><th class="th text-right">Purchased</th><th class="th text-right">Paid</th><th class="th text-right">Due</th><th class="th text-right">Defect rate</th><th class="th text-right">Returns</th><th class="th"></th></x-slot:head>
        @forelse ($this->rows as $r)
            @php($defect = $r->received_units ? round($r->damaged_units / $r->received_units * 100, 1) : 0)
            <tr wire:key="r{{ $r->id }}" @class(['tr-hover cursor-pointer', 'bg-brand-tint' => $expanded === $r->id])>
                <td class="td" wire:click="toggle({{ $r->id }})"><div class="font-semibold">{{ $r->name }} @unless ($r->is_active)<x-ops.badge tone="mute">Inactive</x-ops.badge>@endunless</div><div class="text-xs text-ink-muted">{{ $r->contact_name }} · {{ $r->phone }}</div></td>
                <td class="td text-sm" wire:click="toggle({{ $r->id }})">{{ $r->categories ?: '—' }}</td>
                <td class="td text-right" wire:click="toggle({{ $r->id }})">{{ $r->purchase_orders_count }}</td><td class="td text-right" wire:click="toggle({{ $r->id }})">{{ $r->batches_count }}</td>
                <td class="td text-right" wire:click="toggle({{ $r->id }})">{{ (int) $r->stock }}</td>
                <td class="td text-right tabular-nums" wire:click="toggle({{ $r->id }})">{{ tk($r->purchased) }}</td><td class="td text-right tabular-nums text-ok" wire:click="toggle({{ $r->id }})">{{ tk($r->paid) }}</td>
                <td @class(['td text-right font-semibold tabular-nums', 'text-warn' => $r->due > 0]) wire:click="toggle({{ $r->id }})">{{ $r->due ? tk($r->due) : '—' }}</td>
                <td @class(['td text-right font-semibold', 'text-bad' => $defect > 5, 'text-warn' => $defect > 2 && $defect <= 5, 'text-ok' => $defect <= 2]) wire:click="toggle({{ $r->id }})">{{ $defect }}%</td>
                <td class="td text-right" wire:click="toggle({{ $r->id }})">{{ $r->open_returns ?: '—' }}</td>
                <td class="td"><button type="button" x-data @click.stop="$wire.edit({{ $r->id }}); $dispatch('open-modal', 'supplier')" class="btn-secondary btn-sm">Edit</button></td>
            </tr>
            @if ($expanded === $r->id && ($d = $this->detail))
                <tr wire:key="rx{{ $r->id }}"><td colspan="11" class="border-b border-soft bg-zebra p-3.5">
                    <div class="mb-3 flex flex-wrap gap-1.5">
                        <a href="{{ route('ops.purchases.index', ['supplier' => $r->id, 'new' => 1]) }}" wire:navigate class="btn-primary btn-sm">New purchase order</a>
                        <a href="{{ route('ops.purchases.index', ['filters' => ['supplier' => $r->id]]) }}" wire:navigate class="btn-secondary btn-sm">{{ $r->purchase_orders_count }} purchase orders</a>
                        <button wire:click="openPay({{ $r->id }})" class="btn-secondary btn-sm">Record payment</button>
                        <a href="{{ route('ops.supplier-returns.index', ['view' => 'pool']) }}" wire:navigate class="btn-secondary btn-sm">Supplier return</a>
                        @if ($r->phone)<a href="tel:{{ $r->phone }}" class="btn-secondary btn-sm">Call</a>@endif
                        <a href="{{ route('ops.batches.index', ['filters' => ['supplier' => $r->id]]) }}" wire:navigate class="btn-secondary btn-sm">Trace</a>
                        @if ($d['sup']->user)<a href="{{ route('ops.users.show', [$d['sup']->user_id, 'tab' => 'supplier']) }}" wire:navigate class="btn-secondary btn-sm">Master profile · {{ $d['sup']->user->name }}</a>@endif
                    </div>
                    <div class="grid gap-3 lg:grid-cols-3">
                        <x-ops.card title="Batches" flush>@forelse ($d['batches'] as $b)<a href="{{ route('ops.trace', ['q' => $b->code]) }}" wire:navigate class="flex items-center gap-2 border-t border-soft px-3 py-1.5 text-sm text-ink first:border-0 hover:bg-white"><span class="mono font-semibold">{{ $b->code }}</span><span class="flex-1 truncate">{{ $b->variant->label }}</span><span class="text-ink-muted">{{ $b->remaining }}/{{ $b->qty }}</span></a>@empty<div class="p-3 text-ink-muted">No batches.</div>@endforelse</x-ops.card>
                        <x-ops.card title="Supplier returns" flush>@forelse ($d['returns'] as $sr)<a href="{{ route('ops.supplier-returns.index', ['open' => $sr->id]) }}" wire:navigate class="flex items-center gap-2 border-t border-soft px-3 py-1.5 text-sm text-ink first:border-0 hover:bg-white"><span class="mono font-semibold">{{ $sr->code }}</span><span class="flex-1">{{ tk($sr->value) }}</span><x-ops.badge map="supplier_return_status" :status="$sr->status" /></a>@empty<div class="p-3 text-ink-muted">No supplier returns.</div>@endforelse</x-ops.card>
                        <x-ops.card title="Ledger" flush><div class="max-h-64 overflow-y-auto">@forelse ($d['ledger'] as $e)<div class="grid grid-cols-[56px_minmax(0,1fr)_80px_80px] gap-2 border-t border-soft px-3 py-1.5 text-xs first:border-0"><span class="text-ink-muted">{{ \Carbon\Carbon::parse($e['date'])->format('d M') }}</span><span class="truncate">{{ $e['text'] }}</span><span @class(['text-right', 'text-ok' => $e['amount'] < 0])>{{ $e['amount'] < 0 ? '−'.tk(-$e['amount']) : tk($e['amount']) }}</span><span class="text-right font-semibold">{{ tk($e['balance']) }}</span></div>@empty<div class="p-3 text-ink-muted">No entries.</div>@endforelse</div></x-ops.card>
                    </div>
                </td></tr>
            @endif
        @empty <x-ops.empty colspan="11" title="No suppliers yet" hint="Add a user with a supplier profile to start purchase orders."><a href="{{ route('ops.users.index', ['new' => 'supplier']) }}" wire:navigate class="btn-primary btn-sm">+ Add supplier</a></x-ops.empty> @endforelse
    </x-ops.table>
    <x-ops.pagination :paginator="$this->rows" />
</section>

<x-ops.modal name="supplier" :title="$editingId ? 'Edit supplier' : 'Add supplier'">
    <div class="grid gap-3 sm:grid-cols-2">
        <x-ops.field label="Supplier name" required class="sm:col-span-2" :error="$errors->first('s.name')"><input wire:model="s.name" class="input"></x-ops.field>
        <x-ops.field label="Contact person"><input wire:model="s.contact_name" class="input"></x-ops.field>
        <x-ops.field label="Phone" :error="$errors->first('s.phone')"><input wire:model="s.phone" inputmode="tel" class="input"></x-ops.field>
        <x-ops.field label="Address" class="sm:col-span-2"><input wire:model="s.address" class="input"></x-ops.field>
        <x-ops.field label="Categories" hint="e.g. Cargo pant, Polo" class="sm:col-span-2"><input wire:model="s.categories" class="input"></x-ops.field>
        <label class="flex items-center gap-2"><input type="checkbox" wire:model="s.is_active" class="accent-brand"> Active (shown when creating purchase orders)</label>
    </div>
    <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="saveSupplier" class="btn-primary">Save supplier</button></x-slot:footer>
</x-ops.modal>
<x-ops.modal name="supplier-pay" title="Record supplier payment">
    <div class="grid gap-3 sm:grid-cols-2">
        <x-ops.field label="Amount (৳)" required :error="$errors->first('payAmount')"><input type="number" min="1" wire:model="payAmount" class="input"></x-ops.field>
        <x-ops.field label="From account"><select wire:model="payAccount" class="input">@foreach (config('ops.accounts') as $a)<option>{{ $a }}</option>@endforeach</select></x-ops.field>
        <x-ops.field label="Note" class="sm:col-span-2"><input wire:model="payNote" class="input" placeholder="Cheque no., TXN id…"></x-ops.field>
    </div>
    <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="pay" class="btn-primary">Save payment</button></x-slot:footer>
</x-ops.modal>
</div>
