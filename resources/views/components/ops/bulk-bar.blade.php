@props(['count' => 0])
@if ($count)
    <div class="flex flex-wrap items-center gap-1.5 bg-ink px-3 py-2 text-white">
        <span class="mr-1.5 font-medium">{{ $count }} selected</span>
        {{ $slot }}
        <button type="button" wire:click="$set('selected', [])" class="ml-auto px-2.5 py-1 text-side-muted">Clear</button>
    </div>
@endif
