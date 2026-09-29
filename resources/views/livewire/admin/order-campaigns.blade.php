<div class="container-fluid py-4 orders-page">
    <style>
        .oc-table {
            border: 1px solid var(--bs-border-color);
        }
        .oc-table th,
        .oc-table td {
            border: 1px solid var(--bs-border-color);
            vertical-align: middle;
        }
        .oc-table .group-row {
            cursor: pointer;
        }
        .oc-table .group-row:hover {
            background-color: rgba(0, 0, 0, 0.03);
        }
        .oc-table .untracked-row {
            background-color: rgba(0, 0, 0, 0.015);
        }
        .oc-count {
            font-size: 1.15rem;
            font-weight: 700;
        }
        .oc-stat {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .oc-status-badge {
            font-size: 0.72rem;
            font-weight: 500;
        }
        .orders-page .form-control,
        .orders-page .form-select {
            height: 42px;
        }
        [x-cloak] {
            display: none !important;
        }
    </style>

    @php
        $statusColors = [
            'pending' => 'warning text-dark',
            'confirmed' => 'primary',
            'processing' => 'info text-dark',
            'in_transit' => 'info text-dark',
            'delivered' => 'success',
            'on_hold' => 'secondary',
            'cancelled' => 'danger',
            'returned' => 'danger',
        ];
        $levelLabel = ['campaign' => 'Campaign', 'adset' => 'Ad set', 'ad' => 'Ad'][$group_by] ?? 'Campaign';
    @endphp

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h3 class="mb-0">Orders</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('admin.index') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.orders') }}" class="text-decoration-none">Orders</a></li>
                <li class="breadcrumb-item active" aria-current="page">Campaigns</li>
            </ol>
        </nav>
    </div>

    @include('admin.partials.orders-tabs', ['active' => 'campaigns'])

    @if (!$ready)
        <div class="alert alert-warning">
            Campaign tracking columns are not in the database yet. Run <code>php artisan migrate</code> on the server.
        </div>
    @else
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
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small text-muted mb-1">Order status</label>
                        <select wire:model.live="order_status" class="form-select text-capitalize">
                            <option value="">All status</option>
                            @foreach ($status_group as $status)
                                <option value="{{ $status }}">{{ str_replace('_', ' ', $status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small text-muted mb-1">Group by</label>
                        <select wire:model.live="group_by" class="form-select">
                            <option value="campaign">Campaign</option>
                            <option value="adset">Ad set</option>
                            <option value="ad">Ad</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label small text-muted mb-1">Traffic</label>
                        <select wire:model.live="traffic" class="form-select">
                            <option value="">All orders</option>
                            <option value="ads">Only ad / campaign traffic</option>
                            <option value="untracked">Only untracked orders</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted mb-1">Campaign</label>
                        <input type="text" wire:model.live.debounce.400ms="campaign_search" class="form-control"
                            placeholder="Campaign, ad set, ad name or ID">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted mb-1">Product</label>
                        <input type="text" wire:model.live.debounce.400ms="product_search" class="form-control"
                            placeholder="Product name, SKU or ID">
                    </div>
                    <div class="col-12 col-md-4">
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
                ['Orders', number_format($summary['orders']), ''],
                ['From campaigns', number_format($summary['tracked_orders']) . ($summary['orders'] ? ' (' . round($summary['tracked_orders'] / $summary['orders'] * 100) . '%)' : ''), 'text-primary'],
                [$levelLabel . 's', number_format($summary['campaigns']), ''],
                ['Pieces', number_format($summary['pieces']), ''],
                ['Sales', '৳' . number_format($summary['amount']), 'text-success'],
            ] as [$label, $value, $class])
                <div class="col-6 col-lg">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="oc-stat {{ $class }}">{{ $value }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if (empty($groups))
            <div class="card shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    No orders for the selected filters.
                </div>
            </div>
        @else
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 oc-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:50px" class="text-center">#</th>
                                    <th>{{ $levelLabel }}</th>
                                    <th class="text-center" style="width:130px">Channel</th>
                                    <th class="text-center" style="width:90px">Orders</th>
                                    <th class="text-center" style="width:90px">Pieces</th>
                                    <th class="text-center" style="width:120px">Sales</th>
                                    <th style="min-width:200px">Status</th>
                                    <th class="text-center" style="width:110px">Cancel / Return</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            @foreach ($groups as $key => $group)
                                <tbody x-data="{ open: false }" wire:key="group-{{ $group_by }}-{{ md5($key) }}">
                                    <tr class="group-row {{ $group['tracked'] ? '' : 'untracked-row' }}" @click="open = !open">
                                        <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                        <td>
                                            <div class="fw-semibold {{ $group['tracked'] ? '' : 'text-muted' }}">{{ $group['label'] }}</div>
                                            <div class="small text-muted">
                                                @if ($group['parent'])
                                                    <span>{{ $group_by === 'ad' ? '' : 'Campaign: ' }}{{ $group['parent'] }}</span>
                                                @endif
                                                @if ($group['id'])
                                                    <span>{{ $group['parent'] ? '·' : '' }} ID: {{ $group['id'] }}</span>
                                                @endif
                                                @if (!empty($group['hint']))
                                                    <span>{{ $group['hint'] }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            @if ($group['channel'])
                                                <span class="badge bg-light text-dark border text-capitalize">{{ $group['channel'] }}</span>
                                            @endif
                                            @if ($group['medium'])
                                                <div class="small text-muted">{{ $group['medium'] }}</div>
                                            @endif
                                        </td>
                                        <td class="text-center"><span class="oc-count">{{ $group['order_count'] }}</span></td>
                                        <td class="text-center">{{ $group['pieces'] }}</td>
                                        <td class="text-center">৳{{ number_format($group['amount']) }}</td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach ($group['statuses'] as $status => $count)
                                                    <span class="badge rounded-pill oc-status-badge text-capitalize bg-{{ $statusColors[$status] ?? 'secondary' }}">
                                                        {{ str_replace('_', ' ', $status) }} {{ $count }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="text-center {{ $group['lost_rate'] >= 30 ? 'text-danger fw-semibold' : 'text-muted' }}">
                                            {{ $group['lost_rate'] }}%
                                        </td>
                                        <td class="text-center">
                                            <i class="icon-chevron-down" :class="{ 'icon-chevron-up': open }"></i>
                                        </td>
                                    </tr>
                                    <tr x-show="open" x-cloak>
                                        <td colspan="9" class="bg-light p-2">
                                            <table class="table table-sm table-bordered bg-white mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Order No</th>
                                                        <th>Customer</th>
                                                        <th>Phone</th>
                                                        @if ($group_by !== 'ad')
                                                            <th>Ad</th>
                                                        @endif
                                                        <th class="text-center">Pcs</th>
                                                        <th class="text-center">Total</th>
                                                        <th class="text-center">Status</th>
                                                        <th>Time</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($group['orders'] as $order)
                                                        <tr>
                                                            <td>#{{ $order['id'] }}</td>
                                                            <td>{{ $order['name'] }}</td>
                                                            <td>{{ $order['phone'] }}</td>
                                                            @if ($group_by !== 'ad')
                                                                <td class="small text-muted">{{ $order['ad'] ?: '-' }}</td>
                                                            @endif
                                                            <td class="text-center">{{ $order['pieces'] }}</td>
                                                            <td class="text-center">৳{{ number_format($order['total']) }}</td>
                                                            <td class="text-center">
                                                                <span class="badge rounded-pill text-capitalize bg-{{ $statusColors[$order['status']] ?? 'secondary' }}">
                                                                    {{ str_replace('_', ' ', $order['status']) }}
                                                                </span>
                                                            </td>
                                                            <td class="small text-nowrap">{{ $order['time'] }}</td>
                                                            <td class="text-nowrap">
                                                                <a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary"
                                                                    @click.stop="$dispatch('open-order-modal', { id: {{ $order['id'] }} })">
                                                                    Quick view
                                                                </a>
                                                                <a href="{{ route('admin.orders.details', $order['id']) }}"
                                                                    class="btn btn-sm btn-outline-primary" target="_blank">
                                                                    Details
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                </tbody>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>
        @endif
    @endif

    @include('livewire.admin.partials.order-quick-view-modal')
</div>
