{{-- Products header buttons + sub-navigation (Catalog … Brands). Shared by Products (all views) and Attributes. --}}
@php($i = $this->inv) @php($onProducts = $active !== 'attributes')
<x-ops.page-header>
    @if ($onProducts)
        <button type="button" x-data @click="$dispatch('open-modal', 'import-csv')" class="btn-secondary">Import CSV</button>
    @else
        <a href="{{ route('ops.products.index', ['import' => 1]) }}" wire:navigate class="btn-secondary">Import CSV</a>
    @endif
    <a href="{{ route('ops.purchases.index') }}" wire:navigate class="btn-secondary" title="Stock enters through a purchase order → receive → batch">Stock in</a>
    @if ($onProducts)
        <button type="button" x-data @click="$dispatch('open-modal', 'add-product')" class="btn-primary">Add product</button>
    @else
        <a href="{{ route('ops.products.index', ['new' => 1]) }}" wire:navigate class="btn-primary">Add product</a>
    @endif
</x-ops.page-header>

@php($tabCounts = ['catalog' => $i['products'], 'variants' => $i['variants'], 'movements' => $i['movements'], 'low' => $i['low_tab'], 'damaged' => $i['damaged_tab'], 'categories' => $i['categories'], 'attributes' => $i['attributes'], 'brands' => $i['brands']])
<div class="flex gap-1 overflow-x-auto border-b border-line-2 [scrollbar-width:none]">
    @foreach (\App\Livewire\Ops\Products\Index::VIEWS as $k => $label)
        <a href="{{ $k === 'attributes' ? route('ops.attributes.index') : route('ops.products.index', $k === 'catalog' ? [] : ['view' => $k]) }}" wire:navigate @class(['mtab whitespace-nowrap', 'mtab-on' => $active === $k])>
            {{ $label }}
            <span @class(['ml-1 rounded-full px-1.5 text-xs', 'bg-bad-bg text-bad' => in_array($k, ['low', 'damaged']) && $tabCounts[$k], 'bg-soft text-ink-3' => ! (in_array($k, ['low', 'damaged']) && $tabCounts[$k])])>{{ ops_num($tabCounts[$k]) }}</span>
        </a>
    @endforeach
</div>
