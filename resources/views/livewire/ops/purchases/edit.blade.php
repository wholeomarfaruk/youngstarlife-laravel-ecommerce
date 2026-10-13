<div class="flex flex-col gap-3" x-data="{ dirty: false }" @input="dirty = true" x-on:saved.window="dirty = false" x-init="window.opsDirty = false; $watch('dirty', v => window.opsDirty = v)">
    <a href="{{ route('ops.purchases.index') }}" wire:navigate class="self-start text-sm">← Back to purchase orders</a>
    <x-ops.page-header :title="$po->number" :subtitle="$po->supplier->name.' · ordered '.$po->ordered_at->format('d M Y').($po->closed_short ? ' · closed short' : '')">
        <x-ops.badge map="po_status" :status="$po->status" />
        @if ($step === 'edit' && $po->status !== 'cancelled')<button wire:click="save" class="btn-secondary">{{ $po->status === 'draft' ? 'Save draft' : 'Save' }}</button>@endif
        @if ($po->status === 'draft')<button wire:click="save(true)" class="btn-primary">Send to supplier</button>@endif
        @if (in_array($po->status, ['sent', 'partial']) && $step !== 'receive')<button wire:click="$set('step','receive')" class="btn-primary">Receive stock →</button>@endif
        @if ($po->batches->isNotEmpty() && $step !== 'labels')<button wire:click="$set('step','labels')" class="btn-secondary">Labels</button>@endif
        @if ($step !== 'edit')<button wire:click="$set('step','edit')" class="btn-secondary">PO details</button>@endif
        <x-ops.row-menu>
            <x-ops.menu-item wire:click="duplicate">Duplicate PO</x-ops.menu-item>
            <a href="{{ route('ops.purchases.print', $po) }}" target="_blank" class="rounded px-2 py-1.5 text-ink hover:bg-soft">Print / share PO</a>
            @if ($po->status === 'partial')<x-ops.menu-item wire:click="closeShort" wire:confirm="Close short? Remaining pcs will not be owed.">Close short</x-ops.menu-item>@endif
            @if (in_array($po->status, ['draft', 'sent']))<div class="my-1 border-t border-soft"></div><x-ops.menu-item danger wire:click="cancel" wire:confirm="Cancel {{ $po->number }}?">Cancel PO</x-ops.menu-item>@endif
        </x-ops.row-menu>
    </x-ops.page-header>

    {{-- stepper --}}
    @php($stage = ['draft' => 0, 'sent' => 1, 'partial' => 2, 'received' => $po->batches->whereNull('labels_printed_at')->isEmpty() ? 4 : 3, 'cancelled' => 0][$po->status] ?? 0)
    <div class="card flex flex-wrap items-center gap-1.5 px-3.5 py-2.5 text-sm">@foreach (['Create PO', 'Send to supplier', 'Receive & create batches', 'Print labels'] as $i => $s)<span class="flex items-center gap-1.5"><span @class(['grid h-5 w-5 place-items-center rounded-full text-xs', 'bg-brand text-white' => $i < $stage, 'bg-ink text-white' => $i === $stage, 'bg-soft text-ink-muted' => $i > $stage])>{{ $i < $stage ? '✓' : $i + 1 }}</span><span @class(['font-semibold' => $i === $stage])>{{ $s }}</span>@if (! $loop->last)<span class="mx-1 text-ink-faint">→</span>@endif</span>@endforeach</div>

    @if ($errors->any())<div class="rounded-md bg-bad-bg px-3 py-2.5 text-bad">{{ $errors->first() }}</div>@endif

    @if ($step === 'edit')
        @php($total = collect($items)->sum(fn ($i) => (int) $i['qty'] * (int) $i['unit_cost'])) @php($recv = $po->received_value)
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_300px]">
            <div class="flex min-w-0 flex-col gap-3">
                <x-ops.card><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ops.field label="Supplier" required :hint="$po->status !== 'draft' ? 'Locked after sending' : null"><select wire:model="supplier_id" class="input" @disabled($po->status !== 'draft')>@foreach ($suppliers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-ops.field>
                    <x-ops.field label="Order date" required><x-ops.date wire:model="ordered_at" class="w-full" :disabled="! $this->editable" /></x-ops.field>
                    <x-ops.field label="Expected delivery"><x-ops.date wire:model="expected_at" class="w-full" /></x-ops.field>
                    <x-ops.field label="Warehouse" required><select wire:model="warehouse_id" class="input" @disabled(! $this->editable)>@foreach ($warehouses as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-ops.field>
                    <x-ops.field label="Note" class="sm:col-span-2 lg:col-span-4"><input wire:model="note" class="input" placeholder="Delivery instructions, payment terms…"></x-ops.field>
                </div></x-ops.card>
                <x-ops.card :title="'Items · '.count($items)" subtitle="Unit cost pre-fills from the last batch. Received lines are locked." flush>
                    <x-slot:actions>@if ($this->editable)<button wire:click="addLowStock" class="btn-secondary btn-sm">Add low-stock items</button>@endif</x-slot:actions>
                    <x-ops.table min-width="720px">
                        <x-slot:head><th class="th">Product</th><th class="th text-right">Stock</th><th class="th">Qty</th><th class="th">Unit cost (৳)</th><th class="th text-right">Line total</th><th class="th text-right">Received</th><th class="th"></th></x-slot:head>
                        @forelse ($items as $k => $i)<tr wire:key="i{{ $k }}"><td class="td font-medium">{{ $i['label'] }}<div class="mono text-xs text-ink-muted">{{ $i['sku'] }}</div></td><td class="td text-right text-ink-muted">{{ $i['stock'] }}</td>
                            <td class="td"><input type="number" min="1" wire:model.live.debounce.300ms="items.{{ $k }}.qty" class="input w-24 py-1" @disabled($i['received'] || ! $this->editable)></td>
                            <td class="td"><input type="number" min="1" wire:model.live.debounce.300ms="items.{{ $k }}.unit_cost" class="input w-28 py-1" @disabled($i['received'] || ! $this->editable)></td>
                            <td class="td text-right font-semibold tabular-nums">{{ tk((int) $i['qty'] * (int) $i['unit_cost']) }}</td><td class="td text-right">{{ $i['received'] ?: '—' }}</td>
                            <td class="td">@if (! $i['received'] && $this->editable)<button wire:click="removeItem({{ $k }})" class="text-bad" aria-label="Remove">×</button>@endif</td></tr>
                        @empty <x-ops.empty colspan="7" title="No items yet" hint="Choose a variant below or add low-stock items." /> @endforelse
                    </x-ops.table>
                    @if ($this->editable)
                        <div class="flex flex-wrap gap-2 bg-zebra p-3"><select wire:model="pick" class="input flex-[1_1_260px]"><option value="">Choose a product variant…</option>@foreach ($this->variants as $v)<option value="{{ $v->id }}">{{ $v->label }} · {{ $v->sku }} · stock {{ $v->stock }}</option>@endforeach</select><input type="number" min="1" wire:model="pickQty" class="input w-24"><button wire:click="addItem" class="btn-dark">+ Add item</button></div>
                    @endif
                </x-ops.card>
                @if ($po->receipts->isNotEmpty())
                    <x-ops.card title="Receipts (GRN)" flush>
                        @foreach ($po->receipts as $g)<div class="flex flex-wrap items-center gap-3 border-t border-soft px-3.5 py-2 first:border-0"><span class="mono font-semibold">{{ $g->number }}</span><span class="text-sm text-ink-muted">{{ $g->created_at->format('d M H:i') }}</span><span class="flex-1 text-sm">{{ $g->batches->map(fn ($b) => $b->code.' ×'.$b->qty)->join(', ') }}</span></div>@endforeach
                    </x-ops.card>
                @endif
                @if ($po->batches->isNotEmpty())
                    <x-ops.card title="Batches from this PO" flush>
                        @foreach ($po->batches as $b)<a href="{{ route('ops.trace', ['q' => $b->code]) }}" wire:navigate class="flex items-center gap-3 border-t border-soft px-3.5 py-2 text-ink first:border-0 hover:bg-zebra"><span class="mono font-semibold">{{ $b->code }}</span><span class="flex-1">{{ $b->variant->label }}</span><span class="text-sm">{{ $b->remaining }} / {{ $b->qty }} left</span><x-ops.badge map="batch_status" :status="$b->status" /></a>@endforeach
                    </x-ops.card>
                @endif
            </div>
            <div class="flex flex-col gap-3">
                <x-ops.card title="Payment">
                    <x-ops.field label="Paid now (৳)" :error="$errors->first('paid') ?: $errors->first('paid_from')"><input type="number" min="0" wire:model="paid" class="input"></x-ops.field>
                    <x-ops.field label="From account" class="mt-2"><select wire:model="paid_from" class="input">@foreach (config('ops.accounts') as $a)<option>{{ $a }}</option>@endforeach</select></x-ops.field>
                    <p class="mt-2 text-xs text-ink-muted">Saving a change posts the difference to Accounts and the supplier's due.</p>
                </x-ops.card>
                <x-ops.card title="Totals"><dl class="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1.5 text-sm tabular-nums">
                    <dt class="text-ink-muted">Items</dt><dd>{{ collect($items)->sum(fn ($i) => (int) $i['qty']) }} pcs</dd>
                    <dt class="text-ink-muted">PO total</dt><dd class="font-semibold">{{ tk($total) }}</dd>
                    <dt class="text-ink-muted">Received value</dt><dd>{{ tk($recv) }}</dd>
                    <dt class="text-ink-muted">Paid</dt><dd>{{ tk($po->paid) }}</dd>
                    <dt class="border-t border-soft pt-1 font-semibold">Supplier due</dt><dd @class(['border-t border-soft pt-1 font-bold', 'text-warn' => $recv - $po->paid > 0])>{{ tk(max(0, $recv - $po->paid)) }}</dd>
                </dl></x-ops.card>
            </div>
        </div>
    @elseif ($step === 'receive')
        <x-ops.card title="Count what arrived" subtitle="Good = arrived − damaged. One new batch per variant. Damaged units go to a supplier-return draft." flush>
            <x-slot:actions><button wire:click="receiveAll" class="btn-secondary btn-sm">Receive all remaining</button></x-slot:actions>
            @php($next = (int) preg_replace('/\D/', '', \App\Services\Ops\ReceivingService::nextBatchCode()))
            <x-ops.table min-width="860px">
                <x-slot:head><th class="th">Product</th><th class="th text-right">Ordered</th><th class="th text-right">Before</th><th class="th">Arrived now</th><th class="th">Damaged</th><th class="th text-right">Good</th><th class="th">New batch</th><th class="th">Label</th></x-slot:head>
                @php($n = 0)
                @foreach ($po->items as $i)
                    @php($now = (int) ($receive[$i->id]['now'] ?? 0)) @php($dmg = (int) ($receive[$i->id]['damaged'] ?? 0))
                    <tr @class(['opacity-50' => $i->remaining === 0])><td class="td font-medium">{{ $i->variant->label }}<div class="text-xs text-ink-muted">{{ $i->remaining }} pcs still to arrive</div></td><td class="td text-right">{{ $i->qty }}</td><td class="td text-right text-ink-muted">{{ $i->received }}</td>
                        <td class="td"><input type="number" min="0" max="{{ $i->remaining }}" wire:model.live.debounce.300ms="receive.{{ $i->id }}.now" class="input w-24 py-1 @if ($now > $i->remaining) input-error @endif" @disabled($i->remaining === 0)></td>
                        <td class="td"><input type="number" min="0" wire:model.live.debounce.300ms="receive.{{ $i->id }}.damaged" class="input w-24 py-1" @disabled($i->remaining === 0)></td>
                        <td class="td text-right font-semibold {{ $dmg > $now ? 'text-bad' : '' }}">{{ max(0, $now - $dmg) }}</td>
                        <td class="td mono text-sm">@if ($now > 0){{ preg_replace('/\{#+\}/', str_pad((string) ($next + $n++), 3, '0', STR_PAD_LEFT), ops_setting('batch.format', 'B{###}')) }}@else — @endif</td>
                        <td class="td"><input type="checkbox" wire:model="receive.{{ $i->id }}.label" class="h-4 w-4 accent-brand" title="Print labels for this batch"></td></tr>
                @endforeach
            </x-ops.table>
            <div class="flex flex-wrap items-center justify-end gap-2 p-3"><span class="mr-auto text-sm text-ink-muted">Cannot exceed what was ordered. Supplier due increases only by good units × cost.</span><button wire:click="$set('step','edit')" class="btn-secondary">Back</button><button wire:click="confirmReceive" wire:loading.attr="disabled" class="btn-primary">Confirm receive & create batches</button></div>
        </x-ops.card>
    @else
        @php($printQty = array_filter(array_map('intval', $labelQty)))
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_260px]">
            <x-ops.card title="Print labels" subtitle="One sticker per piece. Barcode = SKU-BATCH, so packing and return scans find the exact batch." flush>
                @foreach ($po->batches as $b)<div class="flex flex-wrap items-center gap-2.5 border-t border-soft px-3.5 py-2.5 first:border-0"><span class="mono font-semibold">{{ $b->code }}</span><span class="flex-1">{{ $b->variant->label }} <span class="mono text-xs text-ink-muted">{{ $b->label_code }}</span></span><input type="number" min="0" wire:model.live.debounce.300ms="labelQty.{{ $b->id }}" class="input w-20 py-1"><x-ops.badge :tone="$b->labels_printed_at ? 'ok' : 'warn'">{{ $b->labels_printed_at ? 'Printed' : 'Not printed' }}</x-ops.badge></div>@endforeach
                <div class="flex flex-wrap items-end justify-end gap-2 border-t border-soft p-3">
                    <x-ops.field label="Size"><select wire:model.live="labelSize" class="input w-auto py-1"><option value="50x30">50 × 30 mm</option><option value="40x25">40 × 25 mm</option><option value="38x25x2">38 × 25 mm × 2</option></select></x-ops.field>
                    <label class="flex items-center gap-2 pb-2"><input type="checkbox" wire:model.live="labelPrice" class="accent-brand"> Price on label</label>
                    <button wire:click="skipLabels" class="btn-secondary">Skip for now</button>
                    <a href="{{ route('ops.labels.print', ['po' => $po->id, 'q' => $printQty, 'size' => $labelSize, 'price' => (int) $labelPrice]) }}" target="_blank" wire:click="markPrinted" @class(['btn-primary', 'pointer-events-none opacity-50' => ! $printQty])>Print {{ array_sum($printQty) }} labels</a>
                </div>
            </x-ops.card>
            @if ($b = $po->batches->first())
                <x-ops.card title="Sticker preview">
                    <div class="mx-auto flex aspect-[5/3] w-full max-w-[220px] flex-col justify-between rounded border border-line-2 bg-white p-2">
                        <div class="flex justify-between text-2xs font-bold"><span>{{ mb_strtoupper(site()->storeName()) }}</span>@if ($labelPrice)<span>{{ tk($b->variant->effective_price) }}</span>@endif</div>
                        <div class="truncate text-xs font-semibold">{{ $b->variant->product->name }} · {{ $b->variant->color }} {{ $b->variant->size }}</div>
                        <div class="h-8 bg-[repeating-linear-gradient(90deg,#1A1A17_0_1px,transparent_1px_3px,#1A1A17_3px_5px,transparent_5px_6px)]"></div>
                        <div class="flex justify-between"><span class="mono text-2xs">{{ $b->label_code }}</span><span class="mono text-2xs font-bold">{{ $b->code }}</span></div>
                    </div>
                </x-ops.card>
            @endif
        </div>
    @endif
</div>
