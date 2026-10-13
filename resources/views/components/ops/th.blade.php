@props(['sort' => null, 'align' => 'left'])
<th {{ $attributes->class(['th', 'text-right' => $align === 'right']) }}>
    @if ($sort)
        <button type="button" wire:click="sortBy('{{ $sort }}')" class="inline-flex items-center gap-1 hover:text-ink">
            {{ $slot }}<span class="text-2xs">{{ $this->sortField === $sort ? ($this->sortDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
        </button>
    @else {{ $slot }} @endif
</th>
