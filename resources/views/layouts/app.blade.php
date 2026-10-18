<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- title / description / share card: page sections (title, meta_description, og_image) over Ops → Settings → SEO --}}
    @php($pageTitle = site()->title(trim($__env->yieldContent('title')) ?: null))
    @php($pageDescription = trim($__env->yieldContent('meta_description')) ?: site('seo.description'))
    <title>{{ $pageTitle }}</title>
    @if ($pageDescription)<meta name="description" content="{{ $pageDescription }}">@endif
    @unless (site('seo.indexable', true))<meta name="robots" content="noindex, nofollow">@endunless
    @if (site('seo.search_console'))<meta name="google-site-verification" content="{{ site('seo.search_console') }}">@endif
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ site()->storeName() }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    @if ($pageDescription)<meta property="og:description" content="{{ $pageDescription }}">@endif
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ trim($__env->yieldContent('og_image')) ?: site()->shareImageUrl() }}">
    <meta name="twitter:card" content="summary_large_image">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ site()->faviconUrl() }}">
    <!-- Hind Siliguri (header, product page, cart) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- font awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-DxV+EoADOkOygM4IR9yXP8Sb2qwgidEmeqAEmDKIOfPRQZOWbXCzLC6vjbZyy0vPisbH2SyW27+ddLVCN+OMzQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Bootstrap CSS  -->
    <link href="{{ asset('frontend/library/bootstrap/bootstrap.min.css') }}" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <!-- Swiperjs css -->
    <link rel="stylesheet" href="{{ asset('frontend/library/swiper/swiper-bundle.min.css') }}">
    <!-- Fancy Box css -->
    <link rel="stylesheet" href="{{ asset('frontend/library/fancybox/fancybox.css') }}">
    <!-- Custom Css  -->
    <style>
        @font-face {
            font-family: 'SolaimanLipi';
            src: url("{{ asset('fonts/SolaimanLipi.ttf') }}") format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }
    </style>

    <link rel="stylesheet" href="{{ asset('frontend/css/style.css?v=1.0.2') }}">
    {{-- Settings → Branding → brand colour --}}
    @if (preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) site('branding.primary_color')))<style>:root { --primary-color: {{ site('branding.primary_color') }}; }</style>@endif



    <style>
        /* header + cart drawer (design: Product section redesign) — header colour = Settings → Branding → brand colour */
        .ys-strip { background: #fff; border-bottom: 1px solid #e4e4e0; font-family: 'Hind Siliguri', system-ui, sans-serif; }
        .ys-strip-in { max-width: 1200px; margin: 0 auto; padding: 6px 20px; display: flex; justify-content: center; gap: 18px; font-size: 13px; flex-wrap: wrap; }
        .ys-strip a { color: #3f423f; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .ys-strip a:hover { color: #262826; text-decoration: underline; }
        .ys-strip .fa-whatsapp { color: #25D366; }
        .ys-header { background: var(--primary-color, #545754); position: sticky; top: 0; z-index: 1030; box-shadow: 0 2px 8px rgba(0,0,0,.12); font-family: 'Hind Siliguri', system-ui, sans-serif; }
        .ys-header-in { max-width: 1200px; margin: 0 auto; padding: 0 20px; height: 60px; display: flex; align-items: center; gap: 12px; }
        .ys-logo { display: flex; align-items: center; gap: 8px; color: #fff; text-decoration: none; flex: none; }
        .ys-logo:hover { color: #fff; }
        .ys-logo-mark { width: 34px; height: 34px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .ys-logo-mark img { width: 100%; height: 100%; object-fit: cover; }
        .ys-logo-name { font-size: 17px; font-weight: 600; letter-spacing: .3px; white-space: nowrap; }
        .ys-nav { flex: 1; min-width: 0; display: flex; justify-content: safe flex-end; gap: 4px; }
        .ys-nav-item { position: relative; flex: none; }
        .ys-nav a { color: #fff; font-size: 15px; padding: 8px 10px; border-radius: 6px; white-space: nowrap; text-decoration: none; display: block; }
        .ys-nav a:hover, .ys-nav a.is-on { background: rgba(255,255,255,.12); color: #fff; }
        .ys-caret { font-size: 11px; margin-left: 4px; opacity: .8; }
        .ys-nav-flat { display: none !important; }
        .ys-sub { display: none; position: absolute; top: 100%; left: 0; min-width: 180px; background: #fff; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,.15); padding: 6px; z-index: 1040; }
        .ys-nav-item:hover > .ys-sub, .ys-nav-item:focus-within > .ys-sub { display: block; }
        .ys-sub a, .ys-sub button { display: block; width: 100%; text-align: left; color: #262826 !important; background: none; border: 0; font-size: 15px; padding: 8px 10px; border-radius: 6px; text-decoration: none; white-space: nowrap; }
        .ys-sub a:hover, .ys-sub button:hover, .ys-sub a.is-on { background: #f4f4f2 !important; }
        .ys-sub-name { display: block; padding: 6px 10px; font-size: 13px; color: #7a7d7a; border-bottom: 1px solid #f0f0ed; margin-bottom: 4px; }
        .ys-sub form { margin: 0; }
        .ys-round { position: relative; flex: none; width: 42px; height: 42px; border-radius: 50%; border: 0; background: rgba(255,255,255,.12); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; text-decoration: none; list-style: none; }
        .ys-round:hover { background: rgba(255,255,255,.22); color: #fff; }
        .ys-round::-webkit-details-marker { display: none; }
        .ys-account { position: relative; flex: none; }
        .ys-account[open] > .ys-sub { display: block; }
        .ys-sub-right { left: auto; right: 0; top: calc(100% + 6px); }
        .ys-cart-badge { position: absolute; top: -2px; right: -2px; background: #c92a2a; color: #fff; border-radius: 10px; min-width: 19px; height: 19px; padding: 0 5px; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; border: 2px solid var(--primary-color, #545754); line-height: 1; }
        .ys-cart-badge[hidden] { display: none; }
        .ys-cart-bump { animation: ys-bump .4s ease; }
        @keyframes ys-bump { 50% { transform: scale(1.15); } }
        .ys-burger { display: none; }
        @media (max-width: 991px) {
            .ys-burger { display: flex; order: 10; }   /* far right, after cart + account */
            .ys-nav { display: none; }   /* phones / tablets: the ☰ sidebar has the menu */
            .ys-logo { margin-right: auto; min-width: 0; }
            .ys-logo-name { overflow: hidden; text-overflow: ellipsis; }
        }
        @media (max-width: 480px) { .ys-header-in { padding: 0 12px; gap: 8px; } .ys-logo-name { font-size: 16px; } }
        @media (max-width: 340px) { .ys-logo-name { display: none; } }
        /* live search modal */
        .ys-search { position: fixed; inset: 0; z-index: 1060; background: rgba(20,22,20,.5); display: flex; justify-content: center; align-items: flex-start; padding: 64px 16px 16px; font-family: 'Hind Siliguri', system-ui, sans-serif; }
        .ys-search[hidden] { display: none; }
        .ys-search-box { background: #fff; border-radius: 14px; width: 100%; max-width: 640px; max-height: calc(100vh - 96px); display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,.25); animation: ys-drop .15s ease-out; }
        @keyframes ys-drop { from { transform: translateY(-8px); opacity: .6; } }
        .ys-search-bar { display: flex; align-items: center; gap: 10px; padding: 10px 10px 10px 16px; border-bottom: 1px solid #ececea; color: #7a7d7a; margin: 0; }
        .ys-search-bar input { flex: 1; min-width: 0; border: 0; outline: 0; font-size: 17px; padding: 8px 0; color: #262826; background: transparent; }
        .ys-search-bar input::-webkit-search-cancel-button { display: none; }
        .ys-search-x { border: 0; background: #f4f4f2; width: 36px; height: 36px; border-radius: 50%; font-size: 22px; line-height: 1; color: #3f423f; cursor: pointer; flex: none; }
        .ys-search-body { overflow-y: auto; overscroll-behavior: contain; padding: 6px; }
        .ys-search-hint { margin: 0; padding: 14px 12px; color: #7a7d7a; font-size: 14px; text-align: center; }
        .ys-search-list { list-style: none; margin: 0; padding: 0; }
        .ys-search-list a { display: flex; align-items: center; gap: 12px; padding: 8px 10px; border-radius: 10px; color: #262826; text-decoration: none; }
        .ys-search-list a:hover, .ys-search-list a.is-on { background: #f4f4f2; }
        .ys-search-list img { width: 52px; height: 52px; border-radius: 8px; object-fit: cover; flex: none; background: #ececea; }
        .ys-search-name { flex: 1; min-width: 0; font-size: 15px; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .ys-search-name mark { background: #fff1b8; padding: 0; color: inherit; }
        .ys-search-price { flex: none; text-align: right; font-weight: 700; font-size: 15px; white-space: nowrap; }
        .ys-search-price del { display: block; font-weight: 400; font-size: 12px; color: #9a9d9a; }
        .ys-search-all { display: block; margin: 4px 6px 6px; padding: 11px; border-radius: 10px; background: var(--primary-color, #545754); color: #fff !important; text-align: center; text-decoration: none; font-weight: 600; }
        .ys-search-all[hidden] { display: none; }
        .ys-search.is-loading .ys-search-bar { background: linear-gradient(90deg, transparent, #f4f4f2, transparent) 0 0 / 200% 100%; animation: ys-shimmer 1s linear infinite; }
        @keyframes ys-shimmer { to { background-position: -200% 0; } }
        @media (max-width: 575px) {
            .ys-search { padding: 0; align-items: stretch; }
            .ys-search-box { max-width: none; max-height: none; border-radius: 0; }
        }
        /* mobile sidebar (☰) */
        .ys-side { width: min(320px, 86vw) !important; font-family: 'Hind Siliguri', system-ui, sans-serif; display: flex; flex-direction: column; }
        .ys-side-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 12px 12px 16px; background: var(--primary-color, #545754); color: #fff; }
        .ys-side-brand { display: flex; align-items: center; gap: 10px; color: #fff !important; text-decoration: none; font-size: 17px; font-weight: 600; min-width: 0; }
        .ys-side-brand span:last-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ys-side-head .ys-dr-x { color: #fff; }
        .ys-side-body { flex: 1; overflow-y: auto; overscroll-behavior: contain; padding: 8px; }
        .ys-side-title { padding: 8px 10px 4px; font-size: 13px; color: #7a7d7a; }
        .ys-side-link, .ys-side-group > summary { display: flex; align-items: center; gap: 10px; padding: 12px 10px; border-radius: 8px; color: #262826 !important; text-decoration: none; font-size: 16px; font-weight: 500; cursor: pointer; list-style: none; }
        .ys-side-group > summary::-webkit-details-marker { display: none; }
        .ys-side-link:hover, .ys-side-group > summary:hover, .ys-side-link.is-on, .ys-side-group > summary.is-on { background: #f4f4f2; }
        .ys-side-link i, .ys-side-group > summary i { width: 18px; text-align: center; color: #545754; }
        .ys-side-caret { margin-left: auto; font-size: 12px; transition: transform .15s; }
        .ys-side-group[open] .ys-side-caret { transform: rotate(180deg); }
        .ys-side-sub { display: block; padding: 10px 10px 10px 38px; border-radius: 8px; color: #3f423f !important; text-decoration: none; font-size: 15px; }
        .ys-side-sub:hover, .ys-side-sub.is-on { background: #f4f4f2; }
        .ys-side-foot { border-top: 1px solid #ececea; padding: 10px 12px calc(10px + env(safe-area-inset-bottom)); display: flex; flex-direction: column; gap: 2px; }
        .ys-side-foot a { display: flex; align-items: center; gap: 10px; padding: 8px 6px; color: #3f423f !important; text-decoration: none; font-size: 14px; }
        .ys-side-foot .fa-whatsapp { color: #25D366; }
        /* cart drawer */
        .ys-drawer { --bs-offcanvas-width: min(400px, 100%); max-width: 100vw; font-family: 'Hind Siliguri', system-ui, sans-serif; color: #262826; box-shadow: -8px 0 30px rgba(0,0,0,.12); }
        .offcanvas-backdrop.show { opacity: 1; background: rgba(20,20,20,.4); }
        .ys-dr-head { display: flex; align-items: center; justify-content: space-between; padding: 16px 18px; border-bottom: 1px solid #eee; }
        .ys-dr-title { font-size: 18px; font-weight: 700; }
        .ys-dr-x { border: 0; background: none; font-size: 26px; line-height: 1; cursor: pointer; color: #262826; padding: 0 4px; }
        .ys-dr-added { margin: 12px 18px 0; padding: 10px 12px; border-radius: 10px; background: #eaf7ef; color: #1d5e36; font-size: 14px; }
        .ys-dr-added[hidden] { display: none; }
        .ys-dr-body { flex: 1; overflow-y: auto; padding: 12px 18px; display: flex; flex-direction: column; gap: 12px; }
        .ys-dl { display: grid; grid-template-columns: 64px 1fr auto; gap: 12px; align-items: start; padding-bottom: 12px; border-bottom: 1px solid #f0f0ed; transition: background .6s; }
        .ys-dl img { width: 64px; height: 76px; object-fit: cover; border-radius: 8px; background: #ece9e0; display: block; }
        .ys-dl-main { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
        .ys-dl-name { font-size: 14px; font-weight: 600; line-height: 1.35; color: #262826; text-decoration: none; }
        .ys-dl-name:hover { color: #262826; text-decoration: underline; }
        .ys-dl-meta { font-size: 13px; color: #6b6e6b; }
        .ys-dl-end { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
        .ys-dl-end b { font-size: 15px; white-space: nowrap; }
        .ys-dl-x { border: 0; background: none; color: #c92a2a; font-size: 13px; cursor: pointer; padding: 0; }
        .ys-dl.is-off { opacity: .55; }
        .ys-dl.is-new { background: #fff8e1; }
        .ys-drawer.is-syncing [data-drawer-checkout] { pointer-events: none; opacity: .65; }
        .ys-drawer.is-syncing .ys-dl-x { opacity: .5; }
        .ys-dl-empty { color: #7a7d7a; text-align: center; padding: 40px 0; }
        .ys-dl-empty a { display: inline-block; margin-top: 12px; color: #262826; font-weight: 600; }
        .ys-dl-skel span { width: 64px; height: 76px; border-radius: 8px; }
        .ys-dl-skel i { display: block; height: 12px; border-radius: 6px; margin: 6px 0 10px; width: 80%; }
        .ys-dl-skel i + i { width: 45%; }
        .ys-dl-skel span, .ys-dl-skel i { background: linear-gradient(90deg, #f0f0f4 25%, #e6e6ec 50%, #f0f0f4 75%); background-size: 200% 100%; animation: ys-shimmer 1.2s infinite; }
        @keyframes ys-shimmer { to { background-position: -200% 0; } }
        .ys-dr-foot { border-top: 1px solid #eee; padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; }
        .ys-dr-foot[hidden] { display: none; }
        .ys-dr-sub { display: flex; justify-content: space-between; font-size: 17px; font-weight: 700; }
        .ys-dr-note { font-size: 13px; color: #6b6e6b; }
        .ys-dr-btn { height: 50px; border-radius: 10px; background: #262826; color: #fff; font-size: 17px; font-weight: 700; display: flex; align-items: center; justify-content: center; text-decoration: none; }
        .ys-dr-btn:hover { background: #3f423f; color: #fff; }
        .ys-dr-btn-o { height: 44px; border-radius: 10px; border: 1.5px solid #d6d6d1; background: #fff; font-size: 15px; cursor: pointer; color: #262826; }
        [x-cloak] { display: none !important; }
    </style>
    @stack('styles')

    {{-- Settings → Pixels & tracking (tags load on production only unless "production only" is off) --}}
    @php($track = app()->environment('production') || ! site('pixels.production_only', true))
    @php($gtm = $track ? site('pixels.gtm') : null)
    @if ($gtm)
        <!-- Google Tag Manager -->
        <script>
            (function(w, d, s, l, i) {
                w[l] = w[l] || [];
                w[l].push({
                    'gtm.start': new Date().getTime(),
                    event: 'gtm.js'
                });
                var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s),
                    dl = l != 'dataLayer' ? '&l=' + l : '';
                j.async = true;
                j.src =
                    'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
                f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', @js($gtm));
        </script>
    @endif
    @if ($track && site('pixels.ga4'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ site('pixels.ga4') }}"></script>
        <script>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', @js(site('pixels.ga4')));</script>
    @endif
    @if ($track && site('pixels.fb_pixel'))
        <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init', @js(site('pixels.fb_pixel')));fbq('track', 'PageView');</script>
    @endif
    @if ($track && site('pixels.tiktok'))
        <script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.load=function(e){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date;var o=d.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load(@js(site('pixels.tiktok')));ttq.page()}(window,document,'ttq');</script>
    @endif

    <!-- End Google Tag Manager -->
    {{-- dataLayer events that must be queued before the rest of the page loads --}}
    @stack('head-scripts')
    @if (site('pixels.fb_domain_verification'))<meta name="facebook-domain-verification" content="{{ site('pixels.fb_domain_verification') }}" />@endif
</head>

<body class="bg-white bg-opacity-50">
    @if ($gtm)
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}" height="0" width="0"
                style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
    @endif
    {{-- Ops → Storefront → Announcement & popup --}}
    @php($announce = site('storefront.announcement'))
    @if (($announce['on'] ?? false) && ($announce['text'] ?? ''))
        <div class="bg-primary-color text-white text-center small fw-semibold py-2 px-3">
            @if ($announce['link'] ?? '')<a href="{{ $announce['link'] }}" class="text-white text-decoration-none">{{ $announce['text'] }}</a>@else{{ $announce['text'] }}@endif
        </div>
    @endif
    {{-- header (design: Product section redesign) — menus from Ops → Storefront → Navigation menus --}}
    <div class="ys-strip">
        <div class="ys-strip-in">
            @foreach ($menus->get('topbar')['items'] as $item)
                <a href="{{ $item['url'] }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif>@if ($item['icon'])<i class="{{ $item['icon'] }}" aria-hidden="true"></i>@endif{{ $item['label'] }}</a>
            @endforeach
        </div>
    </div>
    <header id="header-area" class="ys-header">
        <div class="ys-header-in">
            {{-- phones / tablets: ☰ opens the sidebar (Ops → Storefront → Navigation menus → Mobile sidebar) --}}
            <button type="button" class="ys-round ys-burger" data-bs-toggle="offcanvas" data-bs-target="#ysMenu" aria-controls="ysMenu" aria-label="মেনু খুলুন">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <a href="{{ route('home') }}" class="ys-logo"><span class="ys-logo-mark"><img src="{{ site()->logoUrl() }}" alt="" width="34" height="34"></span><span class="ys-logo-name">{{ site()->storeName() }}</span></a>
            <nav class="ys-nav" aria-label="মেনু">
                @foreach ($menus->get('header')['items'] as $item)
                    <div class="ys-nav-item">
                        <a href="{{ $item['url'] }}" @class(['is-on' => $menus->isActive($item)]) @if ($menus->isActive($item)) aria-current="page" @endif @if ($item['new_tab']) target="_blank" rel="noopener" @endif>@if ($item['icon'])<i class="{{ $item['icon'] }}" aria-hidden="true"></i> @endif{{ $item['label'] }}@if ($item['children'])<span class="ys-caret" aria-hidden="true">▾</span>@endif</a>
                        @if ($item['children'])
                            <div class="ys-sub">@foreach ($item['children'] as $child)<a href="{{ $child['url'] }}" @class(['is-on' => $menus->isActive($child)]) @if ($child['new_tab']) target="_blank" rel="noopener" @endif>{{ $child['label'] }}</a>@endforeach</div>
                        @endif
                    </div>
                    @foreach ($item['children'] as $child)<a href="{{ $child['url'] }}" class="ys-nav-flat" @if ($child['new_tab']) target="_blank" rel="noopener" @endif>{{ $child['label'] }}</a>@endforeach
                @endforeach
            </nav>
            {{-- search: opens the live search modal (frontend/js/search.js) — a real link to /shop without JavaScript --}}
            <a href="{{ route('shop') }}" data-search-open class="ys-round" aria-label="খুঁজুন" title="খুঁজুন">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </a>
            @if (site()->cartEnabled())
            {{-- cart (device cart) — badge kept in sync by window.ysCart.setCount(); click opens the drawer (frontend/js/cart-drawer.js) --}}
            <a href="{{ route('checkout') }}" data-cart-open class="ys-round ys-cart-link" aria-label="কার্ট">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12h11.5l2-8H6"/></svg>
                <span class="ys-cart-badge" data-cart-badge @if (empty($cartCount)) hidden @endif>{{ $cartCount ?? 0 }}</span>
            </a>
            @endif
            @guest
                <a href="{{ route('login') }}" class="ys-round" title="লগইন / অ্যাকাউন্ট" aria-label="লগইন"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg></a>
            @else
                <details class="ys-account">
                    <summary class="ys-round" title="{{ auth()->user()->name }}" aria-label="অ্যাকাউন্ট"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg></summary>
                    <div class="ys-sub ys-sub-right">
                        <span class="ys-sub-name">{{ auth()->user()->name }}</span>
                        @if (auth()->user()->role === 'admin' || auth()->user()->ops_role)<a href="{{ route('ops.dashboard') }}">অ্যাডমিন প্যানেল</a>@endif
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">লগআউট</button></form>
                    </div>
                </details>
            @endguest
        </div>
    </header>
    <aside id="sidebar"></aside>
    {{-- live search modal: filled by frontend/js/search.js from /search/suggest --}}
    <div class="ys-search" id="ysSearch" role="dialog" aria-modal="true" aria-label="প্রোডাক্ট খুঁজুন" hidden data-url="{{ route('search.suggest', [], false) }}" data-shop="{{ route('shop', [], false) }}">
        <div class="ys-search-box">
            <form class="ys-search-bar" action="{{ route('shop') }}" method="get" role="search">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" name="q" placeholder="প্রোডাক্টের নাম বা কোড লিখুন…" autocomplete="off" enterkeyhint="search" aria-label="খুঁজুন" aria-controls="ysSearchList" maxlength="60">
                <button type="button" class="ys-search-x" data-search-close aria-label="বন্ধ করুন">×</button>
            </form>
            <div class="ys-search-body" aria-live="polite">
                <p class="ys-search-hint">কমপক্ষে ২টি অক্ষর লিখুন — যেমন “cargo”, “combo”, “denim”</p>
                <ul class="ys-search-list" id="ysSearchList" role="listbox"></ul>
                <a class="ys-search-all" hidden></a>
            </div>
        </div>
    </div>
    {{-- mobile sidebar: Mobile sidebar menu, or the Main menu while that one is empty; Top bar links at the bottom --}}
    @php($side = $menus->get('mobile'))
    @php($sideItems = $side['items'] ?: $menus->get('header')['items'])
    <div class="offcanvas offcanvas-end ys-side" tabindex="-1" id="ysMenu" aria-labelledby="ysMenuTitle">
        <div class="ys-side-head">
            <a href="{{ route('home') }}" class="ys-side-brand"><span class="ys-logo-mark"><img src="{{ site()->logoUrl() }}" alt="" width="34" height="34" loading="lazy"></span><span id="ysMenuTitle">{{ site()->storeName() }}</span></a>
            <button type="button" class="ys-dr-x" data-bs-dismiss="offcanvas" aria-label="বন্ধ করুন">×</button>
        </div>
        <nav class="ys-side-body" aria-label="মোবাইল মেনু">
            @if ($side['title'])<div class="ys-side-title">{{ $side['title'] }}</div>@endif
            @foreach ($sideItems as $item)
                @if ($item['children'])
                    <details class="ys-side-group" @if ($menus->isActive($item)) open @endif>
                        <summary @class(['is-on' => $menus->isActive($item)])>@if ($item['icon'])<i class="{{ $item['icon'] }}" aria-hidden="true"></i>@endif<span>{{ $item['label'] }}</span><span class="ys-side-caret" aria-hidden="true">▾</span></summary>
                        @unless (str_ends_with($item['url'], '#'))<a href="{{ $item['url'] }}" class="ys-side-sub" @if ($item['new_tab']) target="_blank" rel="noopener" @endif>সব দেখুন</a>@endunless {{-- a "#" parent is only a group title --}}
                        @foreach ($item['children'] as $child)<a href="{{ $child['url'] }}" @class(['ys-side-sub', 'is-on' => $menus->isActive($child)]) @if ($child['new_tab']) target="_blank" rel="noopener" @endif>{{ $child['label'] }}</a>@endforeach
                    </details>
                @else
                    <a href="{{ $item['url'] }}" @class(['ys-side-link', 'is-on' => $menus->isActive($item)]) @if ($menus->isActive($item)) aria-current="page" @endif @if ($item['new_tab']) target="_blank" rel="noopener" @endif>@if ($item['icon'])<i class="{{ $item['icon'] }}" aria-hidden="true"></i>@endif<span>{{ $item['label'] }}</span></a>
                @endif
            @endforeach
        </nav>
        @if ($contact = $menus->get('topbar')['items'])
            <div class="ys-side-foot">
                @foreach ($contact as $item)<a href="{{ $item['url'] }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif>@if ($item['icon'])<i class="{{ $item['icon'] }}" aria-hidden="true"></i>@endif<span>{{ $item['label'] }}</span></a>@endforeach
            </div>
        @endif
    </div>
    @if (site()->cartEnabled())
    {{-- cart drawer: empty shell, filled by frontend/js/cart-drawer.js --}}
    <div class="offcanvas offcanvas-end ys-drawer" tabindex="-1" id="ysCartDrawer" aria-labelledby="ysCartDrawerTitle"
        data-summary-url="{{ route('cart.summary') }}" data-item-url="{{ url('/cart/items') }}" data-checkout-url="{{ route('checkout') }}"
        data-shop-url="{{ route('shop') }}" data-csrf="{{ csrf_token() }}">
        <div class="ys-dr-head">
            <span class="ys-dr-title" id="ysCartDrawerTitle">আপনার কার্ট (<span data-drawer-count>{{ $cartCount ?? 0 }}</span>)</span>
            <button type="button" class="ys-dr-x" data-bs-dismiss="offcanvas" aria-label="বন্ধ করুন">×</button>
        </div>
        <div class="ys-dr-added" data-drawer-added hidden>✓ কার্টে যোগ হয়েছে</div>
        <div class="ys-dr-body" data-drawer-body></div>
        <div class="ys-dr-foot" data-drawer-foot hidden>
            <div class="ys-dr-sub"><span>সাবটোটাল</span><span data-drawer-subtotal>৳ 0</span></div>
            <span class="ys-dr-note">ডেলিভারি চার্জ চেকআউটে যোগ হবে</span>
            <a href="{{ route('checkout') }}" data-drawer-checkout class="ys-dr-btn">চেকআউট করুন</a>
            <button type="button" class="ys-dr-btn-o" data-bs-dismiss="offcanvas">কেনাকাটা চালিয়ে যান</button>
        </div>
    </div>
    @endif
    <main id="Content-body" class="py-3">
        @yield('content')
    </main>
    <footer id="footer-area" class="border-top">
        <div class="container py-3">
            {{-- menu: footer (Ops → Storefront → Navigation menus) --}}
            @php($footerMenu = $menus->get('footer'))
            @if ($footerMenu['title'])<h5 class="text-center fw-semibold text-primary-color">{{ $footerMenu['title'] }}</h5>@endif
            <div class="topbar d-flex justify-content-center">
                <ul class="quick-contact list-inline d-flex justify-content-end gap-3 py-2 mb-0 align-items-center ">
                    @foreach ($footerMenu['items'] as $item)
                        <li class="list-inline fw-bold fs-6"><a href="{{ $item['url'] }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif
                                class="text-decoration-none text-primary-color text-primary-hover">@if ($item['icon'])<i class="{{ $item['icon'] }}"></i> @endif{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            {{-- Ops → Storefront → Social & chat · Settings → Branding → footer note --}}
            @php($social = collect(site('storefront.social'))->only(['facebook', 'instagram', 'youtube', 'tiktok'])->filter())
            @if ($social->isNotEmpty())
                <div class="d-flex justify-content-center gap-3 fs-4 pt-1">
                    @foreach ($social as $net => $url)<a href="{{ $url }}" target="_blank" rel="noopener" class="text-primary-color text-primary-hover" aria-label="{{ ucfirst($net) }}"><i class="fa-brands fa-{{ $net }}"></i></a>@endforeach
                </div>
            @endif
            @if (site('branding.footer_note'))<p class="text-center small text-muted mt-2 mb-0">{{ site()->text('branding.footer_note', ['{year}' => date('Y')]) }}</p>@endif
        </div>
    </footer>
    @include('partials.storefront.chat-popup')
    <div>
        {{-- <style>
            .new-arrival {
                position: fixed;
                right: 20px;
                top: 150px;
                width: 100px;
                height: 100px;
                background-color: #d30101;
                border-radius: 50%;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
                animation: bubbleandshake 2s infinite ease-in-out;
                cursor: grab;
                z-index: 99;
                user-select: none;
            }

            .new-arrival .text {
                font-weight: 700;
                color: #fff;
                text-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
                font-size: 12px;

            }

            @keyframes bubbleandshake {
                0% {
                    transform: translateY(0);
                }

                50% {
                    transform: translateY(-10px);
                }

            }
        </style>
        <div class="new-arrival " id="dragBtn">




            <a href="https://youngstar.life/category/joggers-pant" class="text-center text-decoration-none">
                <dotlottie-wc src="https://lottie.host/8a7eb623-e79f-481e-b63f-555486c2e6a7/0Hmst73iRT.lottie"
                    style="width: 50px;height: 50px" autoplay loop></dotlottie-wc>
                <span class="fw-semibold text-primary-color fs-6 text ms-1 text-center">New Joggers</span>
            </a>
        </div> --}}
    </div>
    <!-- SweetAlert (loaded here instead of <head> so it doesn't block first paint) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Jquery -->
    <script src="{{ asset('frontend/library/jquery/jquery-3.7.1.min.js') }}"></script>
    <!-- Bootstrap Js  -->
    <script src="{{ asset('frontend/library/bootstrap/bootstrap.bundle.min.js') }}"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous">
    </script>
    <!-- Swiperjs Js  -->
    <script src="{{ asset('frontend/library/swiper/swiper-bundle.min.js') }}"></script>
    <!-- Fancybox js -->
    <script src="{{ asset('frontend/library/fancybox/fancybox.umd.js') }}"></script>
    <!-- Custom Js  -->
    <script src="{{ asset('frontend/js/script.js') }}"></script>
    <!-- Initialize Swiper -->
    <!-- Initialize Swiper -->

    <script>
        var swiper = new Swiper(".mySwiper", {
            spaceBetween: 10,
            slidesPerView: 4,
            freeMode: true,
            watchSlidesProgress: true,
        });
        var swiper2 = new Swiper(".mySwiper2", {
            spaceBetween: 10,
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
            thumbs: {
                swiper: swiper,
            },
        });
    </script>

    <script>
        Fancybox.bind("[data-fancybox]", {
            // Optional settings
            Thumbs: {
                autoStart: true,
            },
        });
    </script>


    <script>
        // header cart badge (storefront device cart)
        window.ysCart = {
            setCount: function(n, quiet) {
                document.querySelectorAll('[data-cart-badge]').forEach(function(b) {
                    var changed = String(b.textContent) !== String(n);
                    b.textContent = n; b.hidden = !n;
                    if (quiet || !changed) return;
                    b.parentElement.classList.remove('ys-cart-bump'); void b.offsetWidth; b.parentElement.classList.add('ys-cart-bump');
                });
            }
        };
    </script>
    <script src="{{ asset('frontend/js/search.js') }}?v={{ @filemtime(public_path('frontend/js/search.js')) }}" defer></script>
    @if (site()->cartEnabled())
        <script src="{{ asset('frontend/js/cart-drawer.js') }}?v={{ @filemtime(public_path('frontend/js/cart-drawer.js')) }}"></script>
    @endif
    @stack('scripts')
    <!--Start of Tawk.to Script-->
    {{-- <script type="text/javascript">
        var Tawk_API = Tawk_API || {},
            Tawk_LoadStart = new Date();
        (function() {
            var s1 = document.createElement("script"),
                s0 = document.getElementsByTagName("script")[0];
            s1.async = true;
            s1.src = 'https://embed.tawk.to/690c8bfbc7778b19591c13ad/1j9cg5jom';
            s1.charset = 'UTF-8';
            s1.setAttribute('crossorigin', '*');
            s0.parentNode.insertBefore(s1, s0);
        })();
    </script> --}}
    <!--End of Tawk.to Script-->
</body>

</html>
