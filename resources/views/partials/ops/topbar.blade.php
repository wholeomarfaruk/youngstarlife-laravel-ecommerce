<header class="sticky top-0 z-[5] flex flex-wrap items-center gap-2 border-b border-line bg-panel px-2.5 py-2 min-[600px]:flex-nowrap sm:gap-3 sm:px-5 sm:py-2.5">
    <button type="button" @click="$store.ui.toggleNav()" aria-label="Menu" class="grid h-9 w-9 flex-none place-items-center rounded-md border border-line-2 bg-white max-lg:h-11 max-lg:w-11 lg:hidden">
        <x-heroicon-o-bars-3 class="h-4 w-4" />
    </button>
    <div class="flex min-w-0 flex-1 flex-col">
        <span class="text-xs text-ink-muted">{{ $module ?? '' }}</span>
        <span class="truncate text-lg font-semibold">{{ $title ?? '' }}</span>
    </div>

    {{-- Global scan / search (Livewire, lazy results) --}}
    <livewire:ops.global-search />

    <livewire:ops.notification-bell />

    {{-- Avatar menu --}}
    <div x-data="menu" class="relative flex-none" @keydown.escape="close">
        <button type="button" @click="toggle" class="grid h-9 w-9 place-items-center overflow-hidden rounded-full bg-ink text-xs font-semibold text-white max-lg:h-11 max-lg:w-11" aria-label="Account">
            @if (auth()->user()->avatar_url)<img src="{{ auth()->user()->avatar_url }}" alt="" class="h-full w-full object-cover">@else{{ auth()->user()->initials }}@endif
        </button>
        <div x-cloak x-show="open" @click.outside="close" x-transition.opacity class="absolute right-0 top-[calc(100%+6px)] z-50 flex w-56 flex-col rounded-md border border-line-2 bg-white p-1 shadow-pop">
            <div class="flex flex-col border-b border-soft px-2 pb-2 pt-1.5"><span class="font-semibold">{{ auth()->user()->name }}</span><span class="text-xs text-ink-muted">{{ auth()->user()->email }} · {{ auth()->user()->ops_role ?: ucfirst(auth()->user()->role) }}</span></div>
            <a href="{{ route('ops.profile') }}" wire:navigate @click="close" class="rounded px-2 py-1.5 text-ink hover:bg-soft">My profile</a>
            <a href="{{ route('ops.profile', ['tab' => 'security']) }}" wire:navigate @click="close" class="flex items-center justify-between rounded px-2 py-1.5 text-ink hover:bg-soft">Password & security @unless (auth()->user()->hasTwoFactor())<span class="h-1.5 w-1.5 rounded-full bg-warn" title="Two-factor is off"></span>@endunless</a>
            <a href="{{ route('ops.settings.index') }}" wire:navigate @click="close" class="rounded px-2 py-1.5 text-ink hover:bg-soft">Settings</a>
            <a href="{{ route('ops.settings.index', ['tab' => 'activity']) }}" wire:navigate @click="close" class="rounded px-2 py-1.5 text-ink hover:bg-soft">Activity log</a>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-soft pt-1">@csrf<button class="w-full rounded px-2 py-1.5 text-left text-bad hover:bg-soft">Log out</button></form>
        </div>
    </div>
</header>
