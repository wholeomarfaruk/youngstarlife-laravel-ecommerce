<header class="sticky top-0 z-[5] flex flex-wrap items-center gap-2 border-b border-line bg-panel px-2.5 py-2 sm:flex-nowrap sm:gap-3 sm:px-5 sm:py-2.5">
    <button type="button" @click="$store.ui.toggleNav()" aria-label="Menu" class="grid h-9 w-9 flex-none place-items-center rounded-md border border-line-2 bg-white lg:hidden">
        <x-heroicon-o-bars-3 class="h-4 w-4" />
    </button>
    <div class="flex min-w-0 flex-1 flex-col">
        <span class="text-xs text-ink-muted">{{ $module ?? '' }}</span>
        <span class="truncate text-lg font-semibold">{{ $title ?? '' }}</span>
    </div>

    {{-- Global scan / search (Livewire, lazy results) --}}
    <livewire:admin.global-search />

    <livewire:admin.notification-bell />
</header>
