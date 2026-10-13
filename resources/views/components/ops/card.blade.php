@props(['title' => null, 'subtitle' => null, 'flush' => false])
<section {{ $attributes->class('card') }}>
    @if ($title || isset($actions))
        <header class="card-head">
            <div class="flex min-w-0 flex-col"><span class="card-title">{{ $title }}</span>@if ($subtitle)<span class="text-sm text-ink-muted">{{ $subtitle }}</span>@endif</div>
            @isset($actions)<div class="flex flex-wrap gap-1.5">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div @class(['p-3.5' => ! $flush])>{{ $slot }}</div>
</section>
