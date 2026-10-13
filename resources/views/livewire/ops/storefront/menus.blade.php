@php($locations = \App\Services\Storefront\MenuService::locations())
@php($def = $locations[$location])
@php($types = config('storefront.menu_item_types'))
<div class="flex flex-col gap-3">
    {{-- icon previews use the storefront's icon set --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

    <div class="card">
        <div class="card-head"><span class="card-title">Navigation menus</span><a href="{{ url('/') }}" target="_blank" class="text-sm">View store ↗</a></div>
        <div class="flex flex-wrap gap-1 border-b border-soft px-3 py-2">
            @foreach ($locations as $key => $l)
                <button type="button" wire:click="$set('location', '{{ $key }}')" @class(['chip', 'chip-on' => $location === $key])>{{ $l['label'] }}</button>
            @endforeach
        </div>
        <p class="px-3.5 pt-2.5 text-sm text-ink-muted">{{ $def['description'] }}@if ($this->depth > 1) · Drag an item onto another to make a dropdown.@endif</p>

        @if ($location === 'footer')
            <div class="flex flex-wrap items-end gap-2 px-3.5 pt-3">
                <x-ops.field label="Heading above the links" class="min-w-[260px] flex-1"><input wire:model="title" class="input" placeholder="যেকোনো তথ্যের জন্য…"></x-ops.field>
                <button type="button" wire:click="saveTitle" class="btn-secondary">Save heading</button>
            </div>
        @endif

        <div class="grid items-start gap-3 p-3.5 lg:grid-cols-[minmax(0,1fr)_360px]">
            {{-- ── items: drag & drop (SortableJS) --}}
            <div x-data="sortableTree('reorder')" wire:key="tree-{{ $location }}">
                @if (! $this->tree)
                    <div class="rounded-lg border border-dashed border-line-2 p-6 text-center text-ink-muted">No items yet — add the first one.</div>
                @endif
                <ul data-sortable class="flex flex-col gap-1.5">
                    @foreach ($this->tree as $item)
                        <li data-id="{{ $item['id'] }}" wire:key="mi{{ $item['id'] }}" @class(['rounded-lg border bg-white', 'border-brand' => $editingId === $item['id'], 'border-line' => $editingId !== $item['id']])>
                            @include('livewire.ops.storefront.partials.menu-row', ['item' => $item, 'child' => false])
                            @if ($this->depth > 1)
                                <ul data-sortable data-children @class(['ml-8 flex flex-col gap-1.5 pr-2', 'pb-2' => $item['children'], 'min-h-[6px]' => ! $item['children']])>
                                    @foreach ($item['children'] as $c)
                                        <li data-id="{{ $c['id'] }}" wire:key="mi{{ $c['id'] }}" @class(['rounded-md border bg-zebra', 'border-brand' => $editingId === $c['id'], 'border-line' => $editingId !== $c['id']])>
                                            @include('livewire.ops.storefront.partials.menu-row', ['item' => $c, 'child' => true])
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <button type="button" wire:click="edit" class="btn-secondary btn-sm mt-2.5">+ Add item</button>
            </div>

            {{-- ── editor --}}
            <div class="rounded-lg border border-line p-3 lg:sticky lg:top-[72px]">
                @if (! $editing)
                    <div class="flex flex-col items-center gap-2 py-6 text-center text-sm text-ink-muted">
                        <span>Pick an item to edit, or add a new one.</span>
                        <button type="button" wire:click="edit" class="btn-primary btn-sm">+ Add item</button>
                    </div>
                @else
                    <form wire:submit="save" class="flex flex-col gap-3">
                        <div class="text-sm font-semibold">{{ $editingId ? 'Edit item' : 'New item' }}</div>
                        @if ($errors->any())<div class="rounded-md bg-bad-bg px-3 py-2 text-sm text-bad">{{ $errors->first() }}</div>@endif

                        <x-ops.field label="Links to">
                            <select wire:model.live="f.type" class="input">@foreach ($types as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                        </x-ops.field>

                        @switch($f['type'])
                            @case('route')
                                <x-ops.field label="Page"><select wire:model.live="f.value" class="input">@foreach (config('storefront.menu_routes') as $k => [$l]) <option value="{{ $k }}">{{ $l }}</option>@endforeach</select></x-ops.field>
                            @break
                            @case('category')
                                <div class="label"><span>Category</span>
                                    <x-ops.searchable-select field="f.ref_id" :options="$this->categories" placeholder="Choose a category…" searchPlaceholder="Search categories…" wire:key="cat-{{ $editingId ?? 'new' }}" /></div>
                            @break
                            @case('product')
                                <div class="label"><span>Product</span>
                                    <x-ops.searchable-select field="f.ref_id" placeholder="Choose a product…" searchPlaceholder="Search products…" wire:key="prod-{{ $editingId ?? 'new' }}"
                                        :options="$this->products->mapWithKeys(fn ($p) => [$p->id => $p->name.($p->status === 'active' ? '' : ' ('.$p->status.')')])->all()"
                                        :images="$this->products->mapWithKeys(fn ($p) => [$p->id => $p->thumb_url])->all()" /></div>
                            @break
                            @case('url')
                                <x-ops.field label="Link" hint="/page on this site, or https://… for another site"><input wire:model.blur="f.value" class="input mono" placeholder="/category/new-arrivals"></x-ops.field>
                            @break
                            @case('whatsapp')
                                <x-ops.field label="WhatsApp number" hint="With country code, e.g. 8801613046803"><input wire:model.blur="f.value" inputmode="tel" class="input mono"></x-ops.field>
                            @break
                            @case('phone')
                                <x-ops.field label="Phone number"><input wire:model.blur="f.value" inputmode="tel" class="input mono" placeholder="+8801XXXXXXXXX"></x-ops.field>
                            @break
                        @endswitch

                        <x-ops.field label="Label" required><input wire:model="f.label" class="input" placeholder="Shown on the site"></x-ops.field>

                        @if ($this->depth > 1)
                            <x-ops.field label="Position">
                                <select wire:model="f.parent_id" class="input">
                                    <option value="">Top level</option>
                                    @foreach ($this->tree as $t)
                                        @continue($t['id'] === $editingId)
                                        <option value="{{ $t['id'] }}">Dropdown of “{{ $t['label'] }}”</option>
                                    @endforeach
                                </select>
                            </x-ops.field>
                        @endif

                        <div class="label"><span>Icon <span class="text-ink-faint">(optional)</span></span>
                            <div class="flex gap-1.5"><span class="grid h-[34px] w-9 flex-none place-items-center rounded border border-line-2 text-lg">@if ($f['icon'])<i class="{{ $f['icon'] }}"></i>@endif</span>
                                <input wire:model.live.debounce.400ms="f.icon" class="input mono" placeholder="fa-solid fa-star"></div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach (config('storefront.menu_icons') as $cls => $name)
                                    <button type="button" wire:click="$set('f.icon', '{{ $cls }}')" title="{{ $name }}" @class(['grid h-7 w-7 place-items-center rounded border text-ink-3 hover:border-ink', 'border-brand bg-brand-soft' => $f['icon'] === $cls, 'border-line' => $f['icon'] !== $cls])><i class="{{ $cls }}"></i></button>
                                @endforeach
                                @if ($f['icon'])<button type="button" wire:click="$set('f.icon', '')" class="px-1.5 text-xs text-ink-muted">clear</button>@endif
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-4 text-sm">
                            <label class="flex items-center gap-2"><input type="checkbox" wire:model="f.is_active" class="h-4 w-4 accent-brand"> Show on site</label>
                            <label class="flex items-center gap-2"><input type="checkbox" wire:model="f.new_tab" class="h-4 w-4 accent-brand"> Open in new tab</label>
                        </div>

                        <div class="flex gap-1.5">
                            <button class="btn-primary" wire:loading.attr="disabled" wire:target="save">{{ $editingId ? 'Save item' : 'Add to menu' }}</button>
                            <button type="button" wire:click="cancel" class="btn-secondary">Cancel</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
    <p class="text-xs text-ink-muted">Changes go live immediately. Menus are registered in <span class="mono">config/storefront.php</span>; links to categories and products follow their current URL automatically, and a deleted category or hidden product drops out of the menu by itself.</p>
</div>
