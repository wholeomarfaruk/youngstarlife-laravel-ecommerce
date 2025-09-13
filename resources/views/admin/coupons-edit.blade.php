
@extends('layouts.admin')

@section('content')
    <!-- content area start -->
    <div class="main-content-inner">
        <div class="main-content-wrap">
            <div class="flex items-center flex-wrap justify-between gap20 mb-27">
                <h3>Coupon infomation</h3>
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
                        <a href="{{ route('admin.coupons') }}">
                            <div class="text-tiny">Coupons</div>
                        </a>
                    </li>
                    <li>
                        <i class="icon-chevron-right"></i>
                    </li>
                    <li>
                        <div class="text-tiny">Edit Coupon</div>
                    </li>
                </ul>
            </div>
            <div class="wg-box">
                <form class="form-new-product form-style-1 needs-validation" method="POST" action="{{route('admin.coupons.update',['id'=>$coupon->id])}}" novalidate>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="id" value="{{$coupon->id}}">

                    <fieldset class="name">

                        <div class="body-title">Coupon Code <span class="tf-color-1">*</span></div>
                        <input class="flex-grow @error('code') is-invalid @enderror" type="text" placeholder="Coupon Code" name="code"
                            tabindex="0" value="{{$coupon->code}}" aria-required="true" required="required">
                            @error('code')

                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>

                            @enderror
                    </fieldset>
                    <fieldset class="category">
                        <div class="body-title">Coupon Type</div>
                        <div class="select flex-grow">
                            <select class=" @error('type') is-invalid @enderror" name="type" required>

                                <option value="" {{$coupon->type == '' ? 'selected' : ''}}>Select</option>
                                <option value="fixed" {{$coupon->type == 'fixed' ? 'selected' : ''}}>Fixed</option>
                                <option value="percent" {{$coupon->type == 'percent' ? 'selected' : '' }}>Percent</option>
                            </select>

                        </div>
                        @error('type')

                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>

                    @enderror
                    </fieldset>
                    <fieldset class="name">
                        <div class="body-title">Value <span class="tf-color-1">*</span></div>
                        <input class="flex-grow @error('value') is-invalid @enderror" type="text" placeholder="Coupon Value" name="value"
                            tabindex="0" value="{{ $coupon->value}}" aria-required="true" required="required">
                            @error('value')

                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>

                            @enderror
                        </fieldset>
                    <fieldset class="name">
                        <div class="body-title">Cart Value <span class="tf-color-1">*</span></div>
                        <input class="flex-grow @error('cart_value') is-invalid @enderror" type="text" placeholder="Cart Value"
                            name="cart_value" tabindex="0" value="{{$coupon->cart_value}}" aria-required="true"
                            required="required">
                            @error('cart_value')

                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>

                        @enderror
                    </fieldset>
                    <fieldset class="name">
                        <div class="body-title">Expiry Date <span class="tf-color-1">*</span></div>
                        <input class="flex-grow @error('expiry_date') is-invalid @enderror" type="date" placeholder="Expiry Date"
                            name="expiry_date" tabindex="0" value="{{$coupon->expiry_date}}" aria-required="true"
                            required="required">
                            @error('expiry_date')

                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>

                            @enderror
                    </fieldset>

                    <div class="bot">
                        <div></div>
                        <button class="tf-button w208" type="submit">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- content area end -->
@endsection
