{{--
    Searchable select (copied from ecomx-automation's <x-searchable-select>, restyled to the Ops design tokens).
    Pure Alpine, no library. Same API:
      <x-ops.searchable-select field="filters.supplier" :options="$suppliers" placeholder="All suppliers" />
      <x-ops.searchable-select field="pick" :options="[$id => 'Cargo Pant · L']" :images="[$id => $thumbUrl]" placeholder="Add product…" />
    Added here: the search box is focused on open, ↑ ↓ Enter pick from the keyboard, Esc closes.
--}}
@props([
    'field',
    'value'       => '',
    'options'     => [],
    'images'      => null,
    'placeholder' => 'All',
    'searchPlaceholder' => 'Search…',
    'disabled'    => false,
])

@php
    $imageMap = collect($images ?? []);
    $optionsJson = collect($options)->map(fn ($label, $val) => [
        'value' => (string) $val,
        'label' => (string) $label,
        'image' => $imageMap->get($val),
    ])->values();
@endphp

<div
    x-data="{
        open: false,
        query: '',
        active: 0,
        options: @js($optionsJson),
        value: @entangle($field).live,
        get selectedOption() {
            return this.options.find(o => o.value === String(this.value ?? '')) ?? null;
        },
        get selectedLabel() {
            return this.selectedOption ? this.selectedOption.label : @js($placeholder);
        },
        get filtered() {
            if (!this.query) return this.options;
            const q = this.query.toLowerCase();
            return this.options.filter(o => o.label.toLowerCase().includes(q));
        },
        toggle() {
            this.open = !this.open;
            if (this.open) { this.active = 0; this.$nextTick(() => this.$refs.search.focus()); }
        },
        select(val) {
            this.value = val;
            this.open = false;
            this.query = '';
        },
        move(step) {
            const n = this.filtered.length; if (!n) return;
            this.active = (this.active + step + n) % n;
            this.$nextTick(() => this.$refs.list.querySelector('[data-active]')?.scrollIntoView({ block: 'nearest' }));
        },
    }"
    x-init="$watch('query', () => active = 0)"
    @click.outside="open = false"
    {{ $attributes->merge(['class' => 'relative min-w-0']) }}
>
    <button type="button" @click="toggle()" @if($disabled) disabled @endif
        class="input flex w-full min-w-[160px] items-center justify-between gap-2 text-left disabled:cursor-not-allowed">
        <span class="flex min-w-0 items-center gap-2">
            <template x-if="selectedOption && selectedOption.image">
                <img :src="selectedOption.image" alt="" class="h-5 w-5 shrink-0 rounded object-cover">
            </template>
            <span x-text="selectedLabel" :class="selectedOption ? 'text-ink' : 'text-ink-muted'" class="truncate"></span>
        </span>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
        class="absolute left-0 top-full z-40 mt-1 w-full min-w-64 overflow-hidden rounded-md border border-line-2 bg-white shadow-pop">
        <div class="border-b border-soft p-2">
            <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input type="text" x-model="query" x-ref="search"
                    @keydown.escape.prevent="open = false" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                    @keydown.enter.prevent="filtered[active] && select(filtered[active].value)"
                    placeholder="{{ $searchPlaceholder }}"
                    class="input py-1.5 pl-7 text-sm">
            </div>
        </div>
        <div class="max-h-60 overflow-y-auto py-1" x-ref="list">
            <button type="button" @click="select('')"
                class="w-full px-3 py-1.5 text-left text-sm hover:bg-soft"
                :class="(value ?? '') === '' ? 'font-medium text-brand' : 'text-ink-3'">
                {{ $placeholder }}
            </button>
            <template x-for="(option, i) in filtered" :key="option.value">
                <button type="button" @click="select(option.value)" @mouseenter="active = i" :data-active="i === active ? '' : null"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm"
                    :class="[String(value ?? '') === option.value ? 'font-medium text-brand' : 'text-ink', i === active ? 'bg-soft' : '']">
                    <template x-if="option.image">
                        <img :src="option.image" alt="" class="h-7 w-7 shrink-0 rounded object-cover">
                    </template>
                    <span class="truncate" x-text="option.label"></span>
                </button>
            </template>
            <p x-show="filtered.length === 0" class="px-3 py-2 text-xs text-ink-muted">No matches</p>
        </div>
    </div>
</div>
