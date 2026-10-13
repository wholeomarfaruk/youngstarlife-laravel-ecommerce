<div class="flex flex-col gap-3">
    @if ($sr = $this->current)
        <button wire:click="$set('open', null)" class="self-start text-sm text-brand">← Back to supplier returns</button>
        <x-ops.page-header :title="$sr->code.' · '.$sr->supplier->name" :subtitle="$sr->items->sum('qty').' units · '.tk($sr->value).' · created '.$sr->created_at->format('d M Y')"><x-ops.badge map="supplier_return_status" :status="$sr->status" /></x-ops.page-header>
        @php($steps = \App\Models\Ops\SupplierReturn::STEPS)
        <div class="card grid grid-cols-5 px-3 py-3.5">
            @foreach (['Pending', 'Sent', 'Received by supplier', 'Approved', 'Resolved'] as $i => $label)
                @php($cur = array_search($sr->status, $steps)) @php($cur = $cur === false ? -1 : $cur)
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center"><span @class(['h-3 w-3 flex-none rounded-full', 'bg-brand' => $i <= $cur, 'bg-bad' => $i === $cur + 1 && $sr->status === 'rejected', 'bg-line-2' => $i > $cur && ! ($i === $cur + 1 && $sr->status === 'rejected')])></span><span @class(['h-0.5 flex-1', 'bg-brand' => $i < $cur, 'bg-line' => $i >= $cur, 'invisible' => $loop->last])></span></div>
                    <span @class(['text-sm font-medium', 'text-ink' => $i <= $cur, 'text-ink-muted' => $i > $cur])>{{ $label }}</span>
                    <span class="mono text-2xs text-ink-muted">{{ isset($sr->step_dates[$steps[$i]]) ? \Carbon\Carbon::parse($sr->step_dates[$steps[$i]])->format('d M') : '—' }}</span>
                </div>
            @endforeach
        </div>
        @if (in_array($sr->status, ['rejected', 'cancelled']))<div class="rounded-md bg-bad-bg px-3 py-2.5 text-bad">This supplier return was {{ $sr->status }}{{ isset($sr->step_dates['rejected']) ? ' on '.\Carbon\Carbon::parse($sr->step_dates['rejected'])->format('d M') : '' }}.</div>@endif
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
            <x-ops.card title="Items" flush>
                <x-ops.table min-width="680px"><x-slot:head><th class="th">Product · reason</th><th class="th">Batch</th><th class="th">PO</th><th class="th">Source</th><th class="th text-right">Qty</th><th class="th text-right">Value</th></x-slot:head>
                    @foreach ($sr->items as $i)<tr><td class="td">{{ $i->batch->variant->label }}<div class="text-xs text-ink-muted">{{ $i->reason }}</div></td><td class="td mono font-semibold"><a href="{{ route('ops.trace', ['q' => $i->batch->code]) }}" wire:navigate>{{ $i->batch->code }}</a></td><td class="td mono text-xs">{{ $i->batch->purchaseOrder?->number ?? '—' }}</td><td class="td mono text-xs">{{ $i->source_ref }}</td><td class="td text-right">{{ $i->qty }}</td><td class="td text-right font-semibold">{{ tk($i->qty * $i->unit_cost) }}</td></tr>@endforeach
                    <tr class="bg-zebra font-semibold"><td class="td" colspan="4">Total</td><td class="td text-right">{{ $sr->items->sum('qty') }}</td><td class="td text-right">{{ tk($sr->value) }}</td></tr>
                </x-ops.table>
            </x-ops.card>
            <div class="flex flex-col gap-3">
                <x-ops.card title="Resolution">
                    <div class="flex overflow-hidden rounded-md border border-line-2">@foreach (config('ops.supplier_return_resolutions') as $k => $l)<button wire:click="setResolution('{{ $k }}')" @disabled(! $sr->is_open) @class(['flex-1 border-r border-line py-1.5 last:border-0', 'bg-ink text-white' => $sr->resolution === $k])>{{ $l }}</button>@endforeach</div>
                    <p class="mt-2 text-xs text-ink-muted">{{ ['replacement' => 'Replacement adds new stock into the original batch.', 'credit' => 'Credit lowers what you owe this supplier.', 'refund' => 'Refund posts money in to the account below.'][$sr->resolution] }}</p>
                    <div class="mt-2 grid gap-2">
                        @if ($sr->resolution === 'refund')<x-ops.field label="Refund account"><select wire:model="d.refund_account" class="input">@foreach (config('ops.accounts') as $a)<option>{{ $a }}</option>@endforeach</select></x-ops.field>@endif
                        <x-ops.field label="Approved qty" :hint="'of '.$sr->items->sum('qty').' · blank = all'" :error="$errors->first('d.approved_qty')"><input type="number" min="0" wire:model="d.approved_qty" class="input"></x-ops.field>
                    </div>
                </x-ops.card>
                <x-ops.card title="Shipping & note"><div class="grid gap-2">
                    <x-ops.field label="Ship via"><input wire:model="d.ship_via" class="input" placeholder="Own van / Sundarban courier…"></x-ops.field>
                    <x-ops.field label="Tracking"><input wire:model="d.tracking" class="input mono"></x-ops.field>
                    <x-ops.field label="Note"><textarea wire:model="d.note" rows="2" class="input"></textarea></x-ops.field>
                    <button wire:click="saveDetails" class="btn-secondary btn-sm self-end">Save</button>
                </div></x-ops.card>
                @if ($sr->is_open)
                    <div class="flex flex-col gap-2">
                        @php($nextKey = $steps[array_search($sr->status, $steps) + 1] ?? null)
                        @if ($nextKey)<button wire:click="advance" class="btn-primary">Mark {{ strtolower(config('ops.supplier_return_status.'.$nextKey.'.0')) }}</button>@endif
                        @if ($sr->status !== 'pending')<button wire:click="reject" wire:confirm="Supplier rejected {{ $sr->code }}?" class="btn-danger">Supplier rejected</button>@endif
                        @if (in_array($sr->status, ['pending', 'sent']))<button wire:click="cancel" wire:confirm="Cancel {{ $sr->code }}? Items go back to the damaged pool." class="btn-ghost">Cancel return</button>@endif
                    </div>
                @endif
            </div>
        </div>
    @else
        @php($s = $this->stats)
        <x-ops.kpis><x-ops.kpi label="Open returns" :value="$s['open']" tone="info" /><x-ops.kpi label="Units out" :value="$s['units']" hint="at supplier" /><x-ops.kpi label="Value at supplier" :value="tk($s['value'])" tone="warn" /><x-ops.kpi label="Recovered" :value="tk($s['recovered'])" tone="ok" /><x-ops.kpi label="Awaiting supplier" :value="$s['awaiting']" /><x-ops.kpi label="Rejected" :value="$s['rejected']" tone="bad" /></x-ops.kpis>
        <div class="-mt-1 flex gap-1 border-b border-line-2">@foreach (['returns' => 'Supplier returns', 'pool' => 'Damaged stock to return'] as $k => $l)<button wire:click="$set('view','{{ $k }}')" @class(['mtab', 'mtab-on' => $view === $k])>{{ $l }}@if ($k === 'pool') <span class="ml-1 rounded-full bg-soft px-1.5 text-xs text-ink-3">{{ $this->pool->flatten()->count() }}</span>@endif</button>@endforeach</div>
        @if ($view === 'returns')
            @php($c = $this->counts)
            <section class="card">
                <div class="border-b border-soft px-3 py-2"><x-ops.chips wire:model.live="status" :options="['all' => ['All', $c['all']], 'open' => ['Open', $c['open']]] + collect(config('ops.supplier_return_status'))->mapWithKeys(fn ($v, $k) => [$k => [$v[0], $c[$k] ?? 0]])->all()" /></div>
                <x-ops.toolbar><x-ops.search placeholder="Search SR or supplier…" /><select class="input w-auto" wire:model.live="filters.supplier"><option value="">All suppliers</option>@foreach ($suppliers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-ops.toolbar>
                <x-ops.table min-width="860px"><x-slot:head><th class="th">SR</th><th class="th">Supplier</th><th class="th">Items</th><th class="th text-right">Value</th><th class="th">Status</th><th class="th">Resolution</th><th class="th text-right">Age</th></x-slot:head>
                    @forelse ($this->returns as $r)<tr class="tr-hover cursor-pointer" wire:click="$set('open', {{ $r->id }})" wire:key="sr{{ $r->id }}"><td class="td mono font-semibold text-brand">{{ $r->code }}</td><td class="td">{{ $r->supplier->name }}</td><td class="td max-w-[320px] truncate text-sm">{{ $r->items->map(fn ($i) => $i->batch->variant->label.' ×'.$i->qty)->join(', ') }}</td><td class="td text-right font-semibold">{{ tk($r->value) }}</td><td class="td"><x-ops.badge map="supplier_return_status" :status="$r->status" /></td><td class="td text-sm capitalize">{{ $r->resolution }}</td><td @class(['td text-right text-sm', 'text-bad' => $r->is_open && $r->created_at->diffInDays() > 14])>{{ (int) $r->created_at->diffInDays() }} d</td></tr>
                    @empty <x-ops.empty colspan="7" /> @endforelse
                </x-ops.table>
                <x-ops.pagination :paginator="$this->returns" />
            </section>
        @else
            <p class="text-sm text-ink-muted">Damaged items from customer returns (R…) are grouped by the supplier of their batch. Items damaged on arrival (QC-…) become supplier-return drafts automatically when stock is received.</p>
            @forelse ($this->pool as $supplierId => $items)
                <x-ops.card :title="$items->first()->batch->supplier->name" :subtitle="$items->sum('qty').' units · '.tk($items->sum(fn ($i) => $i->qty * $i->batch->unit_cost))" flush>
                    <x-slot:actions><button wire:click="createFor({{ $supplierId }})" class="btn-primary btn-sm">Create return</button></x-slot:actions>
                    @foreach ($items as $i)<label class="flex items-center gap-3 border-t border-soft px-3.5 py-2 first:border-0"><input type="checkbox" value="{{ $i->id }}" wire:model="pick" class="h-4 w-4 accent-brand"><span class="flex-1">{{ $i->batch->variant->label }} <span class="text-xs text-ink-muted">· {{ $i->reason }} · {{ config('ops.return_conditions.'.$i->condition.'.0') }}</span></span><span class="mono font-semibold">{{ $i->batch->code }}</span><span class="mono text-xs text-ink-muted">{{ $i->batch->purchaseOrder?->number }}</span><span class="mono text-xs text-ink-muted">{{ $i->customerReturn->code }}</span><span class="text-sm">×{{ $i->qty }}</span></label>@endforeach
                </x-ops.card>
            @empty <div class="card p-6 text-center text-ink-muted">No damaged stock waiting.</div> @endforelse
        @endif
    @endif
</div>
