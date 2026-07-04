<div>
    <style>
        .sec-reviews-skeleton .skel {
            background: linear-gradient(90deg, #eeeeee 25%, #f5f5f5 37%, #eeeeee 63%);
            background-size: 400% 100%;
            animation: skelShimmer 1.4s ease infinite;
            border-radius: 8px;
        }

        @keyframes skelShimmer {
            0% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0 50%;
            }
        }

        .sec-reviews-skeleton .skel-title {
            height: 24px;
            width: 260px;
            max-width: 70%;
        }

        .sec-reviews-skeleton .skel-link {
            height: 20px;
            width: 70px;
        }

        .sec-reviews-skeleton .skel-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .sec-reviews-skeleton .skel-card {
            aspect-ratio: 1 / 1;
            width: 100%;
        }

        @media (min-width:768px) {
            .sec-reviews-skeleton .skel-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width:992px) {
            .sec-reviews-skeleton .skel-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
    </style>

    <section class="sec-style-1 sec-reviews-skeleton my-3">
        <div class="container">
            <div class="sec-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="skel skel-title"></div>
                    <div class="skel skel-link"></div>
                </div>
                <hr class="divider mt-0 text-primary-color bg-primary-color" style="height: 2px;">
            </div>
            <div class="sec-body">
                <div class="skel-grid">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="skel skel-card"></div>
                    @endfor
                </div>
            </div>
        </div>
    </section>
</div>
