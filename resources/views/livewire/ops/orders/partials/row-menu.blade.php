{{-- Status-aware actions, same as the design's ⋯ menu. Needs the OrderActions trait on the component. --}}
@php($s = $o->status) @php($tracking = $o->shipment?->tracking)
<x-ops.row-menu>
    <a href="{{ route('ops.orders.show', $o) }}" wire:navigate class="flex justify-between rounded px-2 py-1.5 text-ink hover:bg-soft">View details <span class="text-xs text-ink-muted">↵</span></a>
    @if (in_array($s, ['pending', 'confirmed', 'on_hold']))<a href="{{ route('ops.orders.show', ['order' => $o, 'edit' => 1]) }}" wire:navigate class="flex justify-between rounded px-2 py-1.5 text-ink hover:bg-soft">Edit order <span class="mono text-xs text-ink-muted">E</span></a>@endif
    <x-ops.menu-item wire:click="duplicate({{ $o->id }})">Duplicate</x-ops.menu-item>
    <div class="my-1 border-t border-soft"></div>
    @if ($s === 'pending')<x-ops.menu-item wire:click="setStatus({{ $o->id }}, 'confirmed')">Confirm order</x-ops.menu-item>@endif
    @if (in_array($s, ['pending', 'confirmed']))<a href="{{ route('ops.packing.scan', ['order' => $o->number]) }}" wire:navigate class="rounded px-2 py-1.5 text-ink hover:bg-soft">Pack & scan</a>@endif
    @if ($s === 'packed')<x-ops.menu-item wire:click="setStatus({{ $o->id }}, 'shipped')">Mark shipped</x-ops.menu-item>
        <x-ops.menu-item wire:click="setStatus({{ $o->id }}, 'confirmed')" wire:confirm="Unpack {{ $o->number }}? The units go back into the same batches and stay reserved for this order; it returns to Confirmed for re-packing. An unsent parcel is removed.">Unpack (back to Confirmed)</x-ops.menu-item>@endif
    @if (in_array($s, ['pending', 'confirmed', 'shipped']))<x-ops.menu-item wire:click="setStatus({{ $o->id }}, 'on_hold')">Put on hold</x-ops.menu-item>@endif
    @if ($s === 'on_hold')<x-ops.menu-item wire:click="resume({{ $o->id }})">Resume order</x-ops.menu-item>@endif
    @if (\App\Services\Ops\OrderWorkflow::canResend($o))<a href="{{ route('ops.orders.show', ['order' => $o, 'resend' => 1]) }}" wire:navigate class="rounded px-2 py-1.5 text-ink hover:bg-soft">Send again (new parcel)…</a>@endif
    @if ($s === 'cancelled')<x-ops.menu-item x-on:click="opsConfirm({ title: 'Restore {{ $o->number }}?', text: 'It goes back to Pending — confirm and pack it again as usual. Stock is reserved again on confirm.', confirm: 'Restore' }).then(ok => ok && $wire.restore({{ $o->id }}))">Restore order</x-ops.menu-item>@endif
    <div class="my-1 border-t border-soft"></div>
    <a href="{{ route('ops.orders.print', ['ids' => $o->id]) }}" target="_blank" class="rounded px-2 py-1.5 text-ink hover:bg-soft">Print invoice</a>
    <a href="{{ route('ops.orders.parcel-labels', ['ids' => $o->id]) }}" target="_blank" class="rounded px-2 py-1.5 text-ink hover:bg-soft">Print parcel label</a>
    @if ($tracking)<button type="button" onclick="opsCopy('{{ $tracking }}')" class="flex w-full justify-between rounded px-2 py-1.5 text-left text-ink hover:bg-soft">Copy tracking <span class="mono text-xs text-ink-muted">{{ $tracking }}</span></button>@endif
    @if (in_array($s, \App\Livewire\Ops\Orders\BookCourier::OPEN))<x-ops.menu-item x-on:click="$dispatch('open-modal', 'book-courier-one'); $dispatch('book-courier', { id: {{ $o->id }} })">Book courier…</x-ops.menu-item>@endif
    <a href="tel:{{ $o->customer?->phone ?? $o->ship_phone }}" class="rounded px-2 py-1.5 text-ink hover:bg-soft">Call customer</a>
    @if (in_array($s, ['delivered', 'partially_delivered']))
        <div class="my-1 border-t border-soft"></div>
        <a href="{{ route('ops.returns.receive', ['order' => $o->number]) }}" wire:navigate class="rounded px-2 py-1.5 text-ink hover:bg-soft">Create return</a>
        <a href="{{ route('ops.exchanges.index', ['order' => $o->number]) }}" wire:navigate class="rounded px-2 py-1.5 text-ink hover:bg-soft">Create exchange</a>
    @endif
    <div class="my-1 border-t border-soft"></div>
    <a href="{{ route('ops.trace', ['q' => $o->number]) }}" wire:navigate class="rounded px-2 py-1.5 text-ink hover:bg-soft">Trace items</a>
    <a href="{{ route('ops.orders.show', $o) }}#timeline" wire:navigate class="rounded px-2 py-1.5 text-ink hover:bg-soft">Activity log</a>
    <a href="{{ route('ops.blacklist.index', ['order' => $o->id]) }}" wire:navigate class="rounded px-2 py-1.5 text-bad hover:bg-soft">Blacklist customer…</a>
    @if (in_array($s, ['pending', 'confirmed', 'packed', 'on_hold', 'cancelled']))<div class="my-1 border-t border-soft"></div>@endif
    @if (in_array($s, ['pending', 'confirmed', 'packed', 'on_hold']))
        <x-ops.menu-item danger wire:click="setStatus({{ $o->id }}, 'cancelled')" wire:confirm="Cancel {{ $o->number }}? Reserved / packed stock goes back to its batches.">Cancel order</x-ops.menu-item>
    @endif
    @if (in_array($s, $this::DELETABLE))
        <x-ops.menu-item danger x-on:click="opsConfirm({ title: 'Delete order {{ $o->number }}?', text: 'This removes the order, its items and history for good. Reserved / packed stock goes back to its batches.', confirm: 'Delete', danger: true }).then(ok => ok && $wire.deleteOrder({{ $o->id }}))">Delete order…</x-ops.menu-item>
    @endif
</x-ops.row-menu>
