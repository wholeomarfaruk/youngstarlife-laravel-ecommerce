<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ site()->storeName() }}</title>
    <link rel="icon" type="image/png" href="{{ site()->faviconUrl() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        @font-face { font-family: 'SolaimanLipi'; src: url("{{ asset('fonts/SolaimanLipi.ttf') }}") format('truetype'); font-display: swap; }
        :root { --primary-color: {{ preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) site('branding.primary_color')) ? site('branding.primary_color') : '#525754' }}; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; font-family: 'SolaimanLipi', system-ui, sans-serif; background: #f6f6f7; color: #222; text-align: center; }
        .box { max-width: 440px; }
        img { width: 88px; height: 88px; object-fit: contain; border-radius: 50%; }
        h1 { font-size: 24px; margin: 16px 0 8px; color: var(--primary-color); }
        p { font-size: 18px; line-height: 1.6; margin: 0 0 20px; }
        a.wa { display: inline-flex; gap: 8px; align-items: center; padding: 10px 18px; border-radius: 999px; background: #25d366; color: #fff; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <div class="box">
        <img src="{{ site()->logoUrl() }}" alt="{{ site()->storeName() }}">
        <h1>{{ site()->storeName() }}</h1>
        <p>{{ site()->text('content.maintenance') }}</p>
        @if ($wa = site()->whatsappLink())<a class="wa" href="{{ $wa }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>@endif
    </div>
</body>
</html>
