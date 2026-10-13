<?php

namespace App\Livewire\Ops;

use App\Services\Ops\Tracer;
use Livewire\Component;

/** Topbar "Scan or search anything". Exact barcode / ID match opens on Enter; otherwise top 8 grouped results, Enter → Trace page. */
class GlobalSearch extends Component
{
    public string $q = '';

    public function getResultsProperty(): array { return app(Tracer::class)->resolve($this->q); }

    public function go()
    {
        $q = trim($this->q); if ($q === '') return null;
        $first = $this->results[0] ?? null;
        $this->q = '';
        return $this->redirect($first && $first['exact'] ? $first['url'] : route('ops.trace', ['q' => $q]), navigate: true);
    }

    public function render()
    {
        return <<<'BLADE'
        <div class="relative order-5 w-full flex-none min-[600px]:order-none min-[600px]:w-[min(460px,42vw)]" x-data="{ open: false }" @click.outside="open=false">
            <form wire:submit="go" class="flex items-center gap-1.5 rounded border border-line-2 bg-white px-2 py-1.5 max-lg:min-h-[44px]">
                <span class="mono rounded-sm border border-line px-1 text-xs text-ink-muted">SCAN</span>
                <input id="global-search" wire:model.live.debounce.250ms="q" @focus="open=true" placeholder="Scan or search anything…  ( / )" class="min-w-0 flex-1 border-0 bg-transparent outline-none" autocomplete="off">
                <span class="mono hidden text-2xs text-ink-muted sm:inline">⌘K</span>
            </form>
            <div x-show="open && $wire.q.length > 0" x-cloak class="absolute inset-x-0 top-[calc(100%+6px)] z-50 overflow-hidden rounded-lg border border-line-2 bg-white shadow-search">
                @forelse (collect($this->results)->groupBy('type') as $type => $rows)
                    <div class="border-t border-soft px-3 pb-0.5 pt-1.5 text-2xs font-semibold uppercase tracking-wide text-ink-muted first:border-0">{{ $type }}</div>
                    @foreach ($rows as $r)
                        <a href="{{ $r['url'] }}" wire:navigate class="flex items-center gap-2.5 px-3 py-2 text-ink hover:bg-zebra"><span class="w-16 text-xs text-ink-muted">{{ $type }}</span><span class="truncate font-medium">{{ $r['label'] }}</span><span class="mono ml-auto truncate text-xs text-ink-muted">{{ $r['sub'] }}</span>@if ($r['exact'])<span class="mono text-2xs text-brand">↵</span>@endif</a>
                    @endforeach
                @empty <div class="px-3 py-3 text-ink-muted">No matches</div> @endforelse
                @if (strlen(trim($q)) > 0)<a href="{{ route('ops.trace', ['q' => trim($q)]) }}" wire:navigate class="flex items-center justify-between border-t border-soft bg-zebra px-3 py-2 text-sm text-brand">Trace “{{ trim($q) }}” through the whole chain <span>→</span></a>@endif
            </div>
        </div>
        BLADE;
    }
}
