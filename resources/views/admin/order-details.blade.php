@extends('layouts.admin')

@section('content')
    <!-- content area start -->
    <style>
        .table-transaction>tbody>tr:nth-of-type(odd) {
            --bs-table-accent-bg: #fff !important;
        }
    </style>
    <div class="main-content-inner">
        <div class="main-content-wrap">
            <div class="flex items-center flex-wrap justify-between gap20 mb-27">
                <h3>Order Details</h3>
                <ul class="breadcrumbs flex items-center flex-wrap justify-start gap10">
                    <li>
                        <a href="{{ route('admin.index') }}">
                            <div class="text-tiny">Dashboard</div>
                        </a>
                    </li>
                    <li>
                        <i class="icon-chevron-right"></i>
                    </li>
                    <li>
                        <div class="text-tiny">Order Items</div>
                    </li>
                </ul>
            </div>
            <div class="wg-box mt-5 mb-27">
                <div class="flex items-center justify-between gap10 flex-wrap">
                    <div class="wg-filter flex-grow">

                    </div>
                    <a class="tf-button style-1 w208" href="{{ route('admin.orders') }}">Back</a>
                </div>
                @if (session('status'))
                <div class="alert alert-success" role="alert">
                    {{ session('status') }}
                </div>
                @endif
                <h5>Order Details</h5>
                <div class="table-responsive">
                <table class="table table-striped table-bordered table-transaction">
                    <tbody>

                        <tr>
                            <th>Order No</th>
                            <td>{{$order->id}}</td>
                            <th>Customer Name</th>
                            <td>{{$order->name}}</td>
                            <th>Mobile No</th>
                            <td>{{$order->phone}}</td>

                        </tr>
                        <tr>
                            <th>total Quantity</th>
                            <td>{{$order->Order_Item->count()}}</td>
                            <th>Payment Mode</th>
                            <td>Cash On Delivery</td>
                            <th>Status</th>
                            <td>{{$order->status}}</td>
                        </tr>
                        <tr>
                            <th>Order Date</th>
                            <td>{{$order->created_at}}</td>
                            <th>Delivered Date</th>
                            <td>{{$order->delivery_date}}</td>
                            <th>Canceled Date</th>
                            <td>{{$order->cancelled_date}}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="wg-box">
                <h5>Ordered Items</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>

                            <tr>
                                <th>Name</th>
                                <th class="text-center">Price</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-center">SKU</th>
                                <th class="text-center">Options</th>
                                <th class="text-center">Return Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orderItems as $item)


                            <tr>
                                <td class="pname">
                                    <div class="image">
                                        <img src="{{ asset('storage/images/products/thumbnails/'.$item->product->image)}}" alt="{{ $item->product->name}}" class="image">
                                    </div>
                                    <div class="name">
                                        <a href="javascript:void(0)" target="_blank"
                                            class="body-title-2">{{$item->product->name}}</a>
                                    </div>
                                </td>
                                <td class="text-center">৳{{$item->product->price}}</td>
                                <td class="text-center">{{$item->quantity}}</td>
                                <td class="text-center">{{$item->product->sku}}</td>

                                <td class="text-center">{{$item->options}}</td>
                                <td class="text-center">{{$item->return_status ? 'Yes' : 'No'}}</td>
                                <td class="text-center">
                                    <div class="list-icon-function view-icon">
                                        <div class="item eye">
                                            <i class="icon-eye"></i>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach

                        </tbody>
                    </table>
                </div>

                <div class="divider"></div>
                <div class="flex items-center justify-between flex-wrap gap10 wgp-pagination">
                    {{ $orderItems->links('pagination::bootstrap-5') }}
                </div>
            </div>

            <div class="wg-box mt-5">
                <h5>Shipping Address</h5>
                <div class="my-account__address-item col-md-6">
                    <div class="my-account__address-item__detail">
                        <p>{{$order->name}}</p>
                        <p>Mobile : {{$order->phone}}</p>
                        <p>Delivery Area : {{$order->delivery_area}}</p>
                        <p>Full Address : {{$order->address}}</p>
                        <br>
                    </div>
                </div>
            </div>


            <div class="wg-box mt-5">
                <h5>Update Order Status</h5>
                <form action="{{route('admin.orders.update', $order->id)}}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="order_id" value="{{$order->id}}">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">

                                <select name="status" id="status" class="form-control">
                                    <option value="pending" @if ($order->status == 'pending') selected @endif>Pending</option>
                                    <option value="Delivered" @if ($order->status == 'Delivered') selected @endif>Delivered</option>
                                    <option value="processing" @if ($order->status == 'processing') selected @endif>Processing</option>
                                    <option value="canceled" @if ($order->status == 'canceled') selected @endif>Canceled</option>
                                </select>

                            </div>

                        </div>
                        <div class="col-md-9">
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </div>

                </form>

            </div>
        </div>
    </div>

    <!-- content area end -->
@endsection
