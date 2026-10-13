<div class="flex flex-col gap-3">
    <a href="{{ route('admin.purchases.index') }}" wire:navigate class="self-start text-sm">← All purchase orders</a>
    <x-admin.page-header :title="$po->number" :subtitle="$po->supplier->name">
        <x-admin.badge map="po_status" :status="$po->status" />
        @if (in_array($po->status, ['draft', 'sent']))<button wire:click="save" class="btn-secondary">Save</button>@endif
        @if ($po->status === 'draft')<button wire:click="save(true)" class="btn-primary">Send to supplier</button>@endif
        @if (in_array($po->status, ['sent', 'partial']))<button wire:click="$set('step','receive')" class="btn-primary">Receive stock →</button>@endif
        @if ($po->status === 'partial')<button wire:click="closeShort" wire:confirm="Close short? Remaining pcs will not be owed." class="btn-danger">Close short</button>@endif
        @if ($po->batches->isNotEmpty())<button wire:click="$set('step','labels')" class="btn-secondary">Labels</button>@endif
    </x-admin.page-header>

    @if ($step === 'edit')
        <x-admin.card><div class="grid gap-3 sm:grid-cols-3">
            <x-admin.field label="Supplier" required><select wire:model="supplier_id" class="input" @disabled($po->status !== 'draft')>@foreach ($suppliers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-admin.field>
            <x-admin.field label="Expected delivery"><input type="date" wire:model="expected_at" class="input"></x-admin.field>
            <x-admin.field label="Paid now (৳)"><input type="number" wire:model="paid" class="input"></x-admin.field>
        </div></x-admin.card>
        <x-admin.card :title="'Items · '.count($items)" subtitle="Unit cost pre-fills from the last batch" flush>
            <x-admin.table min-width="640px">
                <x-slot:head><th class="th">Product</th><th class="th">Qty</th><th class="th">Unit cost (৳)</th><th class="th text-right">Line total</th><th class="th text-right">Received</th><th class="th"></th></x-slot:head>
                @foreach ($items as $k => $i)<tr wire:key="i{{ $k }}"><td class="td font-medium">{{ $i['label'] }}</td>
                    <td class="td"><input type="number" wire:model.live.debounce.300ms="items.{{ $k }}.qty" class="input w-24 py-1" @disabled($i['received'])></td>
                    <td class="td"><input type="number" wire:model.live.debounce.300ms="items.{{ $k }}.unit_cost" class="input w-28 py-1" @disabled($i['received'])></td>
                    <td class="td text-right font-semibold tabular-nums">{{ tk((int) $i['qty'] * (int) $i['unit_cost']) }}</td><td class="td text-right">{{ $i['received'] ?: '—' }}</td>
                    <td class="td">@unless ($i['received'])<button wire:click="removeItem({{ $k }})" class="text-bad">×</button>@endunless</td></tr>@endforeach
            </x-admin.table>
            @if (in_array($po->status, ['draft', 'sent']))
                <div class="flex flex-wrap gap-2 bg-zebra p-3"><select wire:model="pick" class="input flex-[1_1_260px]"><option value="">Choose a product variant…</option>@foreach ($this->variants as $v)<option value="{{ $v->id }}">{{ $v->label }} · {{ $v->sku }} · stock {{ $v->stock }}</option>@endforeach</select><input type="number" wire:model="pickQty" class="input w-24"><button wire:click="addItem" class="btn-dark">+ Add item</button></div>
            @endif
        </x-admin.card>
    @elseif ($step === 'receive')
        <x-admin.card title="Count what arrived" flush>
            <x-admin.table min-width="700px">
                <x-slot:head><th class="th">Product</th><th class="th text-right">Ordered</th><th class="th text-right">Before</th><th class="th">Arrived now</th><th class="th">Damaged</th></x-slot:head>
                @foreach ($po->items as $i)<tr><td class="td font-medium">{{ $i->variant->label }}<div class="text-xs text-ink-muted">{{ $i->remaining }} pcs still to arrive</div></td><td class="td text-right">{{ $i->qty }}</td><td class="td text-right text-ink-muted">{{ $i->received }}</td>
                    <td class="td"><input type="number" min="0" max="{{ $i->remaining }}" wire:model="receive.{{ $i->id }}.now" class="input w-24 py-1"></td><td class="td"><input type="number" min="0" wire:model="receive.{{ $i->id }}.damaged" class="input w-24 py-1"></td></tr>@endforeach
            </x-admin.table>
            <div class="flex justify-end gap-2 p-3"><button wire:click="$set('step','edit')" class="btn-secondary">Back</button><button wire:click="confirmReceive" class="btn-primary">Confirm receive & create batches</button></div>
        </x-admin.card>
    @else
        <x-admin.card title="Print labels" subtitle="One sticker per piece. Barcode = SKU + batch, so packing and return scans find the exact batch." flush>
            @foreach ($po->batches as $b)<div class="flex flex-wrap items-center gap-2.5 border-t border-soft px-3.5 py-2.5 first:border-0"><span class="mono font-semibold">{{ $b->code }}</span><span class="flex-1">{{ $b->variant->label }} <span class="mono text-xs text-ink-muted">{{ $b->label_code }}</span></span><input type="number" wire:model="labelQty.{{ $b->id }}" class="input w-20 py-1"><x-admin.badge :tone="$b->labels_printed_at ? 'ok' : 'warn'">{{ $b->labels_printed_at ? 'Printed' : 'Not printed' }}</x-admin.badge></div>@endforeach
            <div class="flex justify-end gap-2 p-3"><a href="{{ route('admin.labels.print', ['po' => $po->id]) }}" target="_blank" wire:click="markPrinted" class="btn-primary">Print {{ array_sum($labelQty) }} labels</a></div>
        </x-admin.card>
    @endif
</div>
