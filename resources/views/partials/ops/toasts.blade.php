<div x-data="toasts" @toast.window="push($event)" class="fixed bottom-20 right-4 z-[95] flex max-w-[calc(100vw-32px)] flex-col gap-2 min-[480px]:bottom-4">
    <template x-for="t in items" :key="t.id">
        <div class="flex min-w-[240px] items-center gap-2.5 rounded-lg bg-ink px-3 py-2.5 text-white shadow-pop" x-transition.opacity>
            <span class="h-2 w-2 rounded-full" :class="t.type === 'bad' ? 'bg-bad-bg' : 'bg-brand-pale'"></span>
            <span class="flex-1" x-text="t.message"></span>
        </div>
    </template>
</div>
