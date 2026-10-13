<div class="flex flex-col gap-3">
    @if ($sr = $this->current)
        <button wire:click="$set('open', null)" class="self-start text-sm text-brand">← All supplier returns</button>
        <x-admin.page-header :title="$sr->code.' · '.$sr->supplier->name"><x-admin.badge map="supplier_return_status" :status="$sr->status" /></x-admin.page-header>
        <x-admin.steps :steps="['Pending', 'Sent', 'Received by supplier', 'Approved', 'Resolved']" :current="array_search($sr->status, \App\Models\SupplierReturn::STEPS)" />
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_300px]">
            <x-admin.card title="Items" flush>
                <x-admin.table min-width="600px"><x-slot:head><th class="th">Product · reason</th><th class="th">Batch</th><th class="th">PO</th><th class="th">Source</th><th class="th text-right">Qty</th><th class="th text-right">Value</th></x-slot:head>
                    @foreach ($sr->items as $i)<tr><td class="td">{{ $i->batch->variant->label }}<div class="text-xs text-ink-muted">{{ $i->reason }}</div></td><td class="td mono font-semibold">{{ $i->batch->code }}</td><td class="td mono text-xs">{{ $i->batch->purchaseOrder?->number }}</td><td class="td mono text-xs">{{ $i->source_ref }}</td><td class="td text-right">{{ $i->qty }}</td><td class="td text-right font-semibold">{{ tk($i->qty * $i->unit_cost) }}</td></tr>@endforeach
                </x-admin.table>
            </x-admin.card>
            <x-admin.card title="Resolution">
                <div class="flex overflow-hidden rounded-md border border-line-2">@foreach (['replacement' => 'Replacement', 'credit' => 'Credit', 'refund' => 'Refund'] as $k => $l)<button wire:click="setResolution('{{ $k }}')" @class(['flex-1 border-r border-line py-1.5 last:border-0', 'bg-ink text-white' => $sr->resolution === $k])>{{ $l }}</button>@endforeach</div>
                <div class="mt-3 flex flex-col gap-2">
                    @if ($sr->is_open)<button wire:click="advance" class="btn-primary">Mark {{ strtolower(config('admin.supplier_return_status.'.(\App\Models\SupplierReturn::STEPS[array_search($sr->status, \App\Models\SupplierReturn::STEPS) + 1] ?? 'resolved').'.0')) }}</button>
                        @if ($sr->status !== 'pending')<button wire:click="reject" class="btn-danger">Supplier rejected</button>@endif @endif
                </div>
            </x-admin.card>
        </div>
    @else
        <div class="-mt-1 flex gap-1 border-b border-line-2">@foreach (['returns' => 'Supplier returns', 'pool' => 'Damaged stock to return'] as $k => $l)<button wire:click="$set('view','{{ $k }}')" @class(['mtab', 'mtab-on' => $view === $k])>{{ $l }}</button>@endforeach</div>
        @if ($view === 'returns')
            <section class="card">
                <x-admin.toolbar><x-admin.chips wire:model.live="status" :options="['all' => ['All', ''], 'open' => ['Open', '']] + collect(config('admin.supplier_return_status'))->map(fn ($v) => [$v[0], ''])->all()" /><x-admin.search placeholder="Search SR…" class="ml-auto" /></x-admin.toolbar>
                <x-admin.table min-width="760px"><x-slot:head><th class="th">SR</th><th class="th">Supplier</th><th class="th">Items</th><th class="th text-right">Value</th><th class="th">Status</th><th class="th">Resolution</th></x-slot:head>
                    @forelse ($this->returns as $r)<tr class="tr-hover cursor-pointer" wire:click="$set('open', {{ $r->id }})"><td class="td mono font-semibold text-brand">{{ $r->code }}</td><td class="td">{{ $r->supplier->name }}</td><td class="td text-sm">{{ $r->items->map(fn ($i) => $i->batch->variant->label.' ×'.$i->qty)->join(', ') }}</td><td class="td text-right font-semibold">{{ tk($r->value) }}</td><td class="td"><x-admin.badge map="supplier_return_status" :status="$r->status" /></td><td class="td capitalize text-sm">{{ $r->resolution }}</td></tr>
                    @empty <x-admin.empty colspan="6" /> @endforelse
                </x-admin.table>
                <x-admin.pagination :paginator="$this->returns" />
            </section>
        @else
            @forelse ($this->pool as $supplierId => $items)
                <x-admin.card :title="$items->first()->batch->supplier->name" flush>
                    <x-slot:actions><button wire:click="createFor({{ $supplierId }})" class="btn-primary btn-sm">Create return</button></x-slot:actions>
                    @foreach ($items as $i)<label class="flex items-center gap-3 border-t border-soft px-3.5 py-2 first:border-0"><input type="checkbox" value="{{ $i->id }}" wire:model="pick" class="accent-brand"><span class="flex-1">{{ $i->batch->variant->label }} <span class="text-xs text-ink-muted">· {{ $i->reason }}</span></span><span class="mono font-semibold">{{ $i->batch->code }}</span><span class="mono text-xs text-ink-muted">{{ $i->customerReturn->code }}</span></label>@endforeach
                </x-admin.card>
            @empty <div class="card p-6 text-center text-ink-muted">No damaged stock waiting.</div> @endforelse
        @endif
    @endif
</div>
