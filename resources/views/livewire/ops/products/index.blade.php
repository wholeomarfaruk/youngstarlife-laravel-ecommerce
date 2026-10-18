<div class="flex flex-col gap-3">
    @include('livewire.ops.products.partials.inventory-head', ['active' => $view])

    @if ($view === 'catalog')
        <section class="card">
            @php($c = $this->counts)
            <div class="border-b border-soft px-3 py-2 max-[479px]:overflow-x-auto max-[479px]:[scrollbar-width:none] max-[479px]:[&>div]:flex-nowrap max-[479px]:[&_button]:flex-none"><x-ops.chips wire:model.live="status" :options="['all' => ['All', $c['all']], 'active' => ['Active', $c['active']], 'draft' => ['Draft', $c['draft']], 'archived' => ['Archived', $c['archived']], 'low' => ['Low stock', $c['low']], 'out' => ['Out of stock', $c['out']], 'damaged' => ['Damaged', $c['damaged']]]" /></div>
            @php($activeFilters = collect(['category', 'type'])->filter(fn ($f) => filled($filters[$f] ?? null))->count() + ($sort !== 'updated' ? 1 : 0))
            <div x-data="{ more: false }">
            <x-ops.toolbar>
                <x-ops.search placeholder="Search name or code…" />
                <button type="button" @click="more = ! more" class="btn-secondary btn-sm min-[480px]:hidden">Filters @if ($activeFilters)<b class="rounded-full bg-ink px-1.5 text-2xs text-white">{{ $activeFilters }}</b>@endif <span x-text="more ? '▴' : '▾'">▾</span></button>
                <div class="contents max-[479px]:hidden" :class="more && 'max-[479px]:!grid w-full grid-cols-2 gap-2'">
                <select class="input w-auto max-[479px]:w-full" wire:model.live="filters.category"><option value="">All categories</option>@foreach ($this->categories as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
                <select class="input w-auto max-[479px]:w-full" wire:model.live="filters.type"><option value="">All types</option>@foreach (config('ops.product_types') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                <select class="input w-auto max-[479px]:col-span-2 max-[479px]:w-full" wire:model.live="sort"><option value="updated">Recently updated</option><option value="name">Name A–Z</option><option value="price_low">Price: low → high</option><option value="price_high">Price: high → low</option><option value="stock_low">Stock: low → high</option><option value="stock_high">Stock: high → low</option></select>
                </div>
                <span class="ml-auto text-sm text-ink-muted">{{ ops_num($this->products->total()) }} products</span>
            </x-ops.toolbar>
            </div>
            <div class="max-[479px]:sticky max-[479px]:bottom-[calc(64px+env(safe-area-inset-bottom))] max-[479px]:z-30 max-[479px]:shadow-pop">
            <x-ops.bulk-bar :count="count($selected)">
                <button wire:click="bulk('active')" class="btn-sm rounded bg-white text-ink">Set active</button>
                <button wire:click="bulk('draft')" class="btn-sm rounded bg-side-hover">Set draft</button>
                <button wire:click="bulk('archived')" wire:confirm="Archive {{ count($selected) }} products?" class="btn-sm rounded bg-side-hover">Archive</button>
                <button wire:click="export" class="btn-sm rounded bg-side-hover">Export</button>
                <a href="{{ route('ops.labels.print', ['products' => implode(',', $selected)]) }}" target="_blank" class="btn-sm rounded bg-side-hover text-white hover:text-white">Print labels</a>
            </x-ops.bulk-bar>
            </div>
            <div class="max-[479px]:hidden">
                <x-ops.table min-width="900px">
                    <x-slot:head><th class="th w-7"></th><x-ops.th sort="name">Product</x-ops.th><x-ops.th>Type</x-ops.th><x-ops.th>Categories</x-ops.th><x-ops.th sort="price" align="right">Price</x-ops.th><x-ops.th align="right">Variants</x-ops.th><x-ops.th sort="stock" align="right">Stock (batches)</x-ops.th><x-ops.th>Status</x-ops.th><th class="th w-10"></th></x-slot:head>
                    @forelse ($this->products as $p)
                        <tr wire:key="p{{ $p->id }}" @class(['tr-hover', 'bg-brand-tint' => in_array((string) $p->id, $selected)])>
                            <td class="td"><input type="checkbox" value="{{ $p->id }}" wire:model.live="selected" class="h-4 w-4 accent-ink"></td>
                            <td class="td"><div class="flex items-center gap-2.5">@if ($p->featured_image)<img src="{{ asset('storage/'.$p->featured_image) }}" alt="" loading="lazy" class="h-9 w-9 rounded-md bg-soft object-cover">@else<span class="grid h-9 w-9 place-items-center rounded-md bg-soft text-xs font-semibold text-ink-muted">{{ mb_substr($p->name, 0, 1) }}</span>@endif<div><a href="{{ route('ops.products.edit', $p) }}" wire:navigate class="font-medium text-ink hover:text-brand">{{ $p->name }}</a><div class="mono text-xs text-ink-muted">{{ $p->code }}</div></div></div></td>
                            <td class="td capitalize">{{ $p->type }}</td><td class="td text-sm">{{ $p->categories->pluck('name')->join(', ') ?: '—' }}</td>
                            <td class="td text-right tabular-nums">@if ($p->sale_price)<span class="font-semibold">{{ tk($p->sale_price) }}</span> <s class="text-xs text-ink-muted">{{ tk($p->price) }}</s>@else{{ tk($p->price) }}@endif</td>
                            <td class="td text-right">{{ $p->variants_count }}</td>
                            <td @class(['td text-right font-semibold tabular-nums', 'text-bad' => (int) $p->stock < $p->low_stock_threshold])>{{ (int) $p->stock }}@if ((int) $p->damaged)<div class="text-xs font-normal text-bad">{{ (int) $p->damaged }} damaged</div>@endif</td>
                            <td class="td"><x-ops.badge map="product_status" :status="$p->status" /></td>
                            <td class="td">@include('livewire.ops.products.partials.row-menu', ['p' => $p])</td>
                        </tr>
                    @empty <x-ops.empty colspan="9"><button wire:click="clearFilters" class="btn-secondary btn-sm">Clear filters</button></x-ops.empty> @endforelse
                </x-ops.table>
            </div>
            {{-- phones: select · photo · price · stock · ⋯ --}}
            <div class="min-[480px]:hidden">
                <div class="flex items-center gap-2 border-b border-soft px-3 py-2">
                    <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" class="h-5 w-5 accent-ink" aria-label="Select all on this page"
                        x-on:change="$wire.set('selected', $event.target.checked ? @js($this->products->pluck('id')->map(fn ($i) => (string) $i)->all()) : [])"> Select page</label>
                </div>
                @forelse ($this->products as $p)
                    @php($low = (int) $p->stock < $p->low_stock_threshold)
                    <div wire:key="m{{ $p->id }}" @class(['flex gap-2.5 border-b border-soft p-3', 'bg-brand-tint' => in_array((string) $p->id, $selected)])>
                        <input type="checkbox" value="{{ $p->id }}" wire:model.live="selected" class="mt-1 h-5 w-5 flex-none accent-ink" aria-label="Select {{ $p->name }}">
                        <a href="{{ route('ops.products.edit', $p) }}" wire:navigate class="flex-none">
                            @if ($p->featured_image)<img src="{{ $p->thumb_url }}" alt="" loading="lazy" class="h-14 w-14 rounded-md bg-soft object-cover">
                            @else<span class="grid h-14 w-14 place-items-center rounded-md bg-soft text-sm font-semibold text-ink-muted">{{ mb_substr($p->name, 0, 1) }}</span>@endif
                        </a>
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <div class="flex items-start gap-2">
                                <a href="{{ route('ops.products.edit', $p) }}" wire:navigate class="min-w-0 flex-1 font-semibold leading-snug text-ink">{{ $p->name }}</a>
                                @include('livewire.ops.products.partials.row-menu', ['p' => $p])
                            </div>
                            <div class="truncate text-xs text-ink-muted"><span class="mono">{{ $p->code }}</span> · {{ $p->variants_count }} {{ str('variant')->plural($p->variants_count) }} · <span class="capitalize">{{ $p->type }}</span>@if ($p->categories->isNotEmpty()) · {{ $p->categories->pluck('name')->join(', ') }}@endif</div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                <span class="tabular-nums">@if ($p->sale_price)<b>{{ tk($p->sale_price) }}</b> <s class="text-xs text-ink-muted">{{ tk($p->price) }}</s>@else<b>{{ tk($p->price) }}</b>@endif</span>
                                <span @class(['tabular-nums', 'font-semibold text-bad' => $low, 'text-ink-3' => ! $low])>{{ (int) $p->stock }} in stock{{ $low ? ' · low' : '' }}</span>
                                @if ((int) $p->damaged)<span class="text-xs text-bad">{{ (int) $p->damaged }} damaged</span>@endif
                                <x-ops.badge map="product_status" :status="$p->status" />
                                @if ($p->sold_out)<x-ops.badge tone="warn">Stock out (website)</x-ops.badge>@endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-sm text-ink-muted">No products match. <button wire:click="clearFilters" class="text-brand">Clear filters</button></div>
                @endforelse
            </div>
            <x-ops.pagination :paginator="$this->products" />
        </section>

    @elseif (in_array($view, ['variants', 'low', 'damaged']))
        @php($rows = $this->variantRows)
        <section class="card">
            @if ($view === 'low')<div class="border-b border-soft bg-warn-bg px-3.5 py-2 text-sm text-warn">Variants below their product's low-stock threshold — out of stock first. Stock in through a purchase order.</div>@endif
            @if ($view === 'damaged')<div class="border-b border-soft bg-bad-bg px-3.5 py-2 text-sm text-bad">Damaged units sit outside sellable stock until they go back to the supplier. Returned = units restocked from customer returns.</div>@endif
            <x-ops.toolbar>
                <x-ops.search placeholder="Product, SKU, barcode, colour…" />
                <span class="ml-auto text-sm text-ink-muted">{{ ops_num($rows->total()) }} variants</span>
                @if ($view === 'low')<a href="{{ route('ops.purchases.index') }}" wire:navigate class="btn-primary btn-sm">New purchase order</a>@endif
                @if ($view === 'damaged')<a href="{{ route('ops.supplier-returns.index', ['view' => 'pool']) }}" wire:navigate class="btn-primary btn-sm">Return to supplier →</a>@endif
            </x-ops.toolbar>
            <div class="min-[480px]:hidden">
                @forelse ($rows as $v)
                    <a href="{{ route('ops.products.edit', ['product' => $v->product_id, 'tab' => $view === 'variants' ? 'variants' : 'stock']) }}" wire:navigate wire:key="mv{{ $v->id }}" class="flex flex-col gap-1 border-b border-soft p-3 text-ink">
                        <span class="flex items-start justify-between gap-2"><span class="min-w-0"><span class="block font-medium leading-snug">{{ $v->product->name }}</span><span class="block text-xs text-ink-muted">{{ $v->option_text ?: 'Default' }} · <span class="mono">{{ $v->sku }}</span></span></span>
                            <span @class(['flex-none text-right text-lg font-semibold tabular-nums', 'text-bad' => $v->stock <= 0, 'text-warn' => $v->stock > 0 && $v->stock < $v->product->low_stock_threshold])>{{ ops_num($v->stock) }}<span class="block text-2xs font-normal text-ink-muted">in stock</span></span></span>
                        <span class="flex flex-wrap gap-x-3 text-xs text-ink-muted">
                            @if ($view === 'variants')<span>{{ tk($v->effective_price) }}</span><span>reserved {{ $v->reserved }}</span><span class="font-medium text-ink-3">available {{ $v->available }}</span>@if ($v->damaged)<span class="text-bad">{{ $v->damaged }} damaged</span>@endif<span class="mono">{{ $v->barcode ?: 'no barcode' }}</span>@endif
                            @if ($view === 'low')<span>threshold {{ $v->product->low_stock_threshold }}</span><span>reserved {{ $v->reserved }}</span><span>last supplier {{ $v->last_supplier ?? '—' }}</span>@endif
                            @if ($view === 'damaged')<span class="font-semibold text-bad">{{ $v->damaged ?: 0 }} damaged</span><span class="text-info">{{ (int) $v->returned }} restocked from returns</span>@endif
                        </span>
                    </a>
                @empty
                    <div class="p-6 text-center text-sm text-ink-muted">{{ $view === 'low' ? 'Nothing below threshold' : 'No variants' }}</div>
                @endforelse
            </div>
            <div class="max-[479px]:hidden">
            <x-ops.table min-width="900px">
                <x-slot:head>
                    <th class="th">Product · variant</th><th class="th">SKU</th>
                    @if ($view === 'variants')<th class="th">Barcode</th><th class="th text-right">Price</th>@endif
                    <th class="th text-right">Stock</th>
                    @if ($view === 'low')<th class="th text-right">Threshold</th><th class="th text-right">Reserved</th><th class="th">Last supplier</th>@endif
                    @if ($view === 'variants')<th class="th text-right">Reserved</th><th class="th text-right">Available</th><th class="th text-right">Damaged</th><th class="th">Status</th>@endif
                    @if ($view === 'damaged')<th class="th text-right">Damaged</th><th class="th text-right">Returned (restocked)</th>@endif
                    <th class="th"></th>
                </x-slot:head>
                @forelse ($rows as $v)
                    <tr wire:key="v{{ $v->id }}" class="tr-hover">
                        <td class="td"><a href="{{ route('ops.products.edit', ['product' => $v->product_id, 'tab' => $view === 'variants' ? 'variants' : 'stock']) }}" wire:navigate class="font-medium text-ink hover:text-brand">{{ $v->product->name }}</a><div class="text-xs text-ink-muted">{{ $v->option_text ?: 'Default' }}</div></td>
                        <td class="td mono text-sm">{{ $v->sku }}</td>
                        @if ($view === 'variants')<td class="td mono text-xs text-ink-muted">{{ $v->barcode ?: '—' }}</td><td class="td text-right tabular-nums">{{ tk($v->effective_price) }}</td>@endif
                        <td @class(['td text-right font-semibold tabular-nums', 'text-bad' => $v->stock <= 0, 'text-warn' => $v->stock > 0 && $v->stock < $v->product->low_stock_threshold])>{{ ops_num($v->stock) }}</td>
                        @if ($view === 'low')<td class="td text-right text-ink-muted">{{ $v->product->low_stock_threshold }}</td><td class="td text-right">{{ $v->reserved }}</td><td class="td text-sm">{{ $v->last_supplier ?? '—' }}</td>@endif
                        @if ($view === 'variants')<td class="td text-right">{{ $v->reserved }}</td><td class="td text-right font-medium">{{ $v->available }}</td><td @class(['td text-right', 'text-bad' => $v->damaged])>{{ $v->damaged ?: '—' }}</td><td class="td">@if ($v->is_active)<x-ops.badge map="product_status" :status="$v->product->status" />@else<x-ops.badge tone="mute">Inactive</x-ops.badge>@endif</td>@endif
                        @if ($view === 'damaged')<td class="td text-right font-semibold text-bad">{{ $v->damaged ?: '—' }}</td><td class="td text-right text-info">{{ (int) $v->returned ?: '—' }}</td>@endif
                        <td class="td text-right"><a href="{{ route('ops.batches.index', ['search' => $v->sku]) }}" wire:navigate class="text-sm">Batches →</a></td>
                    </tr>
                @empty <x-ops.empty colspan="10" :title="$view === 'low' ? 'Nothing below threshold' : 'No variants'" /> @endforelse
            </x-ops.table>
            </div>
            <x-ops.pagination :paginator="$rows" />
        </section>

    @elseif ($view === 'movements')
        @php($rows = $this->movements)
        <section class="card">
            <x-ops.toolbar>
                <x-ops.search placeholder="Batch, SKU or product…" />
                <select wire:model.live="mtype" class="input w-auto"><option value="">All movements</option>@foreach (config('ops.movement_types') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                <span class="ml-auto text-sm text-ink-muted">{{ ops_num($rows->total()) }} movements</span>
            </x-ops.toolbar>
            <div class="min-[480px]:hidden">
                @forelse ($rows as $m)
                    @php($tone = ['stock_in' => 'ok', 'sale' => 'info', 'return_restock' => 'info', 'damage' => 'bad', 'supplier_return' => 'warn', 'replacement' => 'warn'][$m->type] ?? 'mute')
                    <div wire:key="mm{{ $m->id }}" class="flex flex-col gap-1 border-b border-soft p-3 text-sm">
                        <div class="flex items-center gap-2"><x-ops.badge :tone="$tone">{{ config('ops.movement_types.'.$m->type, $m->type) }}</x-ops.badge><span class="mono text-xs text-ink-muted">{{ $m->created_at?->format('d M y H:i') }}</span>
                            <span @class(['ml-auto font-semibold tabular-nums', 'text-ok' => $m->qty > 0, 'text-bad' => $m->qty < 0])>{{ $m->qty > 0 ? '+' : '' }}{{ $m->qty }}</span></div>
                        <div class="leading-snug">{{ $m->variant?->product?->name }} <span class="text-xs text-ink-muted">{{ $m->variant?->option_text }}</span></div>
                        <div class="flex flex-wrap gap-x-2 text-xs text-ink-muted"><a href="{{ route('ops.trace', ['q' => $m->batch?->code]) }}" wire:navigate class="mono">{{ $m->batch?->code }}</a><span>{{ $m->ref?->number ?? $m->ref?->code ?? '' }}</span><span>{{ $m->user?->name ?? 'System' }}</span>@if ($m->note)<span class="w-full">{{ $m->note }}</span>@endif</div>
                    </div>
                @empty
                    <div class="p-6 text-center text-sm text-ink-muted">No stock movements</div>
                @endforelse
            </div>
            <div class="max-[479px]:hidden">
            <x-ops.table min-width="900px">
                <x-slot:head><th class="th">When</th><th class="th">Type</th><th class="th">Product · variant</th><th class="th">Batch</th><th class="th text-right">Qty</th><th class="th">Reference</th><th class="th">By</th></x-slot:head>
                @forelse ($rows as $m)
                    @php($tone = ['stock_in' => 'ok', 'sale' => 'info', 'return_restock' => 'info', 'damage' => 'bad', 'supplier_return' => 'warn', 'replacement' => 'warn'][$m->type] ?? 'mute')
                    <tr wire:key="m{{ $m->id }}" class="tr-hover">
                        <td class="td mono text-xs text-ink-muted">{{ $m->created_at?->format('d M y H:i') }}</td>
                        <td class="td"><x-ops.badge :tone="$tone">{{ config('ops.movement_types.'.$m->type, $m->type) }}</x-ops.badge></td>
                        <td class="td">{{ $m->variant?->product?->name }}<div class="text-xs text-ink-muted">{{ $m->variant?->option_text }} · <span class="mono">{{ $m->variant?->sku }}</span></div></td>
                        <td class="td mono"><a href="{{ route('ops.trace', ['q' => $m->batch?->code]) }}" wire:navigate>{{ $m->batch?->code }}</a></td>
                        <td @class(['td text-right font-semibold tabular-nums', 'text-ok' => $m->qty > 0, 'text-bad' => $m->qty < 0])>{{ $m->qty > 0 ? '+' : '' }}{{ $m->qty }}</td>
                        <td class="td text-sm">{{ $m->ref?->number ?? $m->ref?->code ?? '—' }}@if ($m->note)<div class="text-xs text-ink-muted">{{ $m->note }}</div>@endif</td>
                        <td class="td text-sm text-ink-muted">{{ $m->user?->name ?? 'System' }}</td>
                    </tr>
                @empty <x-ops.empty colspan="7" title="No stock movements" /> @endforelse
            </x-ops.table>
            </div>
            <x-ops.pagination :paginator="$rows" />
        </section>

    @elseif ($view === 'categories')
        <section class="card">
            <div class="flex flex-wrap items-start gap-2 border-b border-soft p-3">
                <div class="min-w-[200px] flex-1"><input wire:model="catName" wire:keydown.enter="addCategory" class="input @error('catName') input-error @enderror" placeholder="New category name">@error('catName')<div class="mt-1 text-xs text-bad">{{ $message }}</div>@enderror</div>
                <select wire:model="catParent" class="input w-auto"><option value="">No parent (top level)</option>@foreach (collect($this->categoryTree)->where('depth', 0) as $r)<option value="{{ $r['cat']->id }}">{{ $r['cat']->name }}</option>@endforeach</select>
                <button type="button" wire:click="addCategory" class="btn-dark">Add category</button>
            </div>
            @forelse ($this->categoryTree as $r)
                @php($c = $r['cat'])
                <div wire:key="c{{ $c->id }}" class="flex items-center gap-3 border-t border-soft px-3.5 py-2 first:border-0 hover:bg-zebra">
                    @if ($renaming === $c->id)
                        <input wire:model="renameTo" wire:keydown.enter="saveRename" wire:keydown.escape="$set('renaming', null)" class="input flex-1 py-1" autofocus>
                        <button type="button" wire:click="saveRename" class="text-sm text-brand">Save</button><button type="button" wire:click="$set('renaming', null)" class="text-sm text-ink-muted">Cancel</button>
                    @else
                        <a href="{{ route('ops.products.index', ['filters' => ['category' => $c->id]]) }}" wire:navigate @class(['flex-1 text-ink hover:text-brand', 'font-semibold' => ! $r['depth'], 'pl-4' => $r['depth']])>{{ $r['depth'] ? '↳ ' : '' }}{{ $c->name }}</a>
                        <span class="text-sm text-ink-muted">{{ $r['count'] }} {{ str('product')->plural($r['count']) }}</span>
                        <button type="button" wire:click="startRename({{ $c->id }})" class="w-16 text-right text-sm text-brand">Rename</button>
                        @if ($r['in_use'])<span class="w-14 text-right text-sm text-bad">In use</span>
                        @else<button type="button" wire:click="deleteCategory({{ $c->id }})" wire:confirm="Delete category {{ $c->name }}?" class="w-14 text-right text-sm text-bad">Delete</button>@endif
                    @endif
                </div>
            @empty <div class="px-3.5 py-6 text-center text-ink-muted">No categories yet.</div> @endforelse
        </section>

    @elseif ($view === 'brands')
        <section class="card">
            <div class="flex flex-wrap items-start gap-2 border-b border-soft p-3">
                <div class="min-w-[200px] flex-1"><input wire:model="brandName" wire:keydown.enter="addBrand" class="input @error('brandName') input-error @enderror" placeholder="Brand name">@error('brandName')<div class="mt-1 text-xs text-bad">{{ $message }}</div>@enderror</div>
                <input wire:model="brandOrigin" wire:keydown.enter="addBrand" class="input min-w-[200px] flex-1" placeholder="Origin, e.g. Bangladesh">
                <button type="button" wire:click="addBrand" class="btn-dark">Add brand</button>
            </div>
            @forelse ($this->brandRows as $b)
                <div wire:key="b{{ $b->id }}" class="flex items-center gap-3 border-t border-soft px-3.5 py-2.5 first:border-0 hover:bg-zebra">
                    <span class="grid h-8 w-8 flex-none place-items-center rounded-md bg-soft text-xs font-semibold text-ink-3">{{ collect(explode(' ', $b->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('') }}</span>
                    @if ($renaming === $b->id)
                        <input wire:model="renameTo" wire:keydown.enter="saveRename('brand')" wire:keydown.escape="$set('renaming', null)" class="input flex-1 py-1" autofocus>
                        <button type="button" wire:click="saveRename('brand')" class="text-sm text-brand">Save</button><button type="button" wire:click="$set('renaming', null)" class="text-sm text-ink-muted">Cancel</button>
                    @else
                        <a href="{{ route('ops.products.index', ['search' => $b->name]) }}" wire:navigate class="min-w-0 flex-1 text-ink"><span class="block font-medium hover:text-brand">{{ $b->name }}</span><span class="block text-xs text-ink-muted">{{ collect([$b->origin, $b->note])->filter()->join(' · ') ?: '—' }}</span></a>
                        <span class="text-sm text-ink-muted">{{ $b->products_count }} {{ str('product')->plural($b->products_count) }}</span>
                        <button type="button" wire:click="startRename({{ $b->id }}, 'brand')" class="w-16 text-right text-sm text-brand">Rename</button>
                        @if ($b->products_count)<span class="w-14 text-right text-sm text-ink-muted">In use</span>
                        @else<button type="button" wire:click="deleteBrand({{ $b->id }})" wire:confirm="Delete brand {{ $b->name }}?" class="w-14 text-right text-sm text-bad">Delete</button>@endif
                    @endif
                </div>
            @empty <div class="px-3.5 py-6 text-center text-ink-muted">No brands yet.</div> @endforelse
        </section>
    @endif

    <x-ops.modal name="add-product" title="Add product">
        <div class="flex flex-col gap-3">
            <div class="grid gap-2 sm:grid-cols-3">@foreach (['simple' => 'One SKU, no options', 'variable' => 'Color / size variants', 'combo' => 'Bundle of products'] as $t => $d)
                <label @class(['cursor-pointer rounded-md border p-2.5', 'border-brand bg-brand-tint' => $newType === $t, 'border-line-2' => $newType !== $t])><input type="radio" wire:model.live="newType" value="{{ $t }}" class="sr-only"><div class="font-semibold capitalize">{{ $t }}</div><div class="text-xs text-ink-muted">{{ $d }}</div></label>@endforeach</div>
            <x-ops.field label="Product name" required :error="$errors->first('newName')"><input wire:model="newName" class="input" placeholder="e.g. Cargo Pant"></x-ops.field>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ops.field label="Category"><select wire:model="newCategory" class="input"><option value="">Choose…</option>@foreach ($this->categories as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-ops.field>
                <x-ops.field label="Brand"><select wire:model="newBrand" class="input"><option value="">No brand</option>@foreach ($this->brands as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-ops.field>
            </div>
        </div>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="create" class="btn-primary">Create & edit →</button></x-slot:footer>
    </x-ops.modal>

    <x-ops.modal name="import-csv" title="Import products from CSV" width="max-w-lg">
        <div class="flex flex-col gap-3 text-sm">
            <p class="text-ink-muted">One row per variant. Rows with the same <b class="mono">code</b> become one product; existing codes / SKUs are updated. Stock is not imported — it comes from purchases → batches.</p>
            <div class="rounded-md bg-zebra p-2.5 mono text-xs text-ink-3">{{ implode(', ', \App\Livewire\Ops\Products\Index::CSV_COLUMNS) }}</div>
            <button type="button" wire:click="csvTemplate" class="self-start text-brand">↓ Download template</button>
            <x-ops.field label="CSV file" required :error="$errors->first('csv')"><input type="file" wire:model="csv" accept=".csv,text/csv" class="input"></x-ops.field>
            <div wire:loading wire:target="csv" class="text-xs text-ink-muted">Uploading…</div>
        </div>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="importCsv" wire:loading.attr="disabled" class="btn-primary">Import</button></x-slot:footer>
    </x-ops.modal>
</div>
