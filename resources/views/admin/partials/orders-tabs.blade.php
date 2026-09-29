<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link {{ ($active ?? '') === 'orders' ? 'active fw-semibold' : '' }}"
            href="{{ route('admin.orders') }}">
            <i class="icon-list me-1"></i> Orders
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($active ?? '') === 'products' ? 'active fw-semibold' : '' }}"
            href="{{ route('admin.orders.products') }}">
            <i class="icon-box me-1"></i> Product Summary
        </a>
    </li>
</ul>
