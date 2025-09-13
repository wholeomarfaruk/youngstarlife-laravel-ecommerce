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

    <link rel="stylesheet" href="{{ asset('frontend/css/style.css') }}">


    <!-- Google Tag Manager -->
    <script>
        (function(w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js'
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src =
                'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-WM7VMCCF');
    </script>
    <!-- End Google Tag Manager -->

    @stack('styles')
</head>

<body class="bg-white bg-opacity-50">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WM7VMCCF" height="0" width="0"
            style="display:none;visibility:hidden"></iframe></noscript>
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
        <section id="breadcrumn-area" class="mt-3">
            <div class="container">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb fs-5">
                        <li class="breadcrumb-item"><a href="https://seldomfashion.com" class="text-dark "
                                style="text-decoration: none">Home</a></li>
                        <li class="breadcrumb-item" style="text-decoration: none" aria-current="page">Product</li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $product->name }}</li>
                    </ol>
                </nav>
            </div>
        </section>
        <section id="product" class="mb-3">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6 left ">
                        <!-- Swiper -->
                        <div style=" max-width: 650px;" class="shadow">
                            <div style="--swiper-navigation-color: #fff; --swiper-pagination-color: #fff"
                                class="swiper mySwiper2">
                                <div class="swiper-wrapper">
                                    <div class="swiper-slide">
                                        <a href="https://www.youtube.com/shorts/70n2ZnZ2ZmQ" data-fancybox="gallery">
                                            <img src="{{ asset('image/product/IMG_0197.webp') }}" />
                                            <span class="play-button"><i class="fa-solid fa-circle-play"></i></span>
                                        </a>
                                    </div>
                                    <div class="swiper-slide">

                                        <a href="{{ asset('image/product/IMG_0143.webp') }}" data-fancybox="gallery">
                                            <img src="{{ asset('image/product/IMG_0143.webp') }}" />
                                        </a>
                                    </div>
                                    <div class="swiper-slide">
                                        <a href="{{ asset('image/product/IMG_0146.webp') }}" data-fancybox="gallery">
                                            <img src="{{ asset('image/product/IMG_0146.webp') }}" />
                                        </a>
                                    </div>
                                    <div class="swiper-slide">
                                        <a href="{{ asset('image/product/IMG_0154.webp') }}" data-fancybox="gallery">
                                            <img src="{{ asset('image/product/IMG_0154.webp') }}" />
                                        </a>
                                    </div>
                                    <div class="swiper-slide">
                                        <a href="{{ asset('image/product/IMG_0156.webp') }}" data-fancybox="gallery">
                                            <img src="{{ asset('image/product/IMG_0156.webp') }}" />
                                        </a>
                                    </div>
                                    <div class="swiper-slide">
                                        <a href="{{ asset('image/product/IMG_0189.webp') }}" data-fancybox="gallery">
                                            <img src="{{ asset('image/product/IMG_0189.webp') }}" />
                                        </a>
                                    </div>
                                    <div class="swiper-slide">
                                        <a href="{{ asset('image/product/IMG_0147.webp') }}" data-fancybox="gallery">
                                            <img src="{{ asset('image/product/IMG_0147.webp') }}" />
                                        </a>
                                    </div>
                                    <div class="swiper-slide">
                                        <a href="{{ asset('image/product/IMG_0194.webp') }}" data-fancybox="gallery">
                                            <img src="{{ asset('image/product/IMG_0194.webp') }}" />
                                        </a>
                                    </div>
                                    <div class="swiper-slide">
                                        <a href="{{ asset('image/product/IMG_0183.webp') }}" data-fancybox="gallery">
                                            <img src="{{ asset('image/product/IMG_0183.webp') }}" />
                                        </a>
                                    </div>
                                    <div class="swiper-slide">
                                        <a href="{{ asset('image/product/IMG_0197.webp') }}" data-fancybox="gallery">
                                            <img src="{{ asset('image/product/IMG_0197.webp') }}" />
                                        </a>
                                    </div>

                                </div>
                                <div class="swiper-button-next"></div>
                                <div class="swiper-button-prev"></div>
                            </div>
                            <div thumbsSlider="" class="swiper mySwiper">
                                <div class="swiper-wrapper">
                                    <div class="swiper-slide">
                                        <img src="{{ asset('image/product/IMG_0197.webp') }}" />
                                        <span class="play-button"><i class="fa-solid fa-circle-play"></i></span>
                                    </div>
                                    <div class="swiper-slide">
                                        <img src="{{ asset('image/product/IMG_0143.webp') }}" />
                                    </div>
                                    <div class="swiper-slide">
                                        <img src="{{ asset('image/product/IMG_0146.webp') }}" />
                                    </div>
                                    <div class="swiper-slide">
                                        <img src="{{ asset('image/product/IMG_0154.webp') }}" />
                                    </div>
                                    <div class="swiper-slide">
                                        <img src="{{ asset('image/product/IMG_0156.webp') }}" />
                                    </div>
                                    <div class="swiper-slide">
                                        <img src="{{ asset('image/product/IMG_0189.webp') }}" />
                                    </div>
                                    <div class="swiper-slide">
                                        <img src="{{ asset('image/product/IMG_0147.webp') }}" />
                                    </div>
                                    <div class="swiper-slide">
                                        <img src="{{ asset('image/product/IMG_0194.webp') }}" />
                                    </div>
                                    <div class="swiper-slide">
                                        <img src="{{ asset('image/product/IMG_0183.webp') }}" />
                                    </div>
                                    <div class="swiper-slide">
                                        <img src="{{ asset('image/product/IMG_0197.webp') }}" />
                                    </div>

                                </div>
                            </div>
                        </div>
                        <!-- Swiper JS -->
                    </div>
                    <div class="col-lg-6 right details ">

                        <h1 class="title text-primary-color fw-bolder mt-3">{{ $product->name }}</h1>

                        <ul>

                            <li>ড্রেস ফেব্রিক্স - প্রিমিয়াম লন </li>
                            <li>দুপাট্টা - ইমপোর্টেড ভিসকোজ কটন</li>
                            <li>সালোয়ার - সফট কটন </li>
                            <li>ড্রেস লং - ৪২" </li>
                            <li>ড্রেস বডি - আনস্টিচড আপটু ৭০ , আপনার প্রেফারেবল সাইজে তৈরি করে নিতে হবে</li>
                        </ul>
                        <div class="row price-details align-items-center justify-content-between">
                            <div class="col-lg-6 Price text-start">
                                {{-- <span class="regular-price fs-5"><del>৳ 2,199.00</del></span> --}}
                                <strong class="fw-bold fs-4">Price: </strong>
                                <span class="discount-price fs-2 fw-bold ">৳ {{ $product->price }}</span>
                            </div>
                            <!--<div class="col-lg-6">-->
                            <!--    <h4 class="stock-in text-success text-end"> Stock Available</h4>-->
                            <!--</div>-->
                        </div>
                        <hr>
                        <p class="fs-6 fw-bold">
                            <strong class="text-danger ">বিদ্রঃ</strong> আপনার অর্ডার নিশ্চিত করতে ফর্মটি পূরণ করে
                            অর্ডার
                            বাটন ক্লিক করুন।
                        </p>
                        <hr>
                        <div class="order-form-box">
                            <h4 class="fw-bold fs-4">অর্ডার ফর্ম</h4>
                            <form id="order-form" action="{{ route('cart.order.place.test') }}" method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-12">
                                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                                        <input type="hidden" name="product_price" id="product_price"
                                            value="{{ $product->price }}">
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold fs-5">আপনার নাম লিখুন</label>
                                            <input type="text" name="name" autocomplete="name"
                                                class="form-control" required id="exampleFormControlInput1"
                                                placeholder="Type Your Full Name">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold fs-5">আপনার মোবাইল লিখুন
                                            </label>
                                            <input name="phone" id="phone" type="text" class="form-control"
                                                required minlength="11" maxlength="11" inputmode="numeric"
                                                pattern="\d{11}" autocomplete="tel"
                                                placeholder="Type Your Phone Number">

                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold fs-5">আপনার ফুল ঠিকানা লিখুন</label>
                                            <textarea autocomplete="address" required name="address" class="form-control" id="exampleFormControlTextarea1"
                                                placeholder="Type Your Full Delivery Address" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold fs-5">ডেলিভারি এরিয়া
                                            </label>
                                            <select name="delivery_area" class="form-select"
                                                aria-label="Default select example">

                                                @foreach ($deliveryAreas as $deliveryArea)
                                                    <option value="{{ $deliveryArea->id }}"
                                                        data-charge="{{ $deliveryArea->charge }}">
                                                        {{ $deliveryArea->name }} - TK {{ $deliveryArea->charge }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold fs-5">পরিমাণ</label>
                                            <div class="input-group w-auto justify-content-end align-items-center">

                                                <button type="button"
                                                    class="fs-2 button-minus border rounded-circle icon-shape icon-sm mx-1 lh-0"
                                                    data-field="quantity">
                                                    <i class="fa-solid fa-circle-minus text-primary-color"></i>
                                                </button>
                                                <!-- <input type="button" value="-"
                                                                    class="button-minus border rounded-circle btn-primary  icon-shape icon-sm mx-1 lh-0"
                                                                    > -->
                                                <input type="number" step="1" max="10" min="1"
                                                    value="1" name="quantity"
                                                    class="quantity-field border-0 text-center w-25 form-control ">

                                                <button type="button"
                                                    class="fs-2 button-plus border rounded-circle btn-primary  icon-shape icon-sm mx-1 lh-0"
                                                    data-field="quantity">
                                                    <i class="fa-solid fa-circle-plus text-primary-color"></i>
                                                </button>
                                            </div>
                                        </div>

                                    </div>
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold fs-5">মোট দাম</label>
                                            <p class="rounded border p-2 fw-bold fs-4" id="total">0</p>
                                            {{-- <input name="total" class="form-control fw-bold fs-5" type="text" value="1950"
                                            aria-label="Disabled input example" readonly> --}}
                                        </div>

                                    </div>
                                    <div class="col-12">
                                       <button id="order-button" type="submit"
                                        class="btn btn-primary bg-primary-color mb-3 w-100 fw-bold fs-5 py-2">অর্ডার
                                        করুন</button>
                                    </div>

                                </div>
                            </form>
                        </div>
                        <hr>
                        <div class="delivery-charge border rounded overflow-hidden mb-3">
                            <table class="table ">

                                <tbody class="fw-bold fs-6 ">
                                    <tr>

                                        <td>ডেলিভারি চার্জ ঢাকার ভিতরে </td>
                                        <td>৳80</td>
                                    </tr>
                                    <tr>

                                        <td>ডেলিভারি চার্জ ঢাকার বাইরে</td>
                                        <td>৳150</td>
                                    </tr>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section id="faq" class=" mb-3">
            <div class="container">

                <h1 class="fs-5 fw-bold bg-primary-color text-center py-3 px-3 text-white">সচরাচর জিজ্ঞাস্য প্রশ্নাবলি
                </h1>
                <ul class="list-inline fs-6 fw-medium">
                    <li><i class="fa-solid fa-angles-right text-primary-color"></i> সারা বাংলাদেশে ক্যাশ অন ডেলিভারি
                        এভেইলেবল </li>
                    <li><i class="fa-solid fa-angles-right  text-primary-color"></i> আপনি যদি আপনার ক্রয়কৃত ড্রেসটি
                        নিয়ে সন্তুষ্ট না হন, তবে শুধু ডেলিভারি চার্জ প্রদান করে ডেলিভারি ম্যানের কাছে সহজেই ফেরত দিতে
                        পারবেন। </li>
                    <li><i class="fa-solid fa-angles-right text-primary-color"></i>সমস্ত এক্সচেঞ্জে উপভোগ করুন সম্পূর্ণ
                        ফ্রি ডেলিভারি — কোন অতিরিক্ত চার্জ নেই, কোন ঝামেলা নেই।
                    <li><i class="fa-solid fa-angles-right text-primary-color"></i>আমাদের আছে ডেলিভারির পর ৩ দিন
                        পর্যন্ত
                        এক্সচেঞ্জ সুবিধা।
                </ul>
            </div>
        </section>

    </main>
    <footer id="footer-area" class="border-top">
        <div class="container py-3">
            <h5 class="text-center fw-semibold text-primary-color"> যেকোনো তথ্যের জন্য আমাদের মেসেজ করুন অথবা কল করুন।
            </h5>
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
        $(document).ready(function() {

            function calculateTotal() {
                // Get product price and convert to float
                let price = parseFloat($('#product_price').val()) || 0;

                // Get quantity
                let quantity = parseInt($('input[name="quantity"]').val()) || 1;

                // Get selected delivery charge
                let deliveryCharge = parseFloat($('select[name="delivery_area"] option:selected').data('charge')) ||
                    0;

                // Calculate total
                let total = (price * quantity) + deliveryCharge;

                // Set formatted total in the total input field
                $('#total').text(total.toFixed(2));
            }

            // Initial calculation on page load
            calculateTotal();

            // Recalculate when quantity changes
            $('input[name="quantity"]').on('input change', function() {
                calculateTotal();
            });

            // Recalculate when delivery area changes
            $('select[name="delivery_area"]').on('change', function() {
                calculateTotal();
            });

            // Optional: plus and minus buttons
            $('.button-plus').click(function() {
                let $input = $(this).siblings('input[name="quantity"]');
                let val = parseInt($input.val()) || 1;
                if (val < parseInt($input.attr('max'))) {
                    $input.val(val + 1).trigger('change');
                }
            });

            $('.button-minus').click(function() {
                let $input = $(this).siblings('input[name="quantity"]');
                let val = parseInt($input.val()) || 1;
                if (val > parseInt($input.attr('min'))) {
                    $input.val(val - 1).trigger('change');
                }
            });

        });
    </script>
    <script>
        // Only digits allowed; also enforces max length = 11
        const phone = document.getElementById('phone');

        // Strip non-digits on input & cap at 11
        phone.addEventListener('input', () => {
            phone.value = phone.value.replace(/\D/g, '').slice(0, 11);
        });

        // Block non-digit keypress (still keep Backspace, Delete, arrows, Tab)
        phone.addEventListener('keydown', (e) => {
            const allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End'];
            if (allowedKeys.includes(e.key)) return;
            if (!/^\d$/.test(e.key)) e.preventDefault();
        });

        // Optional: validate exactly 11 digits on blur
        phone.addEventListener('blur', () => {
            if (phone.value.length !== 11) {
                phone.setCustomValidity('Please enter exactly 11 digits.');
            } else {
                phone.setCustomValidity('');
            }
        });
    </script>


    <script>
        window.dataLayer = window.dataLayer || [];
        dataLayer.push(@json($dataLayer));
    </script>
    <script>
        document.getElementById('order-button').addEventListener('click', function() {
            let value = parseFloat($("#total").text());
            console.log(value);

            dataLayer.push({
                event: "InitiateCheckout",
                content_name: "{{ $product->name }}",
                content_ids: ["{{ $product->id }}"],
                content_type: "product",
                value: value, // number to 2 decimals
                currency: "BDT"
            });

            // Optional: redirect to purchase page
            // window.location.href = '/purchase/{{ $product->id }}';
        });
    </script>
</body>

</html>
