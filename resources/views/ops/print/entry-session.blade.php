<!DOCTYPE html><html><head><meta charset="utf-8"><title>{{ $session->code }}</title>
<style>
    @page { size: A4; margin: 8mm }
    * { box-sizing: border-box }
    body { margin: 0; font: 10.5px/1.3 system-ui, -apple-system, 'Segoe UI', sans-serif; color: #1A1A17; background: #fff }
    .wrap { max-width: 780px; margin: 0 auto; padding: 6px }
    .mono { font-family: ui-monospace, Menlo, Consolas, monospace }
    .muted { color: #6B6A63 }
    .head { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1.5px solid #1A1A17; padding-bottom: 4px }
    .meta { display: flex; gap: 18px; margin: 5px 0 6px }
    table { width: 100%; border-collapse: collapse; table-layout: fixed }
    th { text-align: left; font-weight: 600; font-size: 9.5px; color: #6B6A63; border-bottom: 1px solid #D6D3C9; padding: 2px 4px }
    td { padding: 2px 4px; border-bottom: 1px solid #EEECE6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
    tr.sec td { background: #F3F2EC; font-weight: 600; border-bottom: 1px solid #D6D3C9; padding-top: 3px }
    .r { text-align: right }
    .sign { display: flex; gap: 40px; margin-top: 28px } .sign div { flex: 1; border-top: 1px solid #1A1A17; padding-top: 2px }
    tr { page-break-inside: avoid }
</style></head>
<body onload="window.print()">
@php($co = ops_setting('company'))
<div class="wrap">
    <div class="head">
        <div><b style="font-size:14px">{{ $co['name'] ?? 'YoungStar Life' }}</b> <span class="muted">· {{ $co['phone'] ?? '' }}</span></div>
        <div class="r"><b>Courier entry session</b> <span class="mono">{{ $session->code }}</span> <span class="muted">· {{ $session->created_at->format('d M Y H:i') }}</span></div>
    </div>
    <div class="meta">
        <span><span class="muted">Courier</span> <b>{{ $session->courier->name }}</b></span>
        <span><span class="muted">Pickup man</span> <b>{{ $session->rider ?: '—' }}</b>{{ $session->rider_phone ? ' · '.$session->rider_phone : '' }}</span>
        <span><span class="muted">Handled by</span> <b>{{ $session->opener?->name ?? '—' }}</b></span>
        @foreach (\App\Models\Ops\CourierSessionItem::TYPES as $type => [$label])<span><span class="muted">{{ $label }}</span> <b>{{ $session->items->where('type', $type)->count() }}</b></span>@endforeach
    </div>
    <table>
        <colgroup><col style="width:22px"><col style="width:58px"><col style="width:48px"><col style="width:150px"><col style="width:66px"><col></colgroup>
        <tr><th>#</th><th>Parcel</th><th>Order</th><th>Customer</th><th class="r">COD</th><th>Return · note</th></tr>
        @foreach (\App\Models\Ops\CourierSessionItem::TYPES as $type => [$label])
            @php($rows = $session->items->where('type', $type)->values())
            @continue($rows->isEmpty())
            <tr class="sec"><td colspan="4">{{ $label }} · {{ $rows->count() }}</td><td class="r">{{ $type === 'sent' ? tk($rows->sum(fn ($i) => $i->shipment->cod_amount)) : '' }}</td><td></td></tr>
            @foreach ($rows as $n => $it)
                <tr><td class="muted">{{ $n + 1 }}</td><td class="mono">{{ $it->shipment->code }}</td><td class="mono">{{ $it->shipment->order?->number }}</td><td>{{ $it->shipment->order?->customer?->name }}</td><td class="r">{{ tk($it->shipment->cod_amount) }}</td><td>{{ $it->customerReturn?->code }} {{ $it->note }}</td></tr>
            @endforeach
        @endforeach
        @if ($session->items->isEmpty())<tr><td colspan="6" class="muted">No parcels recorded in this session.</td></tr>@endif
    </table>
    <div class="sign"><div>Pickup man signature</div><div>Warehouse signature</div></div>
</div>
</body></html>
