<div class="p-1">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h5 class="mb-0">Order #{{ $order->id }}</h5>
            <div class="text-muted small">{{ $order->created_at?->format('d M Y, h:i A') }}</div>
        </div>
        <span class="badge bg-primary text-capitalize">{{ str_replace('_', ' ', $order->status) }}</span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="border rounded p-2 h-100">
                <div class="small text-muted mb-1">Customer</div>
                <div class="fw-semibold">{{ $order->name }}</div>
                <div>{{ $order->phone }}</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="border rounded p-2 h-100">
                <div class="small text-muted mb-1">Delivery Area</div>
                <div>{{ $order->delivery_area?->name ?? '-' }}</div>
                <div class="small text-muted mb-1 mt-2">Consignment ID</div>
                <div>{{ $order->consignment_id ?: '-' }}</div>
            </div>
        </div>
        <div class="col-12">
            <div class="border rounded p-2">
                <div class="small text-muted mb-1">Address</div>
                <div>{{ $order->address ?: '-' }}</div>
                @if ($order->notes)
                    <div class="small text-muted mb-1 mt-2">Note</div>
                    <div>{{ $order->notes }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Product</th>
                    <th class="text-center">Price</th>
                    <th class="text-center">Qty</th>
                    <th class="text-center">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($order->Order_Item as $item)
                    <tr>
                        <td>{{ $item->product?->name ?? 'Deleted product' }}</td>
                        <td class="text-center">৳{{ number_format($item->product?->discount_price ?? $item->product?->price ?? 0, 2) }}</td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-center">৳{{ number_format((float) ($item->product?->discount_price ?? $item->product?->price ?? 0) * (int) $item->quantity, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-3">No products found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <tbody>
                <tr>
                    <th>Subtotal</th>
                    <td class="text-end">৳{{ number_format($order->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <th>Delivery Charge</th>
                    <td class="text-end">৳{{ number_format($order->fee, 2) }}</td>
                </tr>
                @if ($order->discount > 0)
                    <tr>
                        <th>Discount</th>
                        <td class="text-end text-danger">-৳{{ number_format($order->discount, 2) }}</td>
                    </tr>
                @endif
                <tr class="table-light">
                    <th>Total</th>
                    <td class="text-end fw-bold">৳{{ number_format($order->total, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-end mt-3">
        <a href="{{ route('admin.orders.details', $order->id) }}" class="btn btn-primary btn-sm">
            View Full Details
        </a>
    </div>
</div>
