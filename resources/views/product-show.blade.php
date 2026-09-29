@extends('layouts.app')

@push('styles')
    <style>
        .mySwiper2 .swiper-slide {
            height: auto;

            text-align: center;
        }

        .mySwiper2 .swiper-slide img {
            height: 100%;
            width: 100%;
            margin: 0 auto;
            object-fit: contain;

        }

        .mySwiper2 .swiper-slide img a {
            display: block;
            text-align: center;
        }

        .navigation .swiper-slide {
            height: 100px;

        }

        .navigation .swiper-slide img {
            height: 100%;
            width: cover;
        }
        @media (max-width:500px){
             .mySwiper2 .swiper-slide {
            height: auto;
            width:100%;

            text-align: center;
        }
        }
    </style>
@endpush
@section('content')
    <section id="breadcrumn-area" class="mt-3">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb fs-5">
                    <li class="breadcrumb-item"><a href="https://seldomfashion.com" class="text-dark "
                            style="text-decoration: none">Home</a></li>
                    <li class="breadcrumb-item" style="text-decoration: none" aria-current="page">Product</li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $product?->name }}</li>
                </ol>
            </nav>
        </div>
    </section>
    <section id="product" class="mb-3">
        <div class="container">
            <div class="row">
                <div class="col-lg-5 left ">
                    <!-- Swiper -->
                    <div style=" max-width: 650px;" class="shadow">
                        <div style="--swiper-navigation-color: #fff; --swiper-pagination-color: #fff"
                            class="swiper mySwiper2">
                            <div class="swiper-wrapper">

                                <div class="swiper-slide">
                                    <a href="{{ asset('storage/images/products/' . $product?->image) }}"
                                        data-fancybox="gallery">
                                        <img src="{{ asset('storage/images/products/' . $product?->image) }}"
                                            fetchpriority="high" decoding="async" />
                                    </a>
                                </div>
                                @if ($product->media->where('category', 'product_images')->count() > 0)
                                    @foreach ($product->media->where('category', 'product_images') as $pimage)
                                        <div class="swiper-slide">
                                            <a href="{{ asset($pimage->path) }}" data-fancybox="gallery">
                                                <img src="{{ asset($pimage->path) }}" loading="lazy" decoding="async" />
                                            </a>
                                        </div>
                                    @endforeach
                                @endif
                                @if ($product?->sizechart)
                                    <div class="swiper-slide">
                                        <a href="{{ asset($product?->sizechart) }}" data-fancybox="gallery">
                                            <img src="{{ asset($product?->sizechart) }}" loading="lazy" decoding="async" />
                                        </a>
                                    </div>
                                @endif
                            </div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                        </div>

                        <div class="swiper mySwiper navigation">
                            <div class="swiper-wrapper">


                                <div class="swiper-slide">


                                    <img src="{{ asset('storage/images/products/' . $product?->image) }}" decoding="async" />

                                </div>

                                @if ($product->media->where('category', 'product_images')->count() > 0)
                                    @foreach ($product->media->where('category', 'product_images') as $pimage)
                                        <div class="swiper-slide">

                                            <img src="{{ asset($pimage->path) }}" loading="lazy" decoding="async" />

                                        </div>
                                    @endforeach
                                @endif
                                @if ($product?->sizechart)
                                    <div class="swiper-slide">

                                        <img src="{{ asset($product?->sizechart) }}" loading="lazy" decoding="async" />

                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>
                    <!-- Swiper JS -->
                </div>
                <div class="col-lg-7 right details ">

                    <h1 class="title text-primary-color fw-bolder mt-3">{{ $product?->name }}</h1>
                    <div>
                        {!! $product?->description !!}
                    </div>


                    <div class="row price-details align-items-center justify-content-between">
                        <div class="col-lg-6 Price text-start">
                            @if ($product?->discount_price && $product?->discount_price > 0)
                                <strong class="fw-bold fs-4"></strong>
                                <span class="regular-price fs-5"><del>৳ {{ $product?->price }} </del></span>
                                <strong class="fw-bold fs-4"> </strong>
                                <span class="discount-price fs-2 fw-bold "> ৳ {{ $product?->discount_price }}</span>
                            @else
                                <strong class="fw-bold fs-4">Price: </strong>
                                <span class="discount-price fs-2 fw-bold ">৳ {{ $product?->price }}</span>
                            @endif

                        </div>
                        @if ($product?->stock_status == 'out_of_stock')
                            <div class="col-lg-6">
                                <h4 class="stock-in text-danger text-end"> Stock Out </h4>
                            </div>
                        @endif
                    </div>
                    <hr>
                    <p class="fs-4 fw-bold">
                        <strong class="text-danger ">বিদ্রঃ</strong> <a
                            href="https://wa.me/8801613046803?text=আমি%20যে%20কোন%20কালার%20দিয়ে%20কম্বো%20করতে%20চাই"
                            target="_blank" class="text-decoration-none text-primary-color text-primary-hover"> যে কোন কালার
                            দিয়ে কম্বো করতে <i class="fa-brands fa-whatsapp"></i> WhatsApp ওয়াটসেপ করুন</a>
                    </p>
                    <hr>
                    <div class="order-form-box">
                        <h4 class="fw-bold fs-4">অর্ডার ফর্ম</h4>
                        <form id="order-form" action="{{ route('cart.order.place') }}" method="post">
                            @csrf
                            <div class="row">
                                <div class="col-12">
                                    <input type="hidden" name="product_id" value="{{ $product?->id }}">
                                    <input type="hidden" name="product_price" id="product_price"
                                        value="{{ $product->discount_price && $product->discount_price > 0 ? $product->discount_price : $product->price }}">

                                </div>

                                @if ($product?->sizes->count() > 0)
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold fs-5 d-block">সাইজ</label>
                                            <style>
                                                .size-option {
                                                    display: inline-block;
                                                    margin-right: 8px;
                                                }

                                                .size-option input[type="radio"] {
                                                    display: none;
                                                    /* hide real radio */
                                                }

                                                .size-option label {
                                                    border: 2px solid #ccc;
                                                    padding: 8px 15px;
                                                    border-radius: 8px;
                                                    cursor: pointer;
                                                    transition: all 0.3s ease;
                                                    user-select: none;
                                                    font-size: 20px;
                                                }

                                                .size-option input[type="radio"]:checked+label {
                                                    background-color: var(--primary-color);
                                                    /* Bootstrap primary */
                                                    color: #fff;
                                                    border-color: var(--primary-color);
                                                }

                                                .size-option label:hover {
                                                    border-color: var(--primary-color);
                                                }
                                            </style>

                                            <div class="d-flex flex-wrap">
                                                @foreach ($product?->sizes as $size)
                                                    <div class="size-option">
                                                        <input class="form-check-input" type="radio" name="size"
                                                            value="{{ $size->name }}" id="size-{{ $size->id }}">
                                                        <label class="form-check-label" for="size-{{ $size->id }}">
                                                            {{ $size->name }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>

                                        </div>
                                    </div>
                                @endif
                                <div class="col-lg-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold fs-5">আপনার নাম লিখুন</label>
                                        <input type="text" name="name" autocomplete="name" class="form-control"
                                            required id="exampleFormControlInput1" placeholder="Type Your Full Name"
                                            value="{{ old('name') }}">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold fs-5">আপনার মোবাইল লিখুন
                                        </label>
                                        <input name="phone" id="phone" type="text"
                                            class="form-control @error('phone') is-invalid @enderror"
                                            required inputmode="numeric" autocomplete="tel"
                                            placeholder="Type Your Phone Number" value="{{ old('phone') }}">
                                        @error('phone')
                                            <span class="invalid-feedback" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold fs-5">আপনার ফুল ঠিকানা লিখুন</label>
                                        <textarea autocomplete="address" required name="address" class="form-control" id="exampleFormControlTextarea1"
                                            placeholder="Type Your Full Delivery Address" rows="3">{{ old('address') }}</textarea>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold fs-5">ডেলিভারি এরিয়া
                                        </label>
                                        <select name="delivery_area" class="form-select"
                                            aria-label="Default select example">

                                            @foreach ($deliveryAreas as $deliveryArea)
                                                <option value="{{ $deliveryArea?->id }}"
                                                    data-charge="{{ $deliveryArea?->charge }}">
                                                    {{ $deliveryArea?->name }} - TK {{ $deliveryArea?->charge }}
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
                                    <style>
                                        #order-button .placing-text {
                                            display: none;
                                        }
                                        #order-button.is-placing .default-text {
                                            display: none;
                                        }
                                        #order-button.is-placing .placing-text {
                                            display: inline-flex;
                                            align-items: center;
                                            gap: 10px;
                                        }
                                        #order-button.is-placing {
                                            opacity: 0.9;
                                            cursor: wait;
                                            animation: order-pulse 1.2s ease-in-out infinite;
                                        }
                                        #order-button .order-spinner {
                                            width: 20px;
                                            height: 20px;
                                            border: 3px solid rgba(255, 255, 255, 0.4);
                                            border-top-color: #fff;
                                            border-radius: 50%;
                                            animation: order-spin 0.7s linear infinite;
                                        }
                                        @keyframes order-spin {
                                            to {
                                                transform: rotate(360deg);
                                            }
                                        }
                                        @keyframes order-pulse {
                                            50% {
                                                transform: scale(0.98);
                                            }
                                        }
                                    </style>
                                    <button id="order-button" type="submit"
                                        {{ $product?->stock_status == 'out_of_stock' ? 'disabled' : '' }}
                                        class="btn btn-primary bg-primary-color mb-3 w-100 fw-bold fs-5 py-2">
                                        <span class="default-text">অর্ডার
                                            করুন {{ $product?->stock_status == 'out_of_stock' ? '(স্টক শেষ)' : '' }}</span>
                                        <span class="placing-text"><span class="order-spinner"></span> অর্ডার প্লেস
                                            হচ্ছে...</span>
                                    </button>
                                </div>

                            </div>
                        </form>
                    </div>
                    <hr>
                    <div class="delivery-charge border rounded overflow-hidden mb-3">
                        <table class="table ">

                            <tbody class="fw-bold fs-6 ">
                                @if ($deliveryAreas->isEmpty())
                                    <tr>
                                        <td colspan="2" class="text-center">ডেলিভারি এরিয়া সেট করা নেই</td>
                                    </tr>
                                @else
                                    @foreach ($deliveryAreas as $deliveryArea)
                                        <tr>
                                            <td>{{ $deliveryArea?->name }}</td>
                                            <td>৳{{ $deliveryArea?->charge }}</td>
                                        </tr>
                                    @endforeach
                                @endif

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @if ($total > 3)
        <style>
            /* Customer Reviews slider */
            .sec-reviews .reviewsSwiper {
                padding-bottom: 40px;
                height: auto !important;
            }

            .sec-reviews .review-card {
                display: block;
                text-decoration: none;
                box-shadow: rgba(149, 157, 165, 0.2) 0px 8px 24px;
                border-radius: 8px;
                overflow: hidden;
                background: #fff;
                transition: all 0.2s ease-in-out;
            }

            .sec-reviews .review-card:hover {
                box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
                transform: scale(1.02);
            }

            .sec-reviews .review-img-box {
                overflow: hidden;
                background: #f4f4f5;
            }

            .sec-reviews .review-img-box img {
                width: 100%;
                height: auto;
                display: block;
                transition: all 0.2s ease-in-out;
            }

            .sec-reviews .review-card:hover .review-img-box img {
                transform: scale(1.08);
            }

            .sec-reviews .review-title {
                font-size: 15px;
                font-weight: 600;
                color: rgb(37, 37, 37);
                padding: 8px 10px;
                text-align: center;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sec-reviews .swiper-pagination-bullet-active {
                background: var(--text-primary-color);
            }
        </style>

        <section class="sec-style-1 sec-reviews my-3">
            <div class="container">
                <div class="sec-header">
                    <div class="d-flex justify-content-between">
                        <div class="">
                            <h2 class="sec-title text-primary-color">{{ $total }} Customer Reviews - কাস্টমার রিভিউ</h2>
                        </div>
                        <div class="text-right">
                            <a href="{{ route('reviews') }}" class="sec-title text-primary-color">See all</a>
                        </div>
                    </div>
                    <hr class="divider mt-0 text-primary-color bg-primary-color" style="height: 2px;">
                </div>
                <div class="sec-body">
                    <div class="swiper reviewsSwiper">
                        <div class="swiper-wrapper">
                            @foreach ($reviews as $slide)
                                <div class="swiper-slide">
                                    <a class="review-card" data-fancybox="reviews"
                                        href="{{ asset('storage/images/slides/' . $slide->image) }}"
                                        @if ($slide->title) data-caption="{{ $slide->title }}" @endif>
                                        <div class="review-img-box">
                                            <img src="{{ asset('storage/images/slides/' . $slide->image) }}"
                                                alt="{{ $slide->title ?? 'Customer review' }}" loading="lazy">
                                        </div>
                                        @if ($slide->title)
                                            <div class="review-title">{{ $slide->title }}</div>
                                        @endif
                                    </a>
                                </div>
                            @endforeach
                        </div>
                        <div class="swiper-pagination"></div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($products->count() > 0)
        <section class="sec-style-2 my-3">
            <div class="container">


                <div class="sec-header">
                    <h2 class="sec-title text-primary-color">More Products - আরো দেখুন</h2>
                    <hr class="divider mt-0 text-primary-color bg-primary-color " style="height: 2px;">
                </div>
                <div class="sec-body">
                    <div class="sec-grid-box">
                        @foreach ($products as $pitem)
                            <div class="sec-grid-item p-card-1">

                                <div class="p-img-box">
                                    <a href="{{ route('product.show', $pitem->slug) }}">
                                        <img src="{{ asset('storage/images/products/' . $pitem->image) }}"
                                            alt="{{ $pitem->name }}" loading="lazy" decoding="async">
                                    </a>
                                </div>
                                <div class="p-info">
                                    <div class="prices">
                                        @if ($pitem->discount_price && $pitem->discount_price > 0)
                                            <del class="old-price">৳ {{ $pitem->price }}</del>
                                            <span class="price">৳ {{ $pitem->discount_price }}</span>
                                        @else
                                            <span class="price">Price: ৳ {{ $pitem->price }}</span>
                                        @endif
                                    </div>
                                    <a href="{{ route('product.show', $pitem->slug) }}">

                                        <h1 class="p-title">{{ $pitem->name }}</h1>
                                    </a>
                                    <a href="{{ route('product.show', $pitem->slug) }}">
                                        <p class="p-description">
                                            বিস্তারিত দেখুন
                                        </p>
                                    </a>
                                </div>
                                <div class="p-btn-group">
                                    <a class="btn btn-primary w-100 d-block"
                                        href="{{ route('product.show', $pitem->slug) }}">Buy Now</a>
                                </div>


                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif
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

                <li><i class="fa-solid fa-angles-right text-primary-color"></i>আমাদের আছে ডেলিভারির পর ৩ দিন
                    পর্যন্ত
                    এক্সচেঞ্জ সুবিধা।
                </li>
            </ul>
        </div>
    </section>
@endsection

@push('scripts')
    @if (session('status') == 'error')
        <script>
            Swal.fire({
                icon: "{{ session('status') == 'error' ? 'error' : 'success' }}",
                title: "{{ session('status') == 'error' ? 'দুঃখিত!' : 'সফল!' }}",
                text: "{{ session('message') }}",
                confirmButtonText: 'ঠিক আছে',
                timer: 4000, // Auto close after 4 seconds
                timerProgressBar: true,
            });
        </script>
        @elseif(session('status') == 'success')
        <script>
            Swal.fire({
                icon: "{{ session('status') == 'error' ? 'error' : 'success' }}",
                title: "{{ session('status') == 'error' ? 'দুঃখিত!' : 'সফল!' }}",
                text: "{{ session('message') }}",
                confirmButtonText: 'ঠিক আছে',
                timer: 4000, // Auto close after 4 seconds
                timerProgressBar: true,
            });
        </script>
    @endif

    <script>
        console.log("Session: " + "{{ session('status') ? session('message') : 'null' }}");
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
        // Same rules as App\Support\Phone::normalize(): accept +880 / 880 / 80 / 0088 prefixes, missing
        // leading 0, spaces, dashes and Bangla digits, and return 01XXXXXXXXX (or null if not a BD mobile).
        window.toAsciiDigits = function(value) {
            return String(value || '').replace(/[০-৯]/g, d => d.charCodeAt(0) - 0x09E6)
                .replace(/[٠-٩]/g, d => d.charCodeAt(0) - 0x0660)
                .replace(/[۰-۹]/g, d => d.charCodeAt(0) - 0x06F0);
        };
        window.normalizeBdPhone = function(value) {
            const digits = toAsciiDigits(value).replace(/\D/g, '');
            if (digits.length < 10) return null;
            const number = digits.slice(-10);
            const prefix = digits.slice(0, -10);
            if (!/^1[3-9]\d{8}$/.test(number) || !/^(00)?8{0,2}0{0,2}$/.test(prefix)) return null;
            return '0' + number;
        };

        const phone = document.getElementById('phone');
        const phoneMessage = 'সঠিক মোবাইল নম্বর দিন (যেমন 01XXXXXXXXX)';

        // Bangla digits -> English, drop everything else; browser blocks submit until the number is valid
        phone.addEventListener('input', () => {
            const cleaned = toAsciiDigits(phone.value).replace(/\D/g, '');
            if (cleaned !== phone.value) phone.value = cleaned;
            phone.setCustomValidity(!cleaned || normalizeBdPhone(cleaned) ? '' : phoneMessage);
        });

        // Show the number in standard form (01XXXXXXXXX) once the customer leaves the field
        phone.addEventListener('blur', () => {
            const normalized = normalizeBdPhone(phone.value);
            if (normalized) phone.value = normalized;
        });

        // Block letters/symbols typed on a keyboard; paste (Ctrl/Cmd+V), Bangla digits and
        // mobile keyboards (which report "Unidentified") still work — the input handler cleans up
        phone.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey || e.altKey || e.key.length !== 1) return;
            if (!/^[0-9০-৯]$/.test(e.key)) e.preventDefault();
        });
    </script>
    <script>
        $(document).ready(function() {
            let pamount = "{{ (float) $product->discount_price > 0 ? $product->discount_price : $product->price }}";
            pamount = parseFloat(pamount);
            console.log('dom ready');
            dataLayer = window.dataLayer || [];
            dataLayer.push({
                ecommerce: null
            }); // we want to null out the ecommerce object, so there's no overlap if events happen on the same page
            dataLayer.push({
                event: 'view_item',
                ecommerce: {
                    value: pamount, // Number, two decimals, required
                    currency: 'BDT', // String, required
                    items: [{
                        item_name: @json($product->name), // String, required
                        item_id: "{{ $product->id }}", // String, required
                        price: pamount, // Number, two decimals, required
                        quantity: 1, // Integer, required
                        item_category: "Pants", // String, optional but advised if available
                        item_brand: 'YoungStar Life', // String, optional, might be useful if you sell different brands
                        item_variant: null // String, optional
                    }]
                },
                // user_data অবজেক্টে শুধুমাত্র সেই ডেটা রাখুন যা আপনার কাছে উপলব্ধ
                // অথবা, যদি কোনো ইউজার ডেটা না থাকে, তাহলে এই অংশটি বাদ দিন।
                // উদাহরণস্বরূপ, যদি আপনি একটি সেশন আইডি ট্র্যাক করতে পারেন:
                user_data: {
                    // first_name: null, // বা এই লাইনগুলো বাদ দিন
                    // last_name: null,
                    // email_address: null,
                    // phone_number: null,
                    // street: null,
                    // country: "BD", // IP Address থেকে পাওয়া গেলে
                    // city: null,
                    // region: null,
                    // postal_code: null,
                    user_id: sessionStorage.getItem('visitorId') ||
                        null, // উদাহরণ: সেশন স্টোরেজ থেকে visitorId ব্যবহার করা
                    // new_customer: 'true' // এটি অনুমান করা কঠিন হবে
                }
            });

            function sentInitialCheckout() {
                let value = parseFloat($("#total").text());
                let quantity = parseFloat($(".quantity-field").val());
                let name = $("input[name='name']").val();
                let phone = $("input[name='phone']").val();
                let address = $("textarea[name='address']").val();
                let size = $("input[name='size']:checked").val() || null;

                // console.log(value);

                dataLayer.push({
                    event: 'begin_checkout',
                    ecommerce: {
                        value: value, // Number, two decimals, required
                        currency: 'BDT', // String, required
                        items: [{
                            item_name: @json($product->name), // String, required
                            item_id: "{{ $product->id }}", // String, required
                            price: pamount, // Number, two decimals, required
                            quantity: quantity, // Integer, required
                            item_category: "Pants", // String, optional but advised if available
                            item_brand: 'YoungStar Life', // String, optional, might be useful if you sell different brands
                            item_variant: size // String, optional
                        }]
                    },
                    // user_data অবজেক্টে শুধুমাত্র সেই ডেটা রাখুন যা আপনার কাছে উপলব্ধ
                    // অথবা, যদি কোনো ইউজার ডেটা না থাকে, তাহলে এই অংশটি বাদ দিন।
                    // উদাহরণস্বরূপ, যদি আপনি একটি সেশন আইডি ট্র্যাক করতে পারেন:
                    user_data: {
                        first_name: name ?? null, // বা এই লাইনগুলো বাদ দিন
                        // last_name: null,
                        // email_address: null,
                        // +8801XXXXXXXXX: Meta's pixel drops the leading 0 and needs "+" to trust the country code
                        phone_number: normalizeBdPhone(phone) ? '+88' + normalizeBdPhone(phone) : (phone || null),
                        street: address ?? null,
                        // country: "BD", // IP Address থেকে পাওয়া গেলে
                        // city: null,
                        // region: null,
                        // postal_code: null,
                        user_id: sessionStorage.getItem('visitorId') ||
                            null, // উদাহরণ: সেশন স্টোরেজ থেকে visitorId ব্যবহার করা
                        // new_customer: 'true' // এটি অনুমান করা কঠিন হবে
                    }
                });

            }

            // Size options are visually hidden radios (see .size-option CSS above), so native
            // HTML5 "required" validation silently blocks submission with no visible message.
            // Validate explicitly and show SweetAlert2 instead.
            $('#order-form').on('submit', function(e) {
                let sizeOptions = $(this).find('input[name="size"]');
                if (sizeOptions.length > 0 && sizeOptions.filter(':checked').length === 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'সাইজ নির্বাচন করুন',
                        text: 'অর্ডার করার আগে অনুগ্রহ করে একটি সাইজ সিলেক্ট করুন।',
                        confirmButtonText: 'ঠিক আছে',
                    });
                    return false;
                }

                // Valid submit: block double taps (a 2nd submit hits the 30 min duplicate check and
                // bounces back, so the customer never sees the order received page / purchase event)
                if (window.orderSubmitting) {
                    e.preventDefault();
                    return false;
                }
                window.orderSubmitting = true;
                $('#order-button').addClass('is-placing').prop('disabled', true);
            });

            // Coming back with the browser back button restores the page from cache; reset the button
            window.addEventListener('pageshow', function(e) {
                if (e.persisted) {
                    window.orderSubmitting = false;
                    $('#order-button').removeClass('is-placing')
                        .prop('disabled', @json($product?->stock_status == 'out_of_stock'));
                }
            });

            $('#order-button').on('click', function(e) {
                e.preventDefault();

                let value = parseFloat($("#total").text());
                let quantity = parseFloat($(".quantity-field").val());
                let name = $("input[name='name']").val();
                let phone = $("input[name='phone']").val();
                let address = $("textarea[name='address']").val();

                // Run your custom logic
                sentInitialCheckout();
                setTimeout(() => {

                }, 1000);
                // Trigger normal validation + submit
                document.getElementById("order-form").requestSubmit();
            });


        })
    </script>
    <script>
        // Smart autosave (abandoned order leads).
        // Saves only once the phone is a valid BD mobile, 1.5s after the customer stops typing, and only
        // when something changed. Also flushes when the tab goes to the background (mobile users
        // switching apps / closing). Never runs while the real order is being submitted.
        (function() {
            var form = document.getElementById('order-form');
            if (!form) return;

            var url = @json(route('cart.order.autosave', [], false)); // relative, independent of APP_URL
            var token = @json(csrf_token());
            var lastSent = '';
            var timer = null;

            function collect() {
                var sizeInput = form.querySelector("input[name='size']:checked");
                var qty = form.querySelector("input[name='quantity']");
                var area = form.querySelector("select[name='delivery_area']");
                return {
                    name: (form.querySelector("input[name='name']") || {}).value || '',
                    phone: normalizeBdPhone((form.querySelector("input[name='phone']") || {}).value) || '',
                    address: (form.querySelector("textarea[name='address']") || {}).value || '',
                    size: sizeInput ? sizeInput.value : '',
                    product_id: (form.querySelector("input[name='product_id']") || {}).value || '',
                    quantity: qty ? qty.value : 1,
                    delivery_area: area ? area.value : '',
                };
            }

            function send(useBeacon) {
                clearTimeout(timer);
                if (window.orderSubmitting) return;

                var data = collect();
                if (!data.phone || !data.product_id) return; // not a valid BD mobile yet

                var snapshot = JSON.stringify(data);
                if (snapshot === lastSent) return;
                lastSent = snapshot;

                var body = new FormData();
                body.append('_token', token);
                Object.keys(data).forEach(function(key) {
                    body.append(key, data[key]);
                });

                try {
                    if (useBeacon && navigator.sendBeacon && navigator.sendBeacon(url, body)) return;
                    fetch(url, {
                        method: 'POST',
                        body: body,
                        keepalive: true,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }).catch(function() {
                        lastSent = ''; // retry on the next change
                    });
                } catch (e) {}
            }

            function schedule() {
                clearTimeout(timer);
                timer = setTimeout(function() {
                    send(false);
                }, 1500);
            }

            form.addEventListener('input', schedule);
            form.addEventListener('change', schedule);
            $('.button-plus, .button-minus').on('click', schedule);

            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden') send(true);
            });
            window.addEventListener('pagehide', function() {
                send(true);
            });
        })();
    </script>

    <script>
        function initReviewsSwiper() {
            var el = document.querySelector('.sec-reviews .reviewsSwiper');
            if (!el || el.dataset.swiperInit || typeof Swiper === 'undefined') return;
            el.dataset.swiperInit = '1';
            new Swiper(el, {
                spaceBetween: 15,
                slidesPerView: 2, // mobile
                grabCursor: true,
                loop: true,
                autoplay: {
                    delay: 2500,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true,
                },
                pagination: {
                    el: ".sec-reviews .swiper-pagination",
                    clickable: true,
                },
                breakpoints: {
                    768: { slidesPerView: 3 }, // tablet
                    992: { slidesPerView: 4 }, // desktop
                },
            });
        }
        initReviewsSwiper();
    </script>
@endpush
