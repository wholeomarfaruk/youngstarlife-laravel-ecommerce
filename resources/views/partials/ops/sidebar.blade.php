<aside
    class="fixed inset-y-0 left-0 z-[60] flex w-[260px] flex-col gap-0.5 overflow-auto bg-side px-2.5 py-3.5 text-side-text transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:w-sidebar lg:translate-x-0"
    :class="$store.ui.nav ? 'translate-x-0' : '-translate-x-[105%] lg:translate-x-0'">
    <div class="flex items-center gap-2 px-1.5 pb-3.5">
        <div class="grid h-6 w-6 place-items-center rounded bg-side-text text-xs font-bold text-side">YS</div>
        <div class="flex flex-col"><span class="text-base font-semibold">{{ site('company.name') ?: site()->storeName() }}</span><span class="text-xs text-side-dim">Operations</span></div>
    </div>

    <a href="{{ route('ops.trace') }}" wire:navigate @click="$store.ui.nav=false"
       class="mb-2.5 flex items-center justify-between gap-2 rounded border border-side-line px-2 py-1.5 text-left text-white hover:text-white">
        <span class="font-medium">Trace anything</span><span class="mono text-xs text-side-dim">⌘K</span>
    </a>

    <nav class="flex flex-col gap-0.5">
        @foreach (config('ops.nav') as $i => $item)
            @continue(! \App\Http\Middleware\OpsAccess::allowed(auth()->user(), $item['label']))
            @php($active = request()->routeIs(...explode('|', $item['match'])))
            <a href="{{ route($item['route']) }}" wire:navigate @click="$store.ui.nav=false"
               @class(['flex min-h-[36px] items-center gap-2 rounded px-2 py-1.5 font-medium max-lg:min-h-[44px]', 'bg-side-hover text-white hover:text-white' => $active, 'text-side-muted hover:text-white' => ! $active])>
                <span class="mono w-3 text-2xs text-side-num">{{ $i + 1 }}</span>{{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <a href="{{ route('ops.profile') }}" wire:navigate @click="$store.ui.nav=false" class="mt-auto flex flex-col gap-0.5 border-t border-side-hover px-1.5 pt-2.5 text-xs text-side-dim hover:text-side-text">
        <span class="text-side-text">{{ auth()->user()->name }} · {{ auth()->user()->ops_role ?: ucfirst(auth()->user()->role) }}</span>
        <span>{{ auth()->user()->warehouse?->name ?? \App\Models\Ops\Warehouse::where('is_default', true)->value('name') ?? 'Main warehouse' }}</span>
    </a>
</aside>
