{{-- <x-ops.chips wire:model.live="status" :options="['all'=>['All',12], 'pending'=>['Pending',3]]" /> --}}
@props(['options'])
<div class="flex flex-wrap gap-1" x-data="{ v: @entangle($attributes->wire('model')) }">
    @foreach ($options as $key => [$label, $count])
        <button type="button" @click="v = '{{ $key }}'" class="chip" :class="v === '{{ $key }}' && 'chip-on'">
            {{ $label }} <span class="tabular-nums opacity-75">{{ $count }}</span>
        </button>
    @endforeach
</div>
