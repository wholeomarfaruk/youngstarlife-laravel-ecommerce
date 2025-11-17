@extends('layouts.admin')

@section('content')
    <!-- content area start -->
    <div class="main-content-inner">
        <div class="main-content-wrap">
            <div class="flex items-center flex-wrap justify-between gap20 mb-27">
                <h3>Confirmed Orders</h3>
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
                                <input type="text" placeholder="Search here..." class="" name="search"
                                    tabindex="2" value="" aria-required="true" required="">
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
                 <div class="wg-box d-flex flex-row">
                    <form id="bulk-action-form" action="" class="d-inline" style="width:fit-content;">

                        <div class="flex items-center flex-wrap justify-start gap20 mb-27">


                            <button type="button" class="btn btn-outline-secondary" id="bulk-select-button">Select</button>
                            <button type="button" class="btn btn-outline-secondary" id="all-select-button">All
                                Select</button>

                            <select class="form-control btn-outline-success" style="width: inherit;" name="status"
                                id="bulk-action-status" required>
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
                    <form  class="d-inline" style="width:fit-content;" id="sticker-print-form" action="{{ route('admin.generate.sticker') }}" method="POST">
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
                <div class="wg-table table-all-user">
                    <div class="divider"></div>
                    <div class="flex items-center justify-between flex-wrap gap10 wgp-pagination">
                        {{ $orders->links('pagination::bootstrap-5') }}
                    </div>
                     <style>
                            .select-item:checked {
                                background-color: #0d6efd !important;
                            }
                        </style>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
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
                                    <th></th>
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
                                            <span data-bs-toggle="tooltip" data-bs-html="true" title="Address: {{ $order->address }}&nbsp;&nbsp;Note: {{ $order->note }}&nbsp;&nbsp;Order Items:
                                                    @foreach ($order->Order_Item as $item)
                                                       {{ $item?->product?->name }} x {{ $item?->quantity }}
                                                    @endforeach

                                                ">
                                                <i class="icon-info cursor-pointer"></i>
                                            </span>

                                        </td>
                                        <td class="text-center {{ $order?->customer?->isBlocked ? 'text-danger' : '' }}">{{ $order->phone }}</td>
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
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
    <!-- content area end -->
@endsection
@push('scripts')
    <script>
        $('.delete').click(function(e) {
            e.preventDefault();
            var form = $(this).closest('form');
            var name = $(this).closest('tr').find('.pname').text();
            if (confirm("Are you sure? You want to delete " + name)) {
                form.submit();
            }
        });
        $('.send_courier').click(function(e) {
            e.preventDefault();
            var id = $(this).attr('data-id');
            var href = $(this).attr('href');
            swal.fire({
                title: 'Are you sure?',
                text: "You want to add parcel to this order! Order ID: " + id,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Add Parcel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: "GET",
                        url: href,
                        dataType: "json",
                        success: function(response) {
                            if (response.status === 'success') {
                                swal.fire(
                                    'Success',
                                    response.message,
                                    'success'
                                )
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);

                            } else {
                                swal.fire(
                                    'Error',
                                    response.message,
                                    'error'
                                )
                            }
                            console.log(response);
                        }
                    });
                }
            });
        });
    </script>
       <script>
        $("#bulk-select-button").click(function() {
            $(".select-item").toggle();

            $(".select-item").prop('checked', false);

        })

        document.getElementById('bulk-action-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const status = document.getElementById('bulk-action-status').value;
            console.log(status);
            if (status == '' || status == 'Select Status' || status == null) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No action status selected',
                    text: 'Please select a valid action status to perform.'
                });
                return;
            }
            var selected = document.querySelectorAll('input.select-item:checked');
            const ids = selected ? [...selected].map(el => el.value) : [];


            if (ids.length > 0) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, Update'
                }).then((result) => {
                    if (result.isConfirmed) {

                        fetch("{{ route('admin.orders.status.update.bulk') }}", {
                                method: 'put',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                        .content,
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    ids: ids,
                                    status: status,
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Order Status Updated Successfully',
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                    setTimeout(() => location.reload(), 1500);
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: data.message || 'Something went wrong'
                                    });
                                }
                            })
                            .catch(error => {
                                console.error("Error:", error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'An error occurred while updating order statuses'
                                });
                            });
                    }
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'No selection',
                    text: 'Please select at least one enquiry to perform this action.'
                });
            }
        });

        document.getElementById('bulk-sticker-print').addEventListener('click', () => {
            console.log('clicked');
            const form = document.getElementById('sticker-print-form');
            const input = form.querySelector('input[name="ids"]');

            var selected = document.querySelectorAll('input.select-item:checked');
            const ids = selected ? [...selected].map(el => el.value) : [];

            if (ids.length > 0) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, generate sticker!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        input.value = ids;
                        form.submit();
                    }


                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'No selection',
                    text: 'Please select at least one enquiry to generate sticker.'
                });
            }
        });
        document.getElementById('bulk-action-button').addEventListener('click', () => {
            console.log('clicked');

            var selected = document.querySelectorAll('input.select-item:checked');
            const ids = selected ? [...selected].map(el => el.value) : [];

            if (ids.length > 0) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete them!'
                }).then((result) => {
                    if (result.isConfirmed) {

                    }
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'No selection',
                    text: 'Please select at least one enquiry to delete.'
                });
            }
        });
    </script>
@endpush
