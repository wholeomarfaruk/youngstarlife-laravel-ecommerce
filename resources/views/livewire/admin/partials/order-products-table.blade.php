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
@endphp
<div class="table-responsive">
    <table class="table align-middle mb-0 op-table">
        <thead class="table-light">
            <tr>
                <th style="width:50px" class="text-center">#</th>
                <th>Product</th>
                @if ($split_size)
                    <th class="text-center" style="width:90px">Size</th>
                @endif
                <th class="text-center" style="width:110px">Quantity</th>
                <th class="text-center" style="width:90px">Orders</th>
                <th class="text-center" style="width:120px">Amount</th>
                <th style="width:40px"></th>
            </tr>
        </thead>
        @foreach ($rows as $key => $row)
            <tbody x-data="{ open: false }" wire:key="{{ $tableKey }}-{{ $key }}">
                <tr class="product-row" @click="open = !open">
                    <td class="text-center text-muted">{{ $loop->iteration }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img src="{{ $productImages[$row['product_id']] ?? asset('website/img/thumbnails/featured_img.jpg') }}"
                                class="op-thumb" alt="">
                            <div>
                                <div class="fw-semibold">{{ $row['name'] }}</div>
                                <div class="small text-muted">
                                    ID: {{ $row['product_id'] }}
                                    @if ($row['sku'])
                                        · SKU: {{ $row['sku'] }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>
                    @if ($split_size)
                        <td class="text-center">
                            @if ($row['size'])
                                <span class="badge bg-dark">{{ $row['size'] }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    @endif
                    <td class="text-center">
                        <span class="op-qty">{{ $row['qty'] }}</span> <span class="small text-muted">pcs</span>
                        @if ($row['returned'])
                            <div class="small text-danger">{{ $row['returned'] }} returned</div>
                        @endif
                    </td>
                    <td class="text-center">{{ collect($row['orders'])->pluck('id')->unique()->count() }}</td>
                    <td class="text-center">৳{{ number_format($row['amount']) }}</td>
                    <td class="text-center">
                        <i class="icon-chevron-down" :class="open && 'icon-chevron-up'"></i>
                    </td>
                </tr>
                <tr x-show="open" x-cloak>
                    <td colspan="{{ $split_size ? 7 : 6 }}" class="bg-light p-2">
                        <table class="table table-sm table-bordered bg-white mb-0">
                            <thead>
                                <tr>
                                    <th>Order No</th>
                                    <th>Customer</th>
                                    <th>Phone</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-center">Status</th>
                                    <th>Time</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($row['orders'] as $order)
                                    <tr>
                                        <td>#{{ $order['id'] }}</td>
                                        <td>{{ $order['name'] }}</td>
                                        <td>{{ $order['phone'] }}</td>
                                        <td class="text-center">{{ $order['qty'] }}</td>
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
