{{-- Phone (< 480px) bottom bar: Home · Orders · Scan · Products · More --}}
<nav class="fixed inset-x-0 bottom-0 z-[55] grid grid-cols-5 border-t border-line bg-panel px-1 pt-1 pb-[calc(4px+env(safe-area-inset-bottom))] min-[480px]:hidden">
    @php($items = [['Home', 'ops.dashboard', 'home'], ['Orders', 'ops.orders.index', 'shopping-bag'], ['Scan', null, 'qr-code'], ['Products', 'ops.products.index', 'cube'], ['More', null, 'ellipsis-horizontal']])
    @foreach ($items as [$label, $route, $icon])
        @if ($label === 'Scan')
            <a href="{{ route('ops.packing.scan') }}" wire:navigate class="flex min-h-[52px] flex-col items-center justify-center gap-1 text-xs text-ink-3">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-brand text-white"><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" /></span>Scan
            </a>
        @elseif ($label === 'More')
            <button type="button" @click="$store.ui.nav=true" class="flex min-h-[52px] flex-col items-center justify-center gap-1 text-xs text-ink-3"><x-heroicon-o-ellipsis-horizontal class="h-6 w-6" />More</button>
        @else
            <a href="{{ route($route) }}" wire:navigate @class(['flex min-h-[52px] flex-col items-center justify-center gap-1 text-xs', 'font-semibold text-brand' => request()->routeIs(str_replace('.index', '', $route).'*') || request()->routeIs($route), 'text-ink-3' => ! (request()->routeIs(str_replace('.index', '', $route).'*') || request()->routeIs($route))])>
                <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-6 w-6" />{{ $label }}
            </a>
        @endif
    @endforeach
</nav>
