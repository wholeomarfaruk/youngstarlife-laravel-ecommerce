@props(['title' => null, 'subtitle' => null])
<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex min-w-0 flex-col gap-0.5">
        @if ($title)<h1 class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>@endif
        @if ($subtitle)<span class="text-sm text-ink-muted">{{ $subtitle }}</span>@endif
    </div>
    <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
</div>
