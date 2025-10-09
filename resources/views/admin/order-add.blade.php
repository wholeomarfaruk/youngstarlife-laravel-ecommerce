@extends('layouts.admin')

@section('content')
    <!-- content area start -->
    <div class="main-content-inner">
        <!-- main-content-wrap -->
        <div class="main-content-wrap">
            <div class="flex items-center flex-wrap justify-between gap20 mb-27">
                <h3>Add New Order</h3>
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
                        <a href="{{ route('admin.orders') }}">
                            <div class="text-tiny">Orders</div>
                        </a>
                    </li>
                    <li>
                        <i class="icon-chevron-right"></i>
                    </li>
                    <li>
                        <div class="text-tiny">Add Order</div>
                    </li>
                </ul>
            </div>
            <!-- form-add-product -->
            <form class="tf-section-1 form-add-product needs-validation" method="POST" enctype="multipart/form-data"
                action="{{ route('admin.orders.store') }}" novalidate>
                @csrf
                <div class="wg-box">
                    <fieldset class="name">
                        <div class="body-title mb-10">Customer Name <span class="tf-color-1">*</span>
                        </div>
                        <input class="mb-10 @error('name') is-invalid @enderror" type="text" placeholder="Enter name"
                            name="name" tabindex="0" aria-required="true" value="{{ old('phone') }}" required
                            autocomplete="off" autofocus>
                        <div class="text-tiny">Do not exceed 100 characters when entering the customer name</div>
                        @error('name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>
                    <fieldset class="name">
                        <div class="body-title mb-10">Phone <span class="tf-color-1">*</span>
                        </div>
                        <input class="mb-10 @error('phone') is-invalid @enderror" type="number" required
                            placeholder="Enter phone number" name="phone" tabindex="0" aria-required="true"
                            value="{{ old('phone') }}" autocomplete="phone" autofocus>
                        <div class="text-tiny">Do not exceed 11 when entering the phone number</div>
                        @error('phone')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>



                    <fieldset class="name">
                        <div class="body-title mb-10">Delivery Address <span class="tf-color-1"></span></div>
                        <textarea class="mb-10 @error('address') is-invalid @enderror" name="address" tabindex="0" aria-required="true">{{ old('address') }}</textarea>
                        @error('address')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>
                    <fieldset class="name">
                        <div class="body-title mb-10">Note <span class="tf-color-1"></span></div>
                        <textarea class="mb-10 @error('note') is-invalid @enderror" name="note" tabindex="0" aria-required="true">{{ old('note') }}</textarea>
                        @error('short_description')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>


                            <!-- Product Selector -->
                            <fieldset class="mb-3">
                                <label for="products" class="form-label fw-semibold">Add Products</label>
                                <select name="products[]" id="products"
                                    class="form-control selectpicker @error('products') is-invalid @enderror" required
                                    multiple data-live-search="true" title="Choose products...">

                                    @foreach ($products as $product)
                                      
                                        <option value="{{ $product->id }}" data-id="{{ $product->id }}"
                                            {{ $product->stock_status == 'out_of_stock' ? 'disabled' : '' }}
                                            >
                                            {{ $product->name }} -
                                            {{ $product->stock_status == 'in_stock' ? 'In Stock' : 'Out of Stock' }} -
                                            {{ $product->discount_price ?? $product->price }} Tk
                                        </option>
                                    @endforeach
                                </select>
                            </fieldset>

                            <!-- Dynamic Product List -->
                            <div id="editForm" class="mt-3">

                            </div>
                            <!-- delivery charge -->
                            <fieldset class="mt-3">
                                <label for="delivery_charge" class="form-label fw-semibold">Delivery Charge</label>
                                <input type="number" id="delivery_charge" name="delivery_charge" class="form-control"
                                    placeholder="Enter delivery charge" value=""
                                    value="" min="0">
                            </fieldset>
                            <!-- Discount -->
                            <fieldset class="mt-3">
                                <label for="discount" class="form-label fw-semibold">Discount Amount</label>
                                <input type="number" id="discount" name="discount" class="form-control"
                                    placeholder="Enter discount amount if any" value=""
                                    min="0">
                            </fieldset>



                    <!-- Footer -->

                    <table class="table table-bordered mb-3 text-center align-middle">
                        <tr>
                            <th width="40%">Sub Total:</th>
                            <td><span id="subTotal">0</span> Tk</td>
                        </tr>
                        <tr>
                            <th>Discount:</th>
                            <td><span id="discount_price">0</span> Tk</td>
                        </tr>
                        <tr class="table-info">
                            <th>Total:</th>
                            <td><strong><span id="total">0</span> Tk</strong></td>
                        </tr>
                    </table>



                    <div class="cols gap10">
                        <button class="tf-button w-full" type="submit">Add New Order</button>
                    </div>

                </div>

            </form>
            <!-- /form-add-product -->
        </div>
        <!-- /main-content-wrap -->
    </div>
    <!-- content area end -->
@endsection
@push('scripts')
    @if (Session::has('error'))
        <script>
            Swal.fire({
                icon: "error",
                title: "Oops...",
                text: "{{ Session::get('error') }}",
            });
        </script>
    @endif

    <script>
        $(function() {
            $('#myFile').on('change', function() {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#imgpreview').show();
                    $('#imgpreview img').attr('src', e.target.result);
                }
                reader.readAsDataURL(this.files[0]);
            });
            $('#gFile').on('change', function() {

                var photos = this.files;
                $.each(photos, function(i, photo) {
                    $('#galPreview').prepend('<div class="item"><img src="' + URL.createObjectURL(
                        photo) + '"></div>');

                })

            });
        })

        function stringtoSlug(str) {
            str = str.replace(/^\s+|\s+$/g, ''); // trim leading/trailing spaces
            str = str.toLowerCase();
            str = str.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
                .replace(/\s+/g, '-') // collapse whitespace and replace by -
                .replace(/-+/g, '-'); // collapse dashes



            $('#slug_input').val(str);

        }
    </script>
    <script src="https://cdn.tiny.cloud/1/6fc0o57nwmnuyujo3x2t2m7qttqr09s74djxb47lnzygcixp/tinymce/8/tinymce.min.js"
        referrerpolicy="origin" crossorigin="anonymous"></script>

    <script>
        tinymce.init({
            selector: '#editor',
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',


        });
    </script>

    <script>
        let sizeId = 0;

        function addSize(value = "") {
            sizeId++;
            const container = document.getElementById("sizes-container");

            const div = document.createElement("div");

            div.classList.add('size-row', 'mb-5', 'd-flex', 'justify-content-between', 'align-items-center');
            div.setAttribute("data-id", sizeId);

            div.innerHTML = `
        <input type="text" name="sizes[]" value="${value}" placeholder="Enter size">

        <button type="button" onclick="deleteSize(${sizeId})">   <i class="icon-trash-2 text-danger"></i></button>
      `;

            container.appendChild(div);
        }

        function deleteSize(id) {
            const row = document.querySelector(`.size-row[data-id='${id}']`);
            if (row) row.remove();
        }

        function editSize(id) {
            const row = document.querySelector(`.size-row[data-id='${id}'] input`);
            if (row) {
                const newValue = prompt("Edit size:", row.value);
                if (newValue !== null) row.value = newValue;
            }
        }
    </script>
            <!-- ====================== JS Section ====================== -->
        <script>
            var allProducts = @json($products);

            const productSelect = document.getElementById('products');
            const editForm = document.getElementById('editForm');
            const discountInput = document.getElementById('discount');
            const subTotalEl = document.getElementById('subTotal');
            const discountEl = document.getElementById('discount_price');
            const fee = document.getElementById('delivery_charge');
            const totalEl = document.getElementById('total');

            // ===== Initialize when modal is shown =====
            document.getElementById('exampleModal').addEventListener('shown.bs.modal', function() {
                attachQuantityListeners();
                calculateTotal();
            });

            // ===== Product Selector Change =====
            productSelect.addEventListener('change', function() {
                const selectedIds = Array.from(this.selectedOptions).map(opt => opt.value);
                let addedIds = Array.from(editForm.querySelectorAll('input.edit_product_id')).map(input => input.value);
                let filteredIds = selectedIds.filter(id => !addedIds.includes(id));
                const selectedProducts = allProducts.filter(p => filteredIds.includes(String(p.id)));
                console.log(selectedIds);
                console.log(addedIds);
                console.log(filteredIds);
                console.log(selectedProducts);
                console.log(allProducts);
                selectedProducts.forEach(product => {
                    const price = product.discount_price ?? product.price;
                    const formHtml = `
            <div id="product-item-${product.id}" class="product-item border rounded bg-light p-3 mb-3">
                <div class="row align-items-center text-center text-md-start">
                    <!-- 1️⃣ Image -->
                    <div class="col-12 col-md-2 mb-2 mb-md-0">
                        <img src="/storage/images/products/${product.image}" alt="${product.name}"
                             class="img-fluid rounded" style="max-height: 80px; object-fit: cover;">
                    </div>

                    <!-- 2️⃣ Title & Price -->
                    <div class="col-12 col-md-3 mb-2 mb-md-0">
                        <h6 class="mb-1">${product.name}</h6>
                        <p class="mb-0">Price:
                            <strong class="product-price" data-price="${price}">${price} Tk</strong>
                        </p>
                    </div>

                    <!-- 3️⃣ Quantity -->
                    <div class="col-6 col-md-2 mb-2 mb-md-0">
                        <label class="form-label small">Quantity</label>
                        <input type="text" hidden name="order_items[${product.id}][id]" value="">

                        <input type="number" name="order_items[${product.id}][quantity]" value="1" min="1"
                               class="form-control quantity-input" data-id="${product.id}">
                    </div>

                    <!-- 4️⃣ Options -->
                    <div class="col-6 col-md-3 mb-2 mb-md-0">
                        <label class="form-label small">Size</label>
                        <input type="text" name="order_items[${product.id}][size]" class="form-control"
                               placeholder="Enter size" value="">
                    </div>

                    <!-- 5️⃣ Actions -->
                    <div class="col-12 col-md-2">
                        <button type="button" class="btn btn-danger btn-sm w-100 mt-2 mt-md-0"
                                onclick="removeProduct(${product.id})">Remove</button>
                    </div>
                </div>
            </div>`;


                    $(editForm).append(formHtml);


                });

                // editForm.appendChild = formHtml;

                attachQuantityListeners();
                calculateTotal();
            });

            // ===== Attach quantity input listeners =====
            function attachQuantityListeners() {
                document.querySelectorAll('.quantity-input').forEach(input => {
                    input.removeEventListener('input', calculateTotal); // avoid duplicates
                    input.addEventListener('input', calculateTotal);
                });
            }

            // ===== Remove product =====
            function removeProduct(productId) {
                const productItem = document.getElementById(`product-item-${productId}`);
                if (productItem) productItem.remove();

                const option = document.querySelector(`#products option[value="${productId}"]`);
                if (option) option.selected = false;

                if ($('.selectpicker').length) $('.selectpicker').selectpicker('refresh');
                calculateTotal();
            }

            // ===== Calculate totals =====
            discountInput.addEventListener('input', calculateTotal);
            fee.addEventListener('input', calculateTotal);

            function calculateTotal() {
                let subTotal = 0;

                document.querySelectorAll('.product-item').forEach(item => {
                    const priceEl = item.querySelector('.product-price');
                    const quantityInput = item.querySelector('.quantity-input');
                    if (!priceEl || !quantityInput) return;

                    const price = parseFloat(priceEl.dataset.price) || 0;
                    const qty = parseInt(quantityInput.value) || 0;
                    subTotal += price * qty;
                });

                const deliveryCharge = parseFloat(fee.value) || 0;

                const discount = parseFloat(discountInput.value) || 0;
                const total = Math.max(subTotal - discount, 0) + deliveryCharge;


                subTotalEl.textContent = subTotal.toFixed(2);
                discountEl.textContent = discount.toFixed(2);
                totalEl.textContent = total.toFixed(2);
            }

            // ===== Run once on load =====
            attachQuantityListeners();
            calculateTotal();
        </script>

@endpush
