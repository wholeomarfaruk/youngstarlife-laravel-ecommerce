<div wire:poll.30s.visible x-data="menu" class="relative flex-none">
    <button @click="toggle" class="relative grid h-9 w-9 place-items-center rounded-md border border-line-2 bg-white" aria-label="Notifications">
        <x-heroicon-o-bell class="h-[18px] w-[18px]" />
        @if ($unread)<span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-bad"></span>@endif
    </button>
    <div x-cloak x-show="open" @click.outside="close" class="absolute right-0 top-[calc(100%+6px)] z-50 w-80 max-w-[calc(100vw-16px)] rounded-lg border border-line-2 bg-white p-1 shadow-pop">
        <div class="flex items-center justify-between px-2.5 py-2"><span class="font-semibold">Notifications</span><button wire:click="markAllRead" class="text-sm text-brand">Mark all read</button></div>
        @forelse ($items as $n)<a href="{{ $n->data['url'] ?? '#' }}" wire:navigate class="flex gap-2.5 border-t border-soft px-2.5 py-2 text-ink"><span @class(['mt-1.5 h-2 w-2 flex-none rounded-full', 'bg-brand' => ! $n->read_at, 'bg-line-2' => $n->read_at])></span><span class="flex flex-col"><span>{{ $n->data['message'] ?? '' }}</span><span class="text-xs text-ink-muted">{{ $n->created_at->diffForHumans() }}</span></span></a>
        @empty <div class="px-2.5 py-4 text-center text-ink-muted">You're all caught up.</div> @endforelse
    </div>
</div>
