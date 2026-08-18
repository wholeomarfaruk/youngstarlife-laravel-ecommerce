<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Orders Export</title>
    <style>
        body { font-family: 'solaimanlipi', DejaVu Sans, sans-serif; font-size: 7px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #ccc; padding: 2px 3px; text-align: left; word-wrap: break-word; }
        th { background-color: #f2f2f2; }
        tfoot td { font-weight: bold; }
        h1 { margin: 0 0 3px; font-size: 14px; }
        p.export-date { margin: 0 0 8px; font-style: italic; color: #555; font-size: 8px; }

        .col-date { width: 6%; }
        .col-id { width: 4%; }
        .col-name { width: 10%; }
        .col-address { width: 16%; }
        .col-phone { width: 7%; }
        .col-total { width: 5%; }
        .col-status { width: 10%; }
        .col-consignment { width: 9%; }
        .col-courier-status { width: 10%; }
        .col-item { width: 15%; }
        .col-size { width: 6%; }
    </style>
</head>
<body>
    <h1>YoungStar Life</h1>
    <p class="export-date">Export date: {{ now()->format('d M Y, h:i A') }}</p>
    <table>
        <thead>
            <tr>
                <th class="col-date">Date</th>
                <th class="col-id">ID</th>
                <th class="col-name">Customer Name</th>
                <th class="col-address">Address</th>
                <th class="col-phone">Phone</th>
                <th class="col-total">Total</th>
                <th class="col-status">Status</th>
                <th class="col-consignment">Consignment ID</th>
                <th class="col-courier-status">Courier Status</th>
                <th class="col-item">Item Description</th>
                <th class="col-size">Size</th>
            </tr>
        </thead>
        <tbody>
            @php $totalSum = 0; @endphp
            @foreach ($orders as $order)
                @php
                    $firstItem = $order->order_item->first();
                    $size = $firstItem ? ($firstItem->options['size'] ?? '') : '';
                    $productName = $firstItem ? $firstItem->product->name : '';
                    if ($size) {
                        $productName .= " ($size)";
                    }
                    $totalSum += (float) $order->total;
                @endphp
                <tr>
                    <td class="col-date">{{ $order->updated_at }}</td>
                    <td class="col-id">{{ $order->id }}</td>
                    <td class="col-name">{{ $order->name }}</td>
                    <td class="col-address">{{ $order->address }}</td>
                    <td class="col-phone">{{ $order->phone }}</td>
                    <td class="col-total">{{ $order->total }}</td>
                    <td class="col-status">{{ $order->status ? str_replace('_', ' ', $order->status) : '' }}</td>
                    <td class="col-consignment">{{ $order->consignment_id ?: '' }}</td>
                    <td class="col-courier-status">{{ $order->courier_status ? str_replace('_', ' ', $order->courier_status->value) : '' }}</td>
                    <td class="col-item">{{ $firstItem ? $productName : '' }}</td>
                    <td class="col-size">{{ $firstItem ? $size : '' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4"></td>
                <td>Total</td>
                <td>{{ number_format($totalSum, 2) }}</td>
                <td colspan="5"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
