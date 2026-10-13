{{-- Ops → Storefront → Social & chat (floating buttons) and Announcement & popup. Nothing renders while they are off. --}}
@php($s = site('storefront.social'))
@php($chat = array_filter([
    'whatsapp' => ($s['whatsapp_on'] ?? false) ? site()->whatsappLink() : null,
    'messenger' => ($s['messenger_on'] ?? false) && ($s['messenger'] ?? '') ? 'https://m.me/'.ltrim(preg_replace('#^https?://(www\.)?(m\.me|facebook\.com|messenger\.com/t)/#', '', $s['messenger']), '/') : null,
    'call' => ($s['call_on'] ?? false) && ($s['phone'] ?? '') ? 'tel:'.preg_replace('/[^\d+]/', '', $s['phone']) : null,
]))
@if ($chat)
    <style>
        .ys-chat { position: fixed; right: 16px; bottom: 16px; z-index: 1040; display: flex; flex-direction: column; gap: 10px; }
        .ys-chat a { width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 26px; box-shadow: 0 4px 14px rgba(0,0,0,.2); text-decoration: none; transition: transform .15s; }
        .ys-chat a:hover { transform: scale(1.08); color: #fff; }
        .ys-chat .wa { background: #25d366; } .ys-chat .ms { background: #0084ff; } .ys-chat .cl { background: var(--primary-color); font-size: 22px; }
    </style>
    <div class="ys-chat">
        @isset($chat['call'])<a href="{{ $chat['call'] }}" class="cl" aria-label="Call"><i class="fa-solid fa-phone"></i></a>@endisset
        @isset($chat['messenger'])<a href="{{ $chat['messenger'] }}" target="_blank" rel="noopener" class="ms" aria-label="Messenger"><i class="fa-brands fa-facebook-messenger"></i></a>@endisset
        @isset($chat['whatsapp'])<a href="{{ $chat['whatsapp'] }}" target="_blank" rel="noopener" class="wa" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>@endisset
    </div>
@endif

@php($popup = site('storefront.popup'))
@if (($popup['on'] ?? false) && ($popup['title'] ?? ''))
    <div class="modal fade" id="ysPopup" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                <h4 class="fw-bold text-primary-color mb-2">{{ $popup['title'] }}</h4>
                @if ($popup['body'] ?? '')<p class="mb-3">{{ $popup['body'] }}</p>@endif
                @if ($popup['coupon'] ?? '')<div class="fs-4 fw-bold border border-2 border-dashed rounded py-2 mb-2" style="letter-spacing:.1em">{{ $popup['coupon'] }}</div>@endif
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var key = 'ys_popup_' + @js(md5(($popup['title'] ?? '').($popup['body'] ?? ''))), every = @js(($popup['frequency'] ?? 'once') === 'every');
            try { if (!every && localStorage.getItem(key)) return; } catch (e) {}
            setTimeout(function () {
                if (!window.bootstrap) return;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('ysPopup')).show();
                try { localStorage.setItem(key, '1'); } catch (e) {}
            }, {{ max(0, (int) ($popup['delay'] ?? 5)) }} * 1000);
        });
    </script>
@endif
