<div class="flex flex-col gap-3">
    <section class="card">
        <x-admin.toolbar><x-admin.search placeholder="Search name or phone…" /><button wire:click="newDraft" class="btn-primary ml-auto">+ New draft</button></x-admin.toolbar>
        @if ($editing !== null)
            <div class="flex flex-col gap-3 border-b border-soft bg-zebra p-3.5">
                <div class="flex items-center justify-between text-sm"><span class="font-medium text-brand" wire:loading.remove wire:target="d">● Saved · auto-saved</span><span wire:loading wire:target="d" class="text-ink-muted">Saving…</span></div>
                <div class="grid gap-2.5 sm:grid-cols-3">
                    <x-admin.field label="Customer name" required :error="$errors->first('d.name')"><input wire:model.live.debounce.600ms="d.name" class="input"></x-admin.field>
                    <x-admin.field label="Phone" required :error="$errors->first('d.phone')"><input wire:model.live.debounce.600ms="d.phone" inputmode="tel" class="input" placeholder="01XXXXXXXXX"></x-admin.field>
                    <x-admin.field label="Delivery area" required :error="$errors->first('d.zone')"><select wire:model.live="d.zone" class="input"><option value="">Choose…</option>@foreach ($this->zones as $z)<option value="{{ $z->id }}">{{ $z->name }} · {{ tk($z->charge) }}</option>@endforeach</select></x-admin.field>
                    <x-admin.field label="Full address" required class="sm:col-span-3" :error="$errors->first('d.address')"><input wire:model.live.debounce.600ms="d.address" class="input"></x-admin.field>
                </div>
                <div class="card">@foreach ($d['lines'] ?? [] as $k => $l)<div class="flex items-center gap-2 border-b border-soft px-2.5 py-2"><span class="flex-1">{{ $this->variants->firstWhere('id', $l['variant_id'])?->label }}</span><input type="number" min="1" wire:model.live.debounce.400ms="d.lines.{{ $k }}.qty" class="input w-16 py-1"></div>@endforeach
                    <div class="flex gap-2 p-2.5"><select wire:model="pick" class="input"><option value="">Add product…</option>@foreach ($this->variants as $v)<option value="{{ $v->id }}">{{ $v->label }} · {{ tk($v->effective_price) }} · {{ $v->stock }} in stock</option>@endforeach</select><button wire:click="addLine" class="btn-secondary">+ Add</button></div></div>
                <div class="flex flex-wrap justify-end gap-2"><button wire:click="discard({{ $editing }})" class="btn-danger">Discard</button><button wire:click="convert" class="btn-primary">Create order →</button></div>
            </div>
        @endif
        @forelse ($this->drafts as $o)
            <button wire:click="edit({{ $o->id }})" wire:key="dr{{ $o->id }}" class="flex w-full flex-wrap items-center gap-x-3.5 gap-y-1 border-b border-soft px-3 py-2.5 text-left hover:bg-zebra">
                <span class="mono font-semibold text-brand">{{ $o->number }}</span><span class="flex-[1_1_200px] font-semibold">{{ $o->ship_name ?: 'Unnamed customer' }} <span class="text-sm font-normal text-ink-muted">{{ $o->ship_phone }} · {{ $o->items->map(fn ($i) => $i->variant->label.' ×'.$i->qty)->join(', ') ?: 'no products' }}</span></span>
                <span class="font-bold tabular-nums">{{ tk($o->total) }}</span><span class="text-xs text-ink-muted">Saved {{ $o->updated_at->diffForHumans() }}</span>
            </button>
        @empty <div class="p-7 text-center text-ink-muted">No drafts. Orders you start but don't finish are saved automatically.</div> @endforelse
        <x-admin.pagination :paginator="$this->drafts" />
    </section>
</div>
