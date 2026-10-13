{{-- Wraps a real <table>. Shows skeleton rows while Livewire is loading. --}}
@props(['minWidth' => '900px', 'cols' => 6])
<div class="relative overflow-x-auto">
    <table class="w-full border-collapse" style="min-width: {{ $minWidth }}">
        <thead><tr>{{ $head }}</tr></thead>
        <tbody wire:loading.class="opacity-50" wire:target="search,status,filters,sortBy,from,to,gotoPage,nextPage,previousPage">{{ $slot }}</tbody>
    </table>
</div>
