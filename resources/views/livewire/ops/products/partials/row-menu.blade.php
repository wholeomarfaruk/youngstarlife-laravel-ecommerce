{{-- product ⋯ menu: catalog table row and phone card (needs the Products\Index actions) --}}
<x-ops.row-menu>
    <a href="{{ route('ops.products.edit', $p) }}" wire:navigate class="flex items-center justify-between rounded px-2 py-1.5 text-ink hover:bg-soft">Edit product</a>
    <a href="{{ route('ops.products.edit', ['product' => $p, 'tab' => 'stock']) }}" wire:navigate class="flex items-center justify-between rounded px-2 py-1.5 text-ink hover:bg-soft">Stock & batches</a>
    <a href="{{ route('ops.batches.index', ['search' => $p->name]) }}" wire:navigate class="flex items-center justify-between rounded px-2 py-1.5 text-ink hover:bg-soft">View batches</a>
    <a href="{{ route('ops.labels.print', ['products' => $p->id]) }}" target="_blank" class="flex items-center justify-between rounded px-2 py-1.5 text-ink hover:bg-soft">Print labels <span class="text-ink-faint">↗</span></a>
    {{-- the website shows Active products only — a draft's page would be a 404 --}}
    @if ($p->status === 'active' && $p->slug)
        <a href="{{ route('product.show', $p->slug) }}" target="_blank" rel="noopener" class="flex items-center justify-between rounded px-2 py-1.5 text-ink hover:bg-soft">Open in store <span class="text-ink-faint">↗</span></a>
    @else
        <span class="flex items-center justify-between rounded px-2 py-1.5 text-ink-faint" title="Only Active products show on the website">Open in store <span>not active</span></span>
    @endif
    <x-ops.menu-item wire:click="duplicate({{ $p->id }})">Duplicate</x-ops.menu-item>
    <div class="my-1 border-t border-soft"></div>
    @foreach (['active' => 'Set active', 'draft' => 'Set draft'] as $st => $l)
        @if ($p->status !== $st)<x-ops.menu-item wire:click="setStatus({{ $p->id }}, '{{ $st }}')">{{ $l }}</x-ops.menu-item>@endif
    @endforeach
    @if ($p->status !== 'archived')<x-ops.menu-item wire:click="setStatus({{ $p->id }}, 'archived')" wire:confirm="Archive {{ $p->name }}? It will be hidden from the store.">Archive</x-ops.menu-item>@endif
    <x-ops.menu-item danger wire:click="delete({{ $p->id }})" wire:confirm="Delete {{ $p->name }}? Only possible if it was never ordered or stocked.">Delete</x-ops.menu-item>
</x-ops.row-menu>
