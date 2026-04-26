<div class="main-content-inner">
    <div class="main-content-wrap">
        <div class="flex items-center flex-wrap justify-between gap20 mb-27">
            <h3>Orders</h3>
            <ul class="breadcrumbs flex items-center flex-wrap justify-start gap10">
                <li>
                    <a href="index.html">
                        <div class="text-tiny">Dashboard</div>
                    </a>
                </li>
                <li>
                    <i class="icon-chevron-right"></i>
                </li>
                <li>
                    <div class="text-tiny">Orders</div>
                </li>
            </ul>
        </div>

        <div class="wg-box">
            <div class="row ">
                <div class="wg-filter col-md-6 mb-3">
                    <form class="form-search">
                        <fieldset class="name">
                            <input type="text" wire:model.live='search' placeholder="Search here..." class=""
                                name="search" tabindex="2" value="" aria-required="true" required="">
                        </fieldset>
                        <div class="button-submit">
                            <button class="" type="submit"><i class="icon-search"></i></button>
                        </div>
                    </form>
                </div>
                <div class="wg-filter col-md-6 mb-3">
                    <form class="form-search" method="GET" action="{{ route('admin.orders.export') }}">
                        <fieldset class="name">
                            <select name="order_status" id="">
                                <option value="">Select Status</option>

                                @foreach ($status_group as $sg)
                                    <option value="{{ $sg->status }}">{{ $sg->status }} ({{ $sg->count }})
                                    </option>
                                @endforeach
                                <option value="courier_not_entered">Courier Not Entered</option>
                                <option value="courier_entered">Courier Entered</option>

                            </select>
                        </fieldset>
                        <div class="button-submit">
                            <button class="" type="submit"><i class="icon-search"></i> Export</button>
                        </div>

                    </form>
                    <a class="tf-button style-1 w208" href="{{ route('admin.orders.add') }}"><i
                            class="icon-plus"></i>Add new</a>
                </div>
            </div>
            <div class="wg-box">
                <div class="flex items-center justify-start flex-wrap gap10">
                    <a class="tf-button style-1 text-capitalize" href="{{ route('admin.orders') }}">All
                        ({{ $orders_count }})</a>

                    @foreach ($status_group as $sg)
                        <a class="tf-button style-1 text-capitalize {{ request()->order_status == $sg->status ? 'bg-primary text-white' : '' }}"
                            href="{{ route('admin.orders', ['order_status' => $sg->status]) }}">{{ $sg->status }}
                            ({{ $sg->count }})
                        </a>
                    @endforeach
                </div>

            </div>
            <div class=" row">
                <div class="col-md-6 d-flex align-items-center gap-2">
                    <form id="bulk-action-form" action="" class="d-inline " style="width:fit-content;">

                        <div class="flex items-center flex-wrap justify-start gap-2 ">


                            <button type="button" class="btn btn-outline-secondary"
                                id="bulk-select-button">Select</button>
                            <button type="button" class="btn btn-outline-secondary" id="all-select-button">All
                                Select</button>

                            <select wire:model.live='order_status' class="form-control btn-outline-success"
                                style="width: inherit;" name="status" id="bulk-action-status" required>
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

                        </div>
                    </form>
                    <form class="d-inline" style="width:fit-content;" id="sticker-print-form"
                        action="{{ route('admin.generate.sticker') }}" method="POST">
                        @csrf
                        <button id="bulk-sticker-print" type="button" class="btn btn-outline-secondary">
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
                <div class="col-md-6">
                    <input type="text" wire:model.live='daterange' class="form-control rangedatepicker"
                        name="daterange" placeholder="Filter Date Range">
                </div>
            </div>
            <div class="wg-table table-all-user">
                <div class="divider"></div>
                <div class="flex items-center justify-between flex-wrap gap10 wgp-pagination">
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
                <div class="table-responsive">
                    <table id="datatable" class="table table-striped table-bordered">
                        <thead>
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
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td class="text-center" data-id="{{ $order->id }}"> <input type="checkbox"
                                            class="form-check-input select-item p-2" name="ids[] "
                                            value="{{ $order->id }}"
                                            style="display: none; z-index: 1; top:10px;left:10px; ">
                                        {{ $order->id }}</td>
                                    <td class="text-center">{{ $order->name }}
                                        <span data-bs-toggle="tooltip" data-bs-html="true"
                                            title="Address: {{ $order->address }}&nbsp;&nbsp;Note: {{ $order->note }}&nbsp;&nbsp;Order Items:
                                                    @foreach ($order->Order_Item as $item)
                                                       {{ $item?->product?->name }} x {{ $item?->quantity }} @endforeach

                                                ">
                                            <i class="icon-info cursor-pointer"></i>
                                        </span>

                                    </td>
                                    <td class="text-center {{ $order?->customer?->isBlocked ? 'text-danger' : '' }}">
                                        {{ $order->phone }}</td>
                                    <td class="text-center">{{ $order->consignment_id }}</td>

                                    <td class="text-center">৳{{ $order->subtotal }}</td>
                                    <td class="text-center">৳{{ $order->discount }}</td>
                                    <td class="text-center">৳{{ $order->fee }}</td>

                                    <td class="text-center">৳{{ $order->total }}</td>


                                    <td class="text-center">{{ $order->status }}</td>
                                    <td class="text-center">{{ $order->created_at }}</td>
                                    <td class="text-center">{{ $order->Order_Item->count() }}</td>
                                    <td class="text-center">{{ $order->delivery_date }} </td>
                                    <td class="text-center">
                                        <div clas="d-flex justify-center gap-2 align-items-center flex-direction-row"
                                            style="display: flex; gap: 10px; justify-content: center; align-items: center; flex-direction: row;">
                                            <a href="{{ route('admin.orders.details', $order->id) }}">
                                                <div class="list-icon-function view-icon">
                                                    <div class="item eye">
                                                        <i class="icon-eye"></i>
                                                    </div>
                                                </div>
                                            </a>
                                            <a href="{{ route('admin.orders.delete.soft', $order->id) }}">
                                                <div class="list-icon-function">
                                                    <div class="item trash">
                                                        <i class="icon-trash"></i>
                                                    </div>
                                                </div>
                                            </a>
                                            <a class="send_courier" data-id="{{ $order->id }}"
                                                href="{{ route('admin.steadfast.place_order', $order->id) }}">
                                                <div class="list-icon-function">
                                                    <div class="item send">
                                                        <i class="icon-send"></i>
                                                    </div>
                                                </div>
                                            </a>
                                            @if (!$order->customer || !$order->customer->isBlocked)
                                                <a id="blockcustomer" href="javascript:void(0)"
                                                    onclick="blockcustomer({{ $order->id }})"
                                                    class="btn btn-danger btn-sm small">Block</a>
                                            @else
                                                <a href="javascript:void(0)" class="btn btn-success btn-sm small"
                                                    onclick="unblockCustomer({{ $order->id }})">Unblock</a>
                                            @endif

                                        </div>
                                    </td>
                                </tr>
                            @endforeach


                        </tbody>
                    </table>
                </div>
            </div>
            <div class="divider"></div>
            <div class="flex items-center justify-between flex-wrap gap10 wgp-pagination">
                {{ $orders->links() }}
            </div>
        </div>
    </div>
</div>
