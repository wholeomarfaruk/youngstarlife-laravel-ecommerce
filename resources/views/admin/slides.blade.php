@extends('layouts.admin')

@push('styles')
    <link rel="stylesheet" href="{{ asset('frontend/library/fancybox/fancybox.css') }}">
    <style>
        .reviews-toolbar {
            gap: 16px;
        }

        .reviews-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 40px;
            background: rgba(34, 197, 94, 0.12);
            color: #16a34a;
            font-weight: 600;
            font-size: 13px;
        }

        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 18px;
            margin-top: 8px;
        }

        .review-tile {
            position: relative;
            border-radius: 14px;
            overflow: hidden;
            background: #f5f6fa;
            box-shadow: 0 2px 8px rgba(16, 24, 40, 0.06);
            border: 1px solid #eef0f4;
            transition: box-shadow .2s ease, transform .2s ease;
        }

        .review-tile:hover {
            box-shadow: 0 10px 26px rgba(16, 24, 40, 0.14);
            transform: translateY(-3px);
        }

        .review-tile .tile-img {
            display: block;
            width: 100%;
            aspect-ratio: 9 / 16; /* phone screenshot shape */
            object-fit: contain; /* show the FULL screenshot, never crop */
            background: #f4f4f5;
            cursor: zoom-in;
            transition: transform .3s ease;
        }

        .review-tile:hover .tile-img {
            transform: scale(1.05);
        }

        .review-tile .tile-id {
            position: absolute;
            top: 10px;
            left: 44px;
            z-index: 2;
            background: rgba(17, 24, 39, 0.72);
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 30px;
            backdrop-filter: blur(2px);
        }

        .review-tile .tile-caption {
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .review-tile .tile-caption.is-empty {
            color: #9ca3af;
            font-weight: 500;
            font-style: italic;
        }

        .review-tile .tile-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 5;
            display: flex;
            gap: 8px;
            opacity: 0;
            transform: translateY(-4px);
            transition: opacity .2s ease, transform .2s ease;
        }

        /* keep the fancybox image link below the action buttons */
        .review-tile > a[data-fancybox] {
            position: relative;
            z-index: 1;
            display: block;
        }

        .review-tile:hover .tile-actions {
            opacity: 1;
            transform: translateY(0);
        }

        .review-tile .tile-actions .act-btn {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: #fff;
            color: #374151;
            box-shadow: 0 2px 6px rgba(16, 24, 40, 0.2);
            cursor: pointer;
            border: none;
            transition: background .15s ease, color .15s ease;
        }

        .review-tile .tile-actions .act-btn:hover {
            background: #111827;
            color: #fff;
        }

        .review-tile .tile-actions .act-btn.act-delete:hover {
            background: #dc2626;
            color: #fff;
        }

        .review-tile .tile-actions form {
            margin: 0;
            display: inline-flex;
        }

        .reviews-empty {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .reviews-empty .icon-image {
            font-size: 40px;
            color: #cbd5e1;
            display: block;
            margin-bottom: 12px;
        }

        /* bulk select controls */
        .select-all-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            margin: 0;
        }

        .select-all-label input {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .bulk-delete-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 8px;
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #dc2626;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s ease;
        }

        .bulk-delete-btn:hover:not(:disabled) {
            background: #dc2626;
            color: #fff;
            border-color: #dc2626;
        }

        .bulk-delete-btn:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .review-tile .tile-select {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 6;
            margin: 0;
            display: inline-flex;
            width: 26px;
            height: 26px;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.92);
            border-radius: 7px;
            box-shadow: 0 2px 6px rgba(16, 24, 40, 0.2);
            cursor: pointer;
        }

        .review-tile .tile-select input {
            width: 16px;
            height: 16px;
            cursor: pointer;
            margin: 0;
        }

        .review-tile.is-selected {
            outline: 2px solid #dc2626;
            outline-offset: -2px;
        }
    </style>
@endpush

@section('content')
    <div class="main-content-inner">
        <div class="main-content-wrap">
            <div class="flex items-center flex-wrap justify-between gap20 mb-27">
                <h3>Customer Reviews</h3>
                <ul class="breadcrumbs flex items-center flex-wrap justify-start gap10">
                    <li>
                        <a href="{{ route('admin.index') }}">
                            <div class="text-tiny">Dashboard</div>
                        </a>
                    </li>
                    <li>
                        <i class="icon-chevron-right"></i>
                    </li>
                    <li>
                        <div class="text-tiny">Customer Reviews</div>
                    </li>
                </ul>
            </div>

            <div class="wg-box">
                @if (Session::has('status'))
                    <div class="alert alert-success" role="alert">
                        {{ Session::get('status') }}
                    </div>
                @endif

                <div class="flex items-center justify-between flex-wrap reviews-toolbar">
                    <div class="flex items-center gap10 flex-wrap">
                        <span class="reviews-count-badge">
                            <i class="icon-image"></i>
                            {{ $slides->total() }} {{ Str::plural('Review', $slides->total()) }}
                        </span>
                        @if ($slides->count() > 0)
                            <label class="select-all-label" id="selectAllWrap">
                                <input type="checkbox" id="selectAll"> Select all
                            </label>
                            <button type="button" class="bulk-delete-btn" id="bulkDeleteBtn" disabled>
                                <i class="icon-trash-2"></i> Delete selected (<span id="selectedCount">0</span>)
                            </button>
                        @endif
                    </div>
                    <a class="tf-button style-1 w208" href="{{ route('admin.slides.add') }}">
                        <i class="icon-plus"></i>Add new
                    </a>
                </div>

                @if ($slides->count() > 0)
                    <form id="bulkDeleteForm" action="{{ route('admin.slides.bulk-delete') }}" method="POST">
                        @csrf
                        @method('DELETE')
                    </form>

                    <div class="reviews-grid">
                        @foreach ($slides as $slide)
                            <div class="review-tile">
                                <span class="tile-id">#{{ $slide->id }}</span>

                                <label class="tile-select" title="Select">
                                    <input type="checkbox" class="review-checkbox" value="{{ $slide->id }}">
                                </label>

                                <div class="tile-actions">
                                    <a href="{{ route('admin.slides.edit', $slide->id) }}" class="act-btn" title="Edit">
                                        <i class="icon-edit-3"></i>
                                    </a>
                                    <form action="{{ route('admin.slides.delete', ['id' => $slide->id]) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="act-btn act-delete delete"
                                            data-name="{{ $slide->title ?? 'this review' }}" title="Delete">
                                            <i class="icon-trash-2"></i>
                                        </button>
                                    </form>
                                </div>

                                <a href="{{ asset('storage/images/slides/' . $slide->image) }}" data-fancybox="reviews"
                                    @if ($slide->title) data-caption="{{ $slide->title }}" @endif>
                                    <img src="{{ asset('storage/images/slides/' . $slide->image) }}"
                                        alt="{{ $slide->title ?? 'Customer review' }}" class="tile-img" loading="lazy">
                                </a>

                                <div class="tile-caption {{ $slide->title ? '' : 'is-empty' }}">
                                    {{ $slide->title ?? 'No caption' }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="divider"></div>
                    <div class="flex items-center justify-between flex-wrap gap10 wgp-pagination">
                        {{ $slides->links('pagination::bootstrap-5') }}
                    </div>
                @else
                    <div class="reviews-empty">
                        <i class="icon-image"></i>
                        <div class="body-title mb-2">No customer reviews yet</div>
                        <div class="text-tiny mb-3">Upload review images to show them on the home page slider.</div>
                        <a class="tf-button style-1" href="{{ route('admin.slides.add') }}">
                            <i class="icon-plus"></i>Add your first review
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('frontend/library/fancybox/fancybox.umd.js') }}"></script>
    <script>
        Fancybox.bind('[data-fancybox="reviews"]', {
            Thumbs: { autoStart: true },
        });

        // Stop clicks on the edit/delete controls from bubbling to Fancybox's
        // delegated document listener (which would otherwise open the lightbox).
        document.querySelectorAll('.tile-actions').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        });

        $('.delete').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var form = $(this).closest('form');
            var name = $(this).data('name');
            Swal.fire({
                title: 'Delete this review?',
                text: name + ' will be permanently removed.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        /* ---- Bulk select & delete ---- */
        (function () {
            var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.review-checkbox'));
            var selectAll = document.getElementById('selectAll');
            var bulkBtn = document.getElementById('bulkDeleteBtn');
            var countEl = document.getElementById('selectedCount');
            var bulkForm = document.getElementById('bulkDeleteForm');
            if (!checkboxes.length || !bulkBtn || !bulkForm) return;

            function selectedIds() {
                return checkboxes.filter(function (c) { return c.checked; })
                    .map(function (c) { return c.value; });
            }

            function refresh() {
                var ids = selectedIds();
                countEl.textContent = ids.length;
                bulkBtn.disabled = ids.length === 0;
                if (selectAll) {
                    selectAll.checked = ids.length === checkboxes.length;
                    selectAll.indeterminate = ids.length > 0 && ids.length < checkboxes.length;
                }
            }

            checkboxes.forEach(function (c) {
                // keep the checkbox from triggering the Fancybox image link
                c.addEventListener('click', function (e) { e.stopPropagation(); });
                c.addEventListener('change', function () {
                    c.closest('.review-tile').classList.toggle('is-selected', c.checked);
                    refresh();
                });
            });

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    checkboxes.forEach(function (c) {
                        c.checked = selectAll.checked;
                        c.closest('.review-tile').classList.toggle('is-selected', c.checked);
                    });
                    refresh();
                });
            }

            bulkBtn.addEventListener('click', function () {
                var ids = selectedIds();
                if (ids.length === 0) return;

                Swal.fire({
                    title: 'Delete ' + ids.length + ' review(s)?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete them!'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    // inject the selected ids into the bulk form and submit
                    ids.forEach(function (id) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'ids[]';
                        input.value = id;
                        bulkForm.appendChild(input);
                    });
                    bulkForm.submit();
                });
            });

            refresh();
        })();
    </script>
@endpush
