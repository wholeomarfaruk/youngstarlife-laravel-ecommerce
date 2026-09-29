<div class="container-fluid py-4 orders-page">
    <style>
        .op-table {
            border: 1px solid var(--bs-border-color);
        }
        .op-table th,
        .op-table td {
            border: 1px solid var(--bs-border-color);
            vertical-align: middle;
        }
        .op-table .product-row {
            cursor: pointer;
        }
        .op-table .product-row:hover {
            background-color: rgba(0, 0, 0, 0.03);
        }
        .op-thumb {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 6px;
        }
        .op-qty {
            font-size: 1.15rem;
            font-weight: 700;
        }
        .op-stat {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .orders-page .form-control,
        .orders-page .form-select {
            height: 42px;
        }
        .op-day-header {
            background: var(--bs-light, #f8f9fa);
        }
        [x-cloak] {
            display: none !important;
        }
    </style>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h3 class="mb-0">Orders</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('admin.index') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.orders') }}" class="text-decoration-none">Orders</a></li>
                <li class="breadcrumb-item active" aria-current="page">Product Summary</li>
            </ol>
        </nav>
    </div>

    @include('admin.partials.orders-tabs', ['active' => 'products'])

    {{-- Filters --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small text-muted mb-1">Start date</label>
                    <input type="date" wire:model.live="start_date" class="form-control">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small text-muted mb-1">End date</label>
                    <input type="date" wire:model.live="end_date" class="form-control">
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label small text-muted mb-1">Order status</label>
                    <select wire:model.live="order_status" class="form-select text-capitalize">
                        <option value="">All status</option>
                        @foreach ($status_group as $status)
                            <option value="{{ $status }}">{{ str_replace('_', ' ', $status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label small text-muted mb-1">Product</label>
                    <input type="text" wire:model.live.debounce.400ms="product_search" class="form-control"
                        placeholder="Product name, SKU or ID">
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label small text-muted mb-1">Order</label>
                    <input type="text" wire:model.live.debounce.400ms="order_search" class="form-control"
                        placeholder="Phone number or order number">
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
                @php $today = now()->toDateString(); @endphp
                @foreach ([
                    'today' => ['Today', $today, $today],
                    'yesterday' => ['Yesterday', now()->subDay()->toDateString(), now()->subDay()->toDateString()],
                    'last7' => ['Last 7 days', now()->subDays(6)->toDateString(), $today],
                    'last30' => ['Last 30 days', now()->subDays(29)->toDateString(), $today],
                    'this_month' => ['This month', now()->startOfMonth()->toDateString(), $today],
                ] as $key => [$label, $from, $to])
                    <button type="button" wire:click="setRange('{{ $key }}')"
                        class="btn btn-sm {{ $start_date === $from && $end_date === $to ? 'btn-primary' : 'btn-outline-primary' }}">
                        {{ $label }}
                    </button>
                @endforeach

                <div class="form-check form-switch ms-md-3 mb-0">
                    <input class="form-check-input" type="checkbox" id="split_size" wire:model.live="split_size">
                    <label class="form-check-label" for="split_size">Split by size</label>
                </div>

                <button type="button" wire:click="resetFilters" class="btn btn-sm btn-outline-secondary ms-auto">
                    Reset filters
                </button>
                <span wire:loading class="spinner-border spinner-border-sm text-primary"></span>
            </div>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['Total pieces', number_format($summary['qty']), 'text-primary'],
            ['Orders', number_format($summary['orders']), ''],
            ['Products', number_format($summary['products']), ''],
            ['Product amount', '৳' . number_format($summary['amount']), 'text-success'],
        ] as [$label, $value, $class])
            <div class="col-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="op-stat {{ $class }}">{{ $value }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if (empty($days))
        <div class="card shadow-sm">
            <div class="card-body text-center text-muted py-5">
                No products ordered for the selected filters.
            </div>
        </div>
    @else
        {{-- Range total (only useful when more than one day) --}}
        @if (count($days) > 1)
            <div class="card shadow-sm mb-4" x-data="{ show: true }">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0">
                        Total for {{ \Carbon\Carbon::parse($start_date)->format('d M Y') }}
                        – {{ \Carbon\Carbon::parse($end_date)->format('d M Y') }}
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="show = !show"
                        x-text="show ? 'Hide' : 'Show'"></button>
                </div>
                <div class="card-body" x-show="show" x-collapse>
                    @include('livewire.admin.partials.order-products-table', [
                        'rows' => $productTotals,
                        'tableKey' => 'total',
                    ])
                </div>
            </div>
        @endif

        {{-- Day wise --}}
        @foreach ($days as $date => $day)
            @php $carbonDate = \Carbon\Carbon::parse($date); @endphp
            <div class="card shadow-sm mb-4" wire:key="day-{{ $date }}">
                <div class="card-header op-day-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">
                        {{ $carbonDate->format('d M Y, l') }}
                        @if ($carbonDate->isToday())
                            <span class="badge bg-success ms-1">Today</span>
                        @elseif ($carbonDate->isYesterday())
                            <span class="badge bg-secondary ms-1">Yesterday</span>
                        @endif
                    </h5>
                    <div class="d-flex gap-3 small">
                        <span><strong>{{ number_format($day['qty']) }}</strong> pcs</span>
                        <span><strong>{{ count($day['order_ids']) }}</strong> orders</span>
                        <span><strong>{{ count($day['rows']) }}</strong> products</span>
                    </div>
                </div>
                <div class="card-body">
                    @include('livewire.admin.partials.order-products-table', [
                        'rows' => $day['rows'],
                        'tableKey' => $date,
                    ])
                </div>
            </div>
        @endforeach
    @endif

    @include('livewire.admin.partials.order-quick-view-modal')
</div>
