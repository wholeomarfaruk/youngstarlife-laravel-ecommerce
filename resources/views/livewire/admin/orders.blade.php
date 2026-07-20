<div class="container-fluid py-4 orders-page">
    <style>
        .orders-table {
            border: 1px solid var(--bs-border-color);
        }
        .orders-table th,
        .orders-table td {
            border: 1px solid var(--bs-border-color);
        }
        .orders-table tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        .orders-page .pagination {
            margin-left: 0;
        }
        .orders-page .pagination .page-link {
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
        }
        .orders-page .form-control,
        .orders-page .form-select,
        .orders-page .btn:not(.btn-sm) {
            height: 42px;
            display: inline-flex;
            align-items: center;
        }
        .orders-page select.form-select {
            display: block;
        }
        .orders-page .toolbar-row {
            flex-wrap: wrap;
        }
        .orders-page .toolbar-row > * {
            flex-shrink: 0;
        }
        .orders-page .search-group {
            flex: 1 1 260px;
            min-width: 220px;
        }
    </style>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h3 class="mb-0">Orders</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('admin.index') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Orders</li>
            </ol>
        </nav>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex toolbar-row gap-2 align-items-center">
                <div class="input-group search-group">
                    <input type="text" wire:model.live="search" placeholder="Search here..."
                        class="form-control" name="search">
                    <span class="input-group-text bg-transparent">
                        <i class="icon-search"></i>
                    </span>
                </div>

                <form class="d-flex gap-2" method="GET" action="{{ route('admin.orders.export') }}">
                    <select name="order_status" class="form-select">
                        <option value="">Select Status</option>
                        @foreach ($status_group as $sg)
                            <option value="{{ $sg->status }}">{{ $sg->status }} ({{ $sg->count }})</option>
                        @endforeach
                        <option value="courier_not_entered">Courier Not Entered</option>
                        <option value="courier_entered">Courier Entered</option>
                    </select>
                    <button class="btn btn-outline-secondary text-nowrap" type="submit">
                        <i class="icon-search"></i> Export
                    </button>
                </form>

                <a class="btn btn-primary text-nowrap" href="{{ route('admin.orders.add') }}">
                    <i class="icon-plus"></i> Add new
                </a>
                <a class="btn btn-outline-primary text-nowrap" href="{{ route('admin.orders.ai_extract') }}">
                    <i class="icon-plus"></i> AI Order Create
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm {{ !request()->order_status ? 'btn-primary' : 'btn-outline-primary' }}"
                    href="{{ route('admin.orders') }}">All ({{ $orders_count }})</a>

                @foreach ($status_group as $sg)
                    <a class="btn btn-sm text-capitalize {{ request()->order_status == $sg->status ? 'btn-primary' : 'btn-outline-primary' }}"
                        href="{{ route('admin.orders', ['order_status' => $sg->status]) }}">
                        {{ $sg->status }} ({{ $sg->count }})
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-lg-8">
                    <form id="bulk-action-form" class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-outline-secondary" id="bulk-select-button">Select</button>
                        <button type="button" class="btn btn-outline-secondary" id="all-select-button">All Select</button>

                        <select wire:model.live="order_status" class="form-select" style="width: auto;"
                            name="status" id="bulk-action-status" required>
                            <option selected>Select Status</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="processing">Processing</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="on_hold">On Hold</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="returned">Returned</option>
                            <option value="pending">Pending</option>
                            <option value="deleted">Delete</option>
                        </select>
                        <button id="bulk-action-button" type="submit" class="btn btn-outline-secondary">
                            Update Status
                        </button>
                    </form>
                    <form class="d-inline" id="sticker-print-form" action="{{ route('admin.generate.sticker') }}"
                        method="POST">
                        @csrf
                        <button id="bulk-sticker-print" type="button" class="btn btn-outline-secondary mt-2">
                            Print Stickers
                        </button>
                        <input type="text" name="ids" hidden>
                    </form>
                    <script>
                        var toggle = false;
                        document.getElementById('all-select-button').addEventListener('click', () => {
                            toggle = !toggle;
                            if (toggle) {
                                $('input.select-item').show();
                                document.querySelectorAll('input.select-item').forEach(el => el.checked = true);
                            } else {
                                $('input.select-item').hide();
                                document.querySelectorAll('input.select-item').forEach(el => el.checked = false);
                            }
                        });
                    </script>
                </div>
                <div class="col-lg-4">
                    <input type="text" wire:model.live="daterange" class="form-control rangedatepicker"
                        name="daterange" placeholder="Filter Date Range">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                {{ $orders->links('pagination::bootstrap-5') }}
            </div>
            <div class="table-responsive">
                <table id="datatable" class="table table-hover align-middle orders-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px">OrderNo</th>
                            <th class="text-center">Name</th>
                            <th class="text-center">Phone</th>
                            <th class="text-center">Consigment ID</th>
                            <th class="text-center">Subtotal</th>
                            <th class="text-center">Discount</th>
                            <th class="text-center">Delivery charge</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Order Date</th>
                            <th class="text-center">Total Items</th>
                            <th class="text-center">Delivered On</th>
                            <th class="text-center" style="width:200px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="text-center" data-id="{{ $order->id }}">
                                    <input type="checkbox" class="form-check-input select-item" name="ids[]"
                                        value="{{ $order->id }}" style="display: none;">
                                    {{ $order->id }}
                                </td>
                                <td class="text-center">
                                    {{ $order->name }}
                                    <span data-bs-toggle="tooltip" data-bs-html="true"
                                        title="Address: {{ $order->address }}&nbsp;&nbsp;Note: {{ $order->note }}&nbsp;&nbsp;Order Items:
                                                @foreach ($order->Order_Item as $item)
                                                   {{ $item?->product?->name }} x {{ $item?->quantity }} @endforeach
                                            ">
                                        <i class="icon-info cursor-pointer"></i>
                                    </span>
                                </td>
                                <td class="text-center {{ $order?->customer?->isBlocked ? 'text-danger' : '' }}">
                                    {{ $order->phone }}
                                </td>
                                <td class="text-center">{{ $order->consignment_id }}</td>
                                <td class="text-center">৳{{ $order->subtotal }}</td>
                                <td class="text-center">৳{{ $order->discount }}</td>
                                <td class="text-center">৳{{ $order->fee }}</td>
                                <td class="text-center">৳{{ $order->total }}</td>
                                <td class="text-center text-capitalize">{{ $order->status }}</td>
                                <td class="text-center">{{ $order->created_at }}</td>
                                <td class="text-center">{{ $order->Order_Item->count() }}</td>
                                <td class="text-center">{{ $order->delivery_date }}</td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        <a href="{{ route('admin.orders.details', $order->id) }}"
                                            class="btn btn-sm btn-outline-secondary" title="View">
                                            <i class="icon-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.orders.delete.soft', $order->id) }}"
                                            class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="icon-trash"></i>
                                        </a>
                                        <a class="send_courier btn btn-sm btn-outline-secondary" data-id="{{ $order->id }}"
                                            href="{{ route('admin.steadfast.place_order', $order->id) }}" title="Send to courier">
                                            <i class="icon-send"></i>
                                        </a>
                                        @if (!$order->customer || !$order->customer->isBlocked)
                                            <a id="blockcustomer" href="javascript:void(0)"
                                                onclick="blockcustomer({{ $order->id }})"
                                                class="btn btn-danger btn-sm">Block</a>
                                        @else
                                            <a href="javascript:void(0)" class="btn btn-success btn-sm"
                                                onclick="unblockCustomer({{ $order->id }})">Unblock</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
                {{ $orders->links() }}
            </div>
        </div>
    </div>
</div>
