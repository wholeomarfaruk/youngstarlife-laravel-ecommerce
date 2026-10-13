<div wire:poll.30s.visible x-data="menu" class="relative flex-none">
    <button @click="toggle" class="relative grid h-9 w-9 place-items-center rounded-md border border-line-2 bg-white max-lg:h-11 max-lg:w-11" aria-label="Notifications">
        <x-heroicon-o-bell class="h-[18px] w-[18px]" />
        @if ($unread)<span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-bad"></span>@endif
    </button>
    <div x-cloak x-show="open" @click.outside="close" class="absolute right-0 top-[calc(100%+6px)] z-50 w-80 max-w-[calc(100vw-16px)] rounded-lg border border-line-2 bg-white p-1 shadow-pop">
        <div class="flex items-center justify-between px-2.5 py-2"><span class="font-semibold">Notifications</span><button wire:click="markAllRead" class="text-sm text-brand">Mark all read</button></div>
        <div class="flex items-center gap-1 border-b border-soft px-2 pb-2">
            @foreach (['all' => 'All', 'unread' => 'Unread'.($unread ? ' '.$unread : '')] as $k => $l)<button wire:click="$set('tab','{{ $k }}')" @class(['chip', 'chip-on' => $tab === $k])>{{ $l }}</button>@endforeach
            <select wire:model.live="category" class="input ml-auto w-auto py-0.5 text-sm"><option value="">All categories</option>@foreach (config('ops.notify_categories') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
        </div>
        <div class="max-h-[60vh] overflow-y-auto">
            @forelse ($items as $n)
                @php($msg = (string) ($n->data['message'] ?? $n->data['title'] ?? ''))
                <button type="button" wire:click="open('{{ $n->id }}')" class="flex w-full gap-2.5 border-t border-soft px-2.5 py-2 text-left text-ink first:border-0 hover:bg-zebra">
                    <span @class(['mt-1.5 h-2 w-2 flex-none rounded-full', 'bg-brand' => ! $n->read_at, 'bg-line-2' => $n->read_at])></span>
                    <span class="flex min-w-0 flex-col"><span>{{ html_entity_decode(strip_tags($msg)) }}</span><span class="text-xs text-ink-muted">{{ config('ops.notify_categories.'.($n->data['category'] ?? 'system'), 'System') }} · {{ $n->created_at->diffForHumans() }}</span></span>
                </button>
            @empty <div class="px-2.5 py-4 text-center text-ink-muted">You're all caught up.</div> @endforelse
        </div>
    </div>
</div>
