{{-- Return receive: the batches a returned line goes back to (Receive::batchPreview → ReturnStock::batchesFor). ✓ = its sticker was scanned here --}}
@forelse ($bp as $x)
    <div class="leading-tight">
        @if ($x['code'])
            <span class="mono font-semibold">{{ $x['code'] }}</span>@if (count($bp) > 1) <span class="text-ink-muted">×{{ $x['n'] }}</span>@endif
            @if ($x['scanned'])<span class="text-xs font-semibold text-ok" title="Batch sticker scanned"> ✓ {{ $x['scanned'] }} scanned</span>@endif
            <div class="text-xs text-ink-muted">{{ $x['supplier'] ?? 'no supplier' }} · {{ $x['po'] ?? '—' }}</div>
        @else
            <span class="text-ink-muted">no batch{{ count($bp) > 1 ? ' ×'.$x['n'] : '' }}</span>
        @endif
    </div>
@empty
    <span class="text-ink-muted">no batch</span>
@endforelse
