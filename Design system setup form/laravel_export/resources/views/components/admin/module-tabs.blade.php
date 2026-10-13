@props(['group'])
<div class="-mt-1 flex gap-1 overflow-x-auto border-b border-line-2 [scrollbar-width:none] max-sm:-mx-2.5 max-sm:px-2.5">
    @foreach (config("admin.tabs.$group") as [$label, $route])
        <a href="{{ route($route) }}" wire:navigate @class(['mtab', 'mtab-on' => request()->routeIs($route)])>
            {{ $label }}
            @if ($route === 'admin.orders.drafts') <span class="ml-1 rounded-full bg-soft px-1.5 text-xs text-ink-3">{{ cache()->remember('drafts.count', 60, fn () => \App\Models\Order::drafts()->count()) }}</span> @endif
        </a>
    @endforeach
</div>
