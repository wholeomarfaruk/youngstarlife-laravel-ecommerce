@extends('layouts.admin')

@section('content')
    <!-- content area start -->
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
                <div class="row " >
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
                                        <option value="{{ $sg->status }}">{{ $sg->status }} ({{ $sg->count }})</option>
                                    @endforeach

                                </select>
                            </fieldset>
                            <div class="button-submit">
                                <button class="" type="submit"><i class="icon-search"></i> Export</button>
                            </div>

                        </form>
                        <a class="tf-button style-1 w208" href="{{route('admin.products.add')}}"><i
                            class="icon-plus"></i>Add new</a>
                    </div>
                </div>
              
                <div class="wg-table table-all-user">
                    <div class="divider"></div>
                    <div class="flex items-center justify-between flex-wrap gap10 wgp-pagination">
                        {{ $orders->links('pagination::bootstrap-5') }}
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th style="width:70px">OrderNo</th>
                                    <th class="text-center">Name</th>
                                    <th class="text-center">Phone</th>
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
                                        <td class="text-center">{{ $order->id }}</td>
                                        <td class="text-center">{{ $order->name }}</td>
                                        <td class="text-center">{{ $order->phone }}</td>
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
                                                <form action="{{ route('admin.orders.delete', $order->id) }}"
                                                    method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <div class="list-icon-function delete">
                                                        <button type="submit" class="item trash">
                                                            <i class="icon-trash"></i>
                                                        </button>
                                                    </div>
                                                </form>
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
        })
    </script>
@endpush
