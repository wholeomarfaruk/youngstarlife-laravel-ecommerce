@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{asset('css/style.css')}}">
@endpush
@section('content')
    <div class="container">

        <div class="row justify-content-center order-main-box mt-2">
            @if (isset($order) && isset($orderItems) && $orderItems->count() > 0)


            <div class="col-md-8 order-info-box">
                {{-- <div class="order-logo mt-4">
                    <a class="footer-brand text-decoration-none text-success fw-bolder fs-4" href="#">
                        YOUNGSTAR Life</a>
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

                            <p class="order-product-size">Size: {{json_decode($item?->options)?->size}}</p>
                        </div>
                        <p class="order-product-price ms-auto">{{$item->product->discount_price && $item->product->discount_price > 0 ? $item->product->discount_price : $item->product->price}} x {{$item->quantity}} = {{$item->subtotal}}  টাকা</p>
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
                        <p class="delivery-notice">আপনার অর্ডারের জন্য আন্তরিক কৃতজ্ঞতা। ২৪ ঘণ্টার মধ্যে আমাদের টিম আপনার সাথে যোগাযোগ করবে এবং খুব শীঘ্রই আপনার অর্ডারটি প্রসেস করা হবে । YOUNGSTAR Life এর সাথেই থাকুন।</p>
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

@push('scripts')

    <script>
       
        dataLayer = window.dataLayer || [];
        dataLayer.push({
            event: 'purchase',

            ecommerce: {
                value: {{ $order->total }}, // Number, two decimals, required
                currency: 'BDT', // String, required
                transaction_id: '{{ $order->id }}', // String, required, unique identifier of order/transaction
                items: [{
                    item_name: "{{ $orderItems->first()->product->name }}", // String, required
                    item_id: "{{ $orderItems->first()->product->id }}", // String, required
                    price: {{ $orderItems->first()->product->discount_price ?? $orderItems->first()->product->price }}, // Number, two decimals, required
                    quantity: '{{ $orderItems->first()->quantity }}' ?? 1, // Integer, required
                    item_category: "Pants", // String, optional but advised if available
                    item_brand: 'YoungStar Life', // String, optional, might be useful if you sell different brands
                    item_variant: '{{json_decode($item?->options)?->size}}' // String, optional
                }]
            },
            // user_data অবজেক্টে শুধুমাত্র সেই ডেটা রাখুন যা আপনার কাছে উপলব্ধ
            // অথবা, যদি কোনো ইউজার ডেটা না থাকে, তাহলে এই অংশটি বাদ দিন।
            // উদাহরণস্বরূপ, যদি আপনি একটি সেশন আইডি ট্র্যাক করতে পারেন:
            user_data: {
                first_name: "{{ $order->name }}" ?? null, // বা এই লাইনগুলো বাদ দিন
                // last_name: null,
                // email_address: null,
                phone_number: "{{ $order->phone }}" ?? null,
                street: "{{ $order->address }}" ?? null,
                // country: "BD", // IP Address থেকে পাওয়া গেলে
                // city: null,
                // region: null,
                // postal_code: null,
                user_id: sessionStorage.getItem('visitorId') ||
                    null, // উদাহরণ: সেশন স্টোরেজ থেকে visitorId ব্যবহার করা
                // new_customer: 'true' // এটি অনুমান করা কঠিন হবে
            }
        });
    </script>
@endpush
