@extends('layouts.app')
@section('title', 'চেকআউট')

@push('styles')
    <style>
        /* order confirmation (was css/style.css, which also restyles every <section> site-wide) */
        #received-view .order-info-box { box-shadow: rgba(149, 157, 165, .2) 0 8px 24px; padding: 40px 100px; background: #fff; }
        @media (max-width: 768px) { #received-view .order-info-box { padding: 0 10px 10px; } }
        #received-view .titel { color: #2a2b2a; font-weight: bolder; }
        #received-view .thanks { font-size: 13px; color: #4a5466; font-weight: bold; }
        #received-view .order-some-text { color: #7e8ba0; font-size: 14px; line-height: 25px; }
        #received-view .order-id, #received-view .date { color: #54596a; font-weight: bold; }
        #received-view .order-number, #received-view .date-time { color: #9ba2b0; font-weight: bold; font-size: 14px; }
        #received-view .order-card img { width: 15%; max-width: 90px; border-radius: 15px; }
        #received-view .order-product-name, #received-view .order-product-price { font-size: 14px; color: #54596a; }
        #received-view .order-product-name { font-weight: bold; }
        #received-view .order-product-size { color: #9ba2b0; font-size: 13px; }
        #received-view .delivery-info { text-transform: uppercase; font-weight: bold; color: #9ba2b0; font-size: 13px; }
        #received-view .delivery-info-total, #received-view .order-price-unit-total { text-transform: uppercase; font-weight: bold; color: #283147; font-size: 14px; }
        #received-view .order-price-unit { color: #283147; font-size: 13px; }
        #received-view .some-titel { color: #9ba2b0; font-size: 14px; font-weight: bold; }
        #received-view .person-name, #received-view .delivery-type { color: #504f4f; font-weight: bold; font-size: 14px; }
        #received-view .order-address, #received-view .delivery-notice { color: #7e8ba0; font-size: 13px; }
        #checkout-app [hidden] { display: none !important; }
        .ck-card { background: #fff; border: 1px solid #e9e9ef; border-radius: 10px; padding: 16px; }
        .ck-title { font-weight: 700; font-size: 1.25rem; margin-bottom: 12px; }
        .ck-line { display: flex; gap: 12px; padding: 12px 0; border-top: 1px solid #f0f0f4; align-items: center; }
        .ck-line:first-of-type { border-top: 0; }
        .ck-line img { width: 72px; height: 72px; object-fit: cover; border-radius: 8px; flex: none; background: #f4f4f5; }
        .ck-line .ck-name { font-weight: 600; color: #222; text-decoration: none; line-height: 1.3; }
        .ck-line .ck-meta { font-size: .875rem; color: #6b7280; }
        .ck-line.is-unavailable { opacity: .55; }
        .ck-qty { display: inline-flex; align-items: center; border: 1px solid #ddd; border-radius: 999px; overflow: hidden; }
        .ck-qty button { border: 0; background: transparent; width: 32px; height: 32px; font-size: 18px; line-height: 1; color: var(--primary-color); }
        .ck-qty input { width: 40px; border: 0; text-align: center; font-weight: 600; -moz-appearance: textfield; }
        .ck-qty input::-webkit-outer-spin-button, .ck-qty input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .ck-remove { border: 0; background: transparent; color: #9ca3af; font-size: 18px; }
        .ck-remove:hover { color: #dc3545; }
        .ck-sum div { display: flex; justify-content: space-between; padding: 4px 0; }
        .ck-sum .ck-total { font-size: 1.25rem; font-weight: 700; border-top: 1px dashed #ddd; margin-top: 6px; padding-top: 10px; }
        .ck-busy { opacity: .6; pointer-events: none; }
        .ck-empty { text-align: center; padding: 48px 16px; }
        .ck-empty i { font-size: 48px; color: #c4c4cc; }
        .ck-success-icon { font-size: 48px; color: #198754; }
        #place-order .placing-text { display: none; }
        #place-order.is-placing .default-text { display: none; }
        #place-order.is-placing .placing-text { display: inline-flex; align-items: center; gap: 10px; }
        #place-order .order-spinner { width: 20px; height: 20px; border: 3px solid rgba(255, 255, 255, .4); border-top-color: #fff; border-radius: 50%; animation: ck-spin .7s linear infinite; }
        @keyframes ck-spin { to { transform: rotate(360deg); } }
        @media (min-width: 992px) { .ck-sticky { position: sticky; top: 16px; } }
    </style>
@endpush

@section('content')
    <div class="container" id="checkout-app">
        {{-- ── 1. Cart + checkout --}}
        <div id="checkout-view" @if ($state === 'received') hidden @endif>
            @if ($state === 'checkout' && empty($summary['lines']))
                <div class="ck-card ck-empty my-3">
                    <i class="fa-solid fa-bag-shopping"></i>
                    <h4 class="fw-bold mt-3">আপনার কার্ট খালি</h4>
                    <p class="text-muted">পছন্দের প্রোডাক্ট কার্টে যোগ করে এখানে অর্ডার করুন।</p>
                    <a href="{{ route('shop') }}" class="btn btn-primary bg-primary-color fw-bold px-4">প্রোডাক্ট দেখুন</a>
                </div>
            @elseif ($state === 'checkout')
                <h1 class="fs-3 fw-bolder text-primary-color my-3">চেকআউট</h1>
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="ck-card">
                            <div class="ck-title">আপনার কার্ট (<span data-cart-count>{{ $summary['count'] }}</span>)</div>
                            <div id="cart-lines">
                                @foreach ($summary['lines'] as $l)
                                    <div class="ck-line {{ $l['available'] ? '' : 'is-unavailable' }}" data-line="{{ $l['id'] }}">
                                        <img src="{{ $l['image_url'] }}" alt="" loading="lazy" decoding="async">
                                        <div class="flex-grow-1 min-w-0">
                                            @if ($l['slug'])<a class="ck-name" href="{{ route('product.show', $l['slug']) }}">{{ $l['name'] }}</a>@else<span class="ck-name">{{ $l['name'] }}</span>@endif
                                            <div class="ck-meta">
                                                @if ($l['size'])সাইজ: {{ $l['size'] }} · @endif ৳ {{ number_format($l['price']) }}
                                                @if ($l['regular'] > $l['price'])<del class="ms-1">৳ {{ number_format($l['regular']) }}</del>@endif
                                                @unless ($l['available'])<span class="badge bg-danger ms-1">স্টক শেষ</span>@endunless
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between mt-2">
                                                <div class="ck-qty">
                                                    <button type="button" data-step="-1" aria-label="কমান">−</button>
                                                    <input type="number" min="1" max="{{ \App\Services\Storefront\CartService::MAX_QTY }}" value="{{ $l['qty'] }}" inputmode="numeric" aria-label="পরিমাণ">
                                                    <button type="button" data-step="1" aria-label="বাড়ান">+</button>
                                                </div>
                                                <strong class="fs-5" data-line-total>৳ {{ number_format($l['line']) }}</strong>
                                            </div>
                                        </div>
                                        <button type="button" class="ck-remove align-self-start" data-remove aria-label="সরান"><i class="fa-solid fa-xmark"></i></button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <a href="{{ route('shop') }}" class="d-inline-block mt-2 text-primary-color fw-semibold text-decoration-none"><i class="fa-solid fa-plus"></i> আরো প্রোডাক্ট যোগ করুন</a>
                    </div>

                    <div class="col-lg-5">
                        <form id="checkout-form" class="ck-card ck-sticky" action="{{ route('checkout.place') }}" method="post" novalidate>
                            @csrf
                            <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
                            <div class="ck-title">ডেলিভারি তথ্য</div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">আপনার নাম লিখুন</label>
                                <input type="text" name="name" autocomplete="name" class="form-control" required placeholder="Type Your Full Name" value="{{ old('name') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">আপনার মোবাইল লিখুন</label>
                                <input name="phone" id="phone" type="text" class="form-control" required inputmode="numeric" autocomplete="tel" placeholder="01XXXXXXXXX" value="{{ old('phone') }}">
                                <div class="invalid-feedback">সঠিক মোবাইল নম্বর দিন (যেমন 01XXXXXXXXX)</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">আপনার ফুল ঠিকানা লিখুন</label>
                                <textarea name="address" autocomplete="street-address" required class="form-control" rows="3" placeholder="Type Your Full Delivery Address">{{ old('address') }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">ডেলিভারি এরিয়া</label>
                                <select name="delivery_area" class="form-select" required>
                                    @foreach ($areas as $a)
                                        <option value="{{ $a->id }}" data-charge="{{ (float) $a->charge }}">{{ $a->name }} - TK {{ (float) $a->charge }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ck-sum mb-3">
                                <div><span>সাবটোটাল</span><span data-subtotal>৳ {{ number_format($summary['subtotal']) }}</span></div>
                                <div><span>ডেলিভারি চার্জ</span><span data-delivery>৳ {{ number_format($summary['delivery']) }}</span></div>
                                <div class="ck-total"><span>মোট</span><span data-total>৳ {{ number_format($summary['total']) }}</span></div>
                            </div>
                            <button id="place-order" type="submit" class="btn btn-primary bg-primary-color w-100 fw-bold fs-5 py-2">
                                <span class="default-text">অর্ডার কনফার্ম করুন</span>
                                <span class="placing-text"><span class="order-spinner"></span> অর্ডার প্লেস হচ্ছে...</span>
                            </button>
                            <p class="text-muted small text-center mt-2 mb-0"><i class="fa-solid fa-truck"></i> {{ site()->text('content.cod_note') }}</p>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        {{-- ── 2. Order received (same page; filled in place after ordering) --}}
        <div id="received-view" @if ($state !== 'received') hidden @endif>
            @if ($order)
                @include('partials.storefront.order-received', ['order' => $order, 'orderItems' => $orderItems])
            @endif
        </div>
    </div>
@endsection

{{-- purchase fires once per order (session); on the in-page switch it is pushed by the script below instead --}}
@if ($purchase)
    @push('head-scripts')
        <script>
            (function() {
                var p = @json($purchase);
                try { p.user_data.user_id = sessionStorage.getItem('visitorId'); } catch (e) {}
                window.dataLayer = window.dataLayer || [];
                dataLayer.push({ ecommerce: null });
                dataLayer.push(p);
            })();
        </script>
    @endpush
@endif

@push('scripts')
    @if (session('status'))
        <script>
            Swal.fire({ icon: @json(session('status') === 'error' ? 'error' : 'info'), text: @json(session('message')), confirmButtonText: 'ঠিক আছে' });
        </script>
    @endif
    @if ($state === 'checkout' && !empty($summary['lines']))
        <script>
            (function() {
                var form = document.getElementById('checkout-form');
                var token = form.querySelector('input[name=_token]').value;
                var phone = document.getElementById('phone');
                var area = form.querySelector('select[name=delivery_area]');
                var button = document.getElementById('place-order');
                var summary = @json($summary);
                var urls = { item: @json(url('/cart/items')) + '/', autosave: @json(route('checkout.autosave', [], false)) };
                var tk = function(n) { return '৳ ' + Math.round(n).toLocaleString('en-US'); };
                var visitorId = function() { try { return sessionStorage.getItem('visitorId'); } catch (e) { return null; } };

                // ── phone: same rules as App\Support\Phone::normalize()
                var toAscii = function(v) {
                    return String(v || '').replace(/[০-৯]/g, d => d.charCodeAt(0) - 0x09E6).replace(/[٠-٩]/g, d => d.charCodeAt(0) - 0x0660).replace(/[۰-۹]/g, d => d.charCodeAt(0) - 0x06F0);
                };
                var normalizePhone = function(v) {
                    var digits = toAscii(v).replace(/\D/g, '');
                    if (digits.length < 10) return null;
                    var number = digits.slice(-10), prefix = digits.slice(0, -10);
                    if (!/^1[3-9]\d{8}$/.test(number) || !/^(00)?8{0,2}0{0,2}$/.test(prefix)) return null;
                    return '0' + number;
                };
                phone.addEventListener('input', function() {
                    var cleaned = toAscii(phone.value).replace(/\D/g, '');
                    if (cleaned !== phone.value) phone.value = cleaned;
                    phone.classList.toggle('is-invalid', !!cleaned && cleaned.length >= 11 && !normalizePhone(cleaned));
                });
                phone.addEventListener('blur', function() { var n = normalizePhone(phone.value); if (n) phone.value = n; });

                // ── totals
                function render() {
                    var charge = parseFloat(area.selectedOptions[0]?.dataset.charge || 0);
                    document.querySelector('[data-subtotal]').textContent = tk(summary.subtotal);
                    document.querySelector('[data-delivery]').textContent = tk(charge);
                    document.querySelector('[data-total]').textContent = tk(summary.subtotal + charge);
                    document.querySelectorAll('[data-cart-count]').forEach(el => el.textContent = summary.count);
                    window.ysCart && ysCart.setCount(summary.count);
                }
                area.addEventListener('change', render);
                render();

                // ── cart lines: quantity / remove (server is the source of truth for prices)
                function apply(data) {
                    summary = data;
                    window.ysCart && ysCart.sync && ysCart.sync(data);
                    if (!data.lines.length) return location.reload();   // → empty-cart view
                    var ids = data.lines.map(l => String(l.id));
                    document.querySelectorAll('[data-line]').forEach(function(row) {
                        var line = data.lines.find(l => String(l.id) === row.dataset.line);
                        if (!line) return row.remove();
                        row.querySelector('input').value = line.qty;
                        row.querySelector('[data-line-total]').textContent = tk(line.line);
                    });
                    render();
                }
                function send(method, id, body) {
                    var row = document.querySelector('[data-line="' + id + '"]');
                    row && row.classList.add('ck-busy');
                    return fetch(urls.item + id, {
                        method: method, credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify(Object.assign({ delivery_area: area.value }, body || {})),
                    }).then(r => r.ok ? r.json() : Promise.reject(r)).then(apply)
                      .catch(() => Swal.fire({ icon: 'error', text: 'আপডেট করা যায়নি, পেজটি রিফ্রেশ করুন।' }))
                      .finally(() => row && row.classList.remove('ck-busy'));
                }
                var timers = {};
                document.getElementById('cart-lines').addEventListener('click', function(e) {
                    var row = e.target.closest('[data-line]'); if (!row) return;
                    var id = row.dataset.line, input = row.querySelector('input');
                    if (e.target.closest('[data-remove]')) return send('DELETE', id);
                    var step = e.target.closest('[data-step]'); if (!step) return;
                    var qty = Math.max(0, Math.min(parseInt(input.max), (parseInt(input.value) || 1) + parseInt(step.dataset.step)));
                    input.value = qty;
                    clearTimeout(timers[id]); timers[id] = setTimeout(() => send('PATCH', id, { quantity: qty }), 350);   // taps are batched
                });
                document.getElementById('cart-lines').addEventListener('change', function(e) {
                    var row = e.target.closest('[data-line]'); if (!row || e.target.tagName !== 'INPUT') return;
                    send('PATCH', row.dataset.line, { quantity: Math.max(0, parseInt(e.target.value) || 0) });
                });

                // ── tracking
                var items = () => summary.lines.filter(l => l.available).map(l => ({ item_name: l.name, item_id: String(l.catalog_id), price: l.price, quantity: l.qty, item_category: @js(site('pixels.item_category')), item_brand: @js(site('pixels.item_brand')), item_variant: l.size }));
                var track = function(event, extra) {
                    window.dataLayer = window.dataLayer || [];
                    dataLayer.push({ ecommerce: null });
                    dataLayer.push(Object.assign({ event: event, ecommerce: { currency: 'BDT', value: summary.subtotal, items: items() } }, extra || {}));
                };
                track('view_cart', { user_data: { user_id: visitorId() } });

                // ── place order: swap to the confirmation on this page and change the URL to /checkout/order/{id}
                var placing = false;
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var n = normalizePhone(phone.value);
                    phone.setCustomValidity(n ? '' : 'সঠিক মোবাইল নম্বর দিন (যেমন 01XXXXXXXXX)');
                    if (n) phone.value = n;
                    if (!form.checkValidity()) { phone.classList.toggle('is-invalid', !n); return form.reportValidity(); }
                    if (placing) return;
                    placing = true;
                    button.classList.add('is-placing'); button.disabled = true;

                    var charge = parseFloat(area.selectedOptions[0]?.dataset.charge || 0);
                    track('begin_checkout', { ecommerce: { currency: 'BDT', value: summary.subtotal + charge, items: items() },
                        user_data: { first_name: form.elements['name'].value || null, phone_number: '+88' + n, street: form.elements['address'].value || null, user_id: visitorId() } });

                    fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(r => r.json().then(data => ({ status: r.status, data: data })))
                        .then(function(res) {
                            if (res.status === 200 && res.data.ok) return showReceived(res.data);
                            var errors = res.data.errors || {};
                            if (errors.duplicate) Swal.fire({ icon: 'info', title: 'অর্ডার আগেই হয়েছে', text: errors.duplicate[0], confirmButtonText: 'ঠিক আছে' });
                            else Swal.fire({ icon: 'warning', title: 'দুঃখিত!', text: (Object.values(errors)[0] || [res.data.message || 'আবার চেষ্টা করুন'])[0], confirmButtonText: 'ঠিক আছে' });
                            if (errors.phone) phone.classList.add('is-invalid');
                            reset();
                        })
                        .catch(function() {
                            Swal.fire({ icon: 'error', title: 'দুঃখিত!', text: 'ইন্টারনেট সংযোগ চেক করে আবার চেষ্টা করুন।', confirmButtonText: 'ঠিক আছে' });
                            reset();   // same checkout_token → a retry can never create a second order
                        });
                });
                function reset() { placing = false; button.classList.remove('is-placing'); button.disabled = false; }

                function showReceived(data) {
                    window.orderPlaced = true;   // stops the autosave below
                    var received = document.getElementById('received-view');
                    received.innerHTML = data.html;
                    document.getElementById('checkout-view').hidden = true;
                    received.hidden = false;
                    history.pushState({ order: data.order_id }, '', data.url);
                    document.title = 'অর্ডার সাকসেসফুল #' + data.invoice + ' · ' + @js(site()->storeName());
                    window.scrollTo({ top: 0 });
                    window.ysCart && ysCart.setCount(0);
                    window.ysCart && ysCart.sync && ysCart.sync({ lines: [], count: 0, subtotal: 0, delivery: 0, total: 0 });
                    if (data.purchase) {
                        data.purchase.user_data.user_id = visitorId();
                        window.dataLayer = window.dataLayer || [];
                        dataLayer.push({ ecommerce: null });
                        dataLayer.push(data.purchase);
                    }
                }
                // back / forward between /checkout and /checkout/order/{id}: let the server render that state
                window.addEventListener('popstate', () => location.reload());

                // ── abandoned checkout lead: once the phone is valid, 1.5s after typing stops, and when the tab is hidden
                var lastSent = '', timer = null;
                function autosave(useBeacon) {
                    clearTimeout(timer);
                    if (placing || window.orderPlaced) return;
                    var data = { phone: normalizePhone(phone.value) || '', name: form.elements['name'].value, address: form.elements['address'].value, delivery_area: area.value };
                    if (!data.phone) return;
                    var snapshot = JSON.stringify(data) + summary.count;
                    if (snapshot === lastSent) return;
                    lastSent = snapshot;
                    var body = new FormData(); body.append('_token', token);
                    Object.keys(data).forEach(k => body.append(k, data[k]));
                    try {
                        if (useBeacon && navigator.sendBeacon && navigator.sendBeacon(urls.autosave, body)) return;
                        fetch(urls.autosave, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).catch(() => lastSent = '');
                    } catch (e) {}
                }
                ['input', 'change'].forEach(ev => form.addEventListener(ev, () => { clearTimeout(timer); timer = setTimeout(() => autosave(false), 1500); }));
                document.addEventListener('visibilitychange', () => document.visibilityState === 'hidden' && autosave(true));
                window.addEventListener('pagehide', () => autosave(true));

                // back button from another page restores this one from cache: unlock the button
                window.addEventListener('pageshow', e => e.persisted && reset());
            })();
        </script>
    @elseif ($state === 'received')
        <script>window.addEventListener('popstate', () => location.reload());</script>
    @endif
@endpush
