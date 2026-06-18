@extends('layouts.admin')

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-treeview/1.2.0/bootstrap-treeview.min.css"
    integrity="sha512-A81ejcgve91dAWmCGseS60zjrAdohm7PTcAjjiDWtw3Tcj91PNMa1gJ/ImrhG+DbT5V+JQ5r26KT5+kgdVTb5w=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .table-striped th:nth-child(2),
        .table-striped td:nth-child(2) {
            width: inherit;
            padding-bottom: inherit;
        }

        .table-striped th:nth-child(2),
        .table-striped td:nth-child(2) {
            width: inherit;
            padding-bottom: inherit;
        }

        .table-striped th:nth-child(1),
        .table-striped td:nth-child(1) {
            width: 100px;
            padding-bottom: inherit;
        }

        .table-striped th:nth-child(1),
        .table-striped td:nth-child(1) {
            width: 100px;
            padding-bottom: inherit;
        }
    </style>
    <style>
        .tree ul {
            list-style-type: none;
            padding-left: 1.5rem;
        }

        .tree li {
            margin: 0.5rem 0;
        }

        .tree-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #f8f9fa;
        }

        .tree-item .actions {
            flex-shrink: 0;
        }

        .tree ul ul {
            margin-left: 2rem;
            /* indentation for subcategories */
        }
    </style>
    <style>
        .drag-handle {
            cursor: grab;
            color: #888;
            font-size: 18px;
            text-align: center;
        }

        .drag-handle:active {
            cursor: grabbing;
        }

        .sortable-ghost {
            opacity: 0.4;
            background: #e9f5ff;
        }

        .sortable-drag {
            background: #fff;
        }

        .sort-order-input {
            width: 80px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 4px 6px;
        }

        tr.row-saving {
            background: #fff8e1 !important;
        }
    </style>
@endpush
@section('content')
    <!-- content area start -->
    <div class="main-content-inner">
        <div class="main-content-wrap">
            <div class="flex items-center flex-wrap justify-between gap20 mb-27">
                <h3>Categories</h3>
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
                        <div class="text-tiny">Categories</div>
                    </li>
                </ul>
            </div>

            <div class="wg-box">
            <div class="wg-box">
                <form class="form-new-product form-style-1 needs-validation"
                    action="{{ route('admin.categories.assign.products', $category->id) }}" method="POST"
                    enctype="multipart/form-data" novalidate>
                    @csrf

                    <input type="hidden" hidden name="id" value="{{ $category->id }}" />


                    <fieldset class="name">
                        <div class="body-title">Select Products</div>
                        <div class="select flex-grow">
                            <select id="products" class="selectpicker @error('products') is-invalid @enderror"
                                name="products[]" required multiple data-live-search="true" title="Choose products...">
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">
                                        {{ $product->id . ' - ' . $product->name . ' - ' . ($product->discount_price ?? $product->price) }}
                                        Tk</option>
                                @endforeach
                            </select>

                        </div>
                        @error('products')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>


                    <div class="bot">
                        <div></div>
                        <button class="tf-button w208" type="submit">{{ __('Save') }}</button>
                    </div>
                </form>
            </div>
            <div class="wg-box">
                <div class="wg-table table-responsive">
                    @if (Session::has('status'))
                        <div class="alert alert-success" role="alert">
                            {{ Session::get('status') }}
                        </div>
                    @endif
                    <div class="text-tiny mb-2">
                        Drag rows by the <i class="icon-move"></i> handle, or type a number in
                        <strong>Sort Order</strong> to reposition. Changes save automatically.
                    </div>
                    <table class="table table-striped table-bordered" id="category-products-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Sort Order</th>
                                <th>#</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>SKU</th>
                                <th>Stock</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="sortable-products">
                            @if (count($Categoryproducts) > 0)
                                @foreach ($Categoryproducts as $pitem)
                                    <tr data-id="{{ $pitem->id }}">
                                        <td class="drag-handle" title="Drag to reorder">
                                            <i class="icon-menu"></i>
                                        </td>
                                        <td>
                                            <input type="number" class="sort-order-input"
                                                value="{{ $pitem->pivot->sort_order }}" min="0" step="1">
                                        </td>
                                        <td>{{ $pitem->id }}</td>
                                        <td class="pname">
                                            <div class="image">
                                                <img src="{{ asset('storage/images/products/thumbnails/' . $pitem->image) }}"
                                                    alt="{{ $pitem->name }}" class="image">
                                            </div>
                                            <div class="name">
                                                <a target="_blank" href="{{ route('product.show', $pitem->slug) }}"
                                                    class="body-title-2">{{ $pitem->name }}</a>
                                                <div class="text-tiny mt-3">{{ $pitem->slug }}</div>
                                            </div>
                                        </td>
                                        <td>{{ $pitem->price }}</td>
                                        <td>{{ $pitem->sku }}</td>
                                        <td>{{ $pitem->stock_status }}</td>
                                        <td>{{ $pitem->quantity }}</td>
                                        <td>
                                            @if ($pitem->status == 1)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="list-icon-function">


                                                <form
                                                    action="{{ route('admin.categories.unassign.products', ['id' => $category->id]) }}"
                                                    method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="products" value="{{ $pitem->id }}">
                                                    <div class="item text-danger delete">
                                                        <i class="icon-trash-2"></i>
                                                    </div>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="10" class="text-center">No products found</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>
    </div>


    <!-- content area end -->
@endsection
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        $(document).ready(function() {
            const orderUrl = "{{ route('admin.categories.products.order', $category->id) }}";
            const csrf = "{{ csrf_token() }}";
            const tbody = document.getElementById('sortable-products');

            // Renumber the visible rows top-to-bottom and reflect it in the inputs.
            function renumberRows() {
                $('#sortable-products > tr[data-id]').each(function(index) {
                    $(this).find('.sort-order-input').val(index + 1);
                });
            }

            // Collect current order and persist it.
            function saveOrder() {
                const order = [];
                const rows = $('#sortable-products > tr[data-id]');
                rows.each(function(index) {
                    order.push({
                        id: parseInt($(this).data('id'), 10),
                        sort_order: index + 1
                    });
                });

                rows.addClass('row-saving');

                $.ajax({
                    url: orderUrl,
                    method: 'POST',
                    data: {
                        order: order
                    },
                    headers: {
                        'X-CSRF-TOKEN': csrf
                    },
                    success: function() {
                        rows.removeClass('row-saving');
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Sort order saved',
                            showConfirmButton: false,
                            timer: 1500
                        });
                    },
                    error: function() {
                        rows.removeClass('row-saving');
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'error',
                            title: 'Could not save order',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    }
                });
            }

            // Drag-and-drop reordering.
            if (tbody) {
                new Sortable(tbody, {
                    handle: '.drag-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                    onEnd: function() {
                        renumberRows();
                        saveOrder();
                    }
                });
            }

            // Number-input reordering: move the row to the requested position.
            $('#sortable-products').on('change', '.sort-order-input', function() {
                const row = $(this).closest('tr');
                let target = parseInt($(this).val(), 10);
                const rows = $('#sortable-products > tr[data-id]');

                if (isNaN(target) || target < 1) target = 1;
                if (target > rows.length) target = rows.length;

                row.detach();
                const remaining = $('#sortable-products > tr[data-id]');
                if (target - 1 >= remaining.length) {
                    $('#sortable-products').append(row);
                } else {
                    remaining.eq(target - 1).before(row);
                }

                renumberRows();
                saveOrder();
            });

            $('.delete').on('click', function(e) {
                e.preventDefault();
                var form = $(this).closest('form');
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
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
        });
    </script>
@endpush
