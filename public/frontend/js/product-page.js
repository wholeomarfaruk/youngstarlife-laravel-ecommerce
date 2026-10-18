/*
 * Product page (resources/views/livewire/storefront/product-buy.blade.php). All choosing is local and instant;
 * only add-to-cart, order and the lead autosave talk to the server ($wire = App\Livewire\Storefront\ProductBuy, renderless).
 * The server re-checks every price, size and stock — nothing here is trusted.
 */
(function () {
    'use strict';

    // ০-৯ / ٠-٩ / ۰-۹ → 0-9
    var DIGITS = { '০': 0, '১': 1, '২': 2, '৩': 3, '৪': 4, '৫': 5, '৬': 6, '৭': 7, '৮': 8, '৯': 9, '٠': 0, '١': 1, '٢': 2, '٣': 3, '٤': 4, '٥': 5, '٦': 6, '٧': 7, '٨': 8, '٩': 9, '۰': 0, '۱': 1, '۲': 2, '۳': 3, '۴': 4, '۵': 5, '۶': 6, '۷': 7, '۸': 8, '۹': 9 };
    function ascii(s) { return String(s || '').replace(/[০-৯٠-٩۰-۹]/g, function (c) { return DIGITS[c]; }); }

    /** whatever the customer types (+880 17…, ০১৭…, 01 7xx-xxxxxx) → 01XXXXXXXXX while typing */
    function normalizePhone(raw) {
        var d = ascii(raw).replace(/\D/g, '');
        if (/^(00)?880?1/.test(d)) d = d.replace(/^(00)?880?/, '0');   // +880 / 880 / 0088 0 / 88 prefix
        else if (/^1[3-9]/.test(d)) d = '0' + d;                            // typed without the leading 0
        return d.slice(0, 11);
    }
    var PHONE_OK = /^01[3-9]\d{8}$/;
    var STORE = 'ysBuyer';
    var read = function () { try { return JSON.parse(localStorage.getItem(STORE)) || {}; } catch (e) { return {}; } };
    var write = function (v) { try { localStorage.setItem(STORE, JSON.stringify(v)); } catch (e) {} };
    var visitor = function () { try { return sessionStorage.getItem('visitorId'); } catch (e) { return null; } };
    var track = function (ev) { if (!ev) return; window.dataLayer = window.dataLayer || []; dataLayer.push({ ecommerce: null }); ev.user_data = Object.assign({ user_id: visitor() }, ev.user_data || {}); dataLayer.push(ev); };

    window.ysPhone = normalizePhone;

    document.addEventListener('alpine:init', function () {
        Alpine.data('ysProduct', function (d) {
            return {
                d: d, qty: 1, size: null, combo: {}, same: false, chart: false, err: '',
                name: '', phone: '', address: '', area: d.area, formErr: '', bad: '', placing: false, placed: null, done: false, adding: false,   // done = the order-placed modal is open
                begun: false, savedFor: null, saveTimer: null,

                boot: function () {
                    var me = read();   // a returning customer finds the form filled in
                    if (me.name) this.name = me.name;
                    if (me.phone) this.phone = normalizePhone(me.phone);
                    if (me.address) this.address = me.address;
                    if (me.area && d.areas.some(function (a) { return a.id === me.area; })) this.area = me.area;
                    track({ event: 'view_item', ecommerce: { currency: 'BDT', value: d.price, items: [this.gaItem()] } });
                    this.$watch('phone', this.queueSave.bind(this));
                    this.$watch('area', this.queueSave.bind(this));
                },

                // ── price
                get sel() { var s = this.size; return this.d.kind === 'variable' ? this.d.sizes.find(function (x) { return x.v === s; }) : null; },
                get price() { return this.sel ? this.sel.price : this.d.price; },
                get old() { return this.sel ? this.sel.old : this.d.old; },
                get max() { return Math.max(1, this.sel ? this.sel.max : this.d.maxQty); },
                get fee() { var a = this.area; var z = this.d.areas.find(function (x) { return x.id === a; }); return z ? z.fee : 0; },
                get subtotal() { return this.price * this.qty; },
                get total() { return this.subtotal + this.fee; },

                // ── choosing
                pickSize: function (v) { this.size = v; this.err = ''; this.qty = Math.min(this.qty, this.max); },
                pickCombo: function (id, v) {
                    var me = this;
                    if (this.same) this.d.combo.forEach(function (r) { if (!r.fixed && r.sizes.some(function (s) { return s.v === v && !s.out; })) me.combo[r.id] = v; });
                    else this.combo[id] = v;
                    this.err = '';
                },
                toggleSame: function () {
                    this.same = !this.same;
                    var first = this.d.combo.map(function (r) { return this.combo[r.id]; }, this).find(Boolean);
                    if (this.same && first) this.pickCombo(null, first);
                },
                picked: function (v) { return v && (this.size === v || Object.values(this.combo).indexOf(v) !== -1); },
                dec: function () { this.qty = Math.max(1, this.qty - 1); },
                inc: function () { this.qty = Math.min(this.max, this.qty + 1); },

                valid: function () {
                    if (this.d.kind === 'variable' && !this.size) { this.err = 'সাইজ বাছাই করুন'; return false; }
                    var me = this;
                    if (this.d.kind === 'combo' && this.d.combo.some(function (r) { return !r.fixed && !me.combo[r.id]; })) { this.err = 'প্রতিটি আইটেমের সাইজ বাছাই করুন'; return false; }
                    this.err = ''; return true;
                },
                pick: function () { return { qty: this.qty, size: this.size, combo: Object.assign({}, this.combo) }; },
                comboKey: function () { var me = this; return this.d.combo ? this.d.combo.map(function (r) { return me.combo[r.id] || ''; }).join('|') : null; },
                gaItem: function () {
                    return { item_name: this.d.name, item_id: this.d.catalogId, price: this.price, quantity: this.qty, item_category: this.d.gaCategory, item_brand: this.d.gaBrand, item_variant: this.size || this.comboKey() || null };
                },

                // ── cart
                addToCart: function () {
                    if (!this.valid() || this.adding) return;
                    var me = this; this.adding = true;
                    var optimistic = window.ysCart && ysCart.pending;
                    if (optimistic) ysCart.pending({ product_id: this.d.id, name: this.d.name, url: location.pathname, image_url: this.d.thumbs[0], catalog_id: this.d.catalogId,
                        size: this.d.kind === 'combo' ? this.comboKey() : this.size, variant_text: this.variantText(), qty: this.qty, price: this.price, regular: this.old || this.price });
                    this.$wire.addToCart(this.pick()).then(function (r) {
                        if (!r || !r.ok) throw new Error((r && r.error) || 'আবার চেষ্টা করুন');
                        track(r.event);
                        if (window.ysCart) { ysCart.setCount(r.count); if (ysCart.open) ysCart.open(r.cart, r.item_id); }
                    }).catch(function (e) {
                        if (optimistic) ysCart.fail();
                        me.err = e.message || 'ইন্টারনেট সংযোগ চেক করে আবার চেষ্টা করুন।';
                    }).finally(function () { me.adding = false; });
                },
                variantText: function () {
                    if (this.d.kind === 'variable') return 'সাইজ ' + this.size;
                    if (this.d.kind !== 'combo') return null;
                    var me = this;
                    return this.d.combo.map(function (r) { return r.name + ' (' + [r.color, me.combo[r.id]].filter(Boolean).join(', ') + ')'; }).join(' + ');
                },

                // ── order form
                buyNow: function () {
                    if (!this.valid()) return;
                    var f = this.$refs.form; if (!f) return;
                    window.scrollTo({ top: f.getBoundingClientRect().top + window.scrollY - 76, behavior: 'smooth' });
                    var first = !this.name ? this.$refs.name : (!PHONE_OK.test(this.phone) ? this.$refs.phone : (!this.address ? this.$refs.address : null));
                    if (first) setTimeout(function () { first.focus({ preventScroll: true }); }, 450);
                    this.beginCheckout();
                },
                beginCheckout: function () {
                    if (this.begun) return; this.begun = true;
                    track({ event: 'begin_checkout', ecommerce: { currency: 'BDT', value: this.subtotal, items: [this.gaItem()] } });
                },
                setPhone: function (e) {
                    var v = normalizePhone(e.target.value);
                    this.phone = v; if (e.target.value !== v) e.target.value = v;   // Bangla digits / +880 / spaces fixed while typing
                    if (this.bad === 'phone' && PHONE_OK.test(v)) { this.bad = ''; this.formErr = ''; }
                },
                queueSave: function () {
                    clearTimeout(this.saveTimer);
                    if (!PHONE_OK.test(this.phone) || this.savedFor === this.phone + ':' + this.area) return;
                    var me = this;
                    this.saveTimer = setTimeout(function () {
                        if (me.placed || me.placing) return;
                        me.savedFor = me.phone + ':' + me.area;
                        me.$wire.autosave(me.pick(), me.form()).catch(function () {});
                    }, 1500);
                },
                form: function () { return { name: this.name, phone: this.phone, address: this.address, area: this.area }; },
                placeOrder: function () {
                    if (this.placing) return;
                    if (!this.valid()) { this.$root.querySelector('.pp-actions').scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
                    this.bad = !this.name ? 'name' : (!PHONE_OK.test(this.phone) ? 'phone' : (!this.address.trim() ? 'address' : ''));
                    if (this.bad) { this.formErr = 'নাম, সঠিক ১১ ডিজিটের মোবাইল নাম্বার ও ঠিকানা লিখুন'; this.$refs[this.bad].focus(); return; }
                    if (!this.area) { this.formErr = 'ডেলিভারি এরিয়া নির্বাচন করুন'; return; }
                    var me = this; this.placing = true; this.formErr = ''; clearTimeout(this.saveTimer);
                    this.$wire.placeOrder(this.pick(), this.form()).then(function (r) {
                        if (!r || !r.ok) { me.formErr = (r && r.error) || 'আবার চেষ্টা করুন'; if (r && r.field) me.bad = r.field; return; }
                        write({ name: me.name, phone: me.phone, address: me.address, area: me.area });
                        me.placed = r; me.qty = 1; me.done = true;   // modal opens at once — the answer already has the details
                        track(r.purchase);
                    }).catch(function () {
                        me.formErr = 'ইন্টারনেট সংযোগ চেক করে আবার চেষ্টা করুন।';
                    }).finally(function () { me.placing = false; });
                },
            };
        });

        // gallery: thumbnails, arrows, swipe, full-screen (Fancybox when the page has it)
        Alpine.data('ysGallery', function (images) {
            return {
                images: images, i: 0, x0: null,
                go: function (n) { var len = this.images.length; this.i = (n + len) % len; },
                down: function (e) { this.x0 = (e.touches ? e.touches[0] : e).clientX; },
                up: function (e) {
                    if (this.x0 === null) return;
                    var dx = (e.changedTouches ? e.changedTouches[0] : e).clientX - this.x0; this.x0 = null;
                    if (Math.abs(dx) > 40) this.go(this.i + (dx < 0 ? 1 : -1));
                },
                zoom: function () {
                    if (window.Fancybox) Fancybox.show(this.images.map(function (src) { return { src: src, type: 'image' }; }), { startIndex: this.i });
                    else window.open(this.images[this.i], '_blank');
                },
            };
        });
    });
})();
