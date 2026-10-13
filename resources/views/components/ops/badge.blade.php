@props(['map' => null, 'status' => null, 'tone' => 'mute'])
@if ($map)
    @php([$label, $class] = status_badge($map, $status))
    <span {{ $attributes->class($class) }}>{{ $label }}</span>
@else
    <span {{ $attributes->class('badge-'.$tone) }}>{{ $slot }}</span>
@endif
