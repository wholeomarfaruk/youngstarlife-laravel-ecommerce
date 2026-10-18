@props(['label', 'value', 'hint' => null, 'tone' => 'ink', 'href' => null])
@php($color = ['ink' => 'text-ink', 'ok' => 'text-ok', 'info' => 'text-info', 'warn' => 'text-warn', 'bad' => 'text-bad', 'mute' => 'text-mute'][$tone] ?? 'text-ink')
<{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->class('kpi text-left hover:border-ink-faint') }}>
    <span class="text-xs text-ink-muted">{{ $label }}</span>
    <span class="text-xl font-semibold tabular-nums {{ $color }}">{{ $value }}</span>
    @if ($hint)<span class="text-xs text-ink-muted max-[479px]:hidden">{{ $hint }}</span>@endif
</{{ $href ? 'a' : 'div' }}>
