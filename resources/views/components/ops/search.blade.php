@props(['placeholder' => 'Search…'])
<input type="search" placeholder="{{ $placeholder }}" {{ $attributes->class('input flex-[1_1_200px]')->merge(['wire:model.live.debounce.300ms' => 'search']) }} />
