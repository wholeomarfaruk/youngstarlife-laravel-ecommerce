<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoices</title>@vite('resources/css/ops.css')<style>@page{size:A5;margin:10mm}.inv{break-after:page}</style></head>
<body class="bg-white" onload="window.print()">
@php($co = ops_setting('company'))
@foreach ($orders as $o)
<div class="inv mx-auto max-w-[560px] p-4 text-base">
    <div class="flex justify-between"><div><div class="text-xl font-bold">{{ $co['name'] ?? config('app.name') }}</div><div class="text-sm text-ink-muted">{{ $co['phone'] ?? '' }} · {{ $co['address'] ?? '' }}</div></div><div class="text-right"><div class="mono text-lg font-semibold">{{ ($co['invoice_prefix'] ?? 'INV-').ltrim($o->number, '#') }}</div><div class="mono text-sm">{{ $o->number }} · {{ $o->placed_at->format('d M Y') }}</div></div></div>
    <div class="mt-4 text-sm"><b>{{ $o->ship_name }}</b> · {{ $o->ship_phone }}<br>{{ $o->ship_address }}@if ($o->zone)<br><span class="text-ink-muted">{{ $o->zone->name }}</span>@endif</div>
    <table class="mt-4 w-full text-sm"><tr><th class="th">Item</th><th class="th text-right">Qty</th><th class="th text-right">Price</th><th class="th text-right">Total</th></tr>@foreach ($o->items as $i)<tr class="border-b border-line"><td class="py-1.5">{{ $i->variant->label }}@if ($i->combo_text)<div style="font-size:11px">{{ $i->combo_text }}</div>@endif<div class="mono text-xs text-ink-muted">{{ $i->variant->sku }}</div></td><td class="text-right">{{ $i->qty }}</td><td class="text-right">{{ tk($i->unit_price) }}</td><td class="text-right">{{ tk($i->qty * $i->unit_price) }}</td></tr>@endforeach</table>
    <div class="ml-auto mt-3 grid w-64 grid-cols-[1fr_auto] gap-x-4 text-sm tabular-nums"><span>Subtotal</span><span class="text-right">{{ tk($o->subtotal) }}</span>@if ($o->discount)<span>Discount</span><span class="text-right">− {{ tk($o->discount) }}</span>@endif<span>Delivery</span><span class="text-right">{{ tk($o->delivery_charge) }}</span><span class="font-semibold">Total</span><span class="text-right font-semibold">{{ tk($o->total) }}</span>@if ($o->advance)<span>Advance paid</span><span class="text-right">− {{ tk($o->advance) }}</span>@endif<span class="text-lg font-bold">COD</span><span class="text-right text-lg font-bold">{{ tk($o->cod) }}</span></div>
    @if ($o->shipment)<div class="mt-3 text-xs text-ink-muted">Parcel <span class="mono">{{ $o->shipment->code }}</span> · {{ $o->shipment->courier->name }} {{ $o->shipment->tracking }}</div>@endif
</div>
@endforeach
</body></html>
