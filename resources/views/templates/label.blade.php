<!DOCTYPE html>
<html lang="bn">

<head>
    <meta charset="UTF-8">
    <title>3x4 Inch Labels</title>
    <style>
        /* @font-face {
            font-family: 'SolaimanLipi';
            src: url('{{ public_path('fonts/SolaimanLipi.ttf') }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        } */
        @page {
            /* Set physical page margins to 0 */
            margin: 0;
            padding: 0;
            size: 144pt 216pt;
            /* Optionally set size here as well */
        }
        * {
              box-sizing: border-box;
        }

        body {
            font-family: 'SolaimanLipi', sans-serif;
            margin: 0;
            padding: 0;
        }

        .page {
            width: 48.4mm;

            padding: 5px;
           /* border: red 1px solid; */

        }

        .label {
            width: 43mm;
            height: 67mm;
            /* border: 1px solid #000; */
            border-radius: 8px;
            margin-bottom: 5px;
            padding: 8px;

            page-break-inside: avoid;

            /* Prevent label splitting */
        }

        .logo {
            text-align: center;
        }

        .logo img {
            width: 50px;
            height: auto;
            margin-bottom: 2px;
        }

        .info p {
            margin: 2px 0;
            font-size: 11px;
            line-height: 1.2;
        }

        .barcode {
            text-align: center;
            margin-bottom: 2px;
        }

        .barcode div {
            display: inline-block;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>
    <div class="page">
        @foreach ($stickers as $item)
            <div class="label">
                <div class="logo">
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('frontend/img/youngstar_logo_transparent.png'))) }}" alt="Logo">
                    <p style="text-align: center; margin: 0;"><strong>YOUNGSTAR Life</strong></p>
                </div>
                <div class="barcode">
                    {!! DNS1D::getBarcodeHTML($item['consignment_id'], 'C128', 1.2, 40) !!}
                </div>
                <p style="text-align: center; margin: 0; letter-spacing: 2px;"><strong>{{ $item['consignment_id'] }}</strong></p>
                <div class="info">
                    <p><strong>ID:</strong> {{ $item['id'] }}</p>
                    <p><strong>Name:</strong> {{ $item['name'] }}</p>
                    <p><strong>Phone:</strong> {{ $item['phone'] }}</p>
                    <p><strong>Price:</strong> {{ $item['price'] }}</p>
                    <p><strong>Item:</strong> {{ $item['items'] }}</p>
                </div>


            </div>

            {{-- Add page break after each label if needed for printers --}}
            @if (!$loop->last)
                <div class="page-break"></div>
            @endif
        @endforeach
    </div>
</body>

</html>
