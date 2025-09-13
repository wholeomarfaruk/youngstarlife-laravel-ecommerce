<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seldom Fashion</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('frontend/img/seldom-rounded.png') }}">
    <!-- font awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-DxV+EoADOkOygM4IR9yXP8Sb2qwgidEmeqAEmDKIOfPRQZOWbXCzLC6vjbZyy0vPisbH2SyW27+ddLVCN+OMzQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Bootstrap CSS  -->
    <link href="{{ asset('frontend/library/bootstrap/bootstrap.min.css') }}" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <!-- Swiperjs css -->
    <link rel="stylesheet" href="{{ asset('frontend/library/swiper/swiper-bundle.min.css') }}">
    <!-- Fancy Box css -->
    <link rel="stylesheet" href="{{ asset('frontend/library/fancybox/fancybox.css') }}">
    <!-- Custom Css  -->
    <style>
        @font-face {
            font-family: 'SolaimanLipi';
            src: url("{{ asset('fonts/SolaimanLipi.ttf') }}") format('truetype');
            font-weight: normal;
            font-style: normal;
        }
    </style>
<link rel="stylesheet" href="{{asset('css/style.css')}}">
    <link rel="stylesheet" href="{{ asset('frontend/css/style.css') }}">


    <!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-WM7VMCCF');</script>
<!-- End Google Tag Manager -->


 @stack('styles')
</head>

<body class="bg-white bg-opacity-50">
    <!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WM7VMCCF"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
    <header id="header-area" class="shadow bg-white">
        <div class="container">
            <div class="topbar d-flex justify-content-center">
                <ul class="quick-contact list-inline d-flex justify-content-end gap-3 py-2 mb-0 align-items-center ">
                    <li class="list-inline fw-bold fs-6 "><a href="https://wa.me/8801622351266" target="_blank"
                            class="text-decoration-none text-primary-color text-primary-hover"><i
                                class="fa-brands fa-whatsapp"></i> WhatsApp </a></li>
                    <li class="list-inline fw-bold fs-6"><a href="https://m.me/seldombd" target="_blank"
                            class="text-decoration-none text-primary-color text-primary-hover"> <i
                                class="fa-brands fa-facebook-messenger"></i></i> Messagenger </a></li>
                    <li class="list-inline fw-bold fs-6"><a href="tel:+8801622-351266"
                            class="text-decoration-none text-primary-color text-primary-hover"> <i
                                class="fa-solid fa-phone"></i>Call Us +88 01622-351266</a></li>
                </ul>
            </div>
        </div>

        <nav class="navbar navbar-expand-lg bg-primary-color  border-top">

            <div class="container">
                <a class="navbar-brand" href="https://seldomfashion.com">
                    <img src="{{ asset('frontend/img/logo-transparent.png') }}" alt="" style="width:50px; ">
                    Seldom Fashion</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false"
                    aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNavDropdown">
                    <ul class="navbar-nav ">
                        <li class="nav-item fs-5">
                            <a class="nav-link active" aria-current="page" href="https://seldomfashion.com">Home</a>
                        </li>


                        {{-- <li class="nav-item dropdown fs-5">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                Collections
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item fs-5" href="#">Summer Collection</a></li>
                                <li><a class="dropdown-item fs-5" href="#">Premium Collection</a></li>
                                <li><a class="dropdown-item fs-5" href="#">Party Waear</a></li>
                            </ul>
                        </li> --}}
                        <li class="nav-item fs-5">
                            <a class="nav-link" href="https://seldomfashion.com">All Products</a>
                        </li>
                        {{-- <li class="nav-item fs-5">
                            <a class="nav-link" href="#">Contact</a>
                        </li> --}}
                    </ul>
                </div>
            </div>


        </nav>
    </header>
    <aside id="sidebar"></aside>
    <main id="Content-body">


    <div class="container">

        <div class="row justify-content-center order-main-box mt-2">
            @if (isset($order) && isset($orderItems) && $orderItems->count() > 0)


            <div class="col-md-8 order-info-box">
                {{-- <div class="order-logo mt-4">
                    <a class="footer-brand text-decoration-none text-success fw-bolder fs-4" href="#">
                        SELDOM FASHION</a>
                </div> --}}
                <div class="order-text-site mt-5">
                    <h6 class="thanks">অসংখ্য ধন্যবাদ!</h6>
                    <h2 class="titel text-start">অর্ডার সাকসেসফুল</h2>
                    <p class="order-some-text">
                        আপনার অর্ডারের জন্য কৃতজ্ঞতা জানাচ্ছি, কিছুক্ষনের মধ্যে অর্ডারটি
                        প্রসেস করা হবে। শীঘ্রই আমরা আপনার সাথে যোগাযোগ করব, সাথেই থাকুন।
                    </p>
                </div>
                <div class="row date-id mt-4 mb-3">
                    <div class="col">
                        <span class="order-id">Invoice ID:</span>
                        <span class="order-number">{{$order->id}}</span>
                    </div>
                    <div class="col text-end">
                        <span class="date">Date: </span>
                        <span class="date-time">{{$order->created_at}}</span>
                    </div>
                </div>
                <hr class="m-0">
                @foreach ($orderItems as $item)
                    <div class="d-flex order-card p-2">
                        <img src="{{asset('storage/images/products/thumbnails/'.$item->product->image)}}" alt="" class="me-2" />
                        <div class="">
                            <h5 class="order-product-name">{{$item->product->name}}</h5>
                            {{-- <p class="order-product-weight">{{$item->product->weight}}</p> --}}
                        </div>
                        <p class="order-product-price ms-auto">{{$item->product->price}} x {{$item->quantity}} = {{$item->subtotal}}  টাকা</p>
                    </div>
                    <hr class="m-0">
                @endforeach


                <div class="order-price-unit mt-4">
                    {{-- <div class="d-flex justify-content-between">
                        <p class="delivery-info">Subtotal</p>
                        <p class="delivery-price">{{$order->subtotal}} Tk</p>
                    </div> --}}
                    <div class="d-flex justify-content-between">
                        <p class="delivery-info">Delivery</p>
                        <p class="order-price-unit">{{$order->fee}} Tk</p>
                    </div>
                    {{-- <div class="d-flex justify-content-between">
                        <p class=" delivery-info">COD Charge {{$order->cod_percentage}}%</p>
                        <p class="order-price-unit">{{$order->cod_charge}} Tk</p>
                    </div> --}}
                    <hr>
                    <div class="d-flex justify-content-between">
                        <p class=" delivery-info-total">Total</p>
                        <p class="order-price-unit-total">{{$order->total}} Tk</p>
                    </div>
                </div>
                {{-- <p class="i-text" style="font-size: 12px;">
                    <i>বিশেষ দ্রষ্টব্যঃ কাচা পণ্য হওয়ায় ওজনের তারতম্যের কারণে
                        মূল্যমান কিছুটা কম অথবা বেশি হতে পারে।</i>
                </p> --}}
                <hr style="margin: 30px 0px;">
                <div class="row person-information">
                    <div class="col-md-6">
                        <h5 class="some-titel">Address</h5>
                        <h6 class="person-name">{{$order->name}}</h6>
                        <p class="order-address">{{$order->address}} <br>
                            {{$order->phone}}</p>
                    </div>
                    <div class="col-md-6">
                        <h5 class="some-titel">Delivery</h5>
                        <h6 class="delivery-type">ক্যাশ অন ডেলিভারী</h6>
                        <p class="delivery-notice">আপনার অর্ডারের জন্য আন্তরিক কৃতজ্ঞতা। ২৪ ঘণ্টার মধ্যে আমাদের টিম আপনার সাথে যোগাযোগ করবে এবং খুব শীঘ্রই আপনার অর্ডারটি প্রসেস করা হবে । Seldom এর সাথেই থাকুন।</p>
                    </div>
                </div>
                {{-- <div class="buttom-notice mt-5">
                    <p>সম্মানিত গ্রাহকদের রিভিউ এবং আমাদের এক্সক্লুসিভ ডিসকাউন্ট অফারগুলো পেতে এখনই জয়েন করুন:</p>
                    <button class="btn btn-order justify-content-center"><i
                            class="fa-solid fa-user-group me-2"></i>ইকোইটস ফেসবুক গ্রুপ</button>
                </div> --}}
            </div>
            @else
            <div class="d-flex justify-content-center">
                <h1 class="text-danger">আপনার অর্ডার টি নেই</h1>
            </div>
            @endif

        </div>
    </div>

    </main>
    <footer id="footer-area" class="border-top">
        <div class="container py-3">
            <h5 class="text-center fw-semibold text-primary-color"> যেকোনো তথ্যের জন্য আমাদের মেসেজ করুন অথবা কল করুন।</h5>
            <div class="topbar d-flex justify-content-center">
                <ul class="quick-contact list-inline d-flex justify-content-end gap-3 py-2 mb-0 align-items-center ">
                    <li class="list-inline fw-bold fs-6 "><a href="https://wa.me/8801622351266" target="_blank"
                            class="text-decoration-none text-primary-color text-primary-hover"><i
                                class="fa-brands fa-whatsapp"></i> WhatsApp </a></li>
                    <li class="list-inline fw-bold fs-6"><a href="https://m.me/seldombd" target="_blank"
                            class="text-decoration-none text-primary-color text-primary-hover"> <i
                                class="fa-brands fa-facebook-messenger"></i></i> Messagenger </a></li>
                    <li class="list-inline fw-bold fs-6"><a href="tel:+8801622-351266"
                            class="text-decoration-none text-primary-color text-primary-hover"> <i
                                class="fa-solid fa-phone"></i> Call Us +88 01622-351266</a></li>
                </ul>
            </div>
        </div>
    </footer>

    <!-- Jquery -->
    <script src="{{ asset('frontend/library/jquery/jquery-3.7.1.min.js') }}"></script>
    <!-- Bootstrap Js  -->
    <script src="{{ asset('frontend/library/bootstrap/bootstrap.bundle.min.js') }}"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous">
    </script>
    <!-- Swiperjs Js  -->
    <script src="{{ asset('frontend/library/swiper/swiper-bundle.min.js') }}"></script>
    <!-- Fancybox js -->
    <script src="{{ asset('frontend/library/fancybox/fancybox.umd.js') }}"></script>
    <!-- Custom Js  -->
    <script src="{{ asset('frontend/js/script.js') }}"></script>
    <!-- Initialize Swiper -->
    <!-- Initialize Swiper -->
    <script>
        var swiper = new Swiper(".mySwiper", {
            spaceBetween: 10,
            slidesPerView: 4,
            freeMode: true,
            watchSlidesProgress: true,
        });
        var swiper2 = new Swiper(".mySwiper2", {
            spaceBetween: 10,
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
            thumbs: {
                swiper: swiper,
            },
        });
    </script>

    <script>
        Fancybox.bind("[data-fancybox]", {
            // Optional settings
            Thumbs: {
                autoStart: true,
            },
        });
    </script>


    @stack('scripts')
<script>
    window.dataLayer = window.dataLayer || [];
    dataLayer.push(@json($dataLayer));
</script>
</body>

</html>
