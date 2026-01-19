@extends('layouts.admin')

@section('content')
    <!-- content area start -->
    <style>
        .table-transaction>tbody>tr:nth-of-type(odd) {
            --bs-table-accent-bg: #fff !important;
        }
    </style>
    <!-- ====================== Styles ====================== -->
    <style>
        .product-item {
            transition: 0.2s ease-in-out;
        }

        .product-item:hover {
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #f8f9fa;
        }

        @media (max-width: 767px) {

            .product-item .col-md-2,
            .product-item .col-md-3 {
                text-align: center;
            }
        }
    </style>
    <div class="main-content-inner">
        <div class="main-content-wrap">
            <div class="flex items-center flex-wrap justify-between gap20 mb-27">
                <h3>Order Details</h3>
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
                        <div class="text-tiny">Order Items</div>
                    </li>
                </ul>
            </div>
            <div class="wg-box mt-5 mb-27">
                <div class="flex items-center justify-between gap10 flex-wrap">
                    <div class="wg-filter flex-grow">

                    </div>
                    <a class="tf-button style-1 w208" href="{{ route('admin.orders') }}">Back</a>
                </div>
                @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        {{ session('status') }}
                    </div>
                @endif
                <h5>Order Details</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-transaction">
                        <tbody>

                            <tr>
                                <th>Order No</th>
                                <td>{{ $order->id }}</td>
                                <th>Customer Name</th>
                                <td>{{ $order->name }}</td>
                                <th>Mobile No</th>
                                <td>{{ $order->phone }}</td>

                            </tr>
                            <tr>
                                <th>total Quantity</th>
                                <td>{{ $order->Order_Item->count() }}</td>
                                <th>Consigment ID</th>
                                <td>{{ $order->consignment_id }}</td>
                                <th>Status</th>
                                <td>{{ $order->status }}</td>
                            </tr>
                            <tr>
                                <th>Order Date</th>
                                <td>{{ $order->created_at }}</td>
                                <th>Delivered Date</th>
                                <td>{{ $order->delivery_date }}</td>
                                <th>Canceled Date</th>
                                <td>{{ $order->cancelled_date }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="wg-box">
                <h5>Ordered Items -<!-- Button trigger modal -->
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
                        Edit
                    </button>
                </h5>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>

                            <tr>
                                <th>Name</th>
                                <th class="text-center">Price</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-center">SKU</th>
                                <th class="text-center">Options</th>
                                <th class="text-center">Return Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($orderItems->count() > 0)
                                @foreach ($orderItems as $item)
                                    <tr>
                                        <td class="pname">
                                            <div class="image">
                                                <img src="{{ asset('storage/images/products/thumbnails/' . $item->product->image) }}"
                                                    alt="{{ $item->product->name }}" class="image">
                                            </div>
                                            <div class="name">
                                                <a href="javascript:void(0)" target="_blank"
                                                    class="body-title-2">{{ $item->product->name }}</a>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            ৳{{ $item->product->discount_price ?? $item->product->price }}
                                        </td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-center">
                                            {{ floatval($item->product->discount_price ?? $item->product->price) * (int) $item->quantity }}
                                        </td>

                                        <td class="text-center">{{ $item->options }}</td>
                                        <td class="text-center">{{ $item->return_status ? 'Yes' : 'No' }}</td>
                                        <td class="text-center">
                                            <div class="list-icon-function view-icon">
                                                <div class="item eye">
                                                    <i class="icon-eye"></i>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="7" class="text-center">No products found</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <div class="divider"></div>
                <div class="flex items-center justify-between flex-wrap gap10 wgp-pagination">
                    {{ $orderItems->links('pagination::bootstrap-5') }}
                </div>
            </div>

            <div class="wg-box mt-5">
                <div class="tf-section-1 mb-30">
                    <div class="flex gap20 flex-wrap-mobile">
                        <div class="w-half">
                            <h5>Shipping Address <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#orderDetails">
                                    Edit
                                </button></h5>
                            <div class="my-account__address-item col-md-6">

                                <div class="my-account__address-item__detail">
                                    <p>Name : {{ $order->name }}</p>

                                    <p class="{{ $order?->customer?->isBlocked ? 'text-danger' : '' }}">Mobile :
                                        {{ $order->phone }}
                                        @if (!$order->customer || !$order->customer->isBlocked)
                                            <a id="blockcustomer" href="javascript:void(0)"
                                                onclick="blockcustomer({{ $order->id }})"
                                                class="btn btn-danger btn-sm small">Block</a>
                                        @else
                                            <a href="javascript:void(0)" class="btn btn-success btn-sm small" onclick="unblockCustomer({{ $order->id }})">Unblock</a>
                                        @endif

                                    </p>
                                    @if ($order->fraud_check_steadfast)
                                        <strong>SteadFast Customer Check:</strong>
                                        <p>
                                            Total Order : {{ $order->fraud_check_steadfast['total'] ?? 0 }}<br>
                                            Received Order : {{ $order->fraud_check_steadfast['success'] ?? 0 }}<br>
                                            Returned Order : {{ $order->fraud_check_steadfast['cancel'] ?? 0 }}<br>
                                            Score:
                                            @php
                                                $success_steadfast = $order->fraud_check_steadfast['success'] ?? 0;
                                                $total_steadfast = $order->fraud_check_steadfast['total'] ?? 0;

                                                $score_steadfast =
                                                    $total_steadfast > 0
                                                        ? ($success_steadfast / $total_steadfast) * 100
                                                        : 0;
                                                $fraud_score_steadfast = number_format($score_steadfast, 2);
                                            @endphp
                                        </p>
                                    @endif
                                    <div class="progress">
                                        <div class="progress-bar bg-{{ $fraud_score_steadfast >= 70 ? 'success' : 'danger' }}"
                                            role="progressbar"
                                            style="width: {{ $fraud_score_steadfast > 100 ? 100 : $fraud_score_steadfast }}%;"
                                            aria-valuenow="{{ $fraud_score_steadfast > 100 ? 100 : $fraud_score_steadfast }}"
                                            aria-valuemin="0" aria-valuemax="100">
                                            {{ $fraud_score_steadfast > 100 ? 100 : $fraud_score_steadfast }}%
                                        </div>
                                    </div>
                                    @if ($order->fraud_check_pathao)
                                        <strong>Pathao Customer Check:</strong>
                                        <p>
                                            Total Order : {{ $order->fraud_check_pathao['total'] ?? 0 }}<br>
                                            Received Order : {{ $order->fraud_check_pathao['success'] ?? 0 }}<br>
                                            Returned Order : {{ $order->fraud_check_pathao['cancel'] ?? 0 }}<br>
                                            Score:
                                            @php
                                                $success_pathao = $order->fraud_check_pathao['success'] ?? 0;
                                                $total_pathao = $order->fraud_check_pathao['total'] ?? 0;

                                                $score_pathao =
                                                    $total_pathao > 0 ? ($success_pathao / $total_pathao) * 100 : 0;
                                                $fraud_score_pathao = number_format($score_pathao, 2);
                                            @endphp
                                        </p>
                                    @endif
                                    <div class="progress">
                                        <div class="progress-bar bg-{{ $fraud_score_pathao >= 70 ? 'success' : 'danger' }}"
                                            role="progressbar"
                                            style="width: {{ $fraud_score_pathao > 100 ? 100 : $fraud_score_pathao }}%;"
                                            aria-valuenow="{{ $fraud_score_pathao > 100 ? 100 : $fraud_score_pathao }}"
                                            aria-valuemin="0" aria-valuemax="100">
                                            {{ $fraud_score_pathao > 100 ? 100 : $fraud_score_pathao }}%
                                        </div>
                                    </div>


                                    <p>Delivery Area :
                                        {{ $order?->delivery_area?->name . ' - ' . $order?->delivery_area?->charge }} TK
                                    </p>
                                    <p>Full Address : {{ $order->address }}</p>
                                    <p>Order Note : {{ $order->note }}</p>
                                    <br>
                                </div>
                            </div>
                        </div>
                        <div class="w-half">
                            <h5>Order Summary</h5>
                            <div class="my-account__address-item col-md-6">
                                <div class="my-account__address-item__detail">
                                    <p>Product Price :
                                        {{ $order->sub_total }}
                                        Tk</p>
                                    <p>Delivery fee : {{ $order->fee }} Tk</p>
                                    @if ($order->discount > 0)
                                        <p>Discount Amount : {{ $order->discount }} Tk</p>
                                    @endif
                                    <p>Total Bill : {{ $order->total }} TK</p>

                                    <br>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>


            <div class="wg-box mt-5">
                <h5>Update Order Status</h5>
                <form action="{{ route('admin.orders.update', $order->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="order_id" value="{{ $order->id }}">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">

                                <select name="status" id="status" class="form-control">
                                    <option value="pending" @if ($order->status == 'pending') selected @endif>Pending
                                    </option>
                                    <option value="confirmed" @if ($order->status == 'confirmed') selected @endif>Confirmed
                                    </option>
                                    <option value="ready" @if ($order->status == 'ready') selected @endif>Ready
                                    </option>
                                    <option value="delivered" @if ($order->status == 'delivered') selected @endif>Delivered
                                    </option>
                                    <option value="on_hold" @if ($order->status == 'on_hold') selected @endif>On Hold
                                    </option>
                                    <option value="in_review" @if ($order->status == 'in_review') selected @endif>In Review
                                    </option>
                                    <option value="in_transit" @if ($order->status == 'in_transit') selected @endif>
                                        in_transit
                                    </option>
                                    <option value="processing" @if ($order->status == 'processing') selected @endif>
                                        Processing
                                    </option>
                                    <option value="delivery_in_review" @if ($order->status == 'delivery_in_review') selected @endif>
                                        Delivery in Review
                                    </option>
                                    <option value="cancelled" @if ($order->status == 'cancelled') selected @endif>Cancelled
                                    </option>
                                    <option value="returned" @if ($order->status == 'returned') selected @endif>Returned
                                    </option>
                                    <option value="deleted" @if ($order->status == 'deleted') selected @endif>Deleted
                                    </option>
                                </select>

                            </div>

                        </div>
                        <div class="col-md-9">
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </div>

                </form>

            </div>
            <div class="wg-box mt-5">
                <h5>Extra Data</h5>
                <div class="my-account__address-item col-md-12">
                    <div class="my-account__address-item__detail">
                        <p>IP Address: {{ $order->ip_address }}</p>
                        <p class="{{ $order?->device?->isBlocked ? 'text-danger' : '' }}">User Agent: {{ $order->user_agent }}</p>

                        <pre style="font-size: 14px; line-height: 20px; "> {{ json_encode($order->json_data, JSON_PRETTY_PRINT) }}</pre>
                        <br>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal -->
        <!-- Edit Order Modal -->
        <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">

                    <!-- Header -->
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Edit Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <!-- Body -->
                    <div class="modal-body">
                        <form action="{{ route('admin.orders.editupdate', $order->id) }}" method="POST" id="orderEdit">
                            @csrf
                            @method('PUT')

                            <!-- Product Selector -->
                            <fieldset class="mb-3">
                                <label for="products" class="form-label fw-semibold">Add / Edit Products</label>
                                <select name="products[]" id="products"
                                    class="form-control selectpicker @error('products') is-invalid @enderror" required
                                    multiple data-live-search="true" title="Choose products...">

                                    @foreach ($products as $product)
                                        @php
                                            $isSelected = $order->Order_Item
                                                ->pluck('product_id')
                                                ->contains($product->id);
                                        @endphp
                                        <option value="{{ $product->id }}" data-id="{{ $product->id }}"
                                            {{ $product->stock_status == 'out_of_stock' ? 'disabled' : '' }}
                                            {{ $isSelected ? 'selected' : '' }}>
                                            {{ $product->name }} -
                                            {{ $product->stock_status == 'in_stock' ? 'In Stock' : 'Out of Stock' }} -
                                            {{ $product->discount_price ?? $product->price }} Tk
                                        </option>
                                    @endforeach
                                </select>
                            </fieldset>

                            <!-- Dynamic Product List -->
                            <div id="editForm" class="mt-3">
                                @if ($order->Order_Item->count() > 0)
                                    @foreach ($order->Order_Item as $item)
                                        <div id="product-item-{{ $item->product->id }}"
                                            class="product-item border rounded bg-light p-3 mb-3">
                                            <div class="row align-items-center text-center text-md-start">

                                                <!-- 1️⃣ Image -->
                                                <div class="col-12 col-md-2 mb-2 mb-md-0">
                                                    <img src="/storage/images/products/{{ $item->product->image }}"
                                                        alt="{{ $item->product->name }}" class="img-fluid rounded"
                                                        style="max-height: 80px; object-fit: cover;">
                                                </div>

                                                <!-- 2️⃣ Title & Price -->
                                                <div class="col-12 col-md-3 mb-2 mb-md-0">
                                                    <h6 class="mb-1">{{ $item->product->name }}</h6>
                                                    <p class="mb-0">
                                                        Price:
                                                        <strong class="product-price"
                                                            data-price="{{ $item->product->discount_price ?? $item->product->price }}">
                                                            {{ $item->product->discount_price ?? $item->product->price }}
                                                            Tk
                                                        </strong>
                                                    </p>
                                                </div>

                                                <!-- 3️⃣ Quantity -->
                                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                                    <label class="form-label small">Quantity</label>
                                                    <input type="text" class="edit_product_id" hidden
                                                        name="order_items[{{ $item->product->id }}][id]"
                                                        value="{{ $item->product->id }}">
                                                    <input type="number"
                                                        name="order_items[{{ $item->product->id }}][quantity]"
                                                        id="quantity_{{ $item->product->id }}"
                                                        value="{{ $item->quantity }}" min="1"
                                                        class="form-control quantity-input"
                                                        data-id="{{ $item->product->id }}">
                                                </div>

                                                <!-- 4️⃣ Options -->
                                                <div class="col-6 col-md-3 mb-2 mb-md-0">
                                                    <label class="form-label small">Options</label>
                                                    <input type="text"
                                                        name="order_items[{{ $item->product->id }}][size]"
                                                        id="options_{{ $item->product->id }}" class="form-control"
                                                        placeholder="Enter size"
                                                        value="{{ json_decode($item?->options)?->size }}">
                                                </div>

                                                <!-- 5️⃣ Actions -->
                                                <div class="col-12 col-md-2">
                                                    <button type="button"
                                                        class="btn btn-danger btn-sm w-100 mt-2 mt-md-0"
                                                        onclick="removeProduct({{ $item->product->id }})">
                                                        Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                            <!-- delivery charge -->
                            <fieldset class="mt-3">
                                <label for="delivery_charge" class="form-label fw-semibold">Delivery Charge</label>
                                <input type="number" id="delivery_charge" name="delivery_charge" class="form-control"
                                    placeholder="Enter delivery charge" value="{{ $order->fee ?? 0 }}"
                                    value="{{ $order->fee ?? 0 }}" min="0">
                            </fieldset>
                            <!-- Discount -->
                            <fieldset class="mt-3">
                                <label for="discount" class="form-label fw-semibold">Discount Amount</label>
                                <input type="number" id="discount" name="discount" class="form-control"
                                    placeholder="Enter discount amount if any" value="{{ $order->discount ?? 0 }}"
                                    min="0">
                            </fieldset>

                        </form>
                    </div>

                    <!-- Footer -->
                    <div class="modal-footer flex-column align-items-stretch">
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

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary"
                                onclick="document.getElementById('orderEdit').submit();">
                                Save Changes
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <div class="modal fade" id="orderDetails" tabindex="-1" aria-labelledby="exampleModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">

                    <!-- Header -->
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Edit Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <!-- Body -->
                    <div class="modal-body">
                        <form action="{{ route('admin.orders.update.details', $order->id) }}" method="POST"
                            id="orderDetailForm">
                            @csrf
                            @method('PUT')


                            <input type="hidden" name="order_id" value="{{ $order->id }}">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name">Customer Name</label>
                                        <input type="text" name="name" class="form-control" id="name"
                                            value="{{ $order->name }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="phone">Customer Phone</label>
                                        <input type="text" name="phone" class="form-control" id="phone"
                                            value="{{ $order->phone }}">
                                    </div>
                                </div>
                                {{-- <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="delivery_area_id">Delivery Area</label>
                                       <select name="delivery_area_id" class="form-control" id="delivery_area_id">
                                           <option value="">Select Delivery Area</option>
                                           @foreach ($deliveryAreas as $deliveryArea)
                                               <option value="{{ $deliveryArea->id }}" @if ($deliveryArea->id == $order->delivery_area_id) selected @endif>{{ $deliveryArea->name }}</option>
                                           @endforeach
                                       </select>
                                    </div>
                                </div> --}}

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="address">Delivery Address</label>
                                        <textarea name="address" class="form-control" id="address" cols="30" rows="10">{{ $order->address }}</textarea>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="note">Note </label>
                                        <textarea name="note" class="form-control" id="note" cols="30" rows="10">{{ $order->note }}</textarea>
                                    </div>
                                </div>
                            </div>

                        </form>
                    </div>

                    <!-- Footer -->
                    <div class="modal-footer flex-column align-items-stretch">

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary"
                                onclick="document.getElementById('orderDetailForm').submit();">
                                Save Changes
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
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


        <script>

            function blockcustomer(id) {


                if (id) {
                    // 1. Show confirmation dialog using SweetAlert (Swal)
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You won't be able to revert this!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, Block!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // 2. Execute fetch request to the backend
                            fetch(`/admin/orders/${id}/customer/block`, {
                                    // NOTE: For blocking/mutating data, a POST method is generally recommended,
                                    // but sticking to your original GET method here.
                                    method: 'GET',
                                    headers: {
                                        // Assuming you are using jQuery for CSRF token retrieval
                                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                    }
                                })
                                // 3. First .then(): Get the Response object and check status
                                .then(response => {
                                    if (!response.ok) {
                                        // Throw an error if the HTTP status code is 4xx or 5xx
                                        throw new Error(`HTTP error! Status: ${response.status}`);
                                    }
                                    // IMPORTANT FIX: Return the promise from response.json()
                                    return response.json();
                                })
                                // 4. Second .then(): Process the parsed JSON data (responseJson)
                                .then(responseJson => {
                                    console.log(responseJson);
                                    if (responseJson.success) {
                                        // Successfully blocked, reload the page
                                        // location.reload();
                                        Swal.fire('Success!', responseJson.message || 'Customer blocked successfully.', 'success');
                                    } else {
                                        // Block was unsuccessful according to the server's logic/message
                                        console.error("Block failed:", responseJson.message);
                                        Swal.fire('Failed!', responseJson.message ||
                                            'Customer block action failed.', 'error');
                                    }
                                })
                                // 5. .catch(): Handle any errors (network, HTTP status, or JSON parsing)
                                .catch(error => {
                                    console.error('Fetch operation error:', error);
                                    Swal.fire('Error!', 'An unexpected error occurred during the request.',
                                    'error');
                                });
                        }
                    });
                } else {
                    // Show warning if ID is missing
                    Swal.fire({
                        icon: 'warning',
                        title: 'No ID provided',
                        text: 'Cannot block customer without an ID.'
                    });
                }
            }


            function unblockCustomer(id) {
                console.log(id);

                if (id) {
                    // 1. Show confirmation dialog using SweetAlert (Swal)
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You won't be able to revert this!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, Unblock!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // 2. Execute fetch request to the backend
                            fetch(`/admin/orders/${id}/customer/unblock`, {
                                    // NOTE: For blocking/mutating data, a POST method is generally recommended,
                                    // but sticking to your original GET method here.
                                    method: 'GET',
                                    headers: {
                                        // Assuming you are using jQuery for CSRF token retrieval
                                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                    }
                                })
                                // 3. First .then(): Get the Response object and check status
                                .then(response => {
                                    if (!response.ok) {
                                        // Throw an error if the HTTP status code is 4xx or 5xx
                                        throw new Error(`HTTP error! Status: ${response.status}`);
                                    }
                                    // IMPORTANT FIX: Return the promise from response.json()
                                    return response.json();
                                })
                                // 4. Second .then(): Process the parsed JSON data (responseJson)
                                .then(responseJson => {
                                    console.log(responseJson);
                                    if (responseJson.success) {
                                        // Successfully blocked, reload the page
                                        // location.reload();
                                        Swal.fire('Success!', responseJson.message || 'Customer unblocked successfully.', 'success');
                                    } else {
                                        // Block was unsuccessful according to the server's logic/message
                                        console.error("unBlock failed:", responseJson.message);
                                        Swal.fire('Failed!', responseJson.message ||
                                            'Customer unblock action failed.', 'error');
                                    }
                                })
                                // 5. .catch(): Handle any errors (network, HTTP status, or JSON parsing)
                                .catch(error => {
                                    console.error('Fetch operation error:', error);
                                    Swal.fire('Error!', 'An unexpected error occurred during the request.',
                                    'error');
                                });
                        }
                    });
                } else {
                    // Show warning if ID is missing
                    Swal.fire({
                        icon: 'warning',
                        title: 'No ID provided',
                        text: 'Cannot unblock customer without an ID.'
                    });
                }
            }


        </script>
    @endsection
