/**
 * Header live search (layouts/app.blade.php → #ysSearch). The 🔍 button opens the modal; typing asks /search/suggest
 * (debounced, older requests aborted, answers cached per query in this tab). ↑ ↓ pick a result, Enter opens it
 * (or the full results page /shop?q=), Esc / × / backdrop close. Without JavaScript the button is a plain link to /shop.
 */
(function () {
    var el = document.getElementById('ysSearch');
    if (!el) return;
    var input = el.querySelector('input'), list = el.querySelector('.ys-search-list'), hint = el.querySelector('.ys-search-hint'), all = el.querySelector('.ys-search-all');
    var url = el.dataset.url, timer = null, ctrl = null, memo = {}, on = -1, lastFocus = null;

    var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var tk = function (n) { return '৳ ' + Number(n).toLocaleString('en-US'); };
    var mark = function (name, q) {   // highlight the typed words
        var out = esc(name);
        q.split(' ').filter(function (w) { return w.length > 1; }).forEach(function (w) {
            out = out.replace(new RegExp('(' + esc(w).replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi'), '<mark>$1</mark>');
        });
        return out;
    };

    function open(e) {
        if (e) e.preventDefault();
        lastFocus = document.activeElement;
        el.hidden = false; document.documentElement.style.overflow = 'hidden';
        input.focus(); input.select();
    }
    function close() {
        el.hidden = true; document.documentElement.style.overflow = '';
        if (ctrl) ctrl.abort();
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }
    function show(q, data) {
        on = -1;
        if (q.length < 2) { list.innerHTML = ''; all.hidden = true; hint.hidden = false; hint.textContent = 'কমপক্ষে ২টি অক্ষর লিখুন — যেমন “cargo”, “combo”, “denim”'; return; }
        if (!data.items.length) { list.innerHTML = ''; all.hidden = true; hint.hidden = false; hint.textContent = '“' + q + '” এর সাথে মিলে এমন কোনো প্রোডাক্ট পাওয়া যায়নি'; return; }
        hint.hidden = true;
        list.innerHTML = data.items.map(function (p, i) {
            return '<li role="option"><a href="' + esc(p.url) + '" data-i="' + i + '"><img src="' + esc(p.image) + '" alt="" loading="lazy" width="52" height="52">' +
                '<span class="ys-search-name">' + mark(p.name, q) + '</span><span class="ys-search-price">' + (p.old ? '<del>' + tk(p.old) + '</del>' : '') + tk(p.price) + '</span></a></li>';
        }).join('');
        all.hidden = false; all.href = data.all;
        all.textContent = data.total > data.items.length ? 'সব ' + data.total + 'টি ফলাফল দেখুন →' : 'ফলাফল পেজে দেখুন →';
    }
    function search() {
        var q = input.value.trim().replace(/\s+/g, ' ');
        if (q.length < 2) return show(q, { items: [] });
        if (memo[q]) return show(q, memo[q]);
        if (ctrl) ctrl.abort();
        ctrl = window.AbortController ? new AbortController() : null;
        el.classList.add('is-loading');
        fetch(url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, signal: ctrl && ctrl.signal })
            .then(function (r) { if (!r.ok) throw r; return r.json(); })
            .then(function (data) { memo[q] = data; if (input.value.trim().replace(/\s+/g, ' ') === q) show(q, data); })
            .catch(function (e) { if (e && e.name === 'AbortError') return; hint.hidden = false; hint.textContent = 'খোঁজা যায়নি, আবার চেষ্টা করুন'; })
            .finally(function () { el.classList.remove('is-loading'); });
    }
    function move(d) {
        var links = list.querySelectorAll('a'); if (!links.length) return;
        if (on >= 0) links[on].classList.remove('is-on');
        on = (on + d + links.length) % links.length;
        links[on].classList.add('is-on'); links[on].scrollIntoView({ block: 'nearest' });
    }

    document.querySelectorAll('[data-search-open]').forEach(function (a) { a.addEventListener('click', open); });
    el.querySelector('[data-search-close]').addEventListener('click', close);
    el.addEventListener('click', function (e) { if (e.target === el) close(); });
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 220); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
        else if (e.key === 'Enter' && on >= 0) { e.preventDefault(); location.href = list.querySelectorAll('a')[on].href; }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !el.hidden) close();
        if (e.key === '/' && el.hidden && !/input|textarea|select/i.test((e.target.tagName || '')) && !e.target.isContentEditable) open(e);   // "/" opens search
    });
})();
