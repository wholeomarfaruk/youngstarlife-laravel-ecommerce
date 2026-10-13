@props(['name', 'title', 'width' => 'max-w-md'])
<div x-data="modal('{{ $name }}')" x-cloak x-show="open" @keydown.escape.window="open=false" class="fixed inset-0 z-[90] flex items-end justify-center p-4 sm:items-center">
    <div class="absolute inset-0 bg-ink/45" @click="open=false"></div>
    <div class="relative flex max-h-[calc(100vh-32px)] w-full {{ $width }} flex-col rounded-lg border border-line bg-white shadow-pop">
        <header class="flex items-center justify-between gap-3 border-b border-soft px-4 py-3"><span class="text-lg font-semibold">{{ $title }}</span><button @click="open=false" class="text-ink-muted">✕</button></header>
        <div class="overflow-auto p-4">{{ $slot }}</div>
        @isset($footer)<footer class="flex justify-end gap-2 border-t border-soft px-4 py-3">{{ $footer }}</footer>@endisset
    </div>
</div>
