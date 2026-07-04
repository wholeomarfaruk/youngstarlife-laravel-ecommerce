@extends('layouts.app')

@section('content')
    <style>
        .reviews-page .sec-title {
            font-size: 20px;
            font-weight: 600;
        }

        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
        }

        .reviews-grid .review-card {
            display: block;
            text-decoration: none;
            box-shadow: rgba(149, 157, 165, 0.2) 0px 8px 24px;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            transition: all 0.2s ease-in-out;
        }

        .reviews-grid .review-card:hover {
            box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
            transform: scale(1.02);
        }

        .reviews-grid .review-img-box {
            overflow: hidden;
            background: #f4f4f5;
        }

        .reviews-grid .review-img-box img {
            width: 100%;
            aspect-ratio: 9 / 16; /* phone screenshot shape */
            object-fit: contain; /* show the FULL screenshot, never crop */
            display: block;
            transition: all 0.2s ease-in-out;
        }

        .reviews-grid .review-card:hover .review-img-box img {
            transform: scale(1.08);
        }

        .reviews-grid .review-title {
            font-size: 15px;
            font-weight: 600;
            color: rgb(37, 37, 37);
            padding: 8px 10px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width:992px) {
            .reviews-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width:576px) {
            .reviews-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>

    <section class="reviews-page my-3">
        <div class="container">
            <div class="sec-header">
                <div class="d-flex justify-content-between">
                    <div class="">
                        <h2 class="sec-title text-primary-color">Customer Reviews - কাস্টমার রিভিউ</h2>
                    </div>
                    <div class="text-right">
                        <a href="{{ route('home') }}" class="sec-title text-primary-color">Back to Home</a>
                    </div>
                </div>
                <hr class="divider mt-0 text-primary-color bg-primary-color" style="height: 2px;">
            </div>

            @if ($reviews->count() > 0)
                <div class="reviews-grid">
                    @foreach ($reviews as $review)
                        <a class="review-card" data-fancybox="reviews-all"
                            href="{{ asset('storage/images/slides/' . $review->image) }}"
                            @if ($review->title) data-caption="{{ $review->title }}" @endif>
                            <div class="review-img-box">
                                <img src="{{ asset('storage/images/slides/' . $review->image) }}"
                                    alt="{{ $review->title ?? 'Customer review' }}" loading="lazy">
                            </div>
                            @if ($review->title)
                                <div class="review-title">{{ $review->title }}</div>
                            @endif
                        </a>
                    @endforeach
                </div>
            @else
                <p class="text-center py-5">কোনো রিভিউ পাওয়া যায়নি।</p>
            @endif
        </div>
    </section>
@endsection
