<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoices</title>@vite('resources/css/app.css')<style>@page{size:A5;margin:10mm}.inv{break-after:page}</style></head>
<body class="bg-white" onload="window.print()">
@foreach ($orders as $o)
<div class="inv mx-auto max-w-[560px] p-4 text-base">
    <div class="flex justify-between"><div><div class="text-xl font-bold">{{ config('app.name') }}</div><div class="text-sm text-ink-muted">{{ \App\Models\Setting::get('store')['phone'] ?? '' }}</div></div><div class="text-right"><div class="mono text-lg font-semibold">{{ $o->number }}</div><div class="text-sm">{{ $o->placed_at->format('d M Y') }}</div></div></div>
    <div class="mt-4 text-sm"><b>{{ $o->ship_name }}</b> · {{ $o->ship_phone }}<br>{{ $o->ship_address }}</div>
    <table class="mt-4 w-full text-sm">@foreach ($o->items as $i)<tr class="border-b border-line"><td class="py-1.5">{{ $i->variant->label }}</td><td class="text-right">{{ $i->qty }} × {{ tk($i->unit_price) }}</td></tr>@endforeach</table>
    <div class="mt-3 text-right text-sm">Delivery {{ tk($o->delivery_charge) }} · Advance {{ tk($o->advance) }}<div class="text-lg font-bold">COD {{ tk($o->cod) }}</div></div>
</div>
@endforeach
</body></html>
