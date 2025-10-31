<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-resource/css/animate.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-resource/css/animation.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-resource/css/bootstrap.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-resource/css/bootstrap-select.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-resource/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-resource/font/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-resource/icon/style.css') }}">
    <link rel="shortcut icon" href="{{ asset('admin-resource/images/favicon.ico') }}">
    <link rel="apple-touch-icon-precomposed" href="{{ asset('admin-resource/images/favicon.ico') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-resource/css/sweetalert.min.css') }}">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-treeview/1.2.0/bootstrap-treeview.min.css"
        integrity="sha512-A81ejcgve91dAWmCGseS60zjrAdohm7PTcAjjiDWtw3Tcj91PNMa1gJ/ImrhG+DbT5V+JQ5r26KT5+kgdVTb5w=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-resource/css/custom.css') }}">
    @stack('styles')
</head>

<body class="body">

    <div id="wrapper">
        <div id="page" class="">
            <div class="layout-wrap">

                <!-- <div id="preload" class="preload-container">
                <div class="preloading">
                    <span></span>
                </div>
                </div> -->

                <div class="section-menu-left">
                    <div class="box-logo">
                        <a href="{{ route('admin.index') }}" id="site-logo-inner">
                            <h4>YoungStar Life</h4>
                            {{-- <img class="" id="logo_header" alt="" src="{{asset('admin-resource/images/logo/logo.png')}}" > --}}
                        </a>
                        <div class="button-show-hide">
                            <i class="icon-menu-left"></i>
                        </div>
                    </div>
                    <div class="center">
                        <div class="center-item">
                            <div class="center-heading">Main Home</div>
                            <ul class="menu-list">
                                <li class="menu-item">
                                    <a href="{{ route('admin.index') }}" class="">
                                        <div class="icon"><i class="icon-grid"></i></div>
                                        <div class="text">Dashboard</div>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="center-item">
                            <ul class="menu-list">
                                <li class="menu-item has-children">
                                    <a href="javascript:void(0);" class="menu-item-button">
                                        <div class="icon"><i class="icon-shopping-cart"></i></div>
                                        <div class="text">Products</div>
                                    </a>
                                    <ul class="sub-menu">
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.products.add') }}" class="">
                                                <div class="text">Add Product</div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.products') }}" class="">
                                                <div class="text">Products</div>
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                                {{--
                                <li class="menu-item has-children">
                                    <a href="javascript:void(0);" class="menu-item-button">
                                        <div class="icon"><i class="icon-layers"></i></div>
                                        <div class="text">Brand</div>
                                    </a>
                                    <ul class="sub-menu">
                                        <li class="sub-menu-item">
                                            <a href="{{route('admin.brands.add')}}" class="">
                                                <div class="text">New Brand</div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{route('admin.brands')}}" class="">
                                                <div class="text">Brands</div>
                                            </a>
                                        </li>
                                    </ul>
                                </li> --}}
                                <li class="menu-item has-children">
                                    <a href="javascript:void(0);" class="menu-item-button">
                                        <div class="icon"><i class="icon-layers"></i></div>
                                        <div class="text">Category</div>
                                    </a>
                                    <ul class="sub-menu">
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.categories.add') }}" class="">
                                                <div class="text">New Category</div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.categories') }}" class="">
                                                <div class="text">Categories</div>
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                                @php
                                    $orderStatus = App\Models\Order::select('status')
                                        ->selectRaw('COUNT(*) as count')
                                        ->groupBy('status')
                                        ->get();
                                    $orderCount = App\Models\Order::count();
                                @endphp
                                <li class="menu-item has-children {{ Request::is('admin/orders*') ? 'active' : '' }}">
                                    <a href="javascript:void(0);" class="menu-item-button">
                                        <div class="icon"><i class="icon-file-plus"></i></div>
                                        <div class="text">Order</div>
                                    </a>
                                    <ul class="sub-menu ">
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.orders') }}"
                                                class=" {{ Request::is('admin/orders') ? 'active' : '' }}">
                                                <div class="text">Orders ({{ $orderCount ?? 0 }})</div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.orders.pending') }}"
                                                class=" {{ Request::is('admin/orders/pending') ? 'active' : '' }}">
                                                <div class="text">Pending Orders
                                                    ({{ $orderStatus->where('status', 'pending')->first()->count ?? 0 }})
                                                </div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.orders.on_hold') }}"
                                                class=" {{ Request::is('admin/orders/on-hold') ? 'active' : '' }}">
                                                <div class="text">On Hold Orders
                                                    ({{ $orderStatus->where('status', 'on_hold')->first()->count ?? 0 }})
                                                </div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.orders.confirmed') }}"
                                                class=" {{ Request::is('admin/orders/confirmed') ? 'active' : '' }}">
                                                <div class="text">Confirmed Orders
                                                    ({{ $orderStatus->where('status', 'confirmed')->first()->count ?? 0 }})
                                                </div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.orders.processing') }}"
                                                class=" {{ Request::is('admin/orders/processing') ? 'active' : '' }}">
                                                <div class="text">Processing Orders
                                                    ({{ $orderStatus->where('status', 'processing')->first()->count ?? 0 }})
                                                </div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.orders.ready') }}"
                                                class=" {{ Request::is('admin/orders/ready') ? 'active' : '' }}">
                                                <div class="text">Ready Orders
                                                    ({{ $orderStatus->where('status', 'ready')->first()->count ?? 0 }})
                                                </div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.orders.in_review') }}"
                                                class=" {{ Request::is('admin/orders/in-review') ? 'active' : '' }}">
                                                <div class="text">In Review Orders
                                                    ({{ $orderStatus->where('status', 'in_review')->first()->count ?? 0 }})
                                                </div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.orders.in_transit') }}"
                                                class=" {{ Request::is('admin/orders/in-transit') ? 'active' : '' }}">
                                                <div class="text">In Transit Orders
                                                    ({{ $orderStatus->where('status', 'in_transit')->first()->count ?? 0 }})
                                                </div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.orders.delivered') }}"
                                                class=" {{ Request::is('admin/orders/delivered') ? 'active' : '' }}">
                                                <div class="text">Delivered Orders
                                                    ({{ $orderStatus->where('status', 'delivered')->first()->count ?? 0 }})
                                                </div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.orders.delivery_in_review') }}"
                                                class=" {{ Request::is('admin/orders/delivery-in-review') ? 'active' : '' }}">
                                                <div class="text">Delivery In Review Orders
                                                    ({{ $orderStatus->where('status', 'delivery_in_review')->first()->count ?? 0 }})
                                                </div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.orders.returned') }}"
                                                class=" {{ Request::is('admin/orders/returned') ? 'active' : '' }}">
                                                <div class="text">Returned Orders
                                                    ({{ $orderStatus->where('status', 'returned')->first()->count ?? 0 }})
                                                </div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.orders.cancelled') }}"
                                                class=" {{ Request::is('admin/orders/cancelled') ? 'active' : '' }}">
                                                <div class="text">Cancelled Orders
                                                    ({{ $orderStatus->where('status', 'cancelled')->first()->count ?? 0 }})
                                                </div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.orders.deleted') }}"
                                                class=" {{ Request::is('admin/orders/deleted') ? 'active' : '' }}">
                                                <div class="text">Deleted Orders
                                                    ({{ $orderStatus->where('status', 'deleted')->first()->count ?? 0 }})
                                                </div>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                <li class="menu-item">
                                    <a href="{{ route('admin.coupons') }}" class="">
                                        <div class="icon"><i class="icon-grid"></i></div>
                                        <div class="text">Coupns</div>
                                    </a>
                                </li>
                                <li class="menu-item">
                                    <a href="{{ route('admin.deliveryareas') }}" class="">
                                        <div class="icon"><i class="icon-grid"></i></div>
                                        <div class="text">Delivery Areas</div>
                                    </a>
                                </li>
                                {{-- <li class="menu-item">
                                    <a href="{{ route('admin.slides') }}" class="">
                                        <div class="icon"><i class="icon-image"></i></div>
                                        <div class="text">Slider</div>
                                    </a>
                                </li>
                                <li class="menu-item">
                                    <a href="users.html" class="">
                                        <div class="icon"><i class="icon-user"></i></div>
                                        <div class="text">User</div>
                                    </a>
                                </li> --}}
                                {{-- <li class="menu-item has-children">
                                    <a href="javascript:void(0);" class="menu-item-button">
                                        <div class="icon"><i class="icon-bar-chart"></i></div>
                                        <div class="text">Analytics</div>
                                    </a>
                                    <ul class="sub-menu">
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.analytics.report') }}" class="">
                                                <div class="text">Reports</div>
                                            </a>
                                        </li>

                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.google.analytics') }}" class="">
                                                <div class="text">Google Analytics</div>
                                            </a>
                                        </li>
                                        <li class="sub-menu-item">
                                            <a href="{{ route('admin.facebook.pixels') }}" class="">
                                                <div class="text">Facebook Pixels</div>
                                            </a>
                                        </li>
                                    </ul>
                                </li> --}}
                                {{-- <li class="menu-item">
                                    <a href="#" class="">
                                        <div class="icon"><i class="icon-user"></i></div>
                                        <div class="text">Account Settings</div>
                                    </a>
                                </li> --}}
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="section-content-right">

                    <div class="header-dashboard">
                        <div class="wrap">
                            <div class="header-left">
                                <a href="index-2.html">
                                    <img class="" id="logo_header_mobile" alt=""
                                        src="{{ asset('admin-resource/images/logo/logo.png') }}"
                                        data-light="admin/images/logo/logo.png" data-dark="admin/images/logo/logo.png"
                                        data-width="154px" data-height="52px" data-retina="images/logo/logo.png">
                                </a>
                                <div class="button-show-hide">
                                    <i class="icon-menu-left"></i>
                                </div>


                                <form class="form-search flex-grow">
                                    <fieldset class="name">
                                        <input type="text" placeholder="Search here..." class="show-search"
                                            name="name" tabindex="2" value="" aria-required="true"
                                            required="">
                                    </fieldset>
                                    <div class="button-submit">
                                        <button class="" type="submit"><i class="icon-search"></i></button>
                                    </div>
                                    <div class="box-content-search" id="box-content-search">
                                        <ul class="mb-24">
                                            <li class="mb-14">
                                                <div class="body-title">Top selling product</div>
                                            </li>
                                            <li class="mb-14">
                                                <div class="divider"></div>
                                            </li>
                                            <li>
                                                <ul>
                                                    <li class="product-item gap14 mb-10">
                                                        <div class="image no-bg">
                                                            <img src="{{ asset('admin-resource/images/products/17.png') }}"
                                                                alt="">
                                                        </div>
                                                        <div class="flex items-center justify-between gap20 flex-grow">
                                                            <div class="name">
                                                                <a href="product-list.html" class="body-text">Dog Food
                                                                    Rachael Ray Nutrish®</a>
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <li class="mb-10">
                                                        <div class="divider"></div>
                                                    </li>
                                                    <li class="product-item gap14 mb-10">
                                                        <div class="image no-bg">
                                                            <img src="{{ asset('admin-resource/images/products/18.png') }}"
                                                                alt="">
                                                        </div>
                                                        <div class="flex items-center justify-between gap20 flex-grow">
                                                            <div class="name">
                                                                <a href="product-list.html" class="body-text">Natural
                                                                    Dog Food Healthy Dog Food</a>
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <li class="mb-10">
                                                        <div class="divider"></div>
                                                    </li>
                                                    <li class="product-item gap14">
                                                        <div class="image no-bg">
                                                            <img src="{{ asset('admin-resource/images/products/19.png') }}"
                                                                alt="">
                                                        </div>
                                                        <div class="flex items-center justify-between gap20 flex-grow">
                                                            <div class="name">
                                                                <a href="product-list.html" class="body-text">Freshpet
                                                                    Healthy Dog Food and Cat</a>
                                                            </div>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </li>
                                        </ul>
                                        <ul class="">
                                            <li class="mb-14">
                                                <div class="body-title">Order product</div>
                                            </li>
                                            <li class="mb-14">
                                                <div class="divider"></div>
                                            </li>
                                            <li>
                                                <ul>
                                                    <li class="product-item gap14 mb-10">
                                                        <div class="image no-bg">
                                                            <img src="{{ asset('admin-resource/images/products/20.png') }}"
                                                                alt="">
                                                        </div>
                                                        <div class="flex items-center justify-between gap20 flex-grow">
                                                            <div class="name">
                                                                <a href="product-list.html" class="body-text">Sojos
                                                                    Crunchy Natural Grain Free...</a>
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <li class="mb-10">
                                                        <div class="divider"></div>
                                                    </li>
                                                    <li class="product-item gap14 mb-10">
                                                        <div class="image no-bg">
                                                            <img src="{{ asset('admin-resource/images/products/21.png') }}"
                                                                alt="">
                                                        </div>
                                                        <div class="flex items-center justify-between gap20 flex-grow">
                                                            <div class="name">
                                                                <a href="product-list.html" class="body-text">Omar
                                                                    Faruk</a>
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <li class="mb-10">
                                                        <div class="divider"></div>
                                                    </li>
                                                    <li class="product-item gap14 mb-10">
                                                        <div class="image no-bg">
                                                            <img src="{{ asset('admin-resource/images/products/22.png') }}"
                                                                alt="">
                                                        </div>
                                                        <div class="flex items-center justify-between gap20 flex-grow">
                                                            <div class="name">
                                                                <a href="product-list.html" class="body-text">Mega
                                                                    Pumpkin Bone</a>
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <li class="mb-10">
                                                        <div class="divider"></div>
                                                    </li>
                                                    <li class="product-item gap14">
                                                        <div class="image no-bg">
                                                            <img src="{{ asset('admin-resource/images/products/23.png') }}"
                                                                alt="">
                                                        </div>
                                                        <div class="flex items-center justify-between gap20 flex-grow">
                                                            <div class="name">
                                                                <a href="product-list.html" class="body-text">Mega
                                                                    Pumpkin Bone</a>
                                                            </div>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </li>
                                        </ul>
                                    </div>
                                </form>

                            </div>
                            <div class="header-grid">

                                <div class="popup-wrap message type-header">
                                    @php
                                        $orderpendings = auth()->user()->unreadNotifications;
                                    @endphp
                                    <div class="dropdown">
                                        <button class="btn btn-secondary dropdown-toggle" type="button"
                                            id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="header-item">
                                                <span class="text-tiny">{{ $orderpendings->count() }}</span>
                                                <i class="icon-bell"></i>
                                            </span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end has-content"
                                            aria-labelledby="dropdownMenuButton2"
                                            style="max-height: 70vh; overflow: scroll;">
                                            <li>
                                                <h6>Notifications ({{ $orderpendings->count() }}) - <a
                                                        href="{{ route('admin.notifications.clear.all') }}"
                                                        class="notify-clear-all fs-6 text-danger"><i
                                                            class="icon-trash-2"></i> Clear all</a></h6>
                                            </li>

                                            @foreach ($orderpendings as $notify)
                                                <li class="{{ $notify->read_at ? '' : 'unread' }}">
                                                    <div class="message-item item-4">
                                                        <div class="image">
                                                            <i class="icon-noti-4"></i>
                                                        </div>
                                                        <div>
                                                            <div class="body-title-2">{{ $notify->data['title'] }}
                                                            </div>
                                                            <div class="text-tiny">{!! html_entity_decode($notify->data['message'], ENT_QUOTES, 'UTF-8') !!}</< /div>
                                                            </div>
                                                        </div>

                                                    </div>
                                                    <div
                                                        class="footer w-100 d-flex justify-content-end align-items-center gap-3">
                                                        <div class="date">
                                                            <div class="text-tiny">
                                                                {{ $notify->created_at->diffForHumans() }}</div>
                                                        </div>
                                                        <div class="action">
                                                            <a href="{{ route('admin.notifications.read', $notify->id) }}"
                                                                class="btn btn-sm btn-secondary">Mark as read</a>
                                                        </div>
                                                    </div>
                                                    <hr>
                                                </li>
                                            @endforeach
                                            {{-- - <li>
                                                <div class="message-item item-1">
                                                    <div class="image">
                                                        <i class="icon-noti-1"></i>
                                                    </div>
                                                    <div>
                                                        <div class="body-title-2">Discount available</div>
                                                        <div class="text-tiny">Morbi sapien massa, ultricies at rhoncus
                                                            at, ullamcorper nec diam</div>
                                                    </div>
                                                </div>
                                            </li>
                                           <li>
                                                <div class="message-item item-2">
                                                    <div class="image">
                                                        <i class="icon-noti-2"></i>
                                                    </div>
                                                    <div>
                                                        <div class="body-title-2">Account has been verified</div>
                                                        <div class="text-tiny">Mauris libero ex, iaculis vitae rhoncus
                                                            et</div>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="message-item item-3">
                                                    <div class="image">
                                                        <i class="icon-noti-3"></i>
                                                    </div>
                                                    <div>
                                                        <div class="body-title-2">Order shipped successfully</div>
                                                        <div class="text-tiny">Integer aliquam eros nec sollicitudin
                                                            sollicitudin</div>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="message-item item-4">
                                                    <div class="image">
                                                        <i class="icon-noti-4"></i>
                                                    </div>
                                                    <div>
                                                        <div class="body-title-2">Order pending: <span>ID 305830</span>
                                                        </div>
                                                        <div class="text-tiny">Ultricies at rhoncus at ullamcorper
                                                        </div>
                                                    </div>
                                                </div>
                                            </li> --}}
                                            <li><a href="#" class="tf-button w-full">View all</a></li>
                                        </ul>
                                    </div>
                                </div>




                                <div class="popup-wrap user type-header">
                                    <div class="dropdown">
                                        <button class="btn btn-secondary dropdown-toggle" type="button"
                                            id="dropdownMenuButton3" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="header-user wg-user">
                                                <span class="image">
                                                    <img src="{{ asset('admin-resource/images/avatar/user-1.png') }}"
                                                        alt="">
                                                </span>
                                                <span class="flex flex-column">
                                                    <span class="body-title mb-2">{{ auth()->user()->name }}</span>
                                                    <span class="text-tiny">{{ auth()->user()->role }}</span>
                                                </span>
                                            </span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end has-content"
                                            aria-labelledby="dropdownMenuButton3">
                                            <li>
                                                <a href="{{ route('admin.user.index') }}" class="user-item">
                                                    <div class="icon">
                                                        <i class="icon-user"></i>
                                                    </div>
                                                    <div class="body-title-2">Account</div>
                                                </a>
                                            </li>
                                            {{-- <li>
                                                <a href="#" class="user-item">
                                                    <div class="icon">
                                                        <i class="icon-mail"></i>
                                                    </div>
                                                    <div class="body-title-2">Inbox</div>
                                                    <div class="number">27</div>
                                                </a>
                                            </li>
                                            <li>
                                                <a href="#" class="user-item">
                                                    <div class="icon">
                                                        <i class="icon-file-text"></i>
                                                    </div>
                                                    <div class="body-title-2">Taskboard</div>
                                                </a>
                                            </li>
                                            <li>
                                                <a href="#" class="user-item">
                                                    <div class="icon">
                                                        <i class="icon-headphones"></i>
                                                    </div>
                                                    <div class="body-title-2">Support</div>
                                                </a>
                                            </li> --}}
                                            <li>
                                                <form id="logout-form" action="{{ route('logout') }}"
                                                    method="POST">
                                                    @csrf
                                                    <a href="{{ route('logout') }}" class="user-item"
                                                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                                        <div class="icon">
                                                            <i class="icon-log-out"></i>
                                                        </div>
                                                        <div class="body-title-2">Log out</div>
                                                    </a>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="main-content">
                        @yield('content')
                        <div class="bottom-page">
                            <div class="body-text">Copyright © 2025 YoungStar Life</div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('admin-resource/js/jquery.min.js') }}"></script>
    <script src="{{ asset('admin-resource/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('admin-resource/js/bootstrap-select.min.js') }}"></script>
    <script src="{{ asset('admin-resource/js/sweetalert.min.js') }}"></script>
    <script src="{{ asset('admin-resource/js/apexcharts/apexcharts.js') }}"></script>
    <script src="{{ asset('admin-resource/js/main.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Styles -->
    {{-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<!-- Or for RTL support -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css" /> --}}


    <script>
        (function($) {

            var tfLineChart = (function() {

                var chartBar = function() {

                    var options = {
                        series: [{
                                name: 'Total',
                                data: [0.00, 0.00, 0.00, 0.00, 0.00, 273.22, 208.12, 0.00, 0.00,
                                    0.00, 0.00, 0.00
                                ]
                            }, {
                                name: 'Pending',
                                data: [0.00, 0.00, 0.00, 0.00, 0.00, 273.22, 208.12, 0.00, 0.00,
                                    0.00, 0.00, 0.00
                                ]
                            },
                            {
                                name: 'Delivered',
                                data: [0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00,
                                    0.00, 0.00
                                ]
                            }, {
                                name: 'Canceled',
                                data: [0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00,
                                    0.00, 0.00
                                ]
                            }
                        ],
                        chart: {
                            type: 'bar',
                            height: 325,
                            toolbar: {
                                show: false,
                            },
                        },
                        plotOptions: {
                            bar: {
                                horizontal: false,
                                columnWidth: '10px',
                                endingShape: 'rounded'
                            },
                        },
                        dataLabels: {
                            enabled: false
                        },
                        legend: {
                            show: false,
                        },
                        colors: ['#2377FC', '#FFA500', '#078407', '#FF0000'],
                        stroke: {
                            show: false,
                        },
                        xaxis: {
                            labels: {
                                style: {
                                    colors: '#212529',
                                },
                            },
                            categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep',
                                'Oct', 'Nov', 'Dec'
                            ],
                        },
                        yaxis: {
                            show: false,
                        },
                        fill: {
                            opacity: 1
                        },
                        tooltip: {
                            y: {
                                formatter: function(val) {
                                    return "$ " + val + ""
                                }
                            }
                        }
                    };

                    chart = new ApexCharts(
                        document.querySelector("#line-chart-8"),
                        options
                    );
                    if ($("#line-chart-8").length > 0) {
                        chart.render();
                    }
                };

                /* Function ============ */
                return {
                    init: function() {},

                    load: function() {
                        chartBar();
                    },
                    resize: function() {},
                };
            })();

            jQuery(document).ready(function() {});

            jQuery(window).on("load", function() {
                tfLineChart.load();
            });

            jQuery(window).on("resize", function() {});
        })(jQuery);
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-treeview/1.2.0/bootstrap-treeview.min.js"></script>

    {{-- pending orders notifications in sweetalert2 --}}
    <script>
        function viewNotifications($notiy) {
            $.ajax({
                url: "{{ route('admin.orders.pending.notifications') }}",
                method: 'GET',
                success: function(data) {
                    $notiy.html(data);
                }
            });
        }
        fetch("{{ route('admin.orders.pending.notifications') }}", {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            }).then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer)
                            toast.addEventListener('mouseleave', Swal.resumeTimer)
                        }
                    })

                    Toast.fire({
                        icon: 'warning',
                        title: 'You have ' + data.length + ' pending orders'
                    })
                }
                if (data.length > 0) {
                    // Start with a resolved promise to kick off the chain
                    data.reduce(async (previousPromise, element) => {
                        // Await the previous notification/delay to complete
                        await previousPromise;

                        // Await the delay
                        await new Promise(resolve => setTimeout(resolve, 500));

                        // Return a promise for the current notification/interaction
                        return Swal.fire({
                            title: 'Pending Order',
                            // *** FIX HERE: Changed 'text' to 'html' ***
                            html: `Order ID: <a href='/orders/${element.id}/details' target='_blank'>View Details - ${element.id}</a>`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, We will deliver it',
                        });
                    }, Promise.resolve()); // Initial value: a resolved Promise
                }
            })
    </script>
    <script>
        $('.notify-clear-all').click(function(e) {
            // 1. Prevent the default link behavior immediately
            e.preventDefault();

            // 2. Show the SweetAlert confirmation dialog
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Clear all!'
            }).then((result) => { // 3. Handle the result of the SweetAlert dialog
                // Check if the user clicked the 'Confirm' button
                if (result.isConfirmed) {
                    // Get the URL from the link's 'href' attribute
                    var url = $(this).attr('href');
                    // Redirect to the URL using JavaScript's location.href
                    window.location.href = url;
                }
            })
        });
    </script>
    @stack('scripts')
</body>

</html>
