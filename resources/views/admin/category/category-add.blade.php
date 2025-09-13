@extends('layouts.admin')

@section('content')
    <!-- content area start -->
    <div class="main-content-inner">
        <div class="main-content-wrap">
            <div class="flex items-center flex-wrap justify-between gap20 mb-27">
                <h3>Category infomation</h3>
                <ul class="breadcrumbs flex items-center flex-wrap justify-start gap10">
                    <li>
                        <a href="#">
                            <div class="text-tiny">Dashboard</div>
                        </a>
                    </li>
                    <li>
                        <i class="icon-chevron-right"></i>
                    </li>
                    <li>
                        <a href="{{ route('admin.categories') }}">
                            <div class="text-tiny">Categories</div>
                        </a>
                    </li>
                    <li>
                        <i class="icon-chevron-right"></i>
                    </li>
                    <li>
                        <div class="text-tiny">New Category</div>
                    </li>
                </ul>
            </div>
            <!-- new-category -->
            <div class="wg-box">
                <form class="form-new-product form-style-1 needs-validation" action="{{ route('admin.categories.store') }}"
                    method="POST" enctype="multipart/form-data" novalidate>
                    @csrf
                    <fieldset class="name">
                        <div class="body-title">{{ __('Category Name') }} <span class="tf-color-1">*</span></div>
                        <input class="flex-grow @error('name') is-invalid @enderror" type="text" placeholder="category name"
                            name="name" tabindex="0" aria-required="true" value="{{ old('name') }}" required
                            autocomplete="name" autofocus onchange="stringtoSlug(this.value)">
                        @error('name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>

                    <fieldset class="name">
                        <div class="body-title">Status</div>
                        <div class="select flex-grow">
                            <select class=" @error('is_active') is-invalid @enderror" name="is_active" required>

                                <option value="1" {{ old('is_active', 1) =='1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{old('is_active', '') == '0' ? 'selected' : ''}}>Inactive</option>

                            </select>

                        </div>
                        @error('parent')

                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>

                    @enderror
                    </fieldset>
                    <fieldset class="name">
                        <div class="body-title">Parent Category</div>
                        <div class="select flex-grow">
                            <select class=" @error('parent_id') is-invalid @enderror" name="parent_id" required>
                                <option value="">No parent_id</option>
                                @foreach ($categories as $category)
                                    <optgroup label="{{ $category->name }}">
                                        @foreach ($category->children as $child)
                                            <option value="{{ $child->id }}" {{ old('parent_id', 0) == $child->id ? 'selected' : '' }}>{{ $child->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach

                            </select>

                        </div>
                        @error('parent_id')

                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>

                    @enderror
                    </fieldset>

                    <fieldset class="name">
                        <div class="body-title">Description</div>
                        <textarea class="flex-grow @error('description') is-invalid @enderror" name="description" rows="5">{{ old('description') }}</textarea>
                        @error('description')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </fieldset>
                    <fieldset>
                        <div class="body-title">Upload images <span class="tf-color-1">*</span>
                        </div>
                        <div class="upload-image flex-grow">
                            <div class="item" id="imgpreview" style="display:none">
                                <img src="" class="effect8" alt="">
                            </div>
                            <div class="item up-load">
                                <label class="uploadfile" for="myFile">
                                    <span class="icon">
                                        <i class="icon-upload-cloud"></i>
                                    </span>

                                    <span class="body-text">Drop your images here or select <span
                                            class="tf-color">click to browse</span></span>
                                    <input class="@error('image') is-invalid @enderror" type="file" id="myFile" name="image" accept=".jpg, .jpeg, .png">
                                </label>
                            </div>
                        </div>
                        @error('image')

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
        </div>
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

        })


        function stringtoSlug(str) {
                str = str.replace(/^\s+|\s+$/g, ''); // trim leading/trailing spaces
                str = str.toLowerCase();
                str = str.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
                    .replace(/\s+/g, '-') // collapse whitespace and replace by -
                    .replace(/-+/g, '-'); // collapse dashes

                    console.log(str);

                $('#slug_input').val(str);

            }
    </script>
@endpush
