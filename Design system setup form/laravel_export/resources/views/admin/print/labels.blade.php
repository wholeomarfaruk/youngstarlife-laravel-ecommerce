<!DOCTYPE html><html><head><meta charset="utf-8"><title>Labels</title>@vite('resources/css/app.css')
<style>@page{size:50mm 30mm;margin:0}.lbl{width:50mm;height:30mm;padding:2mm 3mm;break-after:page;display:flex;flex-direction:column;justify-content:space-between}</style>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script></head>
<body onload="JsBarcode('.bc').init(); window.print()">
@foreach ($batches as $b)
    @for ($n = 0; $n < $b->qty; $n++)
        <div class="lbl"><div class="flex justify-between text-2xs font-bold"><span>{{ strtoupper(config('app.name')) }}</span><span>{{ tk($b->variant->effective_price) }}</span></div>
            <div class="truncate text-xs font-semibold">{{ $b->variant->product->name }} · {{ $b->variant->color }} {{ $b->variant->size }}</div>
            <svg class="bc" jsbarcode-value="{{ $b->label_code }}" jsbarcode-height="28" jsbarcode-width="1.2" jsbarcode-fontsize="9" jsbarcode-margin="0"></svg></div>
    @endfor
@endforeach
</body></html>
