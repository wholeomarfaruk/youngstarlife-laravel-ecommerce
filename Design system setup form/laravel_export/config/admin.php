<?php
// Single source for navigation, module tabs and status → badge tone. Mirrors the design 1:1.
return [
    'nav' => [
        ['label' => 'Dashboard',            'route' => 'admin.dashboard',       'match' => 'admin.dashboard'],
        ['label' => 'Product & Inventory',  'route' => 'admin.products.index',  'match' => 'admin.products.*|admin.batches.*|admin.purchases.*|admin.supplier-returns.*'],
        ['label' => 'Orders & Packing',     'route' => 'admin.orders.index',    'match' => 'admin.orders.*|admin.packing.*|admin.returns.*'],
        ['label' => 'Couriers',             'route' => 'admin.couriers.index',  'match' => 'admin.couriers.*'],
        ['label' => 'Customers & Suppliers','route' => 'admin.customers.index', 'match' => 'admin.customers.*|admin.suppliers.*'],
        ['label' => 'Accounts',             'route' => 'admin.accounts.index',  'match' => 'admin.accounts.*'],
        ['label' => 'Reports',              'route' => 'admin.reports.index',   'match' => 'admin.reports.*'],
        ['label' => 'Storefront',           'route' => 'admin.storefront.index','match' => 'admin.storefront.*'],
        ['label' => 'Settings',             'route' => 'admin.settings.index',  'match' => 'admin.settings.*'],
    ],
    'tabs' => [
        'inventory' => [
            ['All products', 'admin.products.index'], ['Batches', 'admin.batches.index'],
            ['Purchases', 'admin.purchases.index'], ['Supplier returns', 'admin.supplier-returns.index'],
        ],
        'orders' => [
            ['Orders', 'admin.orders.index'], ['By date', 'admin.orders.by-date'], ['Campaign summary', 'admin.orders.campaigns'],
            ['Drafts', 'admin.orders.drafts'], ['Ordered products', 'admin.orders.products'], ['In courier', 'admin.orders.in-courier'],
            ['Return receive', 'admin.returns.receive'], ['Packing scan', 'admin.packing.scan'],
        ],
        'parties' => [['Customers', 'admin.customers.index'], ['Suppliers', 'admin.suppliers.index']],
    ],
    // badge tone per status (ok | info | warn | bad | mute)
    'order_status' => [
        'pending' => ['Pending', 'mute'], 'confirmed' => ['Confirmed', 'info'], 'processing' => ['Processing', 'info'],
        'packed' => ['Packed', 'info'], 'shipped' => ['Shipped', 'warn'], 'delivered' => ['Delivered', 'ok'],
        'partially_delivered' => ['Partially delivered', 'warn'], 'returning' => ['Returning', 'bad'],
        'returned' => ['Returned', 'bad'], 'on_hold' => ['On hold', 'warn'], 'cancelled' => ['Cancelled', 'mute'],
    ],
    'courier_status' => [
        'pending' => ['Pending', 'mute'], 'picked_up' => ['Picked up', 'info'], 'in_transit' => ['In transit', 'info'],
        'out_for_delivery' => ['Out for delivery', 'warn'], 'delivered' => ['Delivered', 'ok'], 'partial_delivered' => ['Partial delivered', 'warn'],
        'hold' => ['Hold', 'warn'], 'returning' => ['Returning', 'bad'], 'returned' => ['Returned', 'bad'], 'cancelled' => ['Cancelled', 'mute'], 'lost' => ['Lost', 'bad'],
    ],
    'payment_status' => ['pending' => ['Pending', 'mute'], 'partial' => ['Partial', 'warn'], 'paid' => ['Paid', 'ok'], 'refunded' => ['Refunded', 'mute']],
    'batch_status' => ['active' => ['Active', 'ok'], 'queued' => ['Next in queue', 'info'], 'low' => ['Low', 'warn'], 'depleted' => ['Depleted', 'mute']],
    'po_status' => ['draft' => ['Draft', 'mute'], 'sent' => ['Sent to supplier', 'info'], 'partial' => ['Partially received', 'warn'], 'received' => ['Received', 'ok'], 'cancelled' => ['Cancelled', 'bad']],
    'supplier_return_status' => ['pending' => ['Pending', 'mute'], 'sent' => ['Sent', 'info'], 'received' => ['Received by supplier', 'info'], 'approved' => ['Approved', 'warn'], 'resolved' => ['Resolved', 'ok'], 'rejected' => ['Rejected', 'bad'], 'cancelled' => ['Cancelled', 'mute']],
    'return_conditions' => ['good' => 'Good', 'damaged' => 'Damaged', 'used' => 'Used', 'wrong_item' => 'Wrong item', 'missing' => 'Missing'],
    'return_reasons' => ['Damaged', 'Wrong Product', 'Wrong Size', 'Wrong Color', 'Customer Changed Mind', 'Product Defect', 'Missing Part', 'Courier Damage', 'Other'],
    'currency' => '৳',
];
