@extends('layouts.admin')

@section('content')
    <!-- content area start -->
    <style>
        .order-status-badge {
            font-size: .8rem;
            font-weight: 600;
            padding: .4em .8em;
            border-radius: 999px;
            text-transform: capitalize;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #cfe2ff; color: #084298; }
        .status-processing { background: #e2d9f3; color: #4b2e83; }
        .status-ready,
        .status-in_transit { background: #cff4fc; color: #055160; }
        .status-delivered { background: #d1e7dd; color: #0f5132; }
        .status-in_review,
        .status-delivery_in_review,
        .status-on_hold { background: #ffe5d0; color: #7a3b00; }
        .status-cancelled,
        .status-returned,
        .status-deleted { background: #f8d7da; color: #842029; }

        .info-card { border: 0; border-radius: 14px; box-shadow: 0 1px 3px rgba(0, 0, 0, .06); }
        .info-card .card-header {
            background: transparent;
            border-bottom: 1px solid #f0f0f0;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            flex-wrap: wrap;
        }
        .info-card .card-header h6 { margin: 0; font-weight: 600; }
        .info-card .card-body { padding: 1.25rem; }

        .kv-row { display: flex; justify-content: space-between; gap: 1rem; padding: .4rem 0; border-bottom: 1px dashed #eee; font-size: .9rem; }
        .kv-row:last-child { border-bottom: none; }
        .kv-row .kv-label { color: #6c757d; }
        .kv-row .kv-value { font-weight: 500; text-align: right; }

        .fraud-check { border: 1px solid #f0f0f0; border-radius: 10px; padding: .75rem 1rem; margin-top: .75rem; }
        .fraud-check .progress { height: 8px; border-radius: 999px; }

        .product-row-thumb { width: 56px; height: 56px; object-fit: cover; border-radius: 8px; border: 1px solid #eee; }

        .product-item { transition: 0.2s ease-in-out; }
        .product-item:hover { box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); background-color: #f8f9fa; }

        @media (max-width: 767px) {
            .product-item .col-md-2,
            .product-item .col-md-3 { text-align: center; }
        }
    </style>

    <div class="main-content-inner">
        <div class="main-content-wrap">
            <div class="d-flex align-items-center flex-wrap justify-content-between gap-3 mb-4">
                <h3 class="mb-0">Order Details</h3>
                <ul class="breadcrumbs d-flex align-items-center flex-wrap gap-2 mb-0">
                    <li>
                        <a href="{{ route('admin.index') }}">
                            <div class="text-tiny">Dashboard</div>
                        </a>
                    </li>
                    <li><i class="icon-chevron-right"></i></li>
                    <li>
                        <a href="{{ route('admin.orders') }}">
                            <div class="text-tiny">Orders</div>
                        </a>
                    </li>
                    <li><i class="icon-chevron-right"></i></li>
                    <li>
                        <div class="text-tiny">Order #{{ $order->id }}</div>
                    </li>
                </ul>
            </div>

            @if (session('status'))
                <div class="alert alert-success" role="alert">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
            @endif

            <!-- Header summary card -->
            <div class="card info-card mb-3">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                <h5 class="mb-0">Order #{{ $order->id }}</h5>
                                <span class="order-status-badge status-{{ $order->status }}">{{ str_replace('_', ' ', $order->status) }}</span>
                                @if ($order->is_paid)
                                    <span class="badge bg-success">Paid</span>
                                @endif
                            </div>
                            <div class="text-muted small">
                                Placed {{ $order->created_at?->format('d M Y, h:i A') }}
                                @if ($order->delivery_date)
                                    &middot; Delivered {{ \Carbon\Carbon::parse($order->delivery_date)->format('d M Y') }}
                                @endif
                                @if ($order->cancelled_date)
                                    &middot; Cancelled {{ \Carbon\Carbon::parse($order->cancelled_date)->format('d M Y') }}
                                @endif
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="text-end me-2">
                                <div class="text-muted small">Total Bill</div>
                                <div class="fs-5 fw-bold">{{ number_format($order->total, 2) }} Tk</div>
                            </div>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.orders') }}">
                                <i class="icon-chevron-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <!-- Left column -->
                <div class="col-lg-8">
                    <!-- Order meta -->
                    <div class="card info-card mb-3">
                        <div class="card-header">
                            <h6>Order Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-4">
                                    <div class="kv-row"><span class="kv-label">Order No</span><span class="kv-value">#{{ $order->id }}</span></div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="kv-row"><span class="kv-label">Total Quantity</span><span class="kv-value">{{ $order->Order_Item->count() }}</span></div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="kv-row"><span class="kv-label">Consignment ID</span><span class="kv-value">{{ $order->consignment_id ?: '-' }}</span></div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="kv-row"><span class="kv-label">Source</span><span class="kv-value">{{ $order->source ? ucfirst($order->source) : '-' }}</span></div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="kv-row"><span class="kv-label">Courier</span><span class="kv-value">{{ $order->courier_partner ?: '-' }}</span></div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="kv-row"><span class="kv-label">Tracking No</span><span class="kv-value">{{ $order->tracking_number ?: '-' }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ordered items -->
                    <div class="card info-card mb-3">
                        <div class="card-header">
                            <h6>Ordered Items</h6>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
                                <i class="icon-edit-3"></i> Edit Items
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th class="text-center">Price</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-center">Line Total</th>
                                            <th class="text-center">Options</th>
                                            <th class="text-center">Return</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($orderItems as $item)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="{{ asset('storage/images/products/thumbnails/' . $item->product?->image) }}"
                                                            alt="{{ $item->product?->name }}" class="product-row-thumb">
                                                        <span class="fw-semibold">{{ $item->product?->name ?? 'Deleted product' }}</span>
                                                    </div>
                                                </td>
                                                <td class="text-center">৳{{ number_format($item->product?->discount_price ?? $item->product?->price ?? 0, 2) }}</td>
                                                <td class="text-center">{{ $item->quantity }}</td>
                                                <td class="text-center">
                                                    ৳{{ number_format((float) ($item->product?->discount_price ?? $item->product?->price ?? 0) * (int) $item->quantity, 2) }}
                                                </td>
                                                <td class="text-center small text-muted">
                                                    @php
                                                        $opts = is_array($item->options) ? array_filter($item->options) : [];
                                                    @endphp
                                                    @if (!empty($opts))
                                                        {{ collect($opts)->map(fn($v, $k) => ucfirst($k) . ': ' . $v)->implode(', ') }}
                                                    @else
                                                        &mdash;
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if ($item->rstatus)
                                                        <span class="badge bg-warning text-dark">Returned</span>
                                                    @else
                                                        <span class="badge bg-light text-muted">No</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">No products found</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if ($orderItems->hasPages())
                            <div class="card-body border-top py-2">
                                {{ $orderItems->links('pagination::bootstrap-5') }}
                            </div>
                        @endif
                    </div>

                    <!-- Order summary -->
                    <div class="card info-card mb-3">
                        <div class="card-header">
                            <h6>Order Summary</h6>
                        </div>
                        <div class="card-body">
                            <div class="kv-row"><span class="kv-label">Product Price</span><span class="kv-value">{{ number_format($order->subtotal, 2) }} Tk</span></div>
                            <div class="kv-row"><span class="kv-label">Delivery Fee</span><span class="kv-value">{{ number_format($order->fee, 2) }} Tk</span></div>
                            @if ($order->discount > 0)
                                <div class="kv-row"><span class="kv-label">Discount</span><span class="kv-value text-danger">-{{ number_format($order->discount, 2) }} Tk</span></div>
                            @endif
                            <div class="kv-row"><span class="kv-label fw-semibold">Total Bill</span><span class="kv-value fw-bold fs-6">{{ number_format($order->total, 2) }} Tk</span></div>
                        </div>
                    </div>

                    <!-- Status update -->
                    <div class="card info-card mb-3">
                        <div class="card-header">
                            <h6>Update Order Status</h6>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.orders.update', $order->id) }}" method="POST" class="row g-2 align-items-center">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="order_id" value="{{ $order->id }}">
                                <div class="col-sm-8 col-md-6">
                                    <select name="status" id="status" class="form-select">
                                        @foreach ([
                                            'pending' => 'Pending', 'confirmed' => 'Confirmed', 'ready' => 'Ready',
                                            'delivered' => 'Delivered', 'on_hold' => 'On Hold', 'in_review' => 'In Review',
                                            'in_transit' => 'In Transit', 'processing' => 'Processing',
                                            'delivery_in_review' => 'Delivery in Review', 'cancelled' => 'Cancelled',
                                            'returned' => 'Returned', 'deleted' => 'Deleted',
                                        ] as $value => $label)
                                            <option value="{{ $value }}" {{ $order->status == $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-4 col-md-6">
                                    <button type="submit" class="btn btn-primary">Update Status</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right column -->
                <div class="col-lg-4">
                    <!-- Customer / shipping -->
                    <div class="card info-card mb-3">
                        <div class="card-header">
                            <h6>Shipping Address</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#orderDetails">
                                <i class="icon-edit-3"></i> Edit
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="kv-row"><span class="kv-label">Name</span><span class="kv-value">{{ $order->name }}</span></div>
                            <div class="kv-row">
                                <span class="kv-label">Mobile</span>
                                <span class="kv-value {{ $order->customer?->is_blocked ? 'text-danger' : '' }}">
                                    {{ $order->phone }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-end mb-2">
                                @if (!$order->customer || !$order->customer->is_blocked)
                                    <button id="blockcustomer" type="button" onclick="blockcustomer({{ $order->id }})"
                                        class="btn btn-outline-danger btn-sm">Block Customer</button>
                                @else
                                    <span class="badge bg-danger me-2 align-self-center">Blacklisted</span>
                                    <button type="button" class="btn btn-outline-success btn-sm"
                                        onclick="unblockCustomer({{ $order->id }})">Unblock</button>
                                @endif
                            </div>

                            <div class="kv-row">
                                <span class="kv-label">Delivery Area</span>
                                <span class="kv-value">{{ $order->delivery_area?->name ?? '-' }}{{ $order->delivery_area ? ' (' . number_format($order->delivery_area->charge, 0) . ' Tk)' : '' }}</span>
                            </div>
                            <div class="kv-row"><span class="kv-label">Full Address</span><span class="kv-value">{{ $order->address ?: '-' }}</span></div>
                            <div class="kv-row"><span class="kv-label">Order Note</span><span class="kv-value">{{ $order->notes ?: '-' }}</span></div>

                            @if ($order->fraud_check_steadfast && isset($order->fraud_check_steadfast['total']))
                                @php
                                    $successSf = $order->fraud_check_steadfast['success'] ?? 0;
                                    $totalSf = $order->fraud_check_steadfast['total'] ?? 0;
                                    $scoreSf = $totalSf > 0 ? min(($successSf / $totalSf) * 100, 100) : 0;
                                @endphp
                                <div class="fraud-check">
                                    <div class="fw-semibold small mb-1">SteadFast Customer Check</div>
                                    <div class="text-muted small mb-1">
                                        Total: {{ $totalSf }} &middot; Received: {{ $successSf }} &middot;
                                        Returned: {{ $order->fraud_check_steadfast['cancel'] ?? 0 }}
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-{{ $scoreSf >= 70 ? 'success' : 'danger' }}" role="progressbar"
                                            style="width: {{ $scoreSf }}%;">{{ number_format($scoreSf, 0) }}%</div>
                                    </div>
                                </div>
                            @endif

                            @if ($order->fraud_check_pathao && isset($order->fraud_check_pathao['total']))
                                @php
                                    $successPa = $order->fraud_check_pathao['success'] ?? 0;
                                    $totalPa = $order->fraud_check_pathao['total'] ?? 0;
                                    $scorePa = $totalPa > 0 ? min(($successPa / $totalPa) * 100, 100) : 0;
                                @endphp
                                <div class="fraud-check">
                                    <div class="fw-semibold small mb-1">Pathao Customer Check</div>
                                    <div class="text-muted small mb-1">
                                        Total: {{ $totalPa }} &middot; Received: {{ $successPa }} &middot;
                                        Returned: {{ $order->fraud_check_pathao['cancel'] ?? 0 }}
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-{{ $scorePa >= 70 ? 'success' : 'danger' }}" role="progressbar"
                                            style="width: {{ $scorePa }}%;">{{ number_format($scorePa, 0) }}%</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Extra data -->
                    <div class="card info-card mb-3">
                        <div class="card-header">
                            <h6>Extra Data</h6>
                        </div>
                        <div class="card-body">
                            <div class="kv-row"><span class="kv-label">IP Address</span><span class="kv-value">{{ $order->ip_address ?: '-' }}</span></div>
                            <div class="kv-row">
                                <span class="kv-label">User Agent</span>
                                <span class="kv-value text-truncate {{ $order->device?->is_blocked ? 'text-danger' : '' }}" style="max-width:180px;" title="{{ $order->user_agent }}">
                                    {{ $order->user_agent ?: '-' }}
                                </span>
                            </div>
                            @if ($order->json_data)
                                <details class="mt-2">
                                    <summary class="small text-muted" style="cursor:pointer;">Raw JSON data</summary>
                                    <pre class="small mt-2 mb-0" style="white-space:pre-wrap;">{{ json_encode($order->json_data, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Edit Order Items -->
        <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Edit Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form action="{{ route('admin.orders.editupdate', $order->id) }}" method="POST" id="orderEdit">
                            @csrf
                            @method('PUT')

                            <fieldset class="mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label fw-semibold mb-0">Products</label>
                                    <button type="button" class="btn btn-sm btn-primary" id="openProductPicker">
                                        <i class="icon-plus"></i> Add Item
                                    </button>
                                </div>

                                <div id="productPickerPanel" class="border rounded-3 p-3 mb-3 d-none">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                        <input type="text" id="productPickerSearch" class="form-control form-control-sm"
                                            placeholder="Search products...">
                                        <button type="button" class="btn-close flex-shrink-0" id="closeProductPicker"
                                            aria-label="Close"></button>
                                    </div>
                                    <div id="productPickerGrid" class="row g-3" style="max-height:360px; overflow-y:auto;"></div>
                                    <div id="productPickerEmpty" class="text-center text-muted small py-3 d-none">
                                        No products found.
                                    </div>
                                </div>
                            </fieldset>

                            <template id="productPickerCardTemplate">
                                <div class="col-6 col-md-4 col-lg-3 product-picker-item">
                                    <div class="border rounded-3 p-2 text-center h-100 product-picker-card d-flex flex-column"
                                        style="cursor:pointer;">
                                        <div class="d-flex align-items-center justify-content-center mb-2 bg-light rounded"
                                            style="height:120px;">
                                            <img src="" alt="" class="product-picker-image"
                                                style="max-width:100%; max-height:100%; object-fit:contain;">
                                        </div>
                                        <div class="small fw-semibold product-picker-name text-truncate" title=""></div>
                                        <div class="small text-muted product-picker-price mt-auto"></div>
                                    </div>
                                </div>
                            </template>

                            <div id="editForm" class="mt-3">
                                @if ($order->Order_Item->count() > 0)
                                    @foreach ($order->Order_Item as $item)
                                        <div id="product-item-{{ $loop->index }}" class="product-item border rounded bg-light p-3 mb-3">
                                            <div class="row align-items-center text-center text-md-start">
                                                <div class="col-12 col-md-2 mb-2 mb-md-0">
                                                    <img src="/storage/images/products/{{ $item->product->image }}"
                                                        alt="{{ $item->product->name }}" class="img-fluid rounded"
                                                        style="max-height: 80px; object-fit: cover;">
                                                </div>
                                                <div class="col-12 col-md-3 mb-2 mb-md-0">
                                                    <h6 class="mb-1">{{ $item->product->name }}</h6>
                                                    <p class="mb-0">
                                                        Price:
                                                        <strong class="product-price"
                                                            data-price="{{ $item->product->discount_price ?? $item->product->price }}">
                                                            {{ $item->product->discount_price ?? $item->product->price }} Tk
                                                        </strong>
                                                    </p>
                                                </div>
                                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                                    <label class="form-label small">Quantity</label>
                                                    <input type="hidden" name="order_items[{{ $loop->index }}][product_id]"
                                                        value="{{ $item->product->id }}">
                                                    <input type="number"
                                                        name="order_items[{{ $loop->index }}][quantity]"
                                                        value="{{ $item->quantity }}" min="1"
                                                        class="form-control quantity-input"
                                                        data-line="{{ $loop->index }}">
                                                </div>
                                                <div class="col-6 col-md-3 mb-2 mb-md-0">
                                                    <label class="form-label small">Options</label>
                                                    <input type="text"
                                                        name="order_items[{{ $loop->index }}][size]"
                                                        class="form-control"
                                                        placeholder="Enter size"
                                                        value="{{ is_array($item->options) ? data_get($item, 'options.size') : json_decode($item->options)?->size }}">
                                                </div>
                                                <div class="col-12 col-md-2">
                                                    <button type="button" class="btn btn-danger btn-sm w-100 mt-2 mt-md-0"
                                                        onclick="removeProductLine({{ $loop->index }})">
                                                        Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>

                        </form>
                    </div>
                    <div class="modal-footer flex-column align-items-stretch">
                        <table class="table table-bordered mb-2 align-middle">
                            <tr>
                                <th width="40%">Sub Total:</th>
                                <td class="text-end">
                                    <span id="subTotal">0.00</span> Tk
                                </td>
                            </tr>
                            <tr>
                                <th>Delivery Charge:</th>
                                <td>
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <input type="number" id="delivery_charge" name="delivery_charge"
                                            class="form-control form-control-sm text-end" style="max-width:160px;"
                                            value="{{ $order->fee ?? 0 }}" min="0" step="0.01">
                                        <span>Tk</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>Discount:</th>
                                <td>
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <input type="number" id="discount" name="discount"
                                            class="form-control form-control-sm text-end" style="max-width:160px;"
                                            value="{{ $order->discount ?? 0 }}" min="0" step="0.01">
                                        <span>Tk</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>Calculated Total:</th>
                                <td class="text-end">
                                    <span id="calculatedTotal">0.00</span> Tk
                                </td>
                            </tr>
                            <tr class="table-info">
                                <th>Total Amount:</th>
                                <td>
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <input type="number" id="total" name="total" class="form-control form-control-sm text-end fw-bold"
                                            style="max-width:160px;" min="0" step="0.01">
                                        <span>Tk</span>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        <div class="form-text text-start mb-2" id="totalHint"></div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('orderEdit').submit();">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Edit Shipping Details -->
        <div class="modal fade" id="orderDetails" tabindex="-1" aria-labelledby="orderDetailsLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="orderDetailsLabel">Edit Shipping Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form action="{{ route('admin.orders.update.details', $order->id) }}" method="POST" id="orderDetailForm">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="order_id" value="{{ $order->id }}">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Customer Name</label>
                                    <input type="text" name="name" class="form-control" id="name" value="{{ $order->name }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Customer Phone</label>
                                    <input type="text" name="phone" class="form-control" id="phone" value="{{ $order->phone }}">
                                </div>
                                <div class="col-md-12">
                                    <label for="address" class="form-label">Delivery Address</label>
                                    <textarea name="address" class="form-control" id="address" rows="4">{{ $order->address }}</textarea>
                                </div>
                                <div class="col-md-12">
                                    <label for="note" class="form-label">Note</label>
                                    <textarea name="note" class="form-control" id="note" rows="4">{{ $order->notes }}</textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('orderDetailForm').submit();">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ====================== JS Section ====================== -->
        <script>
            var allProducts = @json($products);
            var addedProductIds = new Set(@json($order->Order_Item->pluck('product_id')->values()));

            const editForm = document.getElementById('editForm');
            const discountInput = document.getElementById('discount');
            const subTotalEl = document.getElementById('subTotal');
            const calculatedTotalEl = document.getElementById('calculatedTotal');
            const fee = document.getElementById('delivery_charge');
            const totalInput = document.getElementById('total');
            const totalHint = document.getElementById('totalHint');

            const openProductPickerBtn = document.getElementById('openProductPicker');
            const closeProductPickerBtn = document.getElementById('closeProductPicker');
            const productPickerPanel = document.getElementById('productPickerPanel');
            const productPickerGrid = document.getElementById('productPickerGrid');
            const productPickerEmpty = document.getElementById('productPickerEmpty');
            const productPickerSearch = document.getElementById('productPickerSearch');
            const productPickerCardTemplate = document.getElementById('productPickerCardTemplate');

            let lastCalculatedTotal = 0;

            let nextLineIndex = editForm.querySelectorAll('.product-item').length;

            document.getElementById('exampleModal').addEventListener('shown.bs.modal', function() {
                attachQuantityListeners();
                calculateTotal({ syncTotalInput: true });
            });

            openProductPickerBtn.addEventListener('click', function() {
                const isHidden = productPickerPanel.classList.contains('d-none');
                productPickerPanel.classList.toggle('d-none', !isHidden);
                if (isHidden) {
                    productPickerSearch.value = '';
                    renderProductPicker('');
                    productPickerSearch.focus();
                }
            });

            closeProductPickerBtn.addEventListener('click', function() {
                productPickerPanel.classList.add('d-none');
            });

            productPickerSearch.addEventListener('input', function() {
                renderProductPicker(this.value.trim().toLowerCase());
            });

            function renderProductPicker(query) {
                productPickerGrid.innerHTML = '';

                const available = allProducts.filter(p => !addedProductIds.has(p.id));
                const filtered = query
                    ? available.filter(p => p.name.toLowerCase().includes(query))
                    : available;

                if (filtered.length === 0) {
                    productPickerEmpty.classList.remove('d-none');
                    return;
                }
                productPickerEmpty.classList.add('d-none');

                filtered.forEach(product => {
                    const card = productPickerCardTemplate.content.cloneNode(true);
                    const price = product.discount_price ?? product.price;

                    const img = card.querySelector('.product-picker-image');
                    img.src = `/storage/images/products/${product.image}`;
                    img.alt = product.name;

                    const nameEl = card.querySelector('.product-picker-name');
                    nameEl.textContent = product.name;
                    nameEl.title = product.name;

                    card.querySelector('.product-picker-price').textContent = `${price} Tk`;

                    const cardEl = card.querySelector('.product-picker-card');
                    if (product.stock_status === 'out_of_stock') {
                        cardEl.classList.add('opacity-50');
                        cardEl.style.cursor = 'not-allowed';
                        cardEl.title = 'Out of stock';
                    } else {
                        cardEl.addEventListener('click', () => addProductLine(product));
                    }

                    productPickerGrid.appendChild(card);
                });
            }

            function addProductLine(product) {
                const price = product.discount_price ?? product.price;
                const line = nextLineIndex++;
                const formHtml = `
            <div id="product-item-${line}" class="product-item border rounded bg-light p-3 mb-3">
                <div class="row align-items-center text-center text-md-start">
                    <div class="col-12 col-md-2 mb-2 mb-md-0">
                        <img src="/storage/images/products/${product.image}" alt="${product.name}"
                             class="img-fluid rounded" style="max-height: 80px; object-fit: cover;">
                    </div>
                    <div class="col-12 col-md-3 mb-2 mb-md-0">
                        <h6 class="mb-1">${product.name}</h6>
                        <p class="mb-0">Price:
                            <strong class="product-price" data-price="${price}">${price} Tk</strong>
                        </p>
                    </div>
                    <div class="col-6 col-md-2 mb-2 mb-md-0">
                        <label class="form-label small">Quantity</label>
                        <input type="hidden" name="order_items[${line}][product_id]" value="${product.id}">
                        <input type="number" name="order_items[${line}][quantity]" value="1" min="1"
                               class="form-control quantity-input" data-line="${line}">
                    </div>
                    <div class="col-6 col-md-3 mb-2 mb-md-0">
                        <label class="form-label small">Size</label>
                        <input type="text" name="order_items[${line}][size]" class="form-control"
                               placeholder="Enter size" value="">
                    </div>
                    <div class="col-12 col-md-2">
                        <button type="button" class="btn btn-danger btn-sm w-100 mt-2 mt-md-0"
                                onclick="removeProductLine(${line})">Remove</button>
                    </div>
                </div>
            </div>`;

                $(editForm).append(formHtml);
                addedProductIds.add(product.id);
                renderProductPicker(productPickerSearch.value.trim().toLowerCase());

                attachQuantityListeners();
                calculateTotal({ syncTotalInput: true });
            }

            function attachQuantityListeners() {
                document.querySelectorAll('.quantity-input').forEach(input => {
                    input.removeEventListener('input', handleQuantityInput);
                    input.addEventListener('input', handleQuantityInput);
                });
            }

            function handleQuantityInput() {
                calculateTotal({ syncTotalInput: true });
            }

            function removeProductLine(line) {
                const productItem = document.getElementById(`product-item-${line}`);
                if (productItem) productItem.remove();

                calculateTotal({ syncTotalInput: true });
            }

            discountInput.addEventListener('input', () => calculateTotal({ syncTotalInput: true }));
            fee.addEventListener('input', () => calculateTotal({ syncTotalInput: true }));

            // Editing Total Amount directly: if it's set below the calculated total,
            // the shortfall is automatically added to Discount so the numbers stay consistent.
            totalInput.addEventListener('input', function() {
                const discountBase = parseFloat(discountInput.dataset.manualDiscount ?? discountInput.value) || 0;
                const deliveryCharge = parseFloat(fee.value) || 0;
                const subTotal = getSubTotal();
                const calculatedTotal = Math.max(subTotal - discountBase, 0) + deliveryCharge;

                const enteredTotal = totalInput.value === '' ? null : parseFloat(totalInput.value);

                if (enteredTotal === null || isNaN(enteredTotal) || enteredTotal >= calculatedTotal) {
                    // Back to (or above) the calculated total: restore the manually-set discount, no auto-adjustment.
                    discountInput.value = discountBase.toFixed(2);
                } else {
                    // Entered total is lower than calculated: push the shortfall into Discount.
                    const shortfall = Math.max(calculatedTotal - enteredTotal, 0);
                    discountInput.value = (discountBase + shortfall).toFixed(2);
                }

                calculateTotal({ syncTotalInput: false });
            });

            function getSubTotal() {
                let subTotal = 0;

                document.querySelectorAll('.product-item').forEach(item => {
                    const priceEl = item.querySelector('.product-price');
                    const quantityInput = item.querySelector('.quantity-input');
                    if (!priceEl || !quantityInput) return;

                    const price = parseFloat(priceEl.dataset.price) || 0;
                    const qty = parseInt(quantityInput.value) || 0;
                    subTotal += price * qty;
                });

                return subTotal;
            }

            function calculateTotal(options) {
                const opts = options || {};
                const subTotal = getSubTotal();
                const deliveryCharge = parseFloat(fee.value) || 0;
                const discount = parseFloat(discountInput.value) || 0;
                const calculatedTotal = Math.max(subTotal - discount, 0) + deliveryCharge;

                subTotalEl.textContent = subTotal.toFixed(2);
                calculatedTotalEl.textContent = calculatedTotal.toFixed(2);
                lastCalculatedTotal = calculatedTotal;

                // Remember the discount value the admin actually typed (before any auto top-up),
                // so re-editing Total Amount later recomputes from the real manual discount.
                if (opts.syncTotalInput !== false) {
                    discountInput.dataset.manualDiscount = discount;
                }
                if (opts.syncTotalInput) {
                    totalInput.value = calculatedTotal.toFixed(2);
                }

                const enteredTotal = totalInput.value === '' ? calculatedTotal : parseFloat(totalInput.value);
                if (!isNaN(enteredTotal) && enteredTotal < calculatedTotal) {
                    totalHint.textContent = `Total Amount is below the calculated total (${calculatedTotal.toFixed(2)} Tk) - the ${(calculatedTotal - enteredTotal).toFixed(2)} Tk difference has been added to Discount.`;
                    totalHint.classList.add('text-danger');
                } else {
                    totalHint.textContent = '';
                    totalHint.classList.remove('text-danger');
                }
            }

            discountInput.dataset.manualDiscount = discountInput.value || 0;
            attachQuantityListeners();
            calculateTotal({ syncTotalInput: true });
        </script>

        <script>
            function blockcustomer(id) {
                if (!id) {
                    Swal.fire({ icon: 'warning', title: 'No ID provided', text: 'Cannot block customer without an ID.' });
                    return;
                }

                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, Block!'
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    fetch(`/admin/orders/${id}/customer/block`, {
                            method: 'GET',
                            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
                        })
                        .then(response => {
                            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                            return response.json();
                        })
                        .then(responseJson => {
                            if (responseJson.success) {
                                Swal.fire('Success!', responseJson.message || 'Customer blocked successfully.', 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Failed!', responseJson.message || 'Customer block action failed.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Fetch operation error:', error);
                            Swal.fire('Error!', 'An unexpected error occurred during the request.', 'error');
                        });
                });
            }

            function unblockCustomer(id) {
                if (!id) {
                    Swal.fire({ icon: 'warning', title: 'No ID provided', text: 'Cannot unblock customer without an ID.' });
                    return;
                }

                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, Unblock!'
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    fetch(`/admin/orders/${id}/customer/unblock`, {
                            method: 'GET',
                            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
                        })
                        .then(response => {
                            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                            return response.json();
                        })
                        .then(responseJson => {
                            if (responseJson.success) {
                                Swal.fire('Success!', responseJson.message || 'Customer unblocked successfully.', 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Failed!', responseJson.message || 'Customer unblock action failed.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Fetch operation error:', error);
                            Swal.fire('Error!', 'An unexpected error occurred during the request.', 'error');
                        });
                });
            }
        </script>
    </div>
    @endsection
