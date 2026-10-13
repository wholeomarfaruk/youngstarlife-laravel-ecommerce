<div class="flex flex-col gap-3">
    <section class="card flex flex-col gap-2.5 p-3.5" x-data="scanner('scan')">
        <div class="flex flex-wrap items-baseline justify-between gap-2"><span class="text-md font-semibold">Trace anything</span><span class="text-xs text-ink-muted">Order #5 · parcel P1005 · tracking · batch B002 · PO-102 · barcode / SKU · phone · R1005 · SR1002</span></div>
        <form @submit.prevent="submit" class="flex flex-wrap gap-2">
            <div class="flex min-w-0 flex-[1_1_260px] items-center gap-2.5 rounded-md border-2 border-ink bg-white px-3">
                <span class="mono rounded-sm border border-line px-1 text-xs text-ink-muted">SCAN</span>
                <input x-ref="input" x-model="code" x-init="code = @js($q)" class="mono min-w-0 flex-1 border-0 bg-transparent py-3 text-lg outline-none" placeholder="Scan or type any ID" autocomplete="off">
            </div>
            <button class="btn-dark min-h-[44px] px-4">Enter</button>
        </form>
    </section>

    @if ($q !== '')
        @php($r = $this->result)
        <x-ops.page-header :title="$r['anchor'] ?? 'Nothing traceable for “'.$q.'”'" :subtitle="$r['anchor'] ? count($r['chains']).' chain(s) · Supplier → PO → Batch → Product → Order → Parcel → Courier → Outcome → Return → Supplier return → Resolution' : 'Try an order no., parcel, batch, PO, barcode, phone, return or supplier-return code.'" />
        @foreach ($r['chains'] as $chain)
            <section class="card overflow-x-auto p-3">
                <div class="flex min-w-max items-stretch gap-1.5">
                    @foreach ($chain as $node)
                        @php($cls = ['ok' => 'border-ok bg-ok-bg', 'bad' => 'border-bad bg-bad-bg', 'warn' => 'border-warn-bar bg-warn-bg', 'info' => 'border-info bg-info-bg', 'mute' => 'border-line bg-zebra text-ink-muted', 'ink' => 'border-line bg-white'][$node['tone']] ?? 'border-line bg-white')
                        <{{ $node['url'] ? 'a' : 'div' }} @if ($node['url']) href="{{ $node['url'] }}" wire:navigate @endif class="flex w-[150px] flex-none flex-col gap-0.5 rounded-md border px-2.5 py-2 text-ink hover:text-ink {{ $cls }}">
                            <span class="text-2xs uppercase tracking-wide text-ink-muted">{{ $node['step'] }}</span>
                            <span class="mono truncate font-semibold">{{ $node['label'] }}</span>
                            @if ($node['sub'])<span class="truncate text-xs text-ink-muted">{{ $node['sub'] }}</span>@endif
                        </{{ $node['url'] ? 'a' : 'div' }}>
                        @if (! $loop->last)<span class="grid place-items-center text-ink-faint">→</span>@endif
                    @endforeach
                </div>
            </section>
        @endforeach
        @if (! $r['anchor'] && $this->matches)
            <x-ops.card title="Did you mean" flush>
                @foreach ($this->matches as $m)<a href="{{ $m['url'] }}" wire:navigate class="flex items-center gap-2.5 border-t border-soft px-3.5 py-2 text-ink first:border-0 hover:bg-zebra"><span class="w-24 text-xs text-ink-muted">{{ $m['type'] }}</span><span class="font-medium">{{ $m['label'] }}</span><span class="mono ml-auto text-xs text-ink-muted">{{ $m['sub'] }}</span></a>@endforeach
            </x-ops.card>
        @endif
    @endif
</div>
