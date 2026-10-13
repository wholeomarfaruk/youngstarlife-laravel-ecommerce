<form wire:submit="save" class="flex flex-col gap-3">
    <a href="{{ route('admin.products.index') }}" wire:navigate class="self-start text-sm">← All products</a>
    <x-admin.page-header :title="$product->name" :subtitle="ucfirst($product->type).' product · '.$product->code">
        <a href="{{ route('admin.products.index') }}" wire:navigate class="btn-secondary">Cancel</a>
        <button class="btn-primary" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving…</span></button>
    </x-admin.page-header>

    <div class="flex gap-1 overflow-x-auto border-b border-line-2 [scrollbar-width:none]">
        @foreach (['general' => 'General', 'pricing' => 'Pricing', 'stock' => 'Stocks', 'supplier' => 'Supplier', 'media' => 'Media', 'shipment' => 'Shipment', 'variants' => 'Variants', 'seo' => 'SEO'] as $k => $l)
            @continue($k === 'variants' && $product->type === 'simple')
            <button type="button" wire:click="$set('tab','{{ $k }}')" @class(['mtab', 'mtab-on' => $tab === $k])>{{ $l }}</button>
        @endforeach
    </div>

    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div class="flex min-w-0 flex-col gap-3">
            @if ($tab === 'general')
                <x-admin.card title="Basic information"><div class="grid gap-3 sm:grid-cols-2">
                    <x-admin.field label="Product name" required class="sm:col-span-2" :error="$errors->first('form.name')"><input wire:model.blur="form.name" class="input"></x-admin.field>
                    <x-admin.field label="Code" :error="$errors->first('form.code')"><input wire:model.blur="form.code" class="input mono"></x-admin.field>
                    <x-admin.field label="Brand"><select wire:model="form.brand_id" class="input"><option value="">No brand</option>@foreach ($brands as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-admin.field>
                    <x-admin.field label="Short description" class="sm:col-span-2"><input wire:model="form.short_description" class="input"></x-admin.field>
                    <x-admin.field label="Full description" class="sm:col-span-2"><textarea wire:model="form.description" rows="6" class="input"></textarea></x-admin.field>
                    <x-admin.field label="Size chart" hint="One row per line, columns separated by |" class="sm:col-span-2"><textarea wire:model.live.debounce.500ms="form.size_chart" rows="5" class="input mono text-sm" placeholder="Size | Waist | Length | Hip"></textarea></x-admin.field>
                </div></x-admin.card>
            @elseif ($tab === 'pricing')
                <x-admin.card title="Pricing" subtitle="Cost is not stored on the product. It is captured from the batch at packing time."><div class="grid gap-3 sm:grid-cols-3">
                    <x-admin.field label="Regular price (৳)" required :error="$errors->first('form.price')"><input type="number" wire:model.blur="form.price" class="input"></x-admin.field>
                    <x-admin.field label="Sale price (৳)" :error="$errors->first('form.sale_price')"><input type="number" wire:model.blur="form.sale_price" class="input"></x-admin.field>
                    <x-admin.field label="Low-stock threshold"><input type="number" wire:model="form.low_stock_threshold" class="input"></x-admin.field>
                </div></x-admin.card>
            @elseif ($tab === 'stock')
                <x-admin.card title="Batch-wise stock" subtitle="Stock enters only through Purchases → Receive. Sales deduct from batches; returns restock into the original batch." flush>
                    <x-slot:actions><a href="{{ route('admin.purchases.index') }}" wire:navigate class="btn-primary btn-sm">Stock in → new batch</a></x-slot:actions>
                    <x-admin.table min-width="640px">
                        <x-slot:head><th class="th">Batch</th><th class="th">Variant · supplier</th><th class="th">Received</th><th class="th text-right">In</th><th class="th text-right">Damaged</th><th class="th text-right">Available</th></x-slot:head>
                        @forelse ($this->batches as $b)<tr><td class="td mono font-semibold">{{ $b->code }}</td><td class="td">{{ $b->variant->color }} {{ $b->variant->size }} <span class="text-xs text-ink-muted">· {{ $b->supplier->name }}</span></td><td class="td">{{ $b->received_at->format('d M') }}</td><td class="td text-right">{{ $b->qty }}</td><td class="td text-right">{{ $b->damaged }}</td><td class="td text-right font-semibold">{{ $b->remaining }}</td></tr>
                        @empty <x-admin.empty colspan="6" title="No batches yet" hint="Stock is 0 until the first purchase is received." /> @endforelse
                    </x-admin.table>
                </x-admin.card>
                <x-admin.card title="Stock history" flush>
                    @foreach ($this->movements as $m)<div class="grid grid-cols-[100px_70px_minmax(0,1fr)_60px] gap-2.5 border-t border-soft px-3.5 py-1.5 text-sm first:border-0"><span class="text-ink-muted">{{ $m->created_at->format('d M H:i') }}</span><span class="mono">{{ $m->batch->code }}</span><span>{{ str_replace('_', ' ', $m->type) }}</span><span @class(['text-right font-semibold', 'text-ok' => $m->qty > 0, 'text-bad' => $m->qty < 0])>{{ $m->qty > 0 ? '+' : '' }}{{ $m->qty }}</span></div>@endforeach
                </x-admin.card>
            @elseif ($tab === 'supplier')
                <x-admin.card title="Supplier-wise name / code" subtitle="How each supplier refers to this product (used on purchase orders).">
                    @foreach ($suppliers as $id => $n)
                        <div class="grid items-end gap-2 border-b border-soft py-2 last:border-0 sm:grid-cols-[160px_1fr_1fr]"><span class="font-medium">{{ $n }}</span><input wire:model="supplierLinks.{{ $id }}.supplier_code" class="input mono" placeholder="Supplier code"><input wire:model="supplierLinks.{{ $id }}.supplier_name" class="input" placeholder="Supplier product name"></div>
                    @endforeach
                </x-admin.card>
            @elseif ($tab === 'media')
                <x-admin.card title="Featured image"><div class="flex flex-wrap items-center gap-4">
                    <img src="{{ $featured ? $featured->temporaryUrl() : ($product->featured_image ? asset('storage/'.$product->featured_image) : '') }}" class="aspect-square w-40 rounded-lg bg-soft object-cover" alt="">
                    <label class="btn-primary cursor-pointer">Upload<input type="file" wire:model="featured" accept="image/*" class="sr-only"></label><span wire:loading wire:target="featured" class="text-sm text-ink-muted">Uploading…</span>
                </div></x-admin.card>
                <x-admin.card title="Video"><input wire:model="form.video_url" class="input" placeholder="YouTube / Facebook URL"></x-admin.card>
            @elseif ($tab === 'shipment')
                <x-admin.card title="Shipping"><x-admin.field label="Weight (kg)" class="max-w-xs"><input type="number" step="0.01" wire:model="form.weight_kg" class="input"></x-admin.field></x-admin.card>
            @elseif ($tab === 'variants')
                <x-admin.card title="Variants" subtitle="Stock is read-only — it comes from batches." flush>
                    <x-admin.table min-width="760px">
                        <x-slot:head><th class="th">Color</th><th class="th">Size</th><th class="th">SKU</th><th class="th">Barcode</th><th class="th">Price</th><th class="th">Sale price</th><th class="th text-right">Stock</th><th class="th"></th></x-slot:head>
                        @foreach ($variants as $i => $v)<tr wire:key="v{{ $i }}">
                            <td class="td"><input wire:model="variants.{{ $i }}.color" class="input py-1"></td><td class="td"><input wire:model="variants.{{ $i }}.size" class="input w-16 py-1"></td>
                            <td class="td"><input wire:model="variants.{{ $i }}.sku" class="input mono py-1 @error('variants.'.$i.'.sku') input-error @enderror"></td><td class="td"><input wire:model="variants.{{ $i }}.barcode" class="input mono py-1"></td>
                            <td class="td"><input type="number" wire:model="variants.{{ $i }}.price" class="input w-24 py-1" placeholder="—"></td><td class="td"><input type="number" wire:model="variants.{{ $i }}.sale_price" class="input w-24 py-1" placeholder="—"></td>
                            <td @class(['td text-right font-semibold', 'text-bad' => ! $v['stock']])>{{ $v['stock'] }}</td><td class="td"><button type="button" wire:click="removeVariant({{ $i }})" class="text-bad">×</button></td></tr>@endforeach
                    </x-admin.table>
                    <div class="p-3"><button type="button" wire:click="addVariant" class="btn-secondary btn-sm">+ Add variant</button></div>
                </x-admin.card>
            @elseif ($tab === 'seo')
                <x-admin.card title="Search engine listing"><div class="grid gap-3">
                    <x-admin.field label="Meta title" :hint="strlen($form['meta_title'] ?? '').' / 60'"><input wire:model.live.debounce.400ms="form.meta_title" class="input"></x-admin.field>
                    <x-admin.field label="Meta description"><textarea wire:model="form.meta_description" rows="3" class="input"></textarea></x-admin.field>
                </div></x-admin.card>
            @endif
        </div>
        <div class="flex flex-col gap-3">
            <x-admin.card title="Status"><select wire:model="form.status" class="input"><option value="active">Active</option><option value="draft">Draft</option><option value="archived">Archived</option></select></x-admin.card>
            <x-admin.card title="Categories"><div class="flex max-h-64 flex-col gap-1.5 overflow-auto">@foreach ($categories as $id => $n)<label class="flex items-center gap-2"><input type="checkbox" value="{{ $id }}" wire:model="categoryIds" class="accent-brand"> {{ $n }}</label>@endforeach</div></x-admin.card>
        </div>
    </div>
</form>
