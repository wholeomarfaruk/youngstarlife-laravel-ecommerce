@extends('layouts.app')
@section('title', $product->meta_title ?: $product->name)
@section('meta_description', $product->meta_description ?: \Illuminate\Support\Str::limit(strip_tags((string) ($product->short_description ?: $product->description)), 160))
@section('og_image', $product->image_url)

@push('head-scripts')
    {{-- the main image is the largest thing on the page: fetch it first --}}
    <link rel="preload" as="image" href="{{ $product->image_url }}" fetchpriority="high">
    <link rel="preload" as="script" href="{{ asset('frontend/js/product-page.js') }}?v={{ @filemtime(public_path('frontend/js/product-page.js')) }}">
@endpush

@push('styles')
    @include('partials.storefront.product-page-css')
@endpush

@section('content')
    @php($pp = app(\App\Services\Storefront\ProductPage::class)->data($product))
    <div class="pp">
        <nav class="pp-crumb" aria-label="breadcrumb"><a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('shop') }}">Product</a><span>/</span><span class="pp-crumb-now">{{ $product->name }}</span></nav>

        <div class="pp-grid">
            {{-- gallery --}}
            <div class="pp-gallery" x-data="ysGallery(@js($pp['images']))">
                <div class="pp-main" @touchstart.passive="down($event)" @touchend.passive="up($event)">
                    <img src="{{ $pp['images'][0] ?? $product->image_url }}" :src="images[i]" alt="{{ $product->name }}" width="800" height="1000" fetchpriority="high" decoding="async" @click="zoom()">
                    @if ($pp['old'] > $pp['price'])<span class="pp-badge-off">-{{ (int) round((1 - $pp['price'] / max(1, $pp['old'])) * 100) }}%</span>@endif
                    @if ($pp['kind'] === 'combo')<span class="pp-badge-combo">{{ \App\Services\Storefront\ProductPage::bn(collect($pp['combo'])->sum('qty')) }} পিস কম্বো</span>@endif
                    @if (count($pp['images']) > 1)
                        <button type="button" class="pp-nav pp-prev" @click="go(i - 1)" aria-label="আগের ছবি">‹</button>
                        <button type="button" class="pp-nav pp-next" @click="go(i + 1)" aria-label="পরের ছবি">›</button>
                    @endif
                </div>
                @if (count($pp['images']) > 1)
                    <div class="pp-thumbs">
                        @foreach ($pp['thumbs'] as $k => $t)
                            <button type="button" :class="i === {{ $k }} && 'is-on'" @click="i = {{ $k }}" aria-label="ছবি {{ $k + 1 }}"><img src="{{ $t }}" alt="" width="120" height="120" loading="{{ $k < 4 ? 'eager' : 'lazy' }}" decoding="async"></button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- info, variation, order form (Livewire + Alpine) --}}
            <livewire:storefront.product-buy :product="$product" />
        </div>
    </div>

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
                                        <img src="{{ $pitem->image_url }}"
                                            alt="{{ $pitem->name }}" loading="lazy" decoding="async">
                                    </a>
                                </div>
                                <div class="p-info">
                                    <div class="prices">
                                        @if ($pitem->hasDiscount())
                                            <del class="old-price">৳ {{ $pitem->price }}</del>
                                            <span class="price">৳ {{ $pitem->sale_price }}</span>
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
@endsection

@push('scripts')
    <script src="{{ asset('frontend/js/product-page.js') }}?v={{ @filemtime(public_path('frontend/js/product-page.js')) }}"></script>
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
