@props(['colspan' => 8, 'title' => 'Nothing found', 'hint' => 'Try a different search or clear the filters.'])
<tr><td colspan="{{ $colspan }}" class="px-3 py-8 text-center text-ink-muted">
    <div class="flex flex-col items-center gap-1.5"><span class="font-semibold text-ink">{{ $title }}</span><span class="text-sm">{{ $hint }}</span>{{ $slot }}</div>
</td></tr>
