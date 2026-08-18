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
        [x-cloak] {
            display: none !important;
        }
        .orders-table input[type="checkbox"] {
            display: inline-block;
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

                <form class="d-flex gap-2" method="GET" action="{{ route('admin.orders.export') }}" id="export-form">
                    <select name="order_status" class="form-select">
                        <option value="">Select Status</option>
                        @foreach ($status_group as $sg)
                            <option value="{{ $sg->status }}">{{ $sg->status }} ({{ $sg->count }})</option>
                        @endforeach
                        <option value="courier_not_entered">Courier Not Entered</option>
                        <option value="courier_entered">Courier Entered</option>
                    </select>
                    <input type="hidden" name="format" id="export-format" value="excel">
                    <div x-data="{ open: false }" class="dropdown" @click.outside="open = false">
                        <button type="button" class="btn btn-outline-secondary text-nowrap dropdown-toggle"
                            @click="open = !open">
                            <i class="icon-download"></i> Export
                        </button>
                        <div x-show="open" x-cloak x-transition
                            class="dropdown-menu dropdown-menu-end show position-absolute">
                            <button type="submit" class="dropdown-item"
                                @click="document.getElementById('export-format').value = 'excel'">
                                <i class="icon-file-text me-1"></i> Excel
                            </button>
                            <button type="submit" class="dropdown-item"
                                @click="document.getElementById('export-format').value = 'pdf'">
                                <i class="icon-file me-1"></i> PDF
                            </button>
                        </div>
                    </div>
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
                            <th class="text-center">Source</th>
                            <th class="text-center">Name</th>
                            <th class="text-center">Phone</th>
                            <th class="text-center">Consigment ID</th>
                            <th class="text-center">Courier Status</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Order Date</th>
                            <th class="text-center">Total Items</th>
                            <th class="text-center" style="width:120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="text-center" data-id="{{ $order->id }}">
                                    <input type="checkbox" class="form-check-input select-item" name="ids[]"
                                        value="{{ $order->id }}" style="display: none">
                                    {{ $order->id }}
                                </td>
                                                                <td class="text-center text-capitalize">{{ $order->source ?? '-' }}</td>

                                <td class="text-center">
                                    <span x-data="{ open: false }" class="position-relative d-inline-flex align-items-center gap-1">
                                        {{ $order->name }}
                                        <i class="icon-info cursor-pointer"
                                            @mouseenter="open = true" @mouseleave="open = false"
                                            @click="open = true; $dispatch('open-order-modal', { id: {{ $order->id }} })"></i>
                                        <span x-show="open" x-cloak x-transition
                                            class="position-absolute bg-dark text-white small rounded px-2 py-1"
                                            style="bottom: 100%; left: 50%; transform: translateX(-50%); white-space: nowrap; z-index: 10;">
                                            Click for order details
                                        </span>
                                    </span>
                                </td>
                                <td class="text-center {{ $order?->customer?->isBlocked ? 'text-danger' : '' }}">
                                    {{ $order->phone }}
                                </td>
                                <td class="text-center">{{ $order->consignment_id }}</td>
                                <td class="text-center">
                                    @php
                                        $courierStatusColors = [
                                            'delivered' => 'success',
                                            'rider_assigned' => 'info text-dark',
                                            'in_transit' => 'info text-dark',
                                            'returning' => 'warning text-dark',
                                            'returned' => 'danger',
                                        ];
                                        $courierStatusColor = $courierStatusColors[$order->courier_status?->value] ?? 'secondary';
                                    @endphp
                                    @if ($order->courier_status)
                                        <span
                                            class="badge rounded-pill bg-{{ $courierStatusColor }} text-capitalize">{{ str_replace('_', ' ', $order->courier_status->value) }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center">৳{{ $order->total }}</td>
                                <td class="text-center text-capitalize">{{ $order->status }}</td>
                                <td class="text-center">{{ $order->created_at }}</td>
                                <td class="text-center">{{ $order->Order_Item->count() }}</td>
                                <td class="text-center">
                                    <div x-data="{ open: false }" class="dropdown" @click.outside="open = false">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                            @click="open = !open">
                                            Actions <i class="icon-chevron-down"></i>
                                        </button>
                                        <div x-show="open" x-cloak x-transition
                                            class="dropdown-menu dropdown-menu-end show position-absolute"
                                            style="right: 0; left: auto;">
                                            <a href="{{ route('admin.orders.details', $order->id) }}"
                                                class="dropdown-item">
                                                <i class="icon-eye me-1"></i> View
                                            </a>
                                            <a href="{{ route('admin.orders.delete.soft', $order->id) }}"
                                                class="dropdown-item text-danger">
                                                <i class="icon-trash me-1"></i> Delete
                                            </a>
                                            <a class="send_courier dropdown-item" data-id="{{ $order->id }}"
                                                href="{{ route('admin.steadfast.place_order', $order->id) }}">
                                                <i class="icon-send me-1"></i> Send to courier
                                            </a>
                                            @if (!$order->customer || !$order->customer->isBlocked)
                                                <a id="blockcustomer" href="javascript:void(0)"
                                                    onclick="blockcustomer({{ $order->id }})"
                                                    class="dropdown-item text-danger">Block</a>
                                            @else
                                                <a href="javascript:void(0)" class="dropdown-item text-success"
                                                    onclick="unblockCustomer({{ $order->id }})">Unblock</a>
                                            @endif
                                        </div>
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

    <div x-data="{
            open: false,
            loading: false,
            content: '',
            fetchOrder(id) {
                this.open = true;
                this.loading = true;
                this.content = '';
                fetch('{{ url('admin/orders') }}/' + id + '/quick-view')
                    .then(r => r.text())
                    .then(html => { this.content = html; this.loading = false; })
                    .catch(() => { this.content = '<p class=\'text-danger\'>Failed to load order details.</p>'; this.loading = false; });
            }
         }"
         @open-order-modal.window="fetchOrder($event.detail.id)"
         x-show="open" x-cloak
         class="modal-backdrop-custom position-fixed top-0 start-0 w-100 h-100 align-items-center justify-content-center"
         style="z-index: 1055; background: rgba(0,0,0,.5); display: flex;"
         @click.self="open = false">
         <div class="position-relative d-flex align-items-center justify-content-center w-100 h-100">

        <div class="bg-white rounded shadow" style="width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;"
             @click.outside="open = false">
            <div class="d-flex justify-content-between align-items-center border-bottom p-3">
                <h5 class="mb-0">Order Details</h5>
                <button type="button" class="btn-close" @click="open = false"></button>
            </div>
            <div class="p-3">
                <div x-show="loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <div x-show="!loading" x-html="content"></div>
            </div>
        </div>
        </div>
    </div>
</div>
