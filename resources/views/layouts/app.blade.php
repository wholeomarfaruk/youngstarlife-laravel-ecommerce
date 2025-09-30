<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>YOUNGSTAR Life</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('frontend/img/youngstar logo-circle.png') }}">
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
    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.8.1/dist/dotlottie-wc.js" type="module"></script>
    <!-- Custom Css  -->
    <style>
        @font-face {
            font-family: 'SolaimanLipi';
            src: url("{{ asset('fonts/SolaimanLipi.ttf') }}") format('truetype');
            font-weight: normal;
            font-style: normal;
        }
    </style>

    <link rel="stylesheet" href="{{ asset('frontend/css/style.css?v=1.0.1') }}">



    @stack('styles')
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
        })(window, document, 'script', 'dataLayer', 'GTM-TVMLT6DT');
    </script>
    <!-- End Google Tag Manager -->
    <meta name="facebook-domain-verification" content="q3e3x73iwktzrop9d227rx2rj9bm8v" />
</head>

<body class="bg-white bg-opacity-50">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TVMLT6DT" height="0" width="0"
            style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <header id="header-area" class="shadow bg-white">
        <div class="container">
            <div class="topbar d-flex justify-content-center">
                <ul class="quick-contact list-inline d-flex justify-content-end gap-3 py-2 mb-0 align-items-center ">
                    <li class="list-inline fw-bold fs-6 "><a href="https://wa.me/8801613046803" target="_blank"
                            class="text-decoration-none text-primary-color text-primary-hover"><i
                                class="fa-brands fa-whatsapp"></i> WhatsApp </a></li>

                    <li class="list-inline fw-bold fs-6"><a href="tel:+8801613-046803"
                            class="text-decoration-none text-primary-color text-primary-hover"> <i
                                class="fa-solid fa-phone"></i>Call Us +88 01613-046803</a></li>
                </ul>
            </div>
        </div>

        <nav class="navbar navbar-expand-lg bg-primary-color  border-top">

            <div class="container">
                <a class="navbar-brand" href="/">
                    <img src="{{ asset('frontend/img/youngstar logo-circle.png') }}" alt=""
                        style="width:50px; ">
                    {{-- <i class="fa-regular fa-star me-1"> --}}
                    </i>YOUNGSTAR Life</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false"
                    aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNavDropdown">
                    <ul class="navbar-nav ">
                        <li class="nav-item fs-5">
                            <a class="nav-link active" aria-current="page" href="/">Home</a>
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
                            <a class="nav-link" href="/">All Products</a>
                        </li>
                        <li class="nav-item fs-5">
                            <a class="nav-link" href="#">Contact</a>
                        </li>
                    </ul>
                </div>
            </div>


        </nav>
    </header>
    <aside id="sidebar"></aside>
    <main id="Content-body" class="py-3">
        <section>
            <div class="container p-0">
                <a href="https://wa.me/8801613046803?text=আমি%20বড়%20সাইজের%20জন্য%20কাস্টোমাইজ%20করতে%20চাচ্ছি"
                    style="text-decoration: none;">
                    <h3 class="text-center" style="color: #2c742c; font-weight: bold;"> কাস্টোমাইজ বড় সাইজের জন্য
                        ওয়াটসএপ এ মেসেজ
                        করুন</h3>
                </a>
            </div>
        </section>
        @yield('content')


    </main>
    <footer id="footer-area" class="border-top">
        <div class="container py-3">
            <h5 class="text-center fw-semibold text-primary-color"> যেকোনো তথ্যের জন্য আমাদের মেসেজ করুন অথবা কল করুন।
            </h5>
            <div class="topbar d-flex justify-content-center">
                <ul class="quick-contact list-inline d-flex justify-content-end gap-3 py-2 mb-0 align-items-center ">
                    <li class="list-inline fw-bold fs-6 "><a href="https://wa.me/8801613046803" target="_blank"
                            class="text-decoration-none text-primary-color text-primary-hover"><i
                                class="fa-brands fa-whatsapp"></i> WhatsApp </a></li>

                    <li class="list-inline fw-bold fs-6"><a href="tel:+8801613-046803"
                            class="text-decoration-none text-primary-color text-primary-hover"> <i
                                class="fa-solid fa-phone"></i> Call Us +88 01613-046803</a></li>
                </ul>
            </div>
        </div>
    </footer>
    <div>
        <style>
            .new-arrival {
                position: absolute;
                left: 20px;
                top: 150px;
                width: 100px;
            }
        </style>
        <div class="new-arrival">
            <a href="https://youngstar.life/category/joggers-pant" class="text-center text-decoration-none">
                <dotlottie-wc src="https://lottie.host/8a7eb623-e79f-481e-b63f-555486c2e6a7/0Hmst73iRT.lottie"
                    style="width: 100px;height: 100px" autoplay loop></dotlottie-wc>
                <span class="fw-semibold text-primary-color fs-6">Cargo Pants</span>
            </a>
        </div>
    </div>
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

</body>

</html>
