<!DOCTYPE html><html><head><meta charset="utf-8"><title>{{ $po->number }}</title>@vite('resources/css/ops.css')<style>@page{size:A4;margin:14mm}</style></head>
<body class="bg-white" onload="window.print()">
@php($co = ops_setting('company'))
<div class="mx-auto max-w-[760px] p-4 text-base">
    <div class="flex justify-between"><div><div class="text-2xl font-bold">{{ $co['name'] ?? 'YoungStar Life' }}</div><div class="text-sm text-ink-muted">{{ $co['address'] ?? '' }} · {{ $co['phone'] ?? '' }}</div></div><div class="text-right"><div class="text-xl font-semibold">Purchase order</div><div class="mono text-lg">{{ $po->number }}</div><div class="text-sm">{{ $po->ordered_at->format('d M Y') }}</div></div></div>
    <div class="mt-5 grid grid-cols-2 gap-4 text-sm"><div><div class="text-xs text-ink-muted">Supplier</div><b>{{ $po->supplier->name }}</b><br>{{ $po->supplier->contact_name }} · {{ $po->supplier->phone }}<br>{{ $po->supplier->address }}</div><div><div class="text-xs text-ink-muted">Deliver to</div><b>{{ $po->warehouse?->name }}</b><br>Expected {{ $po->expected_at?->format('d M Y') ?? '—' }}</div></div>
    <table class="mt-5 w-full text-sm"><tr><th class="th">Product</th><th class="th">Supplier code / name</th><th class="th text-right">Qty</th><th class="th text-right">Unit cost</th><th class="th text-right">Total</th></tr>
        @foreach ($po->items as $i)@php($link = $i->variant->product->suppliers->first()?->pivot)<tr class="border-b border-line"><td class="py-1.5">{{ $i->variant->label }}<div class="mono text-xs text-ink-muted">{{ $i->variant->sku }}</div></td><td class="mono text-xs">{{ $link?->supplier_code }} {{ $link?->supplier_name }}</td><td class="text-right">{{ $i->qty }}</td><td class="text-right">{{ tk($i->unit_cost) }}</td><td class="text-right">{{ tk($i->qty * $i->unit_cost) }}</td></tr>@endforeach
        <tr><td colspan="2" class="pt-2 font-semibold">Total</td><td class="pt-2 text-right font-semibold">{{ $po->items->sum('qty') }}</td><td></td><td class="pt-2 text-right font-bold">{{ tk($po->total) }}</td></tr></table>
    @if ($po->note)<p class="mt-4 text-sm"><b>Note:</b> {{ $po->note }}</p>@endif
</div>
</body></html>
