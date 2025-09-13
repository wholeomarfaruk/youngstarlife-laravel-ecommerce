@extends('layouts.app')

@section('content')
    <section id="breadcrumn-area" class="mt-3">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb fs-5">
                    <li class="breadcrumb-item"><a href="https://seldomfashion.com" class="text-dark "
                            style="text-decoration: none">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Product</li>
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
                                    <a href="https://youtu.be/B7fweRfbCUc" data-fancybox="gallery">
                                        <img src="{{ asset('image/product/IMG_0857_1.webp') }}" />
                                        <span class="play-button"><i class="fa-solid fa-circle-play"></i></span>
                                    </a>
                                </div>
                                <div class="swiper-slide">

                                    <a href="{{ asset('image/product/IMG_0855_2.webp') }}" data-fancybox="gallery">
                                        <img src="{{ asset('image/product/IMG_0855_2.webp') }}" />
                                    </a>
                                </div>
                                <div class="swiper-slide">
                                    <a href="{{ asset('image/product/IMG_0857.webp') }}" data-fancybox="gallery">
                                        <img src="{{ asset('image/product/IMG_0857.webp') }}" />
                                    </a>
                                </div>
                                <div class="swiper-slide">
                                    <a href="{{ asset('image/product/IMG_0856_1.webp') }}" data-fancybox="gallery">
                                        <img src="{{ asset('image/product/IMG_0856_1.webp') }}" />
                                    </a>
                                </div>
                                <div class="swiper-slide">
                                    <a href="{{ asset('image/product/IMG_0856_2.webp') }}" data-fancybox="gallery">
                                        <img src="{{ asset('image/product/IMG_0855.webp') }}" />
                                    </a>
                                </div>
                                <div class="swiper-slide">
                                    <a href="{{ asset('image/product/IMG_0856_2.webp') }}" data-fancybox="gallery">
                                        <img src="{{ asset('image/product/IMG_0856_2.webp') }}" />
                                    </a>
                                </div>

                            </div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                        </div>
                        <div thumbsSlider="" class="swiper mySwiper">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide">
                                    <img src="{{ asset('image/product/IMG_0857_1.webp') }}" />
                                    <span class="play-button"><i class="fa-solid fa-circle-play"></i></span>
                                </div>
                                <div class="swiper-slide">
                                    <img src="{{ asset('image/product/IMG_0855_2.webp') }}" />
                                </div>
                                <div class="swiper-slide">
                                    <img src="{{ asset('image/product/IMG_0857.webp') }}" />
                                </div>
                                <div class="swiper-slide">
                                    <img src="{{ asset('image/product/IMG_0856_1.webp') }}" />
                                </div>
                                <div class="swiper-slide">
                                    <img src="{{ asset('image/product/IMG_0856_2.webp') }}" />
                                </div>
                                <div class="swiper-slide">
                                    <img src="{{ asset('image/product/IMG_0855.webp') }}" />
                                </div>

                            </div>
                        </div>
                    </div>
                    <!-- Swiper JS -->
                </div>
                <div class="col-lg-6 right details ">

                    <h1 class="title text-primary-color fw-bolder mt-3">{{ $product->name }}</h1>

                    <ul>

                        <li>ড্রেস ফেব্রিক্স - ইমপোর্টেড ভিসকস কটন </li>
                        <li>দুপাট্টা - ইমপোর্টেড সফ্ট কটন</li>
                        <li>সালোয়ার - কটন </li>
                        <li>ড্রেস লং - ৪৬" </li>
                        <li>ড্রেস বডি - আনস্টিচড আপটু ৫৪ , আপনার প্রেফারেবল সাইজে অল্টার করে নিতে হবে</li>
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
                        <form id="order-form" action="{{ route('cart.order.place') }}" method="post">
                            @csrf
                             <div class="row">
                                <div class="col-12">
                                    <input type="hidden" name="product_id" value="{{$product->id}}">
                                    <input type="hidden" name="product_price" id="product_price" value="{{$product->price}}">
                                </div>
                                <div class="col-lg-6">
                                    <div class="mb-3">
                                        <label  class="form-label fw-bold fs-5">আপনার নাম লিখুন</label>
                                        <input type="text" name="name" autocomplete="name" class="form-control" required
                                            id="exampleFormControlInput1" placeholder="Type Your Full Name">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="mb-3">
                                        <label  class="form-label fw-bold fs-5">আপনার মোবাইল লিখুন
                                        </label>
                                        <input name="phone" autocomplete="tel" type="text" class="form-control" required
                                            id="exampleFormControlInput1" placeholder="Type Your Phone Number">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label  class="form-label fw-bold fs-5">আপনার ফুল ঠিকানা লিখুন</label>
                                        <textarea autocomplete="address" required name="address" class="form-control" id="exampleFormControlTextarea1" placeholder="Type Your Full Delivery Address"
                                            rows="3"></textarea>
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
                                        <label
                                            class="form-label fw-bold fs-5">পরিমাণ</label>
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
                                        <label
                                            class="form-label fw-bold fs-5">মোট দাম</label>
                                        <p class="rounded border p-2 fw-bold fs-4" id="total">0</p>
                                        {{-- <input name="total" class="form-control fw-bold fs-5" type="text" value="1950"
                                            aria-label="Disabled input example" readonly> --}}
                                    </div>

                                </div>
                                <div class="col-12">
                                    <button type="submit"
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
@endsection
@push('scripts')
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
@endpush
