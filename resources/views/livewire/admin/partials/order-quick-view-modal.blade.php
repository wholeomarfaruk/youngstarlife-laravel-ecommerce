    <div x-data="{
            open: false,
            loading: false,
            content: '',
            fetchOrder(id) {
                this.open = true;
                this.loading = true;
                this.content = '';
                fetch('{{ url('admin/orders') }}/' + id + '/quick-view')
                    .then(r => r.text())
                    .then(html => { this.content = html; this.loading = false; })
                    .catch(() => { this.content = '<p class=\'text-danger\'>Failed to load order details.</p>'; this.loading = false; });
            }
         }"
         @open-order-modal.window="fetchOrder($event.detail.id)"
         x-show="open" x-cloak
         class="modal-backdrop-custom position-fixed top-0 start-0 w-100 h-100 align-items-center justify-content-center"
         style="z-index: 1055; background: rgba(0,0,0,.5); display: flex;"
         @click.self="open = false">
         <div class="position-relative d-flex align-items-center justify-content-center w-100 h-100">

        <div class="bg-white rounded shadow" style="width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;"
             @click.outside="open = false">
            <div class="d-flex justify-content-between align-items-center border-bottom p-3">
                <h5 class="mb-0">Order Details</h5>
                <button type="button" class="btn-close" @click="open = false"></button>
            </div>
            <div class="p-3">
                <div x-show="loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <div x-show="!loading" x-html="content"></div>
            </div>
        </div>
        </div>
    </div>
