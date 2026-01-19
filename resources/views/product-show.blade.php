@extends('layouts.app')

@push('styles')
    <style>
        .mySwiper2 .swiper-slide {
            height: 670px;

            text-align: center;
        }

        .mySwiper2 .swiper-slide img {
            height: 100%;
            width: auto;
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
                                        <img src="{{ asset('storage/images/products/' . $product?->image) }}" />
                                    </a>
                                </div>
                                @if ($product->media->where('category', 'product_images')->count() > 0)
                                    @foreach ($product->media->where('category', 'product_images') as $pimage)
                                        <div class="swiper-slide">
                                            <a href="{{ asset($pimage->path) }}" data-fancybox="gallery">
                                                <img src="{{ asset($pimage->path) }}" />
                                            </a>
                                        </div>
                                    @endforeach
                                @endif
                                @if ($product?->sizechart)
                                    <div class="swiper-slide">
                                        <a href="{{ asset($product?->sizechart) }}" data-fancybox="gallery">
                                            <img src="{{ asset($product?->sizechart) }}" />
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


                                    <img src="{{ asset('storage/images/products/' . $product?->image) }}" />

                                </div>

                                @if ($product->media->where('category', 'product_images')->count() > 0)
                                    @foreach ($product->media->where('category', 'product_images') as $pimage)
                                        <div class="swiper-slide">

                                            <img src="{{ asset($pimage->path) }}" />

                                        </div>
                                    @endforeach
                                @endif
                                @if ($product?->sizechart)
                                    <div class="swiper-slide">

                                        <img src="{{ asset($product?->sizechart) }}" />

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
                                                            value="{{ $size->name }}" id="size-{{ $size->id }}"
                                                            required>
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
                                            required id="exampleFormControlInput1" placeholder="Type Your Full Name">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold fs-5">আপনার মোবাইল লিখুন
                                        </label>
                                        <input name="phone" id="phone" type="text" class="form-control"
                                            required minlength="11" inputmode="numeric" autocomplete="tel"
                                            placeholder="Type Your Phone Number">
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
                                    <button id="order-button" type="submit"
                                        {{ $product?->stock_status == 'out_of_stock' ? 'disabled' : '' }}
                                        class="btn btn-primary bg-primary-color mb-3 w-100 fw-bold fs-5 py-2">অর্ডার
                                        করুন {{ $product?->stock_status == 'out_of_stock' ? '(স্টক শেষ)' : '' }}</button>
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
                                            alt="">
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
        // Only digits allowed; also enforces max length = 11
        const phone = document.getElementById('phone');

        // Strip non-digits on input & cap at 11
        phone.addEventListener('input', () => {
            phone.value = phone.value.replace(/\D/g, '');
        });

        // Block non-digit keypress (still keep Backspace, Delete, arrows, Tab)
        phone.addEventListener('keydown', (e) => {
            const allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End'];
            if (allowedKeys.includes(e.key)) return;
            if (!/^\d$/.test(e.key)) e.preventDefault();
        });

        // Optional: validate exactly 11 digits on blur
        // phone.addEventListener('blur', () => {
        //     console.log(phone.value)
        //     if (phone.value.length !== 11) {
        //         phone.setCustomValidity('Please enter exactly 11 digits.');
        //     } else {
        //         phone.setCustomValidity('');
        //     }
        // });
    </script>
    <script>
        $(document).ready(function() {
            let pamount = "{{ $product->discount_price ?? $product->price }}";
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
                        item_name: "{{ $product->name }}", // String, required
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
                let name = $("input[name='name").val();
                let phone = $("input[name='phone").val();
                let address = $("textarea[name='address").val();
                let size = $("input[name='size").val();

                // console.log(value);

                dataLayer.push({
                    event: 'begin_checkout',
                    ecommerce: {
                        value: value, // Number, two decimals, required
                        currency: 'BDT', // String, required
                        items: [{
                            item_name: "{{ $product->name }}", // String, required
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
                        phone_number: phone ?? null,
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
        $(window).on('beforeunload', function() {
            var name = $("input[name='name']").val();
            var phone = $("input[name='phone']").val();
            var address = $("textarea[name='address']").val();
            var size = $("input[name='size").val();
            var product_id = $("input[name='product_id").val();
            var quantity = $("input[name='quantity").val();
            var delivery_area = $("select[name='delivery_area").val();
            var token = "{{ csrf_token() }}";
            console.log("token: " + token);
            var order_data = {
                name: name,
                phone: phone,
                address: address,
                size: size,
                product_id: product_id,
                quantity: quantity,
                delivery_area: delivery_area,
                XSRF_TOKEN: token,
            }
            fetch('/cart/autosave', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token
                },

                body: JSON.stringify(order_data)
            })

            // event.preventDefault();

        })
    </script>
@endpush
