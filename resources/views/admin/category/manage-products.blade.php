@extends('layouts.admin')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-treeview/1.2.0/bootstrap-treeview.min.css"
    integrity="sha512-A81ejcgve91dAWmCGseS60zjrAdohm7PTcAjjiDWtw3Tcj91PNMa1gJ/ImrhG+DbT5V+JQ5r26KT5+kgdVTb5w=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
@push('styles')
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
                                        {{ $product->id . ' - ' . $product->name . ' - ' . $product->discount_price ?? $product->price }}
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
                <div class="table-responsive">
                    @if (Session::has('status'))
                        <div class="alert alert-success" role="alert">
                            {{ Session::get('status') }}
                        </div>
                    @endif
                    <table class="table table-striped table-bordered">
                        <thead>

                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>SKU</th>
                                <th>Stock</th>
                                <th>Quantity</th>
                                <th>Featured</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if (count($Categoryproducts) > 0)
                                @foreach ($Categoryproducts as $pitem)
                                    <tr>
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
                                        <td>{{ $pitem->featured == 1 ? 'Yes' : 'No' }}</td>
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
                                    <td colspan="8" class="text-center">No products found</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="divider"></div>
            <div class="flex items-center justify-between flex-wrap gap10 wgp-pagination mt-5">
                {{-- {{ $Categoryproducts->links('pagination::bootstrap-5') }} --}}
            </div>
        </div>
    </div>


    <!-- content area end -->
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
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
