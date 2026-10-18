@php($rows = $this->rows)
@php($cat = $this->category)
<div class="card">
    <div class="card-head"><span class="card-title">Product order</span><a href="{{ $cat ? route('category.show', $cat->slug) : route('shop') }}" target="_blank" class="text-sm">View on website ↗</a></div>

    {{-- which list: Home & Shop, or one category --}}
    <div class="flex flex-wrap items-end gap-2 border-b border-soft px-3.5 py-3">
        <x-ops.field label="Order of" class="min-w-[260px] flex-1">
            <select wire:model.live="list" class="input">
                <option value="all">All products — Home &amp; Shop</option>
                <optgroup label="Category page">
                    @foreach ($this->categories as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->products_count }})</option>@endforeach
                </optgroup>
            </select>
        </x-ops.field>
        @if ($cat)
            <x-ops.field label="Add a product to {{ $cat->name }}" class="min-w-[260px] flex-1">
                <x-ops.select wire:model.live="addId" :options="$this->addable" placeholder="Search products…" wire:key="add-{{ $cat->id }}-{{ $rows->count() }}" />
            </x-ops.field>
        @endif
    </div>
    <p class="px-3.5 pt-2.5 text-sm text-ink-muted">
        Drag a row by <span class="mono">⠿</span>, or type a number in <b>#</b> to move it there. Every change saves at once.
        {{ $cat ? 'This is the order on the '.$cat->name.' page.' : 'This is the order on Home and Shop (only Active products show there).' }}
    </p>

    <div class="p-3.5" x-data="sortableTree('reorder')" wire:key="order-{{ $list }}">
        @if ($rows->isEmpty())
            <div class="rounded-lg border border-dashed border-line-2 p-6 text-center text-ink-muted">{{ $cat ? 'No products in this category yet — add one above.' : 'No active products.' }}</div>
        @endif
        <ul data-sortable class="flex flex-col gap-1.5">
            @foreach ($rows as $i => $p)
                <li data-id="{{ $p->id }}" wire:key="po-{{ $list }}-{{ $p->id }}" class="flex items-center gap-2.5 rounded-lg border border-line bg-white px-2 py-1.5">
                    <span data-drag class="cursor-grab select-none px-1 text-lg leading-none text-ink-faint" title="Drag to reorder">⠿</span>
                    <input type="number" min="1" max="{{ $rows->count() }}" value="{{ $i + 1 }}" aria-label="Position" wire:key="pos-{{ $p->id }}-{{ $i }}"
                        class="input w-16 px-1.5 py-1 text-center mono" x-on:change="$wire.moveTo({{ $p->id }}, $event.target.value)" x-on:keydown.enter.prevent="$event.target.blur()">
                    <img src="{{ $p->thumb_url }}" alt="" class="h-11 w-11 flex-none rounded object-cover" loading="lazy">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('ops.products.edit', $p) }}" wire:navigate class="block truncate font-medium text-ink hover:underline">{{ $p->name }}</a>
                        <span class="mono text-xs text-ink-muted">{{ $p->code }}</span>
                    </div>
                    <span class="hidden text-sm sm:inline">{{ tk($p->sellPrice()) }}</span>
                    @if ($p->status !== 'active')<x-ops.badge :status="$p->status" map="product_status" />@endif
                    <a href="{{ route('product.show', $p->slug) }}" target="_blank" class="hidden text-sm text-ink-muted hover:text-ink sm:inline" title="View on website">↗</a>
                    @if ($cat)
                        <button type="button" wire:click="remove({{ $p->id }})" wire:confirm="Remove {{ $p->name }} from {{ $cat->name }}? The product itself stays." class="px-1 text-lg leading-none text-ink-faint hover:text-bad" title="Remove from {{ $cat->name }}">×</button>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</div>
