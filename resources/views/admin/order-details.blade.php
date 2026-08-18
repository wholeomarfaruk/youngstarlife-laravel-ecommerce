@extends('layouts.admin')

@section('content')
    <!-- content area start -->
    <div class="main-content-inner" style="font-size: 1.05rem;">
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
            <div class="card shadow-sm mb-3">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                <h5 class="mb-0">Order #{{ $order->id }}</h5>
                                @php
                                    $statusColors = [
                                        'pending' => 'warning text-dark',
                                        'confirmed' => 'primary',
                                        'processing' => 'dark',
                                        'ready' => 'info text-dark',
                                        'in_transit' => 'info text-dark',
                                        'delivered' => 'success',
                                        'in_review' => 'warning text-dark',
                                        'delivery_in_review' => 'warning text-dark',
                                        'on_hold' => 'warning text-dark',
                                        'cancelled' => 'danger',
                                        'returned' => 'danger',
                                        'deleted' => 'danger',
                                    ];
                                    $statusColor = $statusColors[$order->status] ?? 'secondary';
                                @endphp
                                <span
                                    class="badge rounded-pill bg-{{ $statusColor }} fs-6 text-capitalize">{{ str_replace('_', ' ', $order->status) }}</span>
                                @if ($order->is_paid)
                                    <span class="badge bg-success fs-6">Paid</span>
                                @endif
                                @if ($order->courier_status)
                                    @php
                                        $courierStatusColors = [
                                            'delivered' => 'success',
                                            'rider_assigned' => 'info text-dark',
                                            'in_transit' => 'info text-dark',
                                            'returning' => 'warning text-dark',
                                            'returned' => 'danger',
                                        ];
                                        $courierStatusColor = $courierStatusColors[$order->courier_status->value] ?? 'secondary';
                                    @endphp
                                    <span
                                        class="badge rounded-pill bg-{{ $courierStatusColor }} fs-6 text-capitalize">Courier: {{ str_replace('_', ' ', $order->courier_status->value) }}</span>
                                @endif
                            </div>
                            <div class="text-muted">
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
                <div class="col-12">
                    <!-- Order meta -->
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <h6 class="mb-0 fw-bold">Order Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-borderless table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" width="180">Order No</td>
                                            <td class="fw-semibold">#{{ $order->id }}</td>
                                            <td class="text-muted" width="180">Total Quantity</td>
                                            <td class="fw-semibold">{{ $order->Order_Item->count() }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Consignment ID</td>
                                            <td class="fw-semibold">{{ $order->consignment_id ?: '-' }}</td>
                                            <td class="text-muted">Source</td>
                                            <td class="fw-semibold">{{ $order->source ? ucfirst($order->source) : '-' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Courier</td>
                                            <td class="fw-semibold">{{ $order->courier_partner ?: '-' }}</td>
                                            <td class="text-muted">Tracking No</td>
                                            <td class="fw-semibold">{{ $order->tracking_number ?: '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Courier Status</td>
                                            <td class="fw-semibold" colspan="3">
                                                @if ($order->courier_status)
                                                    <span
                                                        class="badge rounded-pill bg-{{ $courierStatusColor }} text-capitalize">{{ str_replace('_', ' ', $order->courier_status->value) }}</span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Ordered items -->
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <h6 class="mb-0 fw-bold">Ordered Items</h6>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                data-bs-target="#exampleModal">
                                <i class="icon-edit-3"></i> Edit Items
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped align-middle mb-0">
                                    <thead class="table-light">
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
                                                            alt="{{ $item->product?->name }}" class="rounded border"
                                                            style="width: 56px; height: 56px; object-fit: cover;">
                                                        <span
                                                            class="fw-semibold">{{ $item->product?->name ?? 'Deleted product' }}</span>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    ৳{{ number_format($item->product?->discount_price ?? ($item->product?->price ?? 0), 2) }}
                                                </td>
                                                <td class="text-center">{{ $item->quantity }}</td>
                                                <td class="text-center">
                                                    ৳{{ number_format((float) ($item->product?->discount_price ?? ($item->product?->price ?? 0)) * (int) $item->quantity, 2) }}
                                                </td>
                                                <td class="text-center">
                                                    @php
                                                        $rawOpts = $item->options;
                                                        if (is_string($rawOpts)) {
                                                            $rawOpts = json_decode($rawOpts, true);
                                                        }
                                                        $opts = is_array($rawOpts) ? array_filter($rawOpts) : [];
                                                    @endphp
                                                    @if (!empty($opts))
                                                        @foreach ($opts as $key => $value)
                                                            <div class="option-item">
                                                                <strong class="option-key">{{ ucfirst($key) }}</strong>:
                                                                <span class="option-value">{{ $value }}</span>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <span class="text-muted">&mdash;</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if ($item->rstatus)
                                                        <span class="badge bg-warning text-dark">Returned</span>
                                                    @else
                                                        <span class="badge bg-light text-muted border">No</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">No products found
                                                </td>
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


                    <!-- Customer / shipping -->
                    <div class="row bg-white">
                        <div class="col-md-6">

                            <div class="card shadow-sm mb-3">
                                <div
                                    class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <h6 class="mb-0 fw-bold">Shipping Details</h6>
                                    <div class="d-flex justify-content-end mb-2 gap-2">
                                        @if (!$order->customer || !$order->customer->is_blocked)
                                            <button id="blockcustomer" type="button"
                                                onclick="blockcustomer({{ $order->id }})"
                                                class="btn btn-outline-danger btn-sm">Block Customer</button>
                                        @else
                                            <span class="badge bg-danger me-2 align-self-center">Blacklisted</span>
                                            <button type="button" class="btn btn-outline-success btn-sm"
                                                onclick="unblockCustomer({{ $order->id }})">Unblock</button>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#orderDetails">
                                            <i class="icon-edit-3"></i> Edit
                                        </button>
                                    </div>

                                </div>
                                <div class="card-body">

                                    <table class="table table-borderless table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted" width="150">Name</td>
                                                <td class="fw-semibold">{{ $order->name }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Mobile</td>
                                                <td
                                                    class="fw-semibold {{ $order->customer?->is_blocked ? 'text-danger' : '' }}">
                                                    {{ $order->phone }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Delivery Area</td>
                                                <td class="fw-semibold">
                                                    {{ $order->delivery_area?->name ?? '-' }}{{ $order->delivery_area ? ' (' . number_format($order->delivery_area->charge, 0) . ' Tk)' : '' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Address</td>
                                                <td class="fw-semibold">{{ $order->address ?: '-' }} </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Order Note</td>
                                                <td class="fw-semibold">{{ $order->notes ?: '-' }}</td>
                                            </tr>
                                        </tbody>

                                    </table>



                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Order summary -->
                            <div class="card shadow-sm mb-3">
                                <div class="card-header bg-white">
                                    <h6 class="mb-0 fw-bold">Order Summary</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted">Product Price</td>
                                                <td class="text-end fw-semibold">{{ number_format($order->subtotal, 2) }}
                                                    Tk</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Delivery Fee</td>
                                                <td class="text-end fw-semibold">{{ number_format($order->fee, 2) }} Tk
                                                </td>
                                            </tr>
                                            @if ($order->discount > 0)
                                                <tr>
                                                    <td class="text-muted">Discount</td>
                                                    <td class="text-end fw-semibold text-danger">
                                                        -{{ number_format($order->discount, 2) }} Tk</td>
                                                </tr>
                                            @endif
                                            <tr class="border-top">
                                                <td class="fw-bold">Total Bill</td>
                                                <td class="text-end fw-bold fs-5">{{ number_format($order->total, 2) }} Tk
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Status update -->
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0 fw-bold">Update Order Status</h6>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.orders.update', $order->id) }}" method="POST"
                                class="row g-2 align-items-center">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="order_id" value="{{ $order->id }}">
                                <div class="col-sm-8 col-md-6">
                                    <select name="status" id="status" class="form-select">
                                        @foreach ([
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'ready' => 'Ready',
            'delivered' => 'Delivered',
            'on_hold' => 'On Hold',
            'in_review' => 'In Review',
            'in_transit' => 'In Transit',
            'processing' => 'Processing',
            'delivery_in_review' => 'Delivery in Review',
            'cancelled' => 'Cancelled',
            'returned' => 'Returned',
            'deleted' => 'Deleted',
        ] as $value => $label)
                                            <option value="{{ $value }}"
                                                {{ $order->status == $value ? 'selected' : '' }}>{{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-4 col-md-6">
                                    <button type="submit" class="btn btn-primary">Update Status</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Courier status update -->
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0 fw-bold">Update Courier Status</h6>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.orders.update.courier_status', $order->id) }}" method="POST"
                                class="row g-2 align-items-center">
                                @csrf
                                @method('PUT')
                                <div class="col-sm-8 col-md-6">
                                    <select name="courier_status" id="courier_status" class="form-select">
                                        <option value="">-- None --</option>
                                        @foreach (\App\Enums\CourierStatus::cases() as $case)
                                            <option value="{{ $case->value }}"
                                                {{ $order->courier_status === $case ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $case->value)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-4 col-md-6">
                                    <button type="submit" class="btn btn-primary">Update Courier Status</button>
                                </div>
                            </form>
                        </div>
                    </div>


                    <!-- Extra data -->
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0 fw-bold">Extra Data</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted" width="150">IP Address</td>
                                                <td class="fw-semibold">{{ $order->ip_address ?: '-' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted" width="150">User Agent</td>
                                                <td
                                                    class="fw-semibold {{ $order->device?->is_blocked ? 'text-danger' : '' }}">
                                                    {{ $order->user_agent ?: '-' }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @if ($order->json_data)
                                <details class="mt-2">
                                    <summary class="text-muted" style="cursor:pointer;">Raw JSON data</summary>
                                    <pre class="mt-2 mb-0" style="white-space:pre-wrap;">{{ json_encode($order->json_data, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Edit Order Items -->
        <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel"
            aria-hidden="true">
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
                            </fieldset>

                            <div id="editForm" class="mt-3">
                                @if ($order->Order_Item->count() > 0)
                                    @foreach ($order->Order_Item as $item)
                                        <div id="product-item-{{ $loop->index }}"
                                            class="product-item border rounded bg-light p-3 mb-3">
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
                                                            {{ $item->product->discount_price ?? $item->product->price }}
                                                            Tk
                                                        </strong>
                                                    </p>
                                                </div>
                                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                                    <label class="form-label small">Quantity</label>
                                                    <input type="hidden"
                                                        name="order_items[{{ $loop->index }}][product_id]"
                                                        value="{{ $item->product->id }}">
                                                    <input type="number"
                                                        name="order_items[{{ $loop->index }}][quantity]"
                                                        value="{{ $item->quantity }}" min="1"
                                                        class="form-control quantity-input"
                                                        data-line="{{ $loop->index }}">
                                                </div>
                                                <div class="col-6 col-md-3 mb-2 mb-md-0">
                                                    <label class="form-label small">Options</label>
                                                    <input type="text" name="order_items[{{ $loop->index }}][size]"
                                                        class="form-control" placeholder="Enter size"
                                                        value="{{ is_array($item->options) ? data_get($item, 'options.size') : json_decode($item->options)?->size }}">
                                                </div>
                                                <div class="col-12 col-md-2">
                                                    <button type="button"
                                                        class="btn btn-danger btn-sm w-100 mt-2 mt-md-0"
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
                                        <input type="number" id="total" name="total"
                                            class="form-control form-control-sm text-end fw-bold" style="max-width:160px;"
                                            min="0" step="0.01">
                                        <span>Tk</span>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        <div class="form-text text-start mb-2" id="totalHint"></div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary"
                                onclick="document.getElementById('orderEdit').submit();">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Product Picker -->
        <div class="modal fade" id="productPickerModal" tabindex="-1" aria-labelledby="productPickerModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="productPickerModalLabel">Add Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="text" id="productPickerSearch" class="form-control mb-3"
                            placeholder="Search products...">
                        <div id="productPickerGrid" class="row g-3"></div>
                        <div id="productPickerEmpty" class="text-center text-muted small py-4 d-none">
                            No products found.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template id="productPickerCardTemplate">
            <div class="col-6 col-md-4 product-picker-item">
                <div class="border rounded-3 p-2 text-center h-100 product-picker-card d-flex flex-column position-relative"
                    style="cursor:pointer;">
                    <button type="button"
                        class="btn btn-primary btn-sm rounded-circle position-absolute d-flex align-items-center justify-content-center product-picker-add"
                        style="width:30px; height:30px; top:-8px; right:-8px; padding:0; line-height:1; z-index:1;"
                        title="Add item">
                        <i class="icon-plus"></i>
                    </button>
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

        <!-- Modal: Edit Shipping Details -->
        <div class="modal fade" id="orderDetails" tabindex="-1" aria-labelledby="orderDetailsLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="orderDetailsLabel">Edit Shipping Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form action="{{ route('admin.orders.update.details', $order->id) }}" method="POST"
                            id="orderDetailForm">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="order_id" value="{{ $order->id }}">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Customer Name</label>
                                    <input type="text" name="name" class="form-control" id="name"
                                        value="{{ $order->name }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Customer Phone</label>
                                    <input type="text" name="phone" class="form-control" id="phone"
                                        value="{{ $order->phone }}">
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
                        <button type="button" class="btn btn-primary"
                            onclick="document.getElementById('orderDetailForm').submit();">
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
            const productPickerGrid = document.getElementById('productPickerGrid');
            const productPickerEmpty = document.getElementById('productPickerEmpty');
            const productPickerSearch = document.getElementById('productPickerSearch');
            const productPickerCardTemplate = document.getElementById('productPickerCardTemplate');

            const exampleModalEl = document.getElementById('exampleModal');
            const productPickerModalEl = document.getElementById('productPickerModal');
            const exampleModal = bootstrap.Modal.getInstance(exampleModalEl) || new bootstrap.Modal(exampleModalEl);
            const productPickerModal = bootstrap.Modal.getInstance(productPickerModalEl) || new bootstrap.Modal(
                productPickerModalEl);

            let lastCalculatedTotal = 0;

            let nextLineIndex = editForm.querySelectorAll('.product-item').length;

            exampleModalEl.addEventListener('shown.bs.modal', function() {
                attachQuantityListeners();
                calculateTotal({
                    syncTotalInput: true
                });
            });

            // Bootstrap modals aren't designed to stack directly, so the parent modal is
            // hidden while the picker is open and re-shown once the picker closes.
            openProductPickerBtn.addEventListener('click', function() {
                exampleModalEl.dataset.reopenPicker = '1';
                exampleModal.hide();
            });

            exampleModalEl.addEventListener('hidden.bs.modal', function() {
                if (exampleModalEl.dataset.reopenPicker === '1') {
                    exampleModalEl.dataset.reopenPicker = '';
                    productPickerSearch.value = '';
                    renderProductPicker('');
                    productPickerModal.show();
                }
            });

            productPickerModalEl.addEventListener('hidden.bs.modal', function() {
                exampleModal.show();
            });

            productPickerModalEl.addEventListener('shown.bs.modal', function() {
                productPickerSearch.focus();
            });

            productPickerSearch.addEventListener('input', function() {
                renderProductPicker(this.value.trim().toLowerCase());
            });

            function renderProductPicker(query) {
                productPickerGrid.innerHTML = '';

                const filtered = query ?
                    allProducts.filter(p => p.name.toLowerCase().includes(query)) :
                    allProducts;

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
                    const addBtn = card.querySelector('.product-picker-add');
                    const addIcon = addBtn.querySelector('i');

                    const alreadyAdded = addedProductIds.has(product.id);
                    const outOfStock = product.stock_status === 'out_of_stock';

                    if (alreadyAdded) {
                        cardEl.classList.add('opacity-50');
                        cardEl.title = 'Already added';
                        addBtn.disabled = true;
                        addBtn.classList.remove('btn-primary');
                        addBtn.classList.add('btn-secondary');
                        addIcon.className = 'icon-check';
                    } else if (outOfStock) {
                        cardEl.classList.add('opacity-50');
                        cardEl.title = 'Out of stock';
                        addBtn.disabled = true;
                    } else {
                        addBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            addProductLine(product);
                        });
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
                calculateTotal({
                    syncTotalInput: true
                });
            }

            function attachQuantityListeners() {
                document.querySelectorAll('.quantity-input').forEach(input => {
                    input.removeEventListener('input', handleQuantityInput);
                    input.addEventListener('input', handleQuantityInput);
                });
            }

            function handleQuantityInput() {
                calculateTotal({
                    syncTotalInput: true
                });
            }

            function removeProductLine(line) {
                const productItem = document.getElementById(`product-item-${line}`);
                if (productItem) productItem.remove();

                calculateTotal({
                    syncTotalInput: true
                });
            }

            discountInput.addEventListener('input', () => calculateTotal({
                syncTotalInput: true
            }));
            fee.addEventListener('input', () => calculateTotal({
                syncTotalInput: true
            }));

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

                calculateTotal({
                    syncTotalInput: false
                });
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
                    totalHint.textContent =
                        `Total Amount is below the calculated total (${calculatedTotal.toFixed(2)} Tk) - the ${(calculatedTotal - enteredTotal).toFixed(2)} Tk difference has been added to Discount.`;
                    totalHint.classList.add('text-danger');
                } else {
                    totalHint.textContent = '';
                    totalHint.classList.remove('text-danger');
                }
            }

            discountInput.dataset.manualDiscount = discountInput.value || 0;
            attachQuantityListeners();
            calculateTotal({
                syncTotalInput: true
            });
        </script>

        <script>
            function blockcustomer(id) {
                if (!id) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No ID provided',
                        text: 'Cannot block customer without an ID.'
                    });
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
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            }
                        })
                        .then(response => {
                            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                            return response.json();
                        })
                        .then(responseJson => {
                            if (responseJson.success) {
                                Swal.fire('Success!', responseJson.message || 'Customer blocked successfully.',
                                        'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Failed!', responseJson.message || 'Customer block action failed.',
                                    'error');
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
                    Swal.fire({
                        icon: 'warning',
                        title: 'No ID provided',
                        text: 'Cannot unblock customer without an ID.'
                    });
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
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            }
                        })
                        .then(response => {
                            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                            return response.json();
                        })
                        .then(responseJson => {
                            if (responseJson.success) {
                                Swal.fire('Success!', responseJson.message || 'Customer unblocked successfully.',
                                        'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Failed!', responseJson.message || 'Customer unblock action failed.',
                                    'error');
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
