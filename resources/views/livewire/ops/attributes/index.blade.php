<div class="flex flex-col gap-3">
    @include('livewire.ops.products.partials.inventory-head', ['active' => 'attributes'])

    <div class="grid gap-3 lg:grid-cols-[320px_minmax(0,1fr)]">
        {{-- attribute list --}}
        <x-ops.card title="Attributes" subtitle="Defined once, reused on every product" flush>
            <x-slot:actions><button type="button" wire:click="create" class="btn-primary btn-sm">+ New</button></x-slot:actions>
            @forelse ($this->attributeList as $k => $a)
                <div wire:key="a{{ $a->id }}" @class(['group flex items-center gap-2 border-t border-soft px-3.5 py-2.5 first:border-0', 'bg-brand-tint' => $open === $a->id, 'hover:bg-zebra' => $open !== $a->id])>
                    <button type="button" wire:click="select({{ $a->id }})" class="flex min-w-0 flex-1 flex-col text-left">
                        <span class="font-semibold text-ink">{{ $a->name }}</span>
                        <span class="text-xs text-ink-muted">{{ \App\Models\Ops\Attribute::TYPES[$a->type] ?? $a->type }} · {{ $a->terms_count }} values · {{ $a->products }} products</span>
                    </button>
                    <span class="flex flex-col text-xs leading-none text-ink-faint opacity-0 group-hover:opacity-100">
                        @unless ($loop->first)<button type="button" wire:click="moveAttribute({{ $a->id }}, -1)" aria-label="Move up">▲</button>@endunless
                        @unless ($loop->last)<button type="button" wire:click="moveAttribute({{ $a->id }}, 1)" aria-label="Move down">▼</button>@endunless
                    </span>
                </div>
            @empty
                <div class="px-3.5 py-4 text-sm text-ink-muted">No attributes yet.</div>
            @endforelse
        </x-ops.card>

        {{-- editor --}}
        <x-ops.card :title="$open ? 'Edit attribute' : 'New attribute'" :subtitle="$open ? 'Renaming a value updates every variant that uses it.' : 'e.g. Color, Size, Material, Fit, Sleeve'">
            <x-slot:actions>@if ($open)<button type="button" wire:click="delete" wire:confirm="Delete {{ $form['name'] }} and all its values?" class="btn-secondary btn-sm text-bad">Delete</button>@endif</x-slot:actions>
            <div class="flex flex-col gap-4">
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_200px]">
                    <x-ops.field label="Name" required :error="$errors->first('form.name')"><input wire:model="form.name" class="input" placeholder="Color"></x-ops.field>
                    <x-ops.field label="Display as"><select wire:model.live="form.type" class="input">@foreach (\App\Models\Ops\Attribute::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></x-ops.field>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.is_filterable" class="h-4 w-4 accent-ink"> Show as a filter on the storefront</label>

                <div>
                    <div class="mb-1.5 flex items-center justify-between"><span class="text-sm font-semibold">Values</span><span class="text-xs text-ink-muted">{{ count($terms) }} values · order here = order on the storefront</span></div>
                    <div class="overflow-hidden rounded-md border border-line">
                        @forelse ($terms as $i => $t)
                            @php($used = $this->usage[$t['value']] ?? 0)
                            <div wire:key="t{{ $t['id'] ?? 'n'.$i }}" class="flex items-center gap-2 border-t border-soft px-2.5 py-1.5 first:border-0">
                                <span class="flex w-5 flex-col text-[10px] leading-none text-ink-faint">
                                    @unless ($loop->first)<button type="button" wire:click="move({{ $i }}, -1)" aria-label="Move up">▲</button>@endunless
                                    @unless ($loop->last)<button type="button" wire:click="move({{ $i }}, 1)" aria-label="Move down">▼</button>@endunless
                                </span>
                                @if ($form['type'] === 'color')
                                    <input type="color" wire:model.live="terms.{{ $i }}.hex" class="h-7 w-9 flex-none cursor-pointer rounded border border-line-2 bg-white p-0.5" title="Swatch colour">
                                @endif
                                <input wire:model="terms.{{ $i }}.value" @class(['input py-1', 'input-error' => $errors->has('terms.'.$i.'.value')])>
                                <span class="w-24 flex-none text-right text-xs text-ink-muted">{{ $used ? $used.' variants' : 'unused' }}</span>
                                <button type="button" wire:click="removeValue({{ $i }})" @if (! $used) wire:confirm="Remove {{ $t['value'] }}?" @endif @class(['w-5 flex-none text-lg leading-none', 'text-bad' => ! $used, 'cursor-not-allowed text-ink-faint' => $used]) title="{{ $used ? 'In use — rename instead' : 'Remove' }}">×</button>
                            </div>
                            @error('terms.'.$i.'.value')<div class="px-10 pb-1 text-xs text-bad">{{ $message }}</div>@enderror
                        @empty
                            <div class="px-3 py-4 text-center text-sm text-ink-muted">No values yet — add some below.</div>
                        @endforelse
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        @if ($form['type'] === 'color')<input type="color" wire:model="newHex" class="h-8 w-10 cursor-pointer rounded border border-line-2 bg-white p-0.5" title="Swatch for new values">@endif
                        <input wire:model="newValues" wire:keydown.enter.prevent="addValues" class="input min-w-0 flex-1" placeholder="Add values — comma separated, e.g. Red, Maroon, Pink">
                        <button type="button" wire:click="addValues" class="btn-secondary">+ Add values</button>
                    </div>
                </div>

                @if ($form['type'] !== 'text' && count($terms))
                    <div>
                        <span class="mb-1.5 block text-xs text-ink-muted">Storefront preview</span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($terms as $t)
                                @if ($form['type'] === 'color')<span class="h-7 w-7 rounded-full border border-line-2 shadow-sm" style="background: {{ $t['hex'] ?: '#fff' }}" title="{{ $t['value'] }}"></span>
                                @else<span class="min-w-[36px] rounded-md border border-line-2 px-2 py-1 text-center text-sm">{{ $t['value'] }}</span>@endif
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex justify-end gap-2 border-t border-soft pt-3">
                    <button type="button" wire:click="save" class="btn-primary">{{ $open ? 'Save attribute' : 'Create attribute' }}</button>
                </div>
            </div>
        </x-ops.card>
    </div>
</div>
