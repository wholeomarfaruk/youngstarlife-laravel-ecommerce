@props(['paginator'])
<div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5 text-sm text-ink-muted">
    <span>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}</span>
    <div class="flex gap-1">
        <button wire:click="previousPage" @disabled($paginator->onFirstPage()) class="btn-secondary btn-sm">Previous</button>
        <button wire:click="nextPage" @disabled(! $paginator->hasMorePages()) class="btn-secondary btn-sm">Next</button>
    </div>
</div>
