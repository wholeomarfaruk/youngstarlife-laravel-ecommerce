// Livewire 3 bundles Alpine. Do NOT import Alpine separately.
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import TomSelect from 'tom-select/base';                            // select2-style searchable select, no jQuery
import TomSelectClearButton from 'tom-select/dist/esm/plugins/clear_button/plugin.js';
import 'tom-select/dist/css/tom-select.css';
import Sortable from 'sortablejs';                                  // drag & drop (lists + nested trees, touch)
import swal from 'sweetalert';                                      // confirm dialogs: window.opsConfirm below

TomSelect.define('clear_button', TomSelectClearButton);
window.Sortable = Sortable;

const toast = (message, type = 'ok') => window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));

// Copy tracking / IDs: onclick="opsCopy('SF553357')"
window.opsCopy = (text) => navigator.clipboard?.writeText(text).then(() => toast('Copied ' + text), () => toast('Could not copy', 'bad'));

// SweetAlert confirm → Promise<boolean>. x-on:click="opsConfirm({ title, text, confirm: 'Delete', danger: true }).then(ok => ok && $wire.deleteOrder(5))"
window.opsConfirm = ({ title = 'Are you sure?', text = '', confirm = 'Yes', cancel = 'Cancel', danger = false } = {}) => swal({
  title, text, icon: danger ? 'warning' : 'info', dangerMode: danger,
  buttons: { cancel: { text: cancel, visible: true, value: false }, confirm: { text: confirm, value: true, closeModal: true } },
}).then(Boolean);

// Scan feedback sound: Livewire $this->dispatch('ops-beep', tone: 'ok' | 'warn' | 'bad' | 'done') — no audio files, WebAudio tones
let beepCtx;
window.addEventListener('ops-beep', e => {
  try {
    beepCtx ??= new (window.AudioContext || window.webkitAudioContext)();
    const notes = { ok: [[880, .08]], done: [[660, .1], [990, .14]], warn: [[440, .12], [440, .12]], bad: [[200, .28]] }[e.detail?.tone] || [];
    let t = beepCtx.currentTime;
    for (const [f, d] of notes) {
      const o = beepCtx.createOscillator(), g = beepCtx.createGain();
      o.type = e.detail?.tone === 'bad' ? 'square' : 'sine'; o.frequency.value = f; g.gain.value = .08;
      o.connect(g).connect(beepCtx.destination); o.start(t); o.stop(t + d); t += d + .05;
    }
  } catch (_) {}
});

// Unsaved-changes guard: a form sets window.opsDirty = true while edited (Product edit, PO, Settings)
window.opsDirty = false;
document.addEventListener('livewire:navigate', e => {
  if (window.opsDirty && !confirm('You have unsaved changes. Leave without saving?')) e.preventDefault(); else window.opsDirty = false;
});
window.addEventListener('beforeunload', e => { if (window.opsDirty) { e.preventDefault(); e.returnValue = ''; } });

document.addEventListener('alpine:init', () => {
  Alpine.store('ui', { nav: false, toggleNav() { this.nav = !this.nav } });

  // Dropdown / row menu (⋯): x-data="menu"
  Alpine.data('menu', () => ({ open: false, toggle() { this.open = !this.open }, close() { this.open = false } }));

  // Modal: x-data="modal('name')" ; open with $dispatch('open-modal','name') or PHP $this->dispatch('open-modal', name: 'name')
  Alpine.data('modal', (name) => ({
    open: false,
    init() {
      window.addEventListener('open-modal', e => (e.detail === name || e.detail?.name === name) && (this.open = true));
      window.addEventListener('close-modal', e => (!e.detail || typeof e.detail !== 'string' || e.detail === name) && (this.open = false));
    },
  }));

  // Date picker (flatpickr): x-data="datepicker(@entangle(...), { max, min })" — used by <x-ops.date>
  Alpine.data('datepicker', (model, opts = {}) => ({
    value: model, fp: null,
    init() {
      this.fp = flatpickr(this.$refs.input, {
        dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y', disableMobile: true,
        maxDate: opts.max || null, minDate: opts.min || null, defaultDate: this.value || null,
        onChange: (_, str) => { if (str !== this.value) this.value = str; },
      });
      if (this.$refs.input.disabled) this.fp.altInput.disabled = true;   // e.g. PO order date locked after sending
      this.$watch('value', v => { if ((v || '') !== (this.fp.input.value || '')) this.fp.setDate(v || null, false); });
    },
    clear() { this.fp.clear(); this.value = ''; },
    destroy() { this.fp?.destroy(); },
  }));

  // Searchable select (Tom Select) — used by <x-ops.select>. Static options, or remote search through a Livewire method
  // (opts.remote = 'searchProducts' → returns [{value, text}]). Two-way bound to wire:model via @entangle.
  Alpine.data('tomSelect', (model, opts = {}) => ({
    value: model, ts: null,
    init() {
      const remote = opts.remote ? {
        valueField: 'value', labelField: 'text', searchField: ['text'], preload: 'focus', loadThrottle: 250,
        load: (q, done) => this.$wire.call(opts.remote, q).then(done).catch(() => done()),
      } : {};
      this.ts = new TomSelect(this.$refs.select, {
        create: false, maxOptions: 300,   // no empty option: the placeholder shows until a value is picked, × clears it placeholder: opts.placeholder || 'Search…',
        plugins: opts.clearable === false ? [] : ['clear_button'],
        render: { no_results: () => '<div class="no-results">Nothing found</div>' },
        onChange: v => { if (String(v ?? '') !== String(this.value ?? '')) this.value = v === '' ? null : v; },
        ...remote,
      });
      if (this.value != null && this.value !== '') this.ts.setValue(String(this.value), true);
      this.$watch('value', v => { if (String(v ?? '') !== String(this.ts.getValue() ?? '')) this.ts.setValue(v == null ? '' : String(v), true); });
    },
    destroy() { this.ts?.destroy(); },
  }));

  // Drag & drop ordering (SortableJS). Lists are elements with [data-sortable]; items carry data-id; a nested list inside an
  // item ([data-sortable][data-children]) makes a tree (max 2 levels). On drop the whole order is sent to the Livewire
  // method as [{id, children: [{id}]}]. x-data="sortableTree('reorder', { nested: true })"
  Alpine.data('sortableTree', (method, opts = {}) => ({
    lists: [],
    init() {
      this.mount();
      // lists appear / disappear when Livewire re-renders this component → re-attach afterwards
      Livewire.hook('commit', ({ component, succeed }) => {
        if (component.id === this.$wire.$id) succeed(() => queueMicrotask(() => this.mount()));
      });
    },
    mount() {
      this.lists.forEach(s => s.destroy());
      this.lists = [...this.$el.querySelectorAll('[data-sortable]')].map(el => Sortable.create(el, {
        group: { name: 'tree', put: (to, from, drag) => !to.el.hasAttribute('data-children') || !drag.querySelector('[data-children] [data-id]') },
        handle: '[data-drag]', animation: 150, forceFallback: true, fallbackOnBody: true,   // pointer-event drag: same on mouse, touch and pen swapThreshold: 0.65, emptyInsertThreshold: 14,
        ghostClass: 'sortable-ghost', chosenClass: 'sortable-chosen',
        onEnd: () => this.$wire.call(method, this.serialize(this.$el.querySelector('[data-sortable]:not([data-children])'))),
      }));
    },
    serialize(list) {
      return [...list.children].filter(li => li.dataset.id).map(li => {
        const kids = li.querySelector(':scope [data-children]');
        return { id: +li.dataset.id, children: kids ? this.serialize(kids) : [] };
      });
    },
    destroy() { this.lists.forEach(s => s.destroy()); },
  }));

  // Toasts: Livewire -> $this->dispatch('toast', message: 'Saved', type: 'ok')
  Alpine.data('toasts', () => ({
    items: [],
    push(e) { const id = Date.now() + Math.random(); this.items.push({ id, ...e.detail }); setTimeout(() => this.items = this.items.filter(i => i.id !== id), 3200) },
  }));

  // Barcode scanner input: keeps focus, submits on Enter (USB/Bluetooth scanners type + Enter)
  Alpine.data('scanner', (method) => ({
    code: '',
    init() { this.$nextTick(() => this.$refs.input?.focus()); },
    submit() { if (!this.code.trim()) return; this.$wire.call(method, this.code.trim()); this.code = ''; this.$refs.input.focus(); },
  }));

  // Phone camera scanning (BarcodeDetector — Chrome / Android). x-data="camera('scan')"
  Alpine.data('camera', (method) => ({
    on: false, stream: null, last: '', error: '',
    async start() {
      if (!('BarcodeDetector' in window)) { this.error = 'Camera scanning needs Chrome on Android. Use a USB / Bluetooth scanner or type the code.'; return; }
      try {
        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        this.on = true; this.error = ''; await this.$nextTick(); this.$refs.video.srcObject = this.stream; await this.$refs.video.play();
        const detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'qr_code', 'code_39', 'upc_a'] });
        const tick = async () => {
          if (!this.on) return;
          try { const [hit] = await detector.detect(this.$refs.video); if (hit && hit.rawValue !== this.last) { this.last = hit.rawValue; this.$wire.call(method, hit.rawValue); setTimeout(() => this.last = '', 2000); } } catch (_) {}
          requestAnimationFrame(tick);
        };
        tick();
      } catch (e) { this.error = 'Camera permission denied or not available.'; }
    },
    stop() { this.on = false; this.stream?.getTracks().forEach(t => t.stop()); this.stream = null; },
    destroy() { this.stop(); },
  }));

  // Global "/" and ⌘K focus the topbar search
  window.addEventListener('keydown', e => {
    const t = (e.target.tagName || '').toLowerCase();
    if (((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') || (e.key === '/' && !['input', 'textarea', 'select'].includes(t))) { e.preventDefault(); document.getElementById('global-search')?.focus(); }
  });
});
