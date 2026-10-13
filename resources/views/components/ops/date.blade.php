{{-- flatpickr date input, Livewire-safe: <x-ops.date wire:model.live="from" class="w-auto py-1" max="2026-10-09" /> (value stays Y-m-d, shown as 17 Mar 2026) --}}
@props(['max' => null, 'min' => null, 'placeholder' => 'Pick a date'])
<span wire:ignore class="relative inline-flex min-w-0 items-center" x-data="datepicker(@entangle($attributes->wire('model')), { max: @js($max), min: @js($min) })">
    <input x-ref="input" type="text" placeholder="{{ $placeholder }}" {{ $attributes->whereDoesntStartWith('wire:model')->class('input pr-6') }}>
    <button type="button" x-show="value" x-cloak @click="clear()" class="absolute right-1.5 text-ink-faint hover:text-ink" aria-label="Clear date">×</button>
</span>
