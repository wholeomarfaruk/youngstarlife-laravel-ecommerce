
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
                        <div class="text-tiny">Setup Google Analytics</div>
                    </li>
                </ul>
            </div>
            <div class="wg-box">
                @if (Session::has('status'))

                    <div class="alert alert-success" role="alert">
                        {{ Session::get('status') }}
                    </div>

                @endif
                <form class="form-new-product form-style-1 needs-validation" method="POST" action="{{route('admin.google.analytics.update')}}" novalidate>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="id" value="{{$google_analytics->id}}">
                    <fieldset class="name">

                        <div class="body-title">Google Analytics Code <span class="tf-color-1">*</span></div>
                        <textarea class="flex-grow @error('code') is-invalid @enderror" placeholder="Google Analytics Code" name="code"
                            tabindex="0" aria-required="true" required="" rows="10">{{$google_analytics->code}}</textarea>
                            @error('code')

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
