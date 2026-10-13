{{-- ⋯ menu. Items are <button wire:click> inside the slot. --}}
<div x-data="menu" class="relative" @keydown.escape="close">
    <button type="button" @click="toggle" class="grid h-7 w-7 place-items-center rounded border border-line bg-white text-lg leading-none text-ink-3" aria-label="Actions">⋯</button>
    <div x-cloak x-show="open" @click.outside="close" @click="close" x-transition.opacity
         class="absolute right-0 top-[calc(100%+4px)] z-40 flex w-56 flex-col rounded-md border border-line-2 bg-white p-1 shadow-pop">{{ $slot }}</div>
</div>
