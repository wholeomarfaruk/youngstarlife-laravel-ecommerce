{{-- one menu item row in the builder ($item from Menus::tree(), $child = inside a dropdown) --}}
<div @class(['flex items-center gap-2 px-2.5', 'py-2' => ! $child, 'py-1.5' => $child, 'opacity-60' => ! $item['is_active']])>
    <span data-drag class="select-none px-0.5 text-lg leading-none text-ink-faint" title="Drag to reorder">⠿</span>
    @if ($item['icon'])<i class="{{ $item['icon'] }} w-4 text-center text-ink-3"></i>@endif
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="truncate font-medium">{{ $item['label'] }}</span>
            <x-ops.badge tone="mute">{{ config('storefront.menu_item_types.'.$item['type'], $item['type']) }}</x-ops.badge>
            @unless ($item['is_active'])<x-ops.badge tone="warn">Hidden</x-ops.badge>@endunless
            @if ($item['url'] === null)<x-ops.badge tone="bad">Broken link</x-ops.badge>@endif
            @if ($item['new_tab'])<span class="text-xs text-ink-muted" title="Opens in a new tab">↗</span>@endif
        </div>
        @if ($item['url'])<div class="mono truncate text-xs text-ink-muted">{{ $item['url'] }}</div>@endif
    </div>
    <button type="button" wire:click="toggle({{ $item['id'] }})" class="text-xs text-ink-muted hover:text-ink" title="{{ $item['is_active'] ? 'Hide' : 'Show' }} on site">{{ $item['is_active'] ? 'Hide' : 'Show' }}</button>
    @if (! $child && $this->depth > 1)<button type="button" wire:click="edit(null, {{ $item['id'] }})" class="text-xs text-ink-muted hover:text-ink" title="Add an item to this dropdown">+ Sub</button>@endif
    <button type="button" wire:click="edit({{ $item['id'] }})" class="btn-secondary btn-sm">Edit</button>
    <button type="button" wire:click="delete({{ $item['id'] }})" wire:confirm="Remove “{{ $item['label'] }}”{{ ! $child && ! empty($item['children']) ? ' and its dropdown items' : '' }}?" class="px-1 text-lg leading-none text-bad" title="Remove">×</button>
</div>
