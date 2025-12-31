@extends('layouts.admin')

@section('content')
    <!-- content area start -->
    <div class="main-content-inner">
        <!-- main-content-wrap -->
        <div class="main-content-wrap">
            <div class="flex items-center flex-wrap justify-between gap20 mb-27">
                <h3>Add Product</h3>
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
                        <a href="{{ route('admin.products') }}">
                            <div class="text-tiny">Products</div>
                        </a>
                    </li>
                    <li>
                        <i class="icon-chevron-right"></i>
                    </li>
                    <li>
                        <div class="text-tiny">Edit product</div>
                    </li>
                </ul>
            </div>
            <!-- form-add-product -->
            <form class="tf-section-2 form-add-product needs-validation" method="POST" enctype="multipart/form-data"
                action="{{ route('admin.products.update') }}" novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" name="id" value="{{ $product->id }}">
                <div class="wg-box">
                    <fieldset class="name">
                        <div class="body-title mb-10">Product name <span class="tf-color-1">*</span>
                        </div>
                        <input class="mb-10 @error('name') is-invalid @enderror" type="text"
                            placeholder="Enter product name" name="name" tabindex="0" aria-required="true"
                            value="{{ $product->name }}" required autocomplete="name" autofocus
                            onchange="stringtoSlug(this.value)">
                        <div class="text-tiny">Do not exceed 100 characters when entering the
                            product name.</div>
                        @error('name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>
<fieldset class="name">
                        <div class="body-title mb-10">Slug <span class="tf-color-1">*</span></div>
                        <input class="mb-10 @error('slug') is-invalid @enderror" type="text"
                            placeholder="Enter slug" name="slug" tabindex="0" value="{{ $product->slug }}"
                            aria-required="true" required="required" onchange="stringtoSlug(this.value)" autofocus id="slug_input">
                        @error('slug')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>

                    <div class="cols gap22">
                        <fieldset class="name">
                            <div class="body-title mb-10">Price <span class="tf-color-1">*</span></div>
                            <input class="mb-10 @error('price') is-invalid @enderror" type="text"
                                placeholder="Enter price" name="price" tabindex="0" value="{{ $product->price }}"
                                aria-required="true" required="required" autofocus>
                            @error('price')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </fieldset>
                        <fieldset class="name">
                            <div class="body-title mb-10">Discount Price <span class="tf-color-1">*</span></div>
                            <input class="mb-10 @error('discount_price') is-invalid @enderror" type="text"
                                placeholder="Enter price" name="discount_price" tabindex="0"
                                value="{{ $product->discount_price }}" aria-required="true" required="required" autofocus>
                            @error('discount_price')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </fieldset>
                    </div>
                    <div class="cols gap22">

                        <fieldset class="name">
                            <div class="body-title mb-10">Quantity <span class="tf-color-1">*</span>
                            </div>
                            <input class="mb-10 @error('quantity') is-invalid @enderror" type="text"
                                placeholder="Enter quantity" name="quantity" tabindex="0"
                                value="{{ $product->quantity }}" aria-required="true" required="required">
                            @error('quantity')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </fieldset>
                    </div>

                    <div class="cols gap22">
                        <fieldset class="name">
                            <div class="body-title mb-10">Stock</div>
                            <div class="select mb-10">
                                <select class="" name="stock_status">
                                    <option value="in_stock" {{ $product->stock_status == 'in_stock' ? 'selected' : '' }}>
                                        InStock</option>
                                    <option value="out_of_stock"
                                        {{ $product->stock_status == 'out_of_stock' ? 'selected' : '' }}>Out of Stock
                                    </option>
                                </select>
                            </div>
                            @error('stock_status')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </fieldset>
                        <fieldset class="name">
                            <div class="body-title mb-10">SKU <span class="tf-color-1">*</span>
                            </div>
                            <input class="mb-10 @error('sku') is-invalid @enderror" type="text" placeholder="Enter SKU"
                                name="sku" tabindex="0" value="{{ $product->sku }}" aria-required="true"
                                required="required">
                            @error('sku')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </fieldset>
                    </div>
                    <fieldset class="name">
                        <div class="body-title mb-10">Description <span class="tf-color-1">*</span></div>
                        <textarea id="editor" class="mb-10 @error('description') is-invalid @enderror" name="description" tabindex="0"
                            aria-required="true" required="required">{{ $product->description }}</textarea>
                        @error('description')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>
                    <fieldset class="name">
                        <div class="body-title mb-10">SEO Description <span class="tf-color-1">*</span></div>
                        <textarea class="mb-10 @error('short_description') is-invalid @enderror" name="short_description" tabindex="0"
                            aria-required="true" required="required">{{ $product->short_description }}</textarea>
                        @error('short_description')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>
                    <div class="cols gap10">
                        <button class="tf-button w-full" type="submit">Update product</button>
                    </div>





                </div>
                <div class="wg-box">
                    <fieldset>
                        <div class="body-title">Upload images <span class="tf-color-1">*</span>
                        </div>
                        <div class="upload-image flex-grow">
                            <div class="item" id="imgpreview">
                                <img src="{{ asset('storage/images/products/thumbnails/' . $product->image) }}"
                                    class="effect8" alt="">
                            </div>
                            <div id="upload-file" class="item up-load">
                                <label class="uploadfile" for="myFile">
                                    <span class="icon">
                                        <i class="icon-upload-cloud"></i>
                                    </span>
                                    <span class="body-text">Drop your images here or select <span class="tf-color">click
                                            to
                                            browse</span></span>
                                    <input type="file" id="myFile" name="image" accept="image/*">
                                </label>
                            </div>
                        </div>
                        @error('image')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>
                    <fieldset>
                        <div class="body-title">Upload images <span class="tf-color-1">*</span>
                        </div>
                        <div class="upload-image flex-grow" id="galPreview">

                            @foreach ($product->media()->where('category', 'product_images')->get() as $image)
                                <div class="item">
                                    <img src="{{ asset($image->path) }}" class="effect8" alt="">
                                </div>
                            @endforeach
                            <div id="upload-files" class="item up-load">
                                <label class="uploadfile" for="gFile">
                                    <span class="icon">
                                        <i class="icon-upload-cloud"></i>
                                    </span>
                                    <span class="body-text">Drop your images here or select <span class="tf-color">click
                                            to
                                            browse</span></span>
                                    <input type="file" multiple id="gFile" name="images[]" accept="image/*">
                                </label>
                            </div>
                        </div>
                        @error('image')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>
                    <fieldset>
                        <div class="body-title">Size images <span class="tf-color-1">*</span>
                        </div>
                        <div class="upload-image flex-grow">
                            <div class="item" id="sizechartpreview">
                                <img src="{{ asset($product->SizeChart) }}"
                                    class="effect8" alt="">
                            </div>
                            <div id="upload-file" class="item up-load">
                                <label class="uploadfile" for="sizechart">
                                    <span class="icon">
                                        <i class="icon-upload-cloud"></i>
                                    </span>
                                    <span class="body-text">Drop your images here or select <span class="tf-color">click
                                            to
                                            browse</span></span>
                                    <input type="file" id="sizechart" name="sizechart" accept="image/*">
                                </label>
                            </div>
                        </div>
                        @error('image')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>
                    <div class="cols gap22">
                        <fieldset class="name">
                            <div class="body-title mb-10">Is Active</div>
                            <div class="checkbox mb-10">
                                <input type="checkbox" name="status" id="" {{ $product->status == 1 ? 'checked' : '' }}>
                            </div>
                            @error('featured')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </fieldset>
                    </div>
                    <div class="cols gap22">
                        <fieldset class="name">
                            <div class="body-title mb-10">Featured</div>
                            <div class="select mb-10">
                                <select class="" name="featured">
                                    <option value="0" {{ $product->featured == 0 ? 'selected' : '' }}>No</option>
                                    <option value="1" {{ $product->featured == 1 ? 'selected' : '' }}>Yes</option>
                                </select>
                            </div>
                            @error('featured')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </fieldset>

                    </div>


                    <h6>Product Sizes</h6>
                    <div id="sizes-container">
                        @if ($product->sizes()->count() > 0)

                            @foreach ($product->sizes as $size)
                                <div class="size-row mb-5" data-id="{{ $size->id }}">
                                    <input type="text" name="sizes[]" value="{{ $size->name }}" placeholder="Enter size">

                                    <button type="button" onclick="deleteSize({{ $size->id }})">Delete</button>
                                </div>
                            @endforeach
                        @endif
                    </div>

                    <button type="button" onclick="addSize()">+ Add Size</button>



                </div>
            </form>
            <!-- /form-add-product -->
        </div>
        <!-- /main-content-wrap -->
    </div>
    <!-- content area end -->
@endsection
@push('scripts')
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
            $('#sizechart').on('change', function() {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#sizechartpreview').show();
                    $('#sizechartpreview img').attr('src', e.target.result);
                }
                reader.readAsDataURL(this.files[0]);
            });
             $('#gFile').on('change', function() {

                var photos = this.files;
                if(photos.length > 0){
                    $('#galPreview item').remove();
                }
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

    <script>
        let sizeId = 0;

        function addSize(value = "") {
            sizeId++;
            const container = document.getElementById("sizes-container");

            const div = document.createElement("div");
            div.className = "size-row";
            div.className = "mb-5";
            div.setAttribute("data-id", sizeId);

            div.innerHTML = `
        <input type="text" name="sizes[]" value="${value}" placeholder="Enter size">
        <button type="button" onclick="editSize(${sizeId})">Edit</button>
        <button type="button" onclick="deleteSize(${sizeId})">Delete</button>
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
      <script src="https://cdn.tiny.cloud/1/6fc0o57nwmnuyujo3x2t2m7qttqr09s74djxb47lnzygcixp/tinymce/8/tinymce.min.js"
        referrerpolicy="origin" crossorigin="anonymous"></script>

    <script>
        tinymce.init({
            selector: '#editor',
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',


        });
    </script>
@endpush
