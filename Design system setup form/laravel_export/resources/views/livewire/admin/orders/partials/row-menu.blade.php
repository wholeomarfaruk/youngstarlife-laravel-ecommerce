{{-- Status-aware actions, same as the design's ⋯ menu --}}
<x-admin.row-menu>
    <a href="{{ route('admin.orders.show', $o) }}" wire:navigate class="rounded px-2 py-1.5 text-ink hover:bg-soft">Open order</a>
    @switch($o->status)
        @case('pending') <x-admin.menu-item wire:click="setStatus({{ $o->id }}, 'confirmed')">Confirm order</x-admin.menu-item> @break
        @case('confirmed') <x-admin.menu-item wire:click="setStatus({{ $o->id }}, 'processing')">Start processing</x-admin.menu-item> @break
        @case('processing') <a href="{{ route('admin.packing.scan', ['order' => $o->number]) }}" wire:navigate class="rounded px-2 py-1.5 text-ink hover:bg-soft">Pack & scan</a> @break
        @case('packed') <x-admin.menu-item wire:click="setStatus({{ $o->id }}, 'shipped')">Mark shipped</x-admin.menu-item> @break
    @endswitch
    <x-admin.menu-item wire:click="setStatus({{ $o->id }}, 'on_hold')">Put on hold</x-admin.menu-item>
    @if (! in_array($o->status, ['shipped', 'delivered', 'returned', 'cancelled']))
        <x-admin.menu-item danger wire:click="setStatus({{ $o->id }}, 'cancelled')" wire:confirm="Cancel {{ $o->number }}?">Cancel order</x-admin.menu-item>
    @endif
</x-admin.row-menu>
