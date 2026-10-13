<div class="flex flex-col gap-3">
    <section class="card flex flex-col gap-2.5 p-3.5" x-data="scanner('scan')">
        <div class="flex flex-wrap items-baseline justify-between gap-2"><span class="text-md font-semibold">Scan order, then each product</span><span class="text-xs text-ink-muted">USB / Bluetooth scanners type the code and press Enter</span></div>
        <form @submit.prevent="submit" class="flex flex-wrap gap-2">
            <div class="flex min-w-0 flex-[1_1_260px] items-center gap-2.5 rounded-md border-2 border-ink bg-white px-3">
                <span class="mono rounded-sm border border-line px-1 text-xs text-ink-muted">SCAN</span>
                <input x-ref="input" x-model="code" class="mono min-w-0 flex-1 border-0 bg-transparent py-3 text-lg outline-none" placeholder="#1042 or product barcode" autocomplete="off">
            </div>
            <button class="btn-dark min-h-[44px] px-4">Enter</button>
        </form>
        @if ($flash)
            <div @class(['rounded-md px-3 py-2.5', 'bg-ok-bg text-ok' => $flash['tone'] === 'ok', 'bg-bad-bg text-bad' => $flash['tone'] === 'bad', 'bg-warn-bg text-warn' => $flash['tone'] === 'warn', 'bg-info-bg text-info' => $flash['tone'] === 'info'])>
                <div class="font-semibold">{{ $flash['title'] }}</div><div class="text-sm">{{ $flash['sub'] }}</div>
            </div>
        @endif
    </section>

    @if ($o = $this->current)
        <x-admin.card :title="'Order '.$o->number.' · '.$this->progress()" flush>
            @foreach ($o->items as $i)
                @php($done = ($scanned[$i->variant_id] ?? 0) >= $i->qty)
                <div @class(['flex items-center gap-3 border-t border-soft px-3.5 py-2.5 first:border-0', 'bg-brand-tint' => $done])>
                    <span @class(['grid h-5 w-5 place-items-center rounded text-xs text-white', 'bg-brand' => $done, 'border border-ink-faint bg-white' => ! $done])>{{ $done ? '✓' : '' }}</span>
                    <div class="min-w-0 flex-1"><div class="font-medium">{{ $i->variant->label }}</div><div class="mono text-xs text-ink-muted">{{ $i->variant->barcode }} · batch {{ $i->allocations->pluck('batch.code')->join(', ') ?: '—' }}</div></div>
                    <span class="font-semibold tabular-nums">{{ $scanned[$i->variant_id] ?? 0 }} / {{ $i->qty }}</span>
                </div>
            @endforeach
            <div class="flex justify-end border-t border-soft p-3"><button wire:click="complete" @disabled(array_sum($scanned) !== $o->items->sum('qty')) class="btn-primary">Mark packed</button></div>
        </x-admin.card>
    @endif
</div>
