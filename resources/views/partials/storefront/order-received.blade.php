{{-- Order confirmation (an Ops order). Rendered by /checkout/order/{id} and injected into /checkout after the order is placed (no reload). --}}
@php($invoice = \App\Services\Storefront\CheckoutService::publicNumber($order))
<div class="row justify-content-center order-main-box mt-2">
    <div class="col-md-8 order-info-box">
        <div class="order-text-site mt-4">
            <div class="ck-success-icon mb-2"><i class="fa-solid fa-circle-check"></i></div>
            <h6 class="thanks">অসংখ্য ধন্যবাদ!</h6>
            <h2 class="titel text-start">অর্ডার সাকসেসফুল</h2>
            <p class="order-some-text">
                আপনার অর্ডারের জন্য কৃতজ্ঞতা জানাচ্ছি, কিছুক্ষনের মধ্যে অর্ডারটি
                প্রসেস করা হবে। শীঘ্রই আমরা আপনার সাথে যোগাযোগ করব, সাথেই থাকুন।
            </p>
        </div>
        <div class="row date-id mt-4 mb-3">
            <div class="col">
                <span class="order-id">Invoice ID:</span>
                <span class="order-number">{{ $invoice }}</span>
            </div>
            <div class="col text-end">
                <span class="date">Date: </span>
                <span class="date-time">{{ $order->placed_at?->format('d M Y, h:i A') }}</span>
            </div>
        </div>
        <hr class="m-0">
        @foreach ($orderItems as $item)
            <div class="d-flex order-card p-2">
                <img src="{{ $item->variant?->product?->thumb_url }}" alt="" class="me-2" decoding="async" />
                <div>
                    <h5 class="order-product-name">{{ $item->variant?->product?->name }}</h5>
                    @if ($item->variant?->size)<p class="order-product-size">Size: {{ $item->variant->size }}</p>@endif
                </div>
                <p class="order-product-price ms-auto">{{ $item->unit_price }} x {{ $item->qty }} = {{ $item->subtotal }} টাকা</p>
            </div>
            <hr class="m-0">
        @endforeach

        <div class="order-price-unit mt-4">
            <div class="d-flex justify-content-between">
                <p class="delivery-info">Subtotal</p>
                <p class="order-price-unit">{{ $order->subtotal }} Tk</p>
            </div>
            <div class="d-flex justify-content-between">
                <p class="delivery-info">Delivery</p>
                <p class="order-price-unit">{{ $order->delivery_charge }} Tk</p>
            </div>
            <hr>
            <div class="d-flex justify-content-between">
                <p class="delivery-info-total">Total</p>
                <p class="order-price-unit-total">{{ $order->total }} Tk</p>
            </div>
        </div>
        <hr style="margin: 30px 0px;">
        <div class="row person-information">
            <div class="col-md-6">
                <h5 class="some-titel">Address</h5>
                <h6 class="person-name">{{ $order->ship_name }}</h6>
                <p class="order-address">{{ $order->ship_address }} <br>{{ $order->ship_phone }}</p>
            </div>
            <div class="col-md-6">
                <h5 class="some-titel">Delivery</h5>
                <h6 class="delivery-type">{{ site()->text('content.payment_label') }}</h6>
                <p class="delivery-notice">{{ site()->text('content.order_thanks', ['{invoice}' => $invoice]) }}</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 my-4">
            <a href="{{ route('shop') }}" class="btn btn-primary bg-primary-color fw-bold">আরো কেনাকাটা করুন</a>
            @if ($wa = site()->whatsappLink(site()->text('content.order_whatsapp_text', ['{invoice}' => $invoice])))<a href="{{ $wa }}" target="_blank" class="btn btn-outline-success fw-bold"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>@endif
        </div>
    </div>
</div>
