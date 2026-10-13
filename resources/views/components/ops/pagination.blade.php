@props(['paginator'])
<div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5 text-sm text-ink-muted">
    <span>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}</span>
    <div class="flex gap-1">
        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" @disabled($paginator->onFirstPage()) class="btn-secondary btn-sm">Previous</button>
        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" @disabled(! $paginator->hasMorePages()) class="btn-secondary btn-sm">Next</button>
    </div>
</div>
