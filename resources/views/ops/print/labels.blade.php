@php([$w, $h] = ['50x30' => [50, 30], '40x25' => [40, 25], '38x25x2' => [38, 25]][$size] ?? [50, 30])
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Labels</title>@vite('resources/css/ops.css')
<style>@page{size:{{ $size === '38x25x2' ? ($w * 2 + 2).'mm' : $w.'mm' }} {{ $h }}mm;margin:0}.sheet{display:flex;flex-wrap:wrap;width:{{ $size === '38x25x2' ? ($w * 2 + 2).'mm' : $w.'mm' }}}.lbl{width:{{ $w }}mm;height:{{ $h }}mm;padding:1.5mm 2.5mm;display:flex;flex-direction:column;justify-content:space-between;overflow:hidden}{{ $size === '38x25x2' ? '.lbl:nth-child(2n){margin-left:2mm}.lbl:nth-child(2n){break-after:page}' : '.lbl{break-after:page}' }}</style>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script></head>
<body onload="JsBarcode('.bc').init(); window.print()">
@if ($batches->isEmpty())<p style="padding:20px;font-family:sans-serif">No labels to print.</p>@endif
<div class="sheet">
@foreach ($batches as $b)
    @for ($n = 0; $n < $b->copies; $n++)
        <div class="lbl"><div class="flex justify-between text-2xs font-bold"><span>{{ strtoupper(ops_setting('branding.store_name', 'YoungStar Life')) }}</span>@if ($price)<span>{{ tk($b->variant->effective_price) }}</span>@endif</div>
            <div class="truncate text-xs font-semibold">{{ $b->variant->product->name }} · {{ $b->variant->color }} {{ $b->variant->size }}</div>
            @if (ops_setting('barcode.batch_label', 'code128') === 'qr')
                <div class="flex items-center gap-1"><img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={{ urlencode(route('ops.trace', ['q' => $b->label_code])) }}" style="height:{{ $h - 14 }}mm" alt=""><span class="mono text-2xs">{{ $b->label_code }}</span></div>
            @else
                <svg class="bc" jsbarcode-value="{{ $b->label_code }}" jsbarcode-height="{{ $h > 26 ? 28 : 20 }}" jsbarcode-width="1.1" jsbarcode-fontsize="8" jsbarcode-margin="0"></svg>
            @endif
        </div>
    @endfor
@endforeach
</div>
</body></html>
