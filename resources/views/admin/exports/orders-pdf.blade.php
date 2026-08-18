<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Orders Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        th { background-color: #f2f2f2; }
        tfoot td { font-weight: bold; }
        h1 { margin: 0 0 4px; font-size: 20px; }
        p.export-date { margin: 0 0 14px; font-style: italic; color: #555; }
    </style>
</head>
<body>
    <h1>YoungStar Life</h1>
    <p class="export-date">Export date: {{ now()->format('d M Y, h:i A') }}</p>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>ID</th>
                <th>Customer Name</th>
                <th>Address</th>
                <th>Phone</th>
                <th>Total</th>
                <th>Item Description</th>
                <th>Size</th>
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
                    <td>{{ $order->updated_at }}</td>
                    <td>{{ $order->id }}</td>
                    <td>{{ $order->name }}</td>
                    <td>{{ $order->address }}</td>
                    <td>{{ $order->phone }}</td>
                    <td>{{ $order->total }}</td>
                    <td>{{ $firstItem ? $productName : '' }}</td>
                    <td>{{ $firstItem ? $size : '' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4"></td>
                <td>Total</td>
                <td>{{ number_format($totalSum, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
