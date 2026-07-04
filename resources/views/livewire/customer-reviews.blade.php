<div>
    @if ($total > 3)
        <style>
            /* Customer Reviews slider */
            .sec-reviews .reviewsSwiper {
                padding-bottom: 40px;
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
                aspect-ratio: 9 / 16; /* phone screenshot shape */
                object-fit: contain; /* show the FULL screenshot, never crop */
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

        {{-- Inline init so it runs when this (lazy-loaded) component's markup lands,
             not tied to the layout's @stack('scripts') which is already flushed. --}}
        <script>
            (function () {
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
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initReviewsSwiper);
                } else {
                    initReviewsSwiper();
                }
            })();
        </script>
    @endif
</div>
