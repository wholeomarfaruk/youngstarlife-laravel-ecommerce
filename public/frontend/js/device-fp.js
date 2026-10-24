/*
 * Storefront device fingerprint: what this browser looks like beyond its cookie — phone model, screen, GPU, canvas …
 * Sent once per browser session; the server keeps a hash on the device (DeviceManager::fingerprint) and the Ops blacklist
 * uses it to spot a blocked visitor who cleared cookies. Never blocks the page: every step fails silently.
 */
(function () {
    var el = document.currentScript;
    if (!el || !window.fetch || !window.Promise) return;
    try { if (sessionStorage.getItem('ys_fp')) return; } catch (e) {}

    function hash(s) { var h = 0x811c9dc5; for (var i = 0; i < s.length; i++) { h ^= s.charCodeAt(i); h = Math.imul(h, 0x01000193) >>> 0; } return h.toString(16); }

    // same drawing renders slightly differently per GPU / driver / font stack
    function canvas() {
        try {
            var c = document.createElement('canvas'); c.width = 240; c.height = 60;
            var x = c.getContext('2d'); if (!x) return '';
            x.textBaseline = 'top'; x.font = '16px Arial'; x.fillStyle = '#f60'; x.fillRect(100, 5, 80, 30);
            x.fillStyle = '#069'; x.fillText('YoungStar ✓ অর্ডার 2026', 4, 8);
            x.fillStyle = 'rgba(102,204,0,0.7)'; x.fillText('YoungStar ✓ অর্ডার 2026', 6, 24);
            return hash(c.toDataURL());
        } catch (e) { return ''; }
    }

    function gpu() {
        try {
            var g = document.createElement('canvas').getContext('webgl'), d = g && g.getExtension('WEBGL_debug_renderer_info');
            return d ? g.getParameter(d.UNMASKED_VENDOR_WEBGL) + ' / ' + g.getParameter(d.UNMASKED_RENDERER_WEBGL) : '';
        } catch (e) { return ''; }
    }

    // Chromium hides the phone model from the user agent ("Android 10; K"); client hints still give it
    var ua = navigator.userAgentData;
    var hints = ua && ua.getHighEntropyValues ? ua.getHighEntropyValues(['model', 'platformVersion']).catch(function () { return {}; }) : Promise.resolve({});

    hints.then(function (h) {
        var s = window.screen || {}, n = navigator, tz = '';
        try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
        var signals = {
            model: h.model || '', os: h.platformVersion || '',
            screen: [Math.max(s.width, s.height), Math.min(s.width, s.height), s.colorDepth, window.devicePixelRatio || 1].join('x'),
            tz: tz, lang: (n.languages || [n.language]).join(','), cpu: n.hardwareConcurrency || 0, mem: n.deviceMemory || 0,
            touch: n.maxTouchPoints || 0, gpu: gpu(), canvas: canvas()
        };
        return fetch(el.dataset.url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': el.dataset.csrf },
            body: JSON.stringify({ s: signals })
        }).then(function (r) { if (r.ok) try { sessionStorage.setItem('ys_fp', '1'); } catch (e) {} });
    }).catch(function () {});
})();
