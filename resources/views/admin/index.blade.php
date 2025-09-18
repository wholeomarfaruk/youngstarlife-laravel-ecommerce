@extends('layouts.admin')

@section('content')
    <!-- content area start -->


        <div class="main-content-inner">

            <div class="main-content-wrap">
                <div class="tf-section-1 mb-30">
                    <div class="flex gap20 flex-wrap-mobile">
                        <div class="w-half">

                            <div class="wg-chart-default mb-20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap14">
                                        <div class="image ic-bg">
                                            <i class="icon-shopping-bag"></i>
                                        </div>
                                        <div>
                                            <div class="body-text mb-2">Total Orders</div>
                                            <h4>{{$total_orders}}</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="wg-chart-default mb-20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap14">
                                        <div class="image ic-bg">
                                            <i class="icon-dollar-sign"></i>
                                        </div>
                                        <div>
                                            <div class="body-text mb-2">Total Amount</div>
                                            <h4>{{$total_orders_sum}}</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="wg-chart-default mb-20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap14">
                                        <div class="image ic-bg">
                                            <i class="icon-shopping-bag"></i>
                                        </div>
                                        <div>
                                            <div class="body-text mb-2">Pending Orders</div>
                                            <h4>{{$pending_orders}}</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="wg-chart-default">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap14">
                                        <div class="image ic-bg">
                                            <i class="icon-dollar-sign"></i>
                                        </div>
                                        <div>
                                            <div class="body-text mb-2">Pending Orders Amount</div>
                                            <h4>{{$pending_orders_sum}}</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="w-half">

                            <div class="wg-chart-default mb-20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap14">
                                        <div class="image ic-bg">
                                            <i class="icon-shopping-bag"></i>
                                        </div>
                                        <div>
                                            <div class="body-text mb-2">Delivered Orders</div>
                                            <h4>{{$delivered_orders}}</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="wg-chart-default mb-20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap14">
                                        <div class="image ic-bg">
                                            <i class="icon-dollar-sign"></i>
                                        </div>
                                        <div>
                                            <div class="body-text mb-2">Delivered Orders Amount</div>
                                            <h4>{{$delivered_orders_sum}}</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="wg-chart-default mb-20">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap14">
                                        <div class="image ic-bg">
                                            <i class="icon-shopping-bag"></i>
                                        </div>
                                        <div>
                                            <div class="body-text mb-2">Canceled Orders</div>
                                            <h4>{{$cancelled_orders}}</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="wg-chart-default">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap14">
                                        <div class="image ic-bg">
                                            <i class="icon-dollar-sign"></i>
                                        </div>
                                        <div>
                                            <div class="body-text mb-2">Canceled Orders Amount</div>
                                            <h4>{{$cancelled_orders_sum}}</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>

                    {{-- <div class="wg-box">
                        <div class="flex items-center justify-between">
                            <h5>Earnings revenue</h5>
                            <div class="dropdown default">
                                <button class="btn btn-secondary dropdown-toggle" type="button"
                                    data-bs-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                    <span class="icon-more"><i class="icon-more-horizontal"></i></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a href="javascript:void(0);">This Week</a>
                                    </li>
                                    <li>
                                        <a href="javascript:void(0);">Last Week</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap40">
                            <div>
                                <div class="mb-2">
                                    <div class="block-legend">
                                        <div class="dot t1"></div>
                                        <div class="text-tiny">Revenue</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap10">
                                    <h4>$37,802</h4>
                                    <div class="box-icon-trending up">
                                        <i class="icon-trending-up"></i>
                                        <div class="body-title number">0.56%</div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="mb-2">
                                    <div class="block-legend">
                                        <div class="dot t2"></div>
                                        <div class="text-tiny">Order</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap10">
                                    <h4>$28,305</h4>
                                    <div class="box-icon-trending up">
                                        <i class="icon-trending-up"></i>
                                        <div class="body-title number">0.56%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="line-chart-8"></div>
                    </div> --}}

                </div>
                <div class="tf-section mb-30">

                    <div class="wg-box">
                        <div class="flex items-center justify-between">
                            <h5>Recent orders</h5>
                            <div class="dropdown default">
                                <a class="btn btn-secondary dropdown-toggle" href="{{route('admin.orders')}}">
                                    <span class="view-all">View all</span>
                                </a>
                            </div>
                        </div>
                        <div class="wg-table table-all-user">
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
                                            <a href="{{ route('admin.orders.details', $order->id) }}">
                                                <div class="list-icon-function view-icon">
                                                    <div class="item eye">
                                                        <i class="icon-eye"></i>
                                                    </div>
                                                </div>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach

                            </tbody>
                        </table>
                    </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>



    <!-- content area end -->
@endsection
