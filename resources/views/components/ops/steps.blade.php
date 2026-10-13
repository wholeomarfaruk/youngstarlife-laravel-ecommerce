@props(['steps', 'current', 'rejected' => false])
<div class="card grid grid-cols-5 px-3 py-3.5">
    @foreach ($steps as $i => $label)
        <div class="flex flex-col gap-1.5">
            <div class="flex items-center"><span @class(['h-3 w-3 flex-none rounded-full', 'bg-brand' => $i <= $current, 'bg-line-2' => $i > $current])></span><span @class(['h-0.5 flex-1', 'bg-brand' => $i < $current, 'bg-line' => $i >= $current, 'invisible' => $loop->last])></span></div>
            <span @class(['text-sm font-medium', 'text-ink' => $i <= $current, 'text-ink-muted' => $i > $current])>{{ $label }}</span>
        </div>
    @endforeach
</div>
