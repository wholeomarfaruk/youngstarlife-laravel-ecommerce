// Livewire 3 bundles Alpine. Do NOT import Alpine separately.
import './bootstrap';

document.addEventListener('alpine:init', () => {
  Alpine.store('ui', { nav: false, toggleNav() { this.nav = !this.nav } });

  // Dropdown / row menu (⋯): x-data="menu"
  Alpine.data('menu', () => ({ open: false, toggle() { this.open = !this.open }, close() { this.open = false } }));

  // Modal: x-data="modal('name')" ; open with $dispatch('open-modal','name')
  Alpine.data('modal', (name) => ({
    open: false,
    init() { window.addEventListener('open-modal', e => e.detail === name && (this.open = true)); window.addEventListener('close-modal', () => this.open = false) },
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

  // Global "/" and ⌘K focus the topbar search
  window.addEventListener('keydown', e => {
    const t = (e.target.tagName || '').toLowerCase();
    if (((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') || (e.key === '/' && !['input', 'textarea', 'select'].includes(t))) { e.preventDefault(); document.getElementById('global-search')?.focus(); }
  });
});
