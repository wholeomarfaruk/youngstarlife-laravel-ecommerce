<form wire:submit="save" class="flex flex-col gap-3"
      x-data="{ dirty: false }" @input="dirty = true" @change="dirty = true" x-on:saved.window="dirty = false"
      x-init="window.opsDirty = false; $watch('dirty', v => window.opsDirty = v)">
    <a href="{{ route('ops.products.index') }}" wire:navigate class="self-start text-sm">← Back to products</a>
    <x-ops.page-header :title="$product->name" :subtitle="ucfirst($product->type).' product · '.$product->code">
        <span x-cloak x-show="dirty" class="text-sm text-warn">● Unsaved changes</span>
        <a href="{{ route('ops.products.index') }}" wire:navigate class="btn-secondary">Cancel</a>
        <button class="btn-primary" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving…</span></button>
    </x-ops.page-header>

    @if ($errors->any())<div class="rounded-md bg-bad-bg px-3 py-2.5 text-bad"><span class="font-semibold">Not saved:</span> {{ $errors->first() }}</div>@endif
    <div class="flex gap-1 overflow-x-auto border-b border-line-2 [scrollbar-width:none]">
        @foreach (['general' => 'General', 'pricing' => 'Pricing', 'stock' => 'Stocks', 'supplier' => 'Supplier', 'media' => 'Media', 'shipment' => 'Shipment', 'variants' => 'Variants', 'combo' => 'Combo', 'seo' => 'SEO'] as $k => $l)
            @continue($k === 'variants' && $product->type === 'simple')
            @continue($k === 'combo' && $product->type !== 'combo')
            <button type="button" wire:click="$set('tab','{{ $k }}')" @class(['mtab', 'mtab-on' => $tab === $k])>{{ $l }}</button>
        @endforeach
    </div>

    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div class="flex min-w-0 flex-col gap-3">
            @if ($tab === 'general')
                <x-ops.card title="Basic information"><div class="grid gap-3 sm:grid-cols-2">
                    <x-ops.field label="Product name" required class="sm:col-span-2" :error="$errors->first('form.name')"><input wire:model.blur="form.name" class="input"></x-ops.field>
                    <x-ops.field label="Code" required :error="$errors->first('form.code')"><input wire:model.blur="form.code" class="input mono"></x-ops.field>
                    <x-ops.field label="Brand"><x-ops.searchable-select field="form.brand_id" :options="$brands" placeholder="No brand" searchPlaceholder="Search brands…" /><div class="flex gap-1.5"><input wire:model="newBrand" wire:keydown.enter.prevent="addBrand" class="input py-1" placeholder="New brand…"><button type="button" wire:click="addBrand" class="btn-secondary btn-sm">Add</button></div></x-ops.field>
                    <x-ops.field label="Short description" class="sm:col-span-2"><input wire:model="form.short_description" class="input"></x-ops.field>
                    <x-ops.field label="Full description" class="sm:col-span-2" hint="Plain lines or a simple list show as ✓ points on the product page; bold, headings, images, tables or videos show as written"><x-ops.rich-text wire:model="form.description" :value="$form['description'] ?? ''" /></x-ops.field>
                </div></x-ops.card>
                @if ($product->type !== 'variable' && isset($variants[0]))
                    <x-ops.card :title="$product->type === 'combo' ? 'Combo barcode' : 'Product barcode'" :subtitle="$product->type === 'combo' ? 'Scanning it picks the whole set. The linked products keep their own barcodes (Combo tab).' : 'Printed on labels and used by every scanner. Empty = an EAN-13 is generated on save; type a supplier barcode to use that instead.'">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ops.field label="Barcode" :error="$errors->first('variants.0.barcode')"><input wire:model="variants.0.barcode" class="input mono" placeholder="Auto"></x-ops.field>
                            <x-ops.field label="SKU" required :error="$errors->first('variants.0.sku')"><input wire:model="variants.0.sku" class="input mono"></x-ops.field>
                        </div>
                    </x-ops.card>
                @endif
                <x-ops.card title="Size chart" subtitle="One row per line, columns separated by |">
                    <x-slot:actions><button type="button" wire:click="insertSizeTemplate" class="btn-secondary btn-sm">Insert template</button></x-slot:actions>
                    <textarea wire:model.live.debounce.400ms="form.size_chart" rows="5" class="input mono text-sm" placeholder="Size | Waist | Length | Hip"></textarea>
                    @php($rows = collect(preg_split('/\r?\n/', (string) ($form['size_chart'] ?? '')))->map(fn ($l) => array_map('trim', explode('|', $l)))->filter(fn ($r) => count($r) > 1 || $r[0] !== ''))
                    @if ($rows->isNotEmpty())
                        <div class="mt-3 overflow-x-auto rounded-md border border-line"><table class="w-full text-sm">@foreach ($rows as $i => $r)<tr>@foreach ($r as $cell)@if ($i === 0)<th class="th">{{ $cell }}</th>@else<td class="border-b border-soft px-3 py-1.5">{{ $cell }}</td>@endif @endforeach</tr>@endforeach</table></div>
                    @endif
                </x-ops.card>
            @elseif ($tab === 'pricing')
                <x-ops.card title="Pricing" subtitle="Cost comes from the batch at packing — it is not stored on the product."><div class="grid gap-3 sm:grid-cols-2">
                    <x-ops.field label="Regular price (৳)" required :error="$errors->first('form.price')"><input type="number" min="0" wire:model.blur="form.price" class="input"></x-ops.field>
                    <x-ops.field label="Sale price (৳)" :error="$errors->first('form.sale_price')" hint="Must be lower than the regular price"><input type="number" min="0" wire:model.blur="form.sale_price" class="input"></x-ops.field>
                    <x-ops.field label="Sale starts"><x-ops.date wire:model="form.sale_starts_at" class="w-full" /></x-ops.field>
                    <x-ops.field label="Sale ends" :error="$errors->first('form.sale_ends_at')"><x-ops.date wire:model="form.sale_ends_at" class="w-full" /></x-ops.field>
                    <x-ops.field label="Tax class"><select wire:model="form.tax_class" class="input"><option value="">Standard (no VAT)</option><option value="vat5">VAT 5%</option><option value="vat7.5">VAT 7.5%</option><option value="vat15">VAT 15%</option></select></x-ops.field>
                    <x-ops.field label="Low-stock threshold" :error="$errors->first('form.low_stock_threshold')"><input type="number" min="0" wire:model="form.low_stock_threshold" class="input"></x-ops.field>
                </div></x-ops.card>
            @elseif ($tab === 'stock')
                @php($b = $this->batches)
                <x-ops.kpis><x-ops.kpi label="Current stock" :value="ops_num($product->variants->sum('stock'))" hint="Σ batch remaining · read-only" /><x-ops.kpi label="Reserved" :value="ops_num($product->variants->sum('reserved'))" tone="info" /><x-ops.kpi label="Damaged" :value="ops_num($product->variants->sum('damaged'))" tone="bad" /><x-ops.kpi label="Batches" :value="$b->count()" /></x-ops.kpis>
                <x-ops.card title="Batch-wise stock" subtitle="Stock enters only through Purchases → Receive. Sales deduct from batches; returns restock into the original batch. No opening stock." flush>
                    <x-slot:actions><a href="{{ route('ops.purchases.index') }}" wire:navigate class="btn-primary btn-sm">Stock in → new batch</a></x-slot:actions>
                    <x-ops.table min-width="820px">
                        <x-slot:head><th class="th">Batch</th><th class="th">Variant · supplier · PO</th><th class="th">Received</th><th class="th text-right">In</th><th class="th text-right">Sold</th><th class="th text-right">Returned</th><th class="th text-right">Damaged</th><th class="th text-right">Available</th></x-slot:head>
                        @forelse ($b as $x)<tr class="tr-hover"><td class="td mono font-semibold"><a href="{{ route('ops.trace', ['q' => $x->code]) }}" wire:navigate>{{ $x->code }}</a></td><td class="td">{{ $x->variant->color }} {{ $x->variant->size }} <span class="text-xs text-ink-muted">· {{ $x->supplier->name }} · <span class="mono">{{ $x->purchaseOrder?->number ?? '—' }}</span></span></td><td class="td">{{ $x->received_at->format('d M') }}</td><td class="td text-right">{{ $x->qty }}</td><td class="td text-right">{{ (int) -$x->sold }}</td><td class="td text-right">{{ (int) $x->returned }}</td><td class="td text-right text-bad">{{ $x->damaged ?: '—' }}</td><td class="td text-right font-semibold">{{ $x->remaining }}</td></tr>
                        @empty <x-ops.empty colspan="8" title="No batches yet" hint="Stock is 0 until the first purchase is received." /> @endforelse
                        @if ($b->isNotEmpty())<tr class="bg-zebra font-semibold"><td class="td" colspan="3">Total</td><td class="td text-right">{{ $b->sum('qty') }}</td><td class="td text-right">{{ (int) -$b->sum('sold') }}</td><td class="td text-right">{{ (int) $b->sum('returned') }}</td><td class="td text-right text-bad">{{ $b->sum('damaged') }}</td><td class="td text-right">{{ $b->sum('remaining') }}</td></tr>@endif
                    </x-ops.table>
                </x-ops.card>
                <x-ops.card title="Stock history" flush>
                    <x-ops.table min-width="640px">
                        <x-slot:head><th class="th">Date</th><th class="th">Batch</th><th class="th">Type</th><th class="th text-right">Qty</th><th class="th">Ref</th><th class="th">User</th></x-slot:head>
                        @forelse ($this->movements as $m)<tr><td class="td text-sm text-ink-muted">{{ $m->created_at->format('d M H:i') }}</td><td class="td mono">{{ $m->batch->code }}</td><td class="td">{{ config('ops.movement_types.'.$m->type, $m->type) }}@if ($m->note)<div class="text-xs text-ink-muted">{{ $m->note }}</div>@endif</td><td @class(['td text-right font-semibold', 'text-ok' => $m->qty > 0, 'text-bad' => $m->qty < 0])>{{ $m->qty > 0 ? '+' : '' }}{{ $m->qty }}</td><td class="td mono text-xs">{{ $m->ref?->number ?? $m->ref?->code ?? '—' }}</td><td class="td text-sm">{{ $this->users[$m->user_id] ?? 'System' }}</td></tr>
                        @empty <x-ops.empty colspan="6" title="No stock movements yet" hint="" /> @endforelse
                    </x-ops.table>
                </x-ops.card>
            @elseif ($tab === 'supplier')
                <x-ops.card title="Supplier-wise name / code" subtitle="How each supplier refers to this product (shown on purchase orders). No default supplier.">
                    @foreach ($suppliers as $id => $n)
                        <div class="grid items-center gap-2 border-b border-soft py-2 last:border-0 sm:grid-cols-[150px_1fr_1fr_auto]">
                            <span class="font-medium">{{ $n }}</span>
                            <input wire:model="supplierLinks.{{ $id }}.supplier_code" class="input mono" placeholder="Supplier code">
                            <input wire:model="supplierLinks.{{ $id }}.supplier_name" class="input" placeholder="Supplier product name">
                            <label class="flex cursor-pointer items-center gap-2">@php($img = $supplierImages[$id] ?? null)
                                @if ($img)<img src="{{ $img->temporaryUrl() }}" class="h-9 w-9 rounded object-cover" alt="">@elseif ($supplierLinks[$id]['image'] ?? null)<img src="{{ asset('storage/'.$supplierLinks[$id]['image']) }}" class="h-9 w-9 rounded object-cover" alt="">@else<span class="grid h-9 w-9 place-items-center rounded border border-dashed border-line-2 text-xs text-ink-muted">IMG</span>@endif
                                <span class="btn-secondary btn-sm">Image</span><input type="file" wire:model="supplierImages.{{ $id }}" accept="image/*" class="sr-only"></label>
                        </div>
                    @endforeach
                </x-ops.card>
            @elseif ($tab === 'media')
                <x-ops.card title="Featured image" subtitle="Square, 1200 × 1200 recommended"><div class="flex flex-wrap items-center gap-4">
                    @if ($featured)<img src="{{ $featured->temporaryUrl() }}" class="aspect-square w-40 rounded-lg bg-soft object-cover" alt="">@elseif ($product->featured_image)<img src="{{ asset('storage/'.$product->featured_image) }}" class="aspect-square w-40 rounded-lg bg-soft object-cover" alt="">@else<span class="grid aspect-square w-40 place-items-center rounded-lg border border-dashed border-line-2 text-sm text-ink-muted">No image</span>@endif
                    <div class="flex flex-col gap-2"><label class="btn-primary cursor-pointer">{{ $product->featured_image ? 'Replace' : 'Upload' }}<input type="file" wire:model="featured" accept="image/*" class="sr-only"></label>
                        @if ($product->featured_image)<button type="button" wire:click="removeFeatured" wire:confirm="Remove the featured image?" class="btn-danger">Remove</button>@endif
                        <span wire:loading wire:target="featured" class="text-sm text-ink-muted">Uploading…</span></div>
                </div></x-ops.card>
                <x-ops.card title="Gallery" subtitle="★ makes an image the featured image">
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(110px,1fr))] gap-2.5">
                        @foreach ($form['gallery'] ?? [] as $i => $g)<div class="group relative"><img src="{{ asset('storage/'.$g) }}" class="aspect-square w-full rounded-md bg-soft object-cover" alt=""><div class="absolute inset-x-1 top-1 flex justify-between"><button type="button" wire:click="makeFeatured({{ $i }})" class="grid h-7 w-7 place-items-center rounded bg-white text-warn shadow-seg" title="Make featured">★</button><button type="button" wire:click="removeGallery({{ $i }})" class="grid h-7 w-7 place-items-center rounded bg-white text-bad shadow-seg" title="Remove">×</button></div></div>@endforeach
                        @foreach ($galleryUploads as $u)<img src="{{ $u->temporaryUrl() }}" class="aspect-square w-full rounded-md bg-soft object-cover opacity-70" alt="">@endforeach
                        <label class="grid aspect-square cursor-pointer place-items-center rounded-md border border-dashed border-line-2 text-sm text-ink-muted hover:bg-zebra">+ Add images<input type="file" wire:model="galleryUploads" accept="image/*" multiple class="sr-only"></label>
                    </div>
                </x-ops.card>
                <x-ops.card title="Video"><x-ops.field label="Video URL" :error="$errors->first('form.video_url')"><input wire:model="form.video_url" class="input" placeholder="YouTube / Facebook URL"></x-ops.field></x-ops.card>
            @elseif ($tab === 'shipment')
                <x-ops.card title="Shipping"><div class="grid gap-3 sm:grid-cols-4">
                    <x-ops.field label="Weight (kg)" :error="$errors->first('form.weight_kg')"><input type="number" step="0.01" min="0" wire:model="form.weight_kg" class="input"></x-ops.field>
                    <x-ops.field label="Length (cm)"><input type="number" min="0" wire:model="form.dim.l" class="input"></x-ops.field>
                    <x-ops.field label="Width (cm)"><input type="number" min="0" wire:model="form.dim.w" class="input"></x-ops.field>
                    <x-ops.field label="Height (cm)"><input type="number" min="0" wire:model="form.dim.h" class="input"></x-ops.field>
                    <label class="flex items-center gap-2 sm:col-span-4"><input type="checkbox" wire:model="form.requires_shipping" class="h-4 w-4 accent-brand"> Requires shipping (physical product)</label>
                </div></x-ops.card>
            @elseif ($tab === 'variants')
                @php($cat = $this->catalog) @php($usedIds = collect($options)->pluck('attribute_id')->all()) @php($combos = count($this->combinations())) @php($missing = $this->missingCount())
                <x-ops.card title="Options" subtitle="Pick from your global attributes — values are shared by every product.">
                    <x-slot:actions><a href="{{ route('ops.attributes.index') }}" wire:navigate class="text-sm">Manage attributes →</a></x-slot:actions>
                    <div class="flex flex-col gap-3">
                        @forelse ($options as $i => $o)
                            @php($a = $cat[$o['attribute_id']] ?? null)
                            @continue(! $a)
                            <div wire:key="opt{{ $a->id }}" class="rounded-md border border-line p-3">
                                <div class="mb-2 flex items-center gap-2">
                                    <span class="font-semibold">{{ $a->name }}</span><span class="text-xs text-ink-muted">{{ count($o['values']) }} of {{ $a->terms->count() }} selected</span>
                                    <span class="ml-auto flex items-center gap-3 text-xs"><button type="button" wire:click="selectAll({{ $i }})" class="text-brand">All</button><button type="button" wire:click="selectAll({{ $i }}, false)" class="text-ink-muted">None</button><button type="button" wire:click="removeOption({{ $i }})" wire:confirm="Remove {{ $a->name }} from this product?" class="text-bad">Remove</button></span>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($a->terms as $t)
                                        @php($on = in_array($t->id, $o['values']))
                                        <button type="button" wire:click="toggleValue({{ $i }}, {{ $t->id }})" @class(['flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-sm', 'border-brand bg-brand-tint font-medium text-brand' => $on, 'border-line-2 bg-white text-ink-3 hover:border-ink-faint' => ! $on, 'min-w-[40px] justify-center' => $a->type === 'size'])>
                                            @if ($a->type === 'color')<span class="h-3.5 w-3.5 rounded-full border border-line-2" style="background: {{ $t->hex ?: '#fff' }}"></span>@endif
                                            {{ $t->value }}@if ($on)<span class="text-xs">✓</span>@endif
                                        </button>
                                    @endforeach
                                    <span class="flex items-center gap-1"><input wire:model="newTerm.{{ $i }}" wire:keydown.enter.prevent="quickTerm({{ $i }})" class="input w-32 py-1 text-sm" placeholder="+ New value"><button type="button" wire:click="quickTerm({{ $i }})" class="btn-secondary btn-sm">Add</button></span>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-md border border-dashed border-line-2 p-4 text-center text-sm text-ink-muted">No options yet — add Color, Size or any attribute to build variants.</div>
                        @endforelse
                        <div class="flex flex-wrap items-center gap-2">
                            @php($left = $cat->reject(fn ($x) => in_array($x->id, $usedIds)))
                            @if ($left->isNotEmpty())
                                <select wire:model="addOption" class="input w-auto py-1"><option value="">Add option…</option>@foreach ($left as $x)<option value="{{ $x->id }}">{{ $x->name }} ({{ $x->terms->count() }})</option>@endforeach</select>
                                <button type="button" wire:click="addOptionRow" class="btn-secondary btn-sm">+ Add option</button>
                            @endif
                            <span class="ml-auto text-sm text-ink-muted">{{ $combos }} combinations · <b @class(['text-brand' => $missing])>{{ $missing }} to create / update</b></span>
                            <button type="button" wire:click="generate" @disabled(! $missing) class="btn-dark btn-sm disabled:opacity-50">{{ $missing ? 'Generate '.$missing.' variants' : 'Up to date' }}</button>
                        </div>
                    </div>
                </x-ops.card>

                @php($optNames = collect($options)->map(fn ($o) => $cat[$o['attribute_id']] ?? null)->filter()->values())
                <x-ops.card title="Variants" :subtitle="count($variants).' variants · stock is read-only — it comes from batches · every variant has its own barcode (empty = auto-generated EAN-13 on save) · no cost column: cost is captured at packing.'" flush>
                    <div class="flex flex-wrap items-end gap-2 border-b border-soft bg-zebra p-3">
                        <x-ops.field label="Price for all"><div class="flex gap-1"><input type="number" min="0" wire:model="allPrice" class="input w-28 py-1"><button type="button" wire:click="applyAll('price')" class="btn-secondary btn-sm">Apply</button></div></x-ops.field>
                        <x-ops.field label="Sale price for all"><div class="flex gap-1"><input type="number" min="0" wire:model="allSale" class="input w-28 py-1"><button type="button" wire:click="applyAll('sale_price')" class="btn-secondary btn-sm">Apply</button></div></x-ops.field>
                    </div>
                    <x-ops.table min-width="820px">
                        <x-slot:head>@forelse ($optNames as $a)<th class="th">{{ $a->name }}</th>@empty<th class="th">Color</th><th class="th">Size</th>@endforelse<th class="th">SKU</th><th class="th">Barcode</th><th class="th">Price</th><th class="th">Sale price</th><th class="th text-right">Stock</th><th class="th"></th></x-slot:head>
                        @foreach ($variants as $i => $v)<tr wire:key="v{{ $v['id'] ?? 'n'.$i }}">
                            @forelse ($optNames as $a)
                                @php($vals = $a->terms->whereIn('id', collect($options)->firstWhere('attribute_id', $a->id)['values'] ?? []))
                                <td class="td"><span class="flex items-center gap-1.5">@if ($a->type === 'color')<span class="h-3.5 w-3.5 flex-none rounded-full border border-line-2" style="background: {{ $a->terms->firstWhere('value', $v['options'][$a->name] ?? null)?->hex ?: '#fff' }}"></span>@endif
                                    <select wire:model.live="variants.{{ $i }}.options.{{ $a->name }}" class="input min-w-[84px] py-1"><option value="">—</option>@foreach ($vals as $t)<option>{{ $t->value }}</option>@endforeach @if (($cur = $v['options'][$a->name] ?? null) && ! $vals->contains('value', $cur))<option>{{ $cur }}</option>@endif</select></span></td>
                            @empty
                                <td class="td"><input wire:model="variants.{{ $i }}.color" class="input py-1"></td><td class="td"><input wire:model="variants.{{ $i }}.size" class="input w-16 py-1"></td>
                            @endforelse
                            <td class="td"><input wire:model="variants.{{ $i }}.sku" class="input mono py-1 @error('variants.'.$i.'.sku') input-error @enderror">@error('variants.'.$i.'.sku')<div class="text-xs text-bad">{{ $message }}</div>@enderror</td><td class="td"><input wire:model="variants.{{ $i }}.barcode" placeholder="Auto" title="Leave empty to generate an EAN-13; type a supplier barcode to use it instead" class="input mono py-1 @error('variants.'.$i.'.barcode') input-error @enderror"></td>
                            <td class="td"><input type="number" min="0" wire:model="variants.{{ $i }}.price" class="input w-24 py-1" placeholder="—"></td><td class="td"><input type="number" min="0" wire:model="variants.{{ $i }}.sale_price" class="input w-24 py-1" placeholder="—"></td>
                            <td @class(['td text-right font-semibold', 'text-bad' => ! $v['stock']])>{{ $v['stock'] }}</td><td class="td"><button type="button" wire:click="removeVariant({{ $i }})" class="text-bad" aria-label="Remove">×</button></td></tr>@endforeach
                    </x-ops.table>
                    <div class="flex flex-wrap items-center gap-2 border-t border-soft p-3">
                        <button type="button" wire:click="addVariant" class="btn-secondary btn-sm">+ Add variant</button>
                        <span class="text-xs text-ink-muted">Removed variants with sales, batches or purchase orders are kept (deactivate them instead).</span>
                    </div>
                </x-ops.card>
            @elseif ($tab === 'combo')
                <x-ops.card title="Combo components" :subtitle="'Available sets = min over components · now '.($product->type === 'combo' ? $product->load('comboItems.variant')->combo_available : 0)" flush>
                    @if (isset($variants[0]))
                        <div class="flex flex-wrap items-center gap-2 border-b border-soft bg-zebra px-3.5 py-2.5 text-sm"><span class="font-semibold">Combo barcode</span><span class="mono">{{ $variants[0]['barcode'] ?: 'generated on save' }}</span><span class="text-xs text-ink-muted">· scanning it picks the whole set · edit on the General tab</span></div>
                    @endif
                    @foreach ($combo as $i => $c)
                        @php($cv = $this->allVariants->firstWhere('id', $c['variant_id']))
                        <div class="flex items-center gap-2.5 border-b border-soft px-3.5 py-2"><span class="w-24 flex-none text-xs font-semibold uppercase tracking-wide text-ink-muted">Linked {{ $loop->iteration }}</span><span class="min-w-0 flex-1">{{ $cv?->label }} <span class="mono block text-xs text-ink-muted">barcode {{ $cv?->barcode ?: '—' }} · {{ $cv?->sku }} · {{ $cv?->stock }} in stock</span></span><input type="number" min="1" wire:model="combo.{{ $i }}.qty" class="input w-20 py-1"><button type="button" wire:click="removeComboItem({{ $i }})" class="text-bad">×</button></div>
                    @endforeach
                    <div class="flex gap-2 p-3">
                        {{-- search by name, size, SKU or barcode — scanning a barcode into it works too --}}
                        <x-ops.searchable-select field="comboPick" class="flex-1" placeholder="Add a linked product…" searchPlaceholder="Name, size, SKU or barcode…"
                            :options="$this->allVariants->mapWithKeys(fn ($v) => [$v->id => $v->label.' · '.$v->sku.' · '.$v->barcode])->all()"
                            :images="$this->allVariants->mapWithKeys(fn ($v) => [$v->id => $v->product->thumb_url])->all()" />
                        <button type="button" wire:click="addComboItem" class="btn-secondary">+ Add</button></div>
                </x-ops.card>
            @elseif ($tab === 'seo')
                <x-ops.card title="Search engine listing"><div class="grid gap-3">
                    <x-ops.field label="Meta title" :hint="strlen($form['meta_title'] ?? '').' / 60'"><input wire:model.live.debounce.400ms="form.meta_title" maxlength="70" class="input"></x-ops.field>
                    <x-ops.field label="Slug" :error="$errors->first('form.slug')" :hint="url('/product/'.($form['slug'] ?? ''))"><input wire:model.live.debounce.400ms="form.slug" class="input mono"></x-ops.field>
                    <x-ops.field label="Meta description" :hint="strlen($form['meta_description'] ?? '').' / 160'"><textarea wire:model.live.debounce.400ms="form.meta_description" rows="3" maxlength="300" class="input"></textarea></x-ops.field>
                    <div class="rounded-md border border-line p-3"><div class="text-xs text-ink-muted">Google preview</div><div class="truncate text-lg text-info">{{ $form['meta_title'] ?: $product->name }}</div><div class="mono truncate text-xs text-ok">{{ url('/product/'.($form['slug'] ?? '')) }}</div><div class="text-sm text-ink-3">{{ \Illuminate\Support\Str::limit($form['meta_description'] ?: $form['short_description'], 160) }}</div></div>
                </div></x-ops.card>
            @endif
        </div>
        <div class="flex flex-col gap-3">
            <x-ops.card title="Status"><select wire:model="form.status" class="input">@foreach (config('ops.product_status') as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                <p class="mt-1.5 text-xs text-ink-muted">Only <b>Active</b> products show on the website.</p>
                @php($typeHelp = ['simple' => 'One item, no options.', 'variable' => 'Sizes / colours — each is a variant with its own SKU and stock.', 'combo' => 'A set made of other products; stock comes from those products.'])
                <x-ops.field label="Product type" class="mt-3">
                    {{-- applied at once (tabs depend on it); unsaved edits in the form are kept --}}
                    <select class="input" wire:key="type-{{ $product->type }}"
                            x-on:change="if (confirm('Change this product to ' + $event.target.selectedOptions[0].text + '?')) $wire.changeType($event.target.value); else $event.target.value = @js($product->type)">
                        @foreach (config('ops.product_types') as $k => $l)<option value="{{ $k }}" @selected($product->type === $k)>{{ $l }}</option>@endforeach
                    </select>
                </x-ops.field>
                <p class="mt-1 text-xs text-ink-muted">{{ $typeHelp[$product->type] ?? '' }}</p></x-ops.card>
            <x-ops.card title="Website">
                <div class="flex flex-col gap-2.5">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.sold_out" class="h-4 w-4 accent-brand"> Show as “Stock out” (can't be ordered)</label>
                    <x-ops.field label="Listing order" hint="Lower shows first on Home and Shop" :error="$errors->first('form.sort_order')"><input type="number" wire:model="form.sort_order" class="input"></x-ops.field>
                    @if ($product->status === 'active')<a href="{{ route('product.show', $product->slug) }}" target="_blank" class="text-sm">View on website ↗</a>@endif
                </div>
            </x-ops.card>
            <x-ops.card title="Categories"><div class="flex max-h-64 flex-col gap-1.5 overflow-auto">@forelse ($categories as $id => $n)<label class="flex items-center gap-2"><input type="checkbox" value="{{ $id }}" wire:model="categoryIds" class="accent-brand"> {{ $n }}</label>@empty<span class="text-sm text-ink-muted">No categories yet.</span>@endforelse</div><div class="mt-2 flex gap-1.5 border-t border-soft pt-2"><input wire:model="newCategory" wire:keydown.enter.prevent="addCategory" class="input py-1" placeholder="New category…"><button type="button" wire:click="addCategory" class="btn-secondary btn-sm">Add</button></div></x-ops.card>
            <x-ops.card title="Tags"><div class="flex flex-wrap gap-1.5">@foreach ($tags as $i => $t)<span class="chip">{{ $t }}<button type="button" wire:click="removeTag({{ $i }})" class="text-ink-muted">×</button></span>@endforeach</div><div class="mt-2 flex gap-1.5"><input wire:model="newTag" wire:keydown.enter.prevent="addTag" class="input py-1" placeholder="Add tag, press Enter"><button type="button" wire:click="addTag" class="btn-secondary btn-sm">Add</button></div></x-ops.card>
            <x-ops.card title="Summary"><dl class="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1.5 text-sm">
                <dt class="text-ink-muted">Type</dt><dd class="capitalize">{{ $product->type }}</dd>
                <dt class="text-ink-muted">Variants</dt><dd>{{ $product->variants->count() }}</dd>
                <dt class="text-ink-muted">Stock</dt><dd @class(['font-semibold', 'text-bad' => $product->variants->sum('stock') < $product->low_stock_threshold])>{{ $product->variants->sum('stock') }}</dd>
                <dt class="text-ink-muted">Price</dt><dd>@if ($product->sale_price){{ tk($product->sale_price) }} <s class="text-ink-muted">{{ tk($product->price) }}</s>@else{{ tk($product->price) }}@endif</dd>
                <dt class="text-ink-muted">Updated</dt><dd>{{ $product->updated_at?->diffForHumans() }}</dd>
            </dl></x-ops.card>
        </div>
    </div>
</form>
