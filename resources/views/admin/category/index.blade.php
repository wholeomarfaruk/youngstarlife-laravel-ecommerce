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
                <div class="flex items-center justify-between gap10 flex-wrap">
                    <div class="wg-filter flex-grow">
                        {{-- <form class="form-search">
                            <fieldset class="name">
                                <input type="text" placeholder="Search here..." class="" name="name"
                                    tabindex="2" value="" aria-required="true" required="">
                            </fieldset>
                            <div class="button-submit">
                                <button class="" type="submit"><i class="icon-search"></i></button>
                            </div>
                        </form> --}}
                    </div>
                    <a class="tf-button style-1 w208" href="{{ route('admin.categories.add') }}"><i
                            class="icon-plus"></i>Add new</a>
                </div>
                <div class="wg-table">
                    <div class="table-responsive">
                        @if (Session::has('status'))
                            <div class="alert alert-success" role="alert">
                                {{ Session::get('status') }}
                            </div>
                        @endif

                        {{-- <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>#ID</th>
                                    <th>Name</th>
                                    <th>Slug</th>
                                    <th>Status</th>
                                    <th>Action</th>

                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categories as $category)

                                <tr>
                                    <td>{{ $category->id }}</td>
                                    <td  >
                                        <div class="flex items-center justify-start flex-wrap gap10">
                                            <div class="image">
                                                <img src="#" alt="" class="image">
                                            </div>
                                            <div class="name">
                                                <a href="#" class="body-title-2">{{ $category->name }}</a>
                                            </div>
                                        </div>


                                    </td>
                                    <td >
                                        {{ $category->slug }}
                                    </td>
                                    <td>
                                        @if ($category->is_active == true)
                                        <span class="badge bg-success  rounded-pill">Active</span>
                                        @elseif ($category->is_active == false)
                                        <span class="badge bg-danger rounded-pill">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="list-icon-function">
                                            <a href="{{ route('admin.categories.edit', ['id'=>$category->id]) }}">
                                                <div class="item edit">
                                                    <i class="icon-edit-3"></i>
                                                </div>
                                            </a>
                                            <form action="{{ route('admin.categories.delete', ['id'=>$category->id]) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button href="{{ route('admin.categories.delete', ['id'=>$category->id]) }}"  type="submit" data-confirm-delete="true" class="item text-danger border-0 delete">
                                                    <i class="icon-trash-2" ></i>
                                                </button>
                                            </form>

                                        </div>
                                    </td>

                                </tr>

                                @endforeach
                            </tbody>
                        </table> --}}
                        <div class="tree">
                            <ul class="list-unstyled ms-3">
                                @foreach ($categories as $category)
                                    <li>
                                        <div
                                            class="tree-item d-flex justify-content-between align-items-center border p-2 mb-1 rounded bg-light">
                                            <span>
                                                @if ($category->children->count())
                                                    <a data-bs-toggle="collapse" href="#cat-{{ $category->id }}"
                                                        role="button">
                                                        📂 {{ $category->name }}
                                                    </a>
                                                @else
                                                    📄 {{ $category->name }}
                                                @endif
                                            </span>
                                            <div class="actions">

                                                <a href="{{ route('admin.categories.manage.products', $category->id) }}"
                                                    class="btn btn-sm btn-primary">Manage Products</a>
                                                <a href="{{ route('admin.categories.edit', $category->id) }}"
                                                    class="btn btn-sm btn-warning">Edit</a>
                                                <form action="{{ route('admin.categories.delete', $category->id) }}"
                                                    method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-danger delete"  data-confirm-delete="true"
                                                       >Delete</button>
                                                         <form action="{{ route('admin.categories.delete', ['id'=>$category->id]) }}" method="POST">

                                            </form>
                                                </form>
                                            </div>
                                        </div>

                                        @if ($category->children->count())
                                            <div class="collapse show" id="cat-{{ $category->id }}">
                                                @include('admin.category.tree', [
                                                    'categories' => $category->children,
                                                ])
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                        </div>
                    </div>
                </div>
            </div>
            <div class="divider"></div>
            <div class="flex items-center justify-between flex-wrap gap10 wgp-pagination mt-5">
                {{-- {{ $categories->links('pagination::bootstrap-5') }} --}}
            </div>
        </div>
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
