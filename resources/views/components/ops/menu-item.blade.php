@props(['danger' => false])
<button type="button" {{ $attributes->class(['flex w-full items-center justify-between gap-2 rounded px-2 py-1.5 text-left hover:bg-soft', 'text-bad' => $danger]) }}>{{ $slot }}</button>
