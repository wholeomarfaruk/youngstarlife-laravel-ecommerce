@extends('layouts.app')

@section('content')
    <style>
        .sec-style-1 {}

        .sec-style-1 .sec-header {}

        .sec-style-1 .sec-header .sec-title {
            font-size: 20px;
            font-weight: 600;

        }

        .sec-style-1 .sec-body {}

        .sec-style-1 .sec-body .sec-grid-box {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }


        .sec-style-1 .sec-body .sec-grid-box .sec-grid-item {}

        .p-card-1 {
            display: flex;
            flex-direction: column;
            gap: 10px;
            box-shadow: rgba(149, 157, 165, 0.2) 0px 8px 24px;
            border-radius: 8px;
            padding: 10px;
            transition: all 0.2s ease-in-out;

        }

        .p-card-1:hover {
            box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
            transform: scale(1.02);
            transition: all 0.2s ease-in-out;
        }

        .p-card-1 a {
            text-decoration: none;
        }

        .p-card-1 .p-img-box {
            overflow: hidden;
            border-radius: 8px;


        }

        .p-card-1 .p-img-box img {
            width: 100%;
            height: 350px;
            object-fit: cover;
            border-radius: 8px;
            transition: all 0.2s ease-in-out;

        }

        .p-card-1:hover .p-img-box img {

            transform: scale(1.1);
            transition: all 0.2s ease-in-out;
        }

        .p-card-1 .p-info {}

        .p-card-1 .p-info .p-title {
            font-size: 18px;
            font-weight: 600;
            color: rgb(37, 37, 37);
        }

        .p-card-1 .p-info .prices {
            display: flex;
            gap: 10px;
            font-weight: 700;
            align-items: end;
        }

        .p-card-1 .p-info .prices .old-price {
            font-size: 16px;
            color: #8f8f8f;
        }

        .p-card-1 .p-info .prices .price {
            font-size: 18px;
        }

        .p-card-1 .p-info .p-description {
            font-size: 18px;
            color: rgb(37, 37, 37);
            margin-bottom: 0;
            font-weight: 600;
        }

        .p-card-1 .p-info .p-description:hover {
            /* text-decoration: underline; */
            color: var(--text-primary-color);
        }

        .p-card-1 .p-btn-group {}

        .p-card-1 .p-btn-group .btn {}

        @media (max-width:992px) {
            .sec-style-1 .sec-body .sec-grid-box {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .p-card-1 .p-img-box img {

                height: 250px;

            }
        }
    </style>

    <section class="sec-style-1 my-3">
        <div class="container">


            <div class="sec-header">
                <h2 class="sec-title text-primary-color">Latest Products - নতুন পণ্য</h2>
                <hr class="divider mt-0 text-primary-color bg-primary-color " style="height: 2px;">
            </div>
            <div class="sec-body">
                <div class="sec-grid-box">
                    @foreach ($products as $product)
                        <div class="sec-grid-item p-card-1">

                            <div class="p-img-box">
                                <a href="{{ route('product.show', $product->slug) }}">
                                    <img src="{{ asset('storage/images/products/' . $product->image) }}" alt="">
                                </a>
                            </div>
                            <div class="p-info">
                                <div class="prices">
                                    @if ($product->discount_price > 0)
                                        <del class="old-price">৳ {{ $product->price }}</del>
                                        <span class="price">৳ {{ $product->discount_price }}</span>
                                    @else

                                        <span class="old-price">Price : </span> <span class="price"> ৳ {{ $product->price }}</span>
                                    @endif

                                </div>
                                <a href="{{ route('product.show', $product->slug) }}">

                                    <h1 class="p-title">{{ $product->name }}</h1>
                                </a>
                                <a href="#">
                                    <p class="p-description">
                                        বিস্তারিত দেখুন
                                    </p>
                                </a>
                            </div>
                            <div class="p-btn-group">
                                <a class="btn btn-primary w-100 d-block" href="{{ route('product.show', $product->slug) }}">Buy Now</a>
                            </div>


                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endsection
