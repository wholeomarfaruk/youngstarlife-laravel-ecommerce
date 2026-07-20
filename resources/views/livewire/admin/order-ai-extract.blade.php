<div class="main-content-inner">
    <div class="main-content-wrap">
        <div class="d-flex align-items-center flex-wrap justify-content-between gap-3 mb-4">
            <h3 class="mb-0">AI Order Extract</h3>
            <ul class="breadcrumbs d-flex align-items-center flex-wrap justify-content-start gap-2 mb-0">
                <li>
                    <a href="{{ route('admin.index') }}">
                        <div class="text-tiny">Dashboard</div>
                    </a>
                </li>
                <li><i class="icon-chevron-right"></i></li>
                <li>
                    <a href="{{ route('admin.orders') }}">
                        <div class="text-tiny">Orders</div>
                    </a>
                </li>
                <li><i class="icon-chevron-right"></i></li>
                <li>
                    <div class="text-tiny">AI Order Extract</div>
                </li>
            </ul>
        </div>

        @if ($step === 'input')
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-3 p-md-4">
                    <div class="row g-2 align-items-end mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Order Source <span class="text-danger">*</span></label>
                            <select wire:model="source" class="form-select @error('source') is-invalid @enderror">
                                <option value="">Select source...</option>
                                @foreach ($sourceOptions as $opt)
                                    <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                            @error('source')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <label for="ai-image-upload"
                                class="d-flex flex-column align-items-center justify-content-center text-center p-4 border border-2 rounded-4 bg-light"
                                style="border-style:dashed !important; border-color:#c9a7e0 !important; min-height:230px; cursor:pointer;">
                                <div class="mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor"
                                        class="text-secondary" viewBox="0 0 16 16">
                                        <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
                                        <path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-10zm10 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1h10z"/>
                                    </svg>
                                </div>
                                <div class="fw-semibold">Drag and drop image or</div>
                                <div class="fw-bold text-danger mb-2">Upload Images</div>
                                <div class="text-muted small">Supported file types: JPG, PNG, Jpeg</div>
                                <div class="text-muted small">Max file size: 5 MB per image</div>
                                <div class="text-muted small">{{ count($uploadedImages) }}/10 images uploaded</div>
                                <input id="ai-image-upload" type="file" wire:model="uploadedImages" accept="image/*"
                                    multiple class="d-none">
                            </label>
                            @error('uploadedImages')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('uploadedImages.*')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror

                            <div wire:loading wire:target="uploadedImages" class="text-muted small mt-2">
                                Uploading images...
                            </div>

                            @if (!empty($uploadedImages))
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    @foreach ($uploadedImages as $index => $image)
                                        <div class="position-relative">
                                            <img src="{{ $image->temporaryUrl() }}" alt="preview"
                                                class="rounded-3 border" style="width:80px; height:80px; object-fit:cover;">
                                            <button type="button"
                                                class="btn btn-danger btn-sm rounded-circle position-absolute p-0 d-flex align-items-center justify-content-center"
                                                style="width:22px; height:22px; top:-8px; right:-8px; line-height:1;"
                                                wire:click="removeImage({{ $index }})">&times;</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="col-lg-6">
                            <div class="border border-2 rounded-4 p-3 h-100" style="border-color:#7b68ee !important;">
                                <div class="d-flex align-items-start gap-2 mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                                        class="text-secondary flex-shrink-0 mt-1" viewBox="0 0 16 16">
                                        <path d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V15a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V1a1 1 0 0 1 1-1zm5.5 1.5v2a1 1 0 0 0 1 1h2l-3-3z"/>
                                    </svg>
                                    <div class="text-muted small">
                                        <div>Customer name</div>
                                        <div>Customer mobile no.</div>
                                        <div>Delivery address (including city, zone, area)</div>
                                        <div>Amount to Collect</div>
                                    </div>
                                </div>
                                <textarea wire:model="pastedText" rows="6"
                                    class="form-control border-0 @error('pastedText') is-invalid @enderror"
                                    placeholder="If any detail is missing from the image, type it here (chat text, customer name, phone, address, amount, etc.)"></textarea>
                                @error('pastedText')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" class="btn btn-outline-danger px-4 py-2 fw-semibold rounded-3"
                            wire:click="runExtraction" wire:loading.attr="disabled" wire:target="runExtraction">
                            <span wire:loading.remove wire:target="runExtraction">Proceed</span>
                            <span wire:loading wire:target="runExtraction">
                                <span class="spinner-border spinner-border-sm me-1"></span> Extracting...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        @else
            <div class="d-flex align-items-start flex-wrap justify-content-between gap-2 mb-3">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @if ($confidence !== null)
                        <span class="badge {{ $confidence >= 0.7 ? 'bg-success' : ($confidence >= 0.4 ? 'bg-warning text-dark' : 'bg-danger') }}">
                            {{ number_format($confidence * 100, 0) }}% confidence
                        </span>
                    @endif
                    @if (!empty($warnings))
                        <span class="text-muted small">{{ implode(' · ', $warnings) }}</span>
                    @endif
                </div>
                <button type="button" class="btn btn-sm btn-link text-secondary text-decoration-none px-0" wire:click="discard">
                    &larr; Start over
                </button>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center flex-wrap justify-content-between gap-2 mb-3">
                        <h6 class="fw-semibold mb-0">Products</h6>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">{{ count($lines) }} item(s)</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="addLine">+ Add item</button>
                        </div>
                    </div>

                    @if (empty($lines))
                        <div class="text-center text-muted py-4 small">No products extracted yet.</div>
                    @endif

                    @foreach ($lines as $line)
                        <div wire:key="line-{{ $line['key'] }}" class="border rounded-3 p-3 mb-2">
                            <div class="row g-3 align-items-start">
                                <div class="col-3 col-sm-2 col-md-1">
                                    <div wire:ignore wire:key="thumb-wrap-{{ $line['key'] }}">
                                        @php
                                            $thumbSrc = $line['matched_product_id']
                                                ? ($productImagesById[$line['matched_product_id']] ?? null)
                                                : null;
                                        @endphp
                                        <a href="{{ $thumbSrc ?? '#' }}" data-fancybox="product-{{ $line['key'] }}"
                                            class="product-thumb-link d-block {{ $thumbSrc ? '' : 'invisible' }}"
                                            data-line-key="{{ $line['key'] }}">
                                            <img src="{{ $thumbSrc }}" alt="" class="product-thumb-img rounded border"
                                                style="width:48px;height:48px;object-fit:cover;">
                                        </a>
                                    </div>
                                </div>

                                <div class="col-9 col-sm-10 col-md-2">
                                    <div class="fw-semibold small">{{ $line['product_name_raw'] ?: 'New item' }}</div>
                                    @if ($line['color_raw'])
                                        <div class="text-muted small">Color: {{ $line['color_raw'] }}</div>
                                    @endif
                                    @if ($line['match_state'] === 'no_match')
                                        <span class="badge bg-danger mt-1">No match</span>
                                    @elseif ($line['match_state'] === 'ambiguous')
                                        <span class="badge bg-warning text-dark mt-1">Multiple candidates</span>
                                    @else
                                        <span class="badge bg-success mt-1">Matched</span>
                                    @endif
                                </div>

                                <div class="col-12 col-md-5">
                                    <div wire:ignore wire:key="select-wrap-{{ $line['key'] }}">
                                        <select class="form-select form-select-sm product-select2"
                                            data-line-key="{{ $line['key'] }}">
                                            <option value="">-- select product --</option>
                                            @foreach ($allProducts as $product)
                                                <option value="{{ $product['id'] }}"
                                                    data-image="{{ $product['image'] }}"
                                                    {{ (int) $line['matched_product_id'] === (int) $product['id'] ? 'selected' : '' }}>
                                                    {{ $product['name'] }} ({{ $product['price'] }} Tk)
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    @if ($line['match_state'] === 'ambiguous')
                                        <button type="button" class="btn btn-sm btn-link px-0 mt-1 text-decoration-none"
                                            wire:click="askAiToResolve({{ $line['key'] }})">Ask AI to pick</button>
                                    @endif
                                </div>

                                <div class="col-4 col-md-1">
                                    <label class="form-label small text-muted mb-1">Qty</label>
                                    <input type="number" min="1" class="form-control form-control-sm"
                                        wire:key="qty-{{ $line['key'] }}"
                                        wire:model.live="lines.{{ $loop->index }}.quantity"
                                        wire:change="recomputeSubtotal">
                                </div>

                                <div class="col-4 col-md-1">
                                    <label class="form-label small text-muted mb-1">Size</label>
                                    <input type="text" class="form-control form-control-sm"
                                        wire:key="size-{{ $line['key'] }}"
                                        wire:model="lines.{{ $loop->index }}.size">
                                </div>

                                <div class="col-4 col-md-1 text-end">
                                    <label class="form-label small text-muted mb-1 d-block">&nbsp;</label>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                        wire:click="removeLine({{ $line['key'] }})">&times;</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3 p-md-4">
                    <h6 class="fw-semibold mb-3">Customer &amp; Order</h6>
                    @if ($existingCustomer)
                        <div class="alert alert-info py-2 small mb-3">
                            Existing customer: <strong>{{ $existingCustomer['name'] }}</strong>
                            ({{ $existingCustomer['phone'] }}) &mdash; {{ $existingCustomer['order_count'] }} previous order(s)
                            @if ($existingCustomer['is_blocked'])
                                <span class="text-danger fw-bold">BLACKLISTED</span>
                            @endif
                        </div>
                    @endif
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted mb-1">Name</label>
                            <input type="text" wire:model="customer.name" class="form-control @error('customer.name') is-invalid @enderror">
                            @error('customer.name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted mb-1">Phone</label>
                            <input type="text" wire:model="customer.phone" class="form-control @error('customer.phone') is-invalid @enderror">
                            @error('customer.phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small text-muted mb-1">Address</label>
                            <input type="text" wire:model="customer.address" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Delivery charge</label>
                            <select wire:model.live="deliveryAreaId" class="form-select">
                                <option value="">-- select area --</option>
                                @foreach ($deliveryAreas as $area)
                                    <option value="{{ $area->id }}">{{ $area->name }} ({{ $area->charge }} Tk)</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Amount to Collect</label>
                            <input type="number" min="0" step="0.01" wire:model.live="amountToCollect" class="form-control"
                                placeholder="{{ number_format($total, 2) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Discount</label>
                            <input type="number" min="0" wire:model.live="discount" class="form-control">
                        </div>
                    </div>

                    @php
                        $collectDiffers = $amountToCollect !== null && round($amountToCollect, 2) !== round($total, 2);
                        $baseTotal = $subtotal + $deliveryCharge;
                    @endphp

                    <div class="d-flex flex-column gap-1 border-top pt-3 mb-3 ms-md-auto w-100" style="max-width:340px;">
                        <div class="d-flex justify-content-between text-muted small">
                            <span>Sub Total</span><span>{{ number_format($subtotal, 2) }} Tk</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small">
                            <span>Delivery charge</span><span>{{ number_format($deliveryCharge, 2) }} Tk</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small">
                            <span>Discount</span><span>-{{ number_format($discount, 2) }} Tk</span>
                        </div>

                        @if ($collectDiffers)
                            <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="totalSource" id="totalSourceCalculated"
                                        wire:click="useCalculatedTotal" {{ $totalSource === 'calculated' ? 'checked' : '' }}>
                                    <label class="form-check-label small fw-semibold" for="totalSourceCalculated">
                                        Total (calculated)
                                    </label>
                                </div>
                                <span class="fw-semibold">{{ number_format($baseTotal, 2) }} Tk</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="totalSource" id="totalSourceCollect"
                                        wire:click="useAmountToCollect" {{ $totalSource === 'collect' ? 'checked' : '' }}>
                                    <label class="form-check-label small fw-semibold" for="totalSourceCollect">
                                        Amount to Collect
                                    </label>
                                </div>
                                <span class="fw-semibold">{{ number_format($amountToCollect, 2) }} Tk</span>
                            </div>
                            <div class="form-text mb-1">
                                Choosing Amount to Collect adds the difference as Discount.
                            </div>
                        @endif

                        <div class="d-flex justify-content-between fw-semibold border-top pt-1 mt-1">
                            <span>Total</span><span>{{ number_format($total, 2) }} Tk</span>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-sm-end">
                        <button type="button" class="btn btn-outline-secondary" wire:click="discard">Discard</button>
                        <button type="button" class="btn btn-danger px-4" wire:click="confirm" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="confirm">Confirm &amp; Create Order</span>
                            <span wire:loading wire:target="confirm">
                                <span class="spinner-border spinner-border-sm me-1"></span> Creating...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="{{ asset('frontend/library/fancybox/fancybox.css') }}">
    <style>
        .select2-result-product { display: flex; align-items: center; gap: 8px; }
        .select2-result-product img { width: 28px; height: 28px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script src="{{ asset('frontend/library/fancybox/fancybox.umd.js') }}"></script>
    <script>
        window.addEventListener('extraction-failed', event => {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: event.detail.message,
            });
        });

        function formatProductOption(option) {
            const image = $(option.element).data('image');

            if (!option.id || !image) {
                return option.text;
            }

            return $(
                '<span class="select2-result-product">' +
                    '<img src="' + image + '" alt="">' +
                    '<span>' + option.text + '</span>' +
                '</span>'
            );
        }

        function updateProductThumb(lineKey, image) {
            const $link = $('.product-thumb-link[data-line-key="' + lineKey + '"]');

            if (!image) {
                $link.addClass('invisible').attr('href', '#');
                return;
            }

            $link.removeClass('invisible').attr('href', image);
            $link.find('img').attr('src', image);
        }

        function initProductSelect2() {
            $('.product-select2').each(function () {
                const $select = $(this);
                if ($select.data('select2')) {
                    return;
                }

                $select.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: '-- select product --',
                    allowClear: true,
                    templateResult: formatProductOption,
                    templateSelection: formatProductOption,
                });

                $select.on('change', function () {
                    const lineKey = $select.data('line-key');
                    const image = $select.find('option:selected').data('image');
                    updateProductThumb(lineKey, image);
                    @this.call('selectProductForLine', lineKey, $select.val());
                });
            });
        }

        document.addEventListener('livewire:navigated', initProductSelect2);
        document.addEventListener('livewire:init', () => {
            initProductSelect2();

            const rescanIfNeeded = ({ el }) => {
                if (el.querySelector && el.querySelector('.product-select2')) {
                    initProductSelect2();
                }
            };

            Livewire.hook('morph.added', rescanIfNeeded);
            Livewire.hook('morph.updated', rescanIfNeeded);

            Fancybox.bind('[data-fancybox^="product-"]', {});
        });

        $(document).ready(initProductSelect2);
    </script>
@endpush
