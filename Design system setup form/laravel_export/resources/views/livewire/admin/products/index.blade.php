<div class="flex flex-col gap-3">
    <x-admin.page-header>
        <a href="{{ route('admin.purchases.index') }}" wire:navigate class="btn-secondary">New purchase → batch</a>
        <button x-data @click="$dispatch('open-modal','add-product')" class="btn-primary">+ Add product</button>
    </x-admin.page-header>
    <section class="card">
        <div class="border-b border-soft px-3 py-2"><x-admin.chips wire:model.live="status" :options="['all' => ['All', ''], 'active' => ['Active', ''], 'draft' => ['Draft', ''], 'archived' => ['Archived', ''], 'low' => ['Low stock', '']]" /></div>
        <x-admin.toolbar>
            <x-admin.search placeholder="Search name or code…" />
            <select class="input w-auto" wire:model.live="filters.category"><option value="">All categories</option>@foreach ($this->categories as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
            <span class="ml-auto text-sm text-ink-muted">{{ $this->products->total() }} products</span>
        </x-admin.toolbar>
        <x-admin.bulk-bar :count="count($selected)"><button wire:click="bulk('active')" class="btn-sm rounded bg-white text-ink">Set active</button><button wire:click="bulk('draft')" class="btn-sm rounded bg-side-hover">Set draft</button></x-admin.bulk-bar>
        <x-admin.table min-width="820px">
            <x-slot:head><th class="th w-7"></th><x-admin.th sort="name">Product</x-admin.th><x-admin.th>Type</x-admin.th><x-admin.th>Categories</x-admin.th><x-admin.th sort="price" align="right">Price</x-admin.th><x-admin.th align="right">Stock (batches)</x-admin.th><x-admin.th>Status</x-admin.th></x-slot:head>
            @forelse ($this->products as $p)
                <tr class="tr-hover" wire:key="p{{ $p->id }}">
                    <td class="td"><input type="checkbox" value="{{ $p->id }}" wire:model.live="selected" class="h-4 w-4 accent-ink"></td>
                    <td class="td"><div class="flex items-center gap-2.5"><img src="{{ $p->featured_image ? asset('storage/'.$p->featured_image) : '' }}" alt="" loading="lazy" class="h-9 w-9 rounded-md bg-soft object-cover"><div><a href="{{ route('admin.products.edit', $p) }}" wire:navigate class="font-medium text-ink hover:text-brand">{{ $p->name }}</a><div class="mono text-xs text-ink-muted">{{ $p->code }} · {{ $p->variants_count }} variants</div></div></div></td>
                    <td class="td capitalize">{{ $p->type }}</td><td class="td text-sm">{{ $p->categories->pluck('name')->join(', ') }}</td>
                    <td class="td text-right tabular-nums">{{ tk($p->sale_price ?? $p->price) }}</td>
                    <td @class(['td text-right font-semibold tabular-nums', 'text-bad' => $p->stock < $p->low_stock_threshold])>{{ (int) $p->stock }}</td>
                    <td class="td"><x-admin.badge :tone="['active' => 'ok', 'draft' => 'mute', 'archived' => 'warn'][$p->status]">{{ ucfirst($p->status) }}</x-admin.badge></td>
                </tr>
            @empty <x-admin.empty colspan="7" /> @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$this->products" />
    </section>

    <x-admin.modal name="add-product" title="Add product">
        <div class="flex flex-col gap-3">
            <div class="grid grid-cols-3 gap-2">@foreach (['simple' => 'One SKU, no options', 'variable' => 'Color / size variants', 'combo' => 'Bundle of products'] as $t => $d)
                <label @class(['cursor-pointer rounded-md border p-2.5', 'border-brand bg-brand-tint' => $newType === $t, 'border-line-2' => $newType !== $t])><input type="radio" wire:model.live="newType" value="{{ $t }}" class="sr-only"><div class="font-semibold capitalize">{{ $t }}</div><div class="text-xs text-ink-muted">{{ $d }}</div></label>@endforeach</div>
            <x-admin.field label="Product name" required :error="$errors->first('newName')"><input wire:model="newName" class="input"></x-admin.field>
        </div>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="create" class="btn-primary">Create & edit →</button></x-slot:footer>
    </x-admin.modal>
</div>
