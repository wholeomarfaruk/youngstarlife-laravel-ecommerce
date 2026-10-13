{{--
    Searchable select (Tom Select — the select2-style dropdown), bound to Livewire:
      <x-ops.select wire:model="f.category_id" :options="$categories" placeholder="Pick a category" />          static [value => label]
      <x-ops.select wire:model="f.product_id" remote="searchProducts" :options="[$id => $label]" />            remote search via a Livewire
                                                                                                             method returning [{value, text}]
    Give it a wire:key that changes when the record being edited changes, so it re-mounts with that record's options.
--}}
@props(['options' => [], 'placeholder' => 'Search…', 'remote' => null, 'clearable' => true])
<div wire:ignore {{ $attributes->whereStartsWith('wire:key') }} {{ $attributes->except(['wire:model', 'wire:model.live', 'wire:key'])->class('min-w-0') }}
     x-data="tomSelect(@entangle($attributes->wire('model')), @js(['remote' => $remote, 'placeholder' => $placeholder, 'clearable' => $clearable]))">
    <select x-ref="select" autocomplete="off" placeholder="{{ $placeholder }}">
        <option value=""></option>{{-- stops the browser picking the first option; Tom Select shows the placeholder instead --}}
        @foreach ($options as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
</div>
