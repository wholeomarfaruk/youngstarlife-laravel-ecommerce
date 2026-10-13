@props(['label', 'error' => null, 'hint' => null, 'required' => false])
<label {{ $attributes->class('label') }}>
    <span>{{ $label }} @if($required)<span class="text-bad">*</span>@endif</span>
    {{ $slot }}
    @if ($error)<span class="text-xs text-bad">{{ $error }}</span>@elseif ($hint)<span class="text-xs text-ink-muted">{{ $hint }}</span>@endif
</label>
