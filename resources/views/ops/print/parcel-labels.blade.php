<!DOCTYPE html><html><head><meta charset="utf-8"><title>Parcel labels</title>@vite('resources/css/ops.css')
<style>@page{size:100mm 75mm;margin:0}.lbl{width:100mm;height:75mm;padding:4mm;break-after:page;display:flex;flex-direction:column;gap:2mm}</style>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script></head>
<body onload="JsBarcode('.bc').init(); window.print()">
@foreach ($orders as $o)
    <div class="lbl border border-ink">
        <div class="flex justify-between"><span class="font-bold">{{ ops_setting('company.name', 'YoungStar Life') }}</span><span class="mono font-semibold">{{ $o->number }}</span></div>
        <div class="text-sm"><b>{{ $o->ship_name }}</b> · {{ $o->ship_phone }}<br>{{ $o->ship_address }}<br>{{ $o->zone?->name }}</div>
        <div class="flex items-center justify-between"><span class="text-lg font-bold">COD {{ tk($o->cod) }}</span><span class="text-sm">{{ $o->shipment?->courier->name ?? 'Courier —' }}</span></div>
        <svg class="bc" jsbarcode-value="{{ $o->shipment?->code ?? ltrim($o->number, '#') }}" jsbarcode-height="36" jsbarcode-width="1.6" jsbarcode-fontsize="11" jsbarcode-margin="0"></svg>
        <div class="text-xs text-ink-muted">{{ $o->items->map(fn ($i) => $i->variant->sku.' ×'.$i->qty)->join(', ') }}</div>
    </div>
@endforeach
</body></html>
