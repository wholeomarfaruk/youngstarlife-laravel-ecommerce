
<div>
    <style>
        #products-sort-table .drag-handle {
            cursor: grab;
            color: #888;
            font-size: 18px;
            text-align: center;
        }

        #products-sort-table .drag-handle:active {
            cursor: grabbing;
        }

        #products-sort-table .sortable-ghost {
            opacity: 0.4;
            background: #e9f5ff;
        }

        #products-sort-table .sortable-drag {
            background: #fff;
        }

        #products-sort-table .sort-order-input {
            width: 80px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 4px 6px;
        }
    </style>
    <div class="main-content-inner">
        <div class="main-content-wrap">
            <div class="flex items-center flex-wrap justify-between gap20 mb-27">
                <h3>All Products</h3>
                <ul class="breadcrumbs flex items-center flex-wrap justify-start gap10">
                    <li>
                        <a href="{{ route('admin.index') }}">
                            <div class="text-tiny">Dashboard</div>
                        </a>
                    </li>
                    <li>
                        <i class="icon-chevron-right"></i>
                    </li>
                    <li>
                        <div class="text-tiny">All Products</div>
                    </li>
                </ul>
            </div>

            <div class="wg-box">
                <div class="flex items-center justify-between gap10 flex-wrap">
                    <div class="wg-filter flex-grow">
                        <form class="form-search">
                            <fieldset class="name">
                                <input wire:model.live="search" type="text" placeholder="Search here..." class="" name="search"
                                    tabindex="2" value="" aria-required="true" required="">
                            </fieldset>
                            <div class="button-submit">
                                <button class="" type="submit"><i class="icon-search"></i></button>
                            </div>
                        </form>
                    </div>
                    <a class="tf-button style-1 w208" href="{{ route('admin.products.add') }}"><i
                            class="icon-plus"></i>Add new</a>
                </div>
                <div class="table-responsive">
                    @if (Session::has('status'))
                        <div class="alert alert-success" role="alert">
                            {{ Session::get('status') }}
                        </div>
                    @endif
                    <div class="text-tiny mb-2">
                        Drag rows by the handle to reorder within this page, or type a number in
                        <strong>Sort Order</strong> to move a product to any global position. Changes save automatically.
                    </div>
                    <table class="table table-striped table-bordered" id="products-sort-table"
                        wire:ignore.self>
                        <thead>

                            <tr>
                                <th></th>
                                <th>Sort Order</th>
                                <th>#</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>SKU</th>
                                <th>Stock</th>
                                <th>Quantity</th>
                                <th>Featured</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="products-sortable">
                            @foreach ($products as $product)
                                <tr wire:key="product-{{ $product->id }}" data-id="{{ $product->id }}">
                                    <td class="drag-handle" title="Drag to reorder">
                                        <i class="icon-menu"></i>
                                    </td>
                                    <td>
                                        <input type="number" class="sort-order-input"
                                            value="{{ $product->sort_order }}" min="1" step="1"
                                            wire:change.debounce.500ms="setPosition({{ $product->id }}, $event.target.value)">
                                    </td>
                                    <td>{{ $product->id }}</td>
                                    <td class="pname">
                                        <div class="image">
                                            <img src="{{ asset('storage/images/products/thumbnails/' . $product->image) }}"
                                                alt="{{ $product->name }}" class="image">
                                        </div>
                                        <div class="name">
                                            <a target="_blank" href="{{ route('product.show', $product->slug) }}"
                                                class="body-title-2">{{ $product->name }}</a>
                                            <div class="text-tiny mt-3">{{ $product->slug }}</div>
                                        </div>
                                    </td>
                                    <td>{{ $product->price }}</td>
                                    <td>{{ $product->sku }}</td>
                                    <td>{{ $product->stock_status }}</td>
                                    <td>{{ $product->quantity }}</td>
                                    <td>{{ $product->featured == 1 ? 'Yes' : 'No' }}</td>
                                    <td >
                                        <div class="list-icon-function">

                                           <div class="form-check form-switch">
  <input class="form-check-input" type="checkbox" role="switch" id="switchCheckChecked" {{ $product->status == 1 ? 'checked' : '' }} wire:click="updateStatus({{ $product->id }})">
  <label class="form-check-label" for="switchCheckChecked">Status</label>
</div>
                                            <a href="{{ route('admin.products.copy', ['id' => $product->id]) }}">
                                                <div class="item edit">
                                                    Copy
                                                </div>
                                            </a>
                                            <a href="{{ route('admin.products.edit', ['id' => $product->id]) }}">
                                                <div class="item edit">
                                                    <i class="icon-edit-3"></i>
                                                </div>
                                            </a>
                                            <form action="{{ route('admin.products.delete', ['id' => $product->id]) }}"
                                                method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <div class="item text-danger delete">
                                                    <i class="icon-trash-2"></i>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="divider"></div>
                <div class="flex items-center justify-between flex-wrap gap10 wgp-pagination">

                    {{ $products->links() }}

                </div>
            </div>
        </div>
    </div>

    @script
    <script>
        // Load SortableJS once, then keep the table sortable across Livewire re-renders.
        function loadSortable(cb) {
            if (window.Sortable) return cb();
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
            s.onload = cb;
            document.head.appendChild(s);
        }

        let sortableInstance = null;

        function initProductsSortable() {
            const el = document.getElementById('products-sortable');
            if (!el) return;

            // Avoid stacking multiple instances on the same element after a morph.
            if (sortableInstance && sortableInstance.el === el) return;
            if (sortableInstance) {
                try { sortableInstance.destroy(); } catch (e) {}
            }

            sortableInstance = window.Sortable.create(el, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd: function() {
                    const ids = Array.from(el.querySelectorAll('tr[data-id]'))
                        .map(tr => tr.getAttribute('data-id'));
                    $wire.updateOrder(ids);
                }
            });
        }

        loadSortable(initProductsSortable);

        // Re-attach after Livewire updates the DOM (search, pagination, reorder).
        Livewire.hook('morphed', () => {
            sortableInstance = null;
            loadSortable(initProductsSortable);
        });
    </script>
    @endscript
</div>
