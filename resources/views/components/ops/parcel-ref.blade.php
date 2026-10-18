{{-- A parcel's courier references, the same everywhere: tracking code + consignment ID (when it is a different number).
     <x-ops.parcel-ref :s="$shipment" />            two short lines
     <x-ops.parcel-ref :s="$shipment" inline />     one line, "·" between
     <x-ops.parcel-ref :s="$shipment" copy />       with copy buttons --}}
@props(['s' => null, 'inline' => false, 'copy' => false, 'empty' => '—'])
@php($trk = (string) ($s?->tracking ?? '')) @php($cid = (string) ($s?->consignment_id ?? ''))
@php($cid = $cid !== '' && $cid !== $trk ? $cid : '')
@if ($trk === '' && $cid === '')
    <span {{ $attributes->class('text-ink-muted') }}>{{ $empty }}</span>
@else
    <span {{ $attributes->class(['mono', 'inline-flex flex-wrap items-baseline gap-x-1.5' => $inline, 'flex flex-col' => ! $inline]) }}>
        @if ($trk !== '')<span title="Tracking code">{{ $trk }}@if ($copy) <button type="button" onclick="opsCopy(@js($trk))" class="font-sans text-xs text-brand">copy</button>@endif</span>@endif
        @if ($trk !== '' && $cid !== '' && $inline)<span class="text-ink-faint">·</span>@endif
        @if ($cid !== '')<span title="Consignment ID"><span class="font-sans text-ink-faint">CID</span> {{ $cid }}@if ($copy) <button type="button" onclick="opsCopy(@js($cid))" class="font-sans text-xs text-brand">copy</button>@endif</span>@endif
    </span>
@endif
