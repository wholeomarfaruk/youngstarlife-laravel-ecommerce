/*
 * Cart drawer (storefront). The markup is an empty shell in layouts/app.blade.php; content is rendered here from JSON so pages
 * pay nothing for it. Fast paths:
 *  - add to cart → the response already contains the cart → opens with no second request (ysCart.open(cart, itemId))
 *  - cart icon   → shows the last cart from sessionStorage at once, refreshes in the background; the fetch starts on hover/touch
 *  - +/− remove  → UI updates first, server sync is batched (350ms) and reconciled with the server's answer
 */
(function () {
    'use strict';
    var el = document.getElementById('ysCartDrawer');
    if (!el || !window.bootstrap) return;

    var cfg = el.dataset;   // summaryUrl, itemUrl, checkoutUrl, shopUrl, csrf
    var KEY = 'ysCartSnapshot';
    var body = el.querySelector('[data-drawer-body]');
    var foot = el.querySelector('[data-drawer-foot]');
    var added = el.querySelector('[data-drawer-added]');   // "✓ কার্টে যোগ হয়েছে" right after an add
    var drawer = bootstrap.Offcanvas.getOrCreateInstance(el);
    var state = null, inflight = null, fetchedAt = 0, timers = {};

    var tk = function (n) { return '৳ ' + Math.round(n || 0).toLocaleString('en-US'); };
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var store = function (s) { try { sessionStorage.setItem(KEY, JSON.stringify(s)); } catch (e) {} };
    var cached = function () { try { return JSON.parse(sessionStorage.getItem(KEY)); } catch (e) { return null; } };
    var badgeCount = function () { var b = document.querySelector('[data-cart-badge]'); return b && !b.hidden ? parseInt(b.textContent) || 0 : 0; };

    // ── rendering
    // design: thumbnail | title · variant (সাইজ 32 / combo items) · পরিমাণ | line total · মুছুন
    function lineHtml(l, isNew) {
        var variant = l.variant_text || (l.size ? 'সাইজ ' + l.size : '');
        var name = l.url ? '<a class="ys-dl-name" href="' + esc(l.url) + '">' + esc(l.name) + '</a>' : '<span class="ys-dl-name">' + esc(l.name) + '</span>';
        return '<div class="ys-dl' + (l.available ? '' : ' is-off') + (isNew ? ' is-new' : '') + '" data-id="' + l.id + '">' +
            '<img src="' + esc(l.image_url) + '" alt="" width="64" height="76" loading="lazy" decoding="async">' +
            '<div class="ys-dl-main">' + name +
                (variant ? '<span class="ys-dl-meta">' + esc(variant) + '</span>' : '') +
                '<span class="ys-dl-meta">পরিমাণ: ' + l.qty + (l.available ? '' : ' · <b style="color:#c92a2a">স্টক শেষ</b>') + '</span>' +
            '</div>' +
            '<div class="ys-dl-end"><b>' + tk(l.line) + '</b><button type="button" class="ys-dl-x" data-x>মুছুন</button></div>' +
        '</div>';
    }

    function render(s, newId) {
        state = s;
        if (added) added.hidden = !newId;
        el.querySelectorAll('[data-drawer-count]').forEach(function (n) { n.textContent = s.count; });
        if (!s.lines || !s.lines.length) {
            body.innerHTML = '<div class="ys-dl-empty">কার্ট খালি<br><a href="' + esc(cfg.shopUrl) + '">প্রোডাক্ট দেখুন →</a></div>';
            foot.hidden = true;
        } else {
            body.innerHTML = s.lines.map(function (l) { return lineHtml(l, String(l.id) === String(newId)); }).join('');
            el.querySelector('[data-drawer-subtotal]').textContent = tk(s.subtotal);
            foot.hidden = false;
        }
        window.ysCart.setCount(s.count, true);
    }

    function skeleton() {
        foot.hidden = true;
        if (added) added.hidden = true;
        body.innerHTML = '<div class="ys-dl ys-dl-skel"><span></span><div><i></i><i></i></div></div>'.repeat(Math.min(Math.max(badgeCount(), 1), 3));
    }

    // ── data
    function load(force) {
        if (inflight) return inflight;
        if (!force && state && Date.now() - fetchedAt < 15000) return Promise.resolve(state);   // fresh enough (prefetch + click)
        inflight = fetch(cfg.summaryUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { if (!r.ok) throw r; return r.json(); })
            .then(function (s) { fetchedAt = Date.now(); store(s); if (!hasPending()) render(s); return s; })
            .finally(function () { inflight = null; });
        return inflight;
    }

    function hasPending() { return Object.keys(timers).length > 0; }

    function recalc(s) {
        var ok = s.lines.filter(function (l) { return l.available; });
        s.subtotal = ok.reduce(function (t, l) { return t + l.line; }, 0);
        s.count = ok.reduce(function (t, l) { return t + l.qty; }, 0);
        return s;
    }

    // optimistic change, then one request per line after the taps stop
    function change(id, qty) {
        var s = JSON.parse(JSON.stringify(state));
        s.lines = s.lines.filter(function (l) {
            if (String(l.id) !== String(id)) return true;
            if (qty < 1) return false;
            l.qty = qty; l.line = l.price * qty;
            return true;
        });
        render(recalc(s));
        store(state);
        clearTimeout(timers[id]);
        timers[id] = setTimeout(function () {
            fetch(cfg.itemUrl + '/' + id, {
                method: qty < 1 ? 'DELETE' : 'PATCH', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                body: qty < 1 ? null : JSON.stringify({ quantity: qty }),
            }).then(function (r) { if (!r.ok) return r.json().catch(function () { return {}; }).then(function (j) { throw j; }); return r.json(); })
              .then(function (srv) { delete timers[id]; fetchedAt = Date.now(); store(srv); if (!hasPending()) render(srv); })
              .catch(function (j) {
                  delete timers[id]; load(true);
                  var msg = j && j.errors && Object.values(j.errors)[0][0];   // e.g. Settings → Order limits
                  if (window.Swal) Swal.fire({ icon: msg ? 'info' : 'error', text: msg || 'আপডেট করা যায়নি, আবার চেষ্টা করুন।', timer: 3000 });
              });
        }, 350);
    }

    body.addEventListener('click', function (e) {
        var row = e.target.closest('[data-id]');
        if (!row || !state || el.classList.contains('is-syncing')) return;   // the new line isn't saved yet
        var line = state.lines.find(function (l) { return String(l.id) === row.dataset.id; });
        if (!line) return;
        if (e.target.closest('[data-x]')) return change(line.id, 0);
        var q = e.target.closest('[data-q]');
        if (q) {
            var want = line.qty + parseInt(q.dataset.q), max = line.max != null ? Math.max(line.qty, line.max) : 50;
            if (want > max) { if (window.Swal && line.limit) Swal.fire({ icon: 'info', text: line.limit, timer: 3000 }); return; }   // Settings → Order limits
            change(line.id, Math.max(0, want));
        }
    });

    // ── public API (setCount lives in the layout; this adds open/refresh)
    window.ysCart.open = function (summary, newId) {
        if (summary) { fetchedAt = Date.now(); store(summary); render(summary, newId); }
        else {
            var c = state || cached();
            if (c && c.count === badgeCount()) render(c); else skeleton();   // instant from cache, then revalidate
            load(true).catch(function () { if (!state) body.innerHTML = '<p class="p-3 text-danger">কার্ট লোড করা যায়নি। আবার চেষ্টা করুন।</p>'; });
        }
        drawer.show();
    };
    window.ysCart.refresh = function () { return load(true); };

    /*
     * Optimistic open for add to cart: called on the click, before the request. Shows the cart (memory / sessionStorage)
     * with the new line already in it, so the drawer never waits for the server. ysCart.open(cart) then swaps in the
     * server's cart; ysCart.fail() rolls back. Checkout stays disabled until the server has confirmed.
     */
    var beforePending = null;
    window.ysCart.pending = function (line) {
        var before = badgeCount();
        var snap = state || cached();
        var trusted = !!snap && snap.count === badgeCount();
        beforePending = trusted ? snap : null;
        var s = trusted ? JSON.parse(JSON.stringify(snap)) : { lines: [], count: 0, subtotal: 0 };
        var same = s.lines.find(function (l) { return String(l.product_id) === String(line.product_id) && (l.size || '') === (line.size || ''); });
        if (same) {
            same.qty = Math.min(50, same.qty + line.qty); same.line = same.price * same.qty;
            s.lines = [same].concat(s.lines.filter(function (l) { return l !== same; }));
        } else {
            s.lines.unshift(Object.assign({ id: 'pending', available: true, regular: line.price, line: line.price * line.qty }, line));
        }
        el.classList.add('is-syncing');
        render(recalc(s), same ? same.id : 'pending');
        if (!trusted && before > 0) {   // the rest of the cart arrives with the server's answer
            window.ysCart.setCount(before + line.qty, true);
            el.querySelectorAll('[data-drawer-count]').forEach(function (n) { n.textContent = before + line.qty; });
            body.insertAdjacentHTML('beforeend', '<div class="ys-dl ys-dl-skel"><span></span><div><i></i><i></i></div></div>');
            el.querySelector('[data-drawer-subtotal]').textContent = '…';
        }
        drawer.show();
    };
    window.ysCart.fail = function () {
        el.classList.remove('is-syncing');
        if (beforePending) render(beforePending); else state = null;
        drawer.hide();
    };
    // another view changed the cart (checkout page): keep the drawer's copy in step
    window.ysCart.sync = function (summary) { if (summary && summary.lines) { fetchedAt = Date.now(); state = summary; store(summary); } };

    // warm the cache after the page has loaded when it doesn't match the server's count, so the next open is instant and right
    var snap0 = cached();
    if (badgeCount() > 0 && (!snap0 || snap0.count !== badgeCount())) {
        var warm = function () { load(true).catch(function () {}); };
        'requestIdleCallback' in window ? requestIdleCallback(warm, { timeout: 3000 }) : setTimeout(warm, 1500);
    } else if (snap0) { state = snap0; }
    var open = window.ysCart.open;
    window.ysCart.open = function (summary, newId) { el.classList.remove('is-syncing'); open(summary, newId); };

    // header cart icon opens the drawer (checkout page keeps the plain link; ctrl/cmd-click still opens a tab)
    var onCheckout = /^\/checkout(\/|$)/.test(location.pathname);
    document.querySelectorAll('[data-cart-open]').forEach(function (a) {
        if (onCheckout) return;
        a.addEventListener('click', function (e) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
            e.preventDefault();
            window.ysCart.open();
        });
        // prefetch on intent so the drawer is usually ready before the click lands
        ['pointerenter', 'touchstart', 'focus'].forEach(function (ev) { a.addEventListener(ev, function () { load(false).catch(function () {}); }, { passive: true, once: true }); });
    });
})();
