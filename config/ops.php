<?php
// Single source for navigation, module tabs and every enum (key → label + badge tone). Mirrors the YoungStar Ops design 1:1.
return [
    'nav' => [
        ['label' => 'Dashboard',             'route' => 'ops.dashboard',        'match' => 'ops.dashboard|ops.trace'],
        ['label' => 'Product & Inventory',   'route' => 'ops.products.index',   'match' => 'ops.products.*|ops.attributes.*|ops.batches.*|ops.purchases.*|ops.supplier-returns.*'],
        ['label' => 'Orders & Packing',      'route' => 'ops.orders.index',     'match' => 'ops.orders.*|ops.packing.*|ops.returns.*|ops.exchanges.*'],
        ['label' => 'Couriers',              'route' => 'ops.couriers.index',   'match' => 'ops.couriers.*'],
        ['label' => 'Users',                 'route' => 'ops.users.index',      'match' => 'ops.users.*|ops.customers.*|ops.suppliers.*|ops.blacklist.*'],
        ['label' => 'Accounts & Finance',    'route' => 'ops.accounts.index',   'match' => 'ops.accounts.*'],
        ['label' => 'Reports',               'route' => 'ops.reports.index',    'match' => 'ops.reports.*'],
        ['label' => 'Storefront',            'route' => 'ops.storefront.index', 'match' => 'ops.storefront.*'],
        ['label' => 'Settings',              'route' => 'ops.settings.index',   'match' => 'ops.settings.*|ops.profile'],
    ],
    'tabs' => [
        'inventory' => [
            ['All products', 'ops.products.index'], ['Batches', 'ops.batches.index'],
            ['Purchases', 'ops.purchases.index'], ['Supplier returns', 'ops.supplier-returns.index'],
        ],
        'couriers' => [
            ['Courier settlements', 'ops.couriers.index'], ['Entry sessions', 'ops.couriers.sessions'],
        ],
        'orders' => [
            ['Orders', 'ops.orders.index'], ['By date', 'ops.orders.by-date'], ['Campaign summary', 'ops.orders.campaigns'],
            ['Drafts', 'ops.orders.drafts'], ['Bulk / AI order', 'ops.orders.bulk'], ['Ordered products', 'ops.orders.products'], ['Bulk courier', 'ops.orders.bulk-courier'], ['In courier', 'ops.orders.in-courier'],
            ['Packing scan', 'ops.packing.scan'], ['Return receive', 'ops.returns.receive'], ['Exchanges', 'ops.exchanges.index'],
        ],
        'parties' => [['Users', 'ops.users.index'], ['Customers', 'ops.customers.index'], ['Suppliers', 'ops.suppliers.index'], ['Blacklist', 'ops.blacklist.index']],
    ],

    // ── Enums: key => [label, tone]   (tone: ok | info | warn | bad | mute)
    'order_status' => [
        'pending' => ['Pending', 'mute'], 'confirmed' => ['Confirmed', 'info'], 'processing' => ['Processing', 'info'],
        'packed' => ['Packed', 'info'], 'shipped' => ['Shipped', 'warn'], 'delivered' => ['Delivered', 'ok'],
        'partially_delivered' => ['Partially delivered', 'warn'], 'returning' => ['Returning', 'bad'],
        'returned' => ['Return received', 'bad'], 'on_hold' => ['On hold', 'warn'], 'cancelled' => ['Cancelled', 'mute'],
    ],
    // order list tab labels (Returning and Returned are separate tabs)
    'order_tabs' => ['all' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'packed' => 'Packed', 'shipped' => 'Shipped',
        'delivered' => 'Delivered', 'partially_delivered' => 'Partial', 'returning' => 'Returning', 'returned' => 'Return received', 'on_hold' => 'On hold', 'cancelled' => 'Cancelled'],
    'courier_status' => [
        'pending' => ['Pending', 'mute'], 'picked_up' => ['Picked up', 'info'], 'in_transit' => ['In transit', 'info'],
        'out_for_delivery' => ['Out for delivery', 'warn'], 'delivered' => ['Delivered', 'ok'], 'partial_delivered' => ['Partial delivered', 'warn'],
        'hold' => ['Hold', 'warn'], 'returning' => ['Returning', 'bad'], 'returned' => ['Returned', 'bad'], 'cancelled' => ['Cancelled', 'mute'], 'lost' => ['Lost', 'bad'],
    ],
    'payment_status' => ['pending' => ['Unpaid', 'mute'], 'partial' => ['Partial', 'warn'], 'paid' => ['Paid', 'ok'], 'failed' => ['Failed', 'bad'], 'refunded' => ['Refunded', 'info']],
    'sources' => ['facebook' => 'Facebook', 'website' => 'Website', 'instagram' => 'Instagram', 'phone' => 'Phone'],
    'product_types' => ['simple' => 'Simple', 'variable' => 'Variable', 'combo' => 'Combo'],
    'product_status' => ['active' => ['Active', 'ok'], 'draft' => ['Draft', 'mute'], 'archived' => ['Archived', 'warn']],
    'batch_status' => ['active' => ['Active', 'ok'], 'queued' => ['Next in queue', 'info'], 'low' => ['Low', 'warn'], 'depleted' => ['Depleted', 'mute']],
    'po_status' => ['draft' => ['Draft', 'mute'], 'sent' => ['Sent to supplier', 'info'], 'partial' => ['Partially received', 'warn'], 'received' => ['Received', 'ok'], 'cancelled' => ['Cancelled', 'bad']],
    'return_types' => ['full' => 'Full', 'partial' => 'Partial', 'exchange' => 'Exchange', 'resend' => 'Replaced parcel'],
    'return_status' => ['received' => ['Received', 'info'], 'inspected' => ['Inspected', 'ok'], 'closed' => ['Closed', 'mute']],
    'supplier_return_status' => ['pending' => ['Pending', 'mute'], 'sent' => ['Sent', 'info'], 'received' => ['Received by supplier', 'info'], 'approved' => ['Approved', 'warn'], 'resolved' => ['Resolved', 'ok'], 'rejected' => ['Rejected', 'bad'], 'cancelled' => ['Cancelled', 'mute']],
    'supplier_return_resolutions' => ['replacement' => 'Replacement', 'credit' => 'Credit', 'refund' => 'Refund'],
    'campaign_status' => ['active' => ['Active', 'ok'], 'paused' => ['Paused', 'warn'], 'ended' => ['Ended', 'mute']],

    // return condition → [label, action, action label, tone]
    'return_conditions' => [
        'good' => ['Good', 'restock', 'Restocked', 'ok'], 'damaged' => ['Damaged', 'damaged', 'Damaged stock', 'bad'],
        'used' => ['Used', 'damaged', 'Damaged stock', 'bad'], 'broken' => ['Broken', 'damaged', 'Damaged stock', 'bad'],
        'wrong_item' => ['Wrong item', 'investigate', 'Investigation', 'warn'], 'missing' => ['Missing', 'courier_claim', 'Courier claim', 'warn'],
        'unsellable' => ['Unsellable', 'loss', 'Recorded as loss', 'mute'],
    ],
    'return_reasons' => ['Damaged', 'Wrong Product', 'Wrong Size', 'Wrong Color', 'Customer Changed Mind', 'Product Defect', 'Missing Part', 'Courier Damage', 'Other'],
    'movement_types' => ['stock_in' => 'Stock in', 'sale' => 'Sale', 'return_restock' => 'Return restock', 'damage' => 'Damage', 'adjust' => 'Adjust', 'transfer' => 'Transfer', 'supplier_return' => 'Supplier return', 'replacement' => 'Replacement'],
    'transaction_categories' => ['Sales', 'Courier payment', 'Supplier', 'Refund', 'Supplier refund', 'Packaging', 'Marketing', 'Salary', 'Other'],
    'accounts' => ['Cash', 'Bank', 'bKash', 'Nagad'],
    'roles' => ['Admin', 'Manager', 'Packer', 'Accounts'],
    // Users → Blacklist
    'blacklist_types' => ['phone' => 'Phone', 'device' => 'Device', 'ip' => 'IP address', 'email' => 'Email'],
    'blacklist_actions' => ['block' => ['Block', 'bad'], 'flag' => ['Flag for review', 'warn']],
    'blacklist_reasons' => ['fake_order' => 'Fake / prank order', 'refused' => 'Refused parcels', 'fraud' => 'Fraud / scam', 'abusive' => 'Abusive behaviour', 'reseller' => 'Unauthorised reseller', 'other' => 'Other'],
    'blacklist_state' => ['active' => ['Active', 'bad'], 'expired' => ['Expired', 'mute'], 'removed' => ['Removed', 'mute']],
    'blacklist_channels' => ['website' => 'Website checkout', 'autosave' => 'Abandoned checkout', 'bulk' => 'Bulk / AI order', 'draft' => 'Draft → order', 'order' => 'Order (flag)'],
    'segments' => ['vip' => ['VIP', 'ok'], 'repeat' => ['Repeat', 'info'], 'new' => ['New', 'mute'], 'risky' => ['Risky', 'bad'], 'due' => ['Has due', 'warn']],
    'coupon_types' => ['pct' => '% off', 'flat' => '৳ off', 'ship' => 'Free delivery', 'bogo' => 'Buy X get Y'],

    // notification events (Settings → Web push matrix) → [label, category]
    // exchange lifecycle (App\Services\Ops\ExchangeService) — key => [label, tone, short]
    'exchange_state' => [
        'awaiting' => ['Waiting to send', 'mute', 'To send'], 'out' => ['With courier', 'info', 'With courier'],
        'collecting' => ['Delivered · old item coming back', 'warn', 'Collecting'], 'completed' => ['Old item received', 'ok', 'Done'],
        'old_missing' => ['Old item not returned', 'bad', 'Old missing'], 'refused' => ['Refused · new item coming back', 'bad', 'Refused'],
        'failed' => ['Refused · new item back in stock', 'mute', 'Failed'], 'cancelled' => ['Cancelled', 'mute', 'Cancelled'],
    ],
    // old-item return line of an exchange (ops_customer_returns.status)
    'return_status' => ['expected' => ['Coming back', 'warn'], 'received' => ['Received · inspect', 'info'], 'inspected' => ['Inspected', 'ok'], 'closed' => ['Closed', 'ok'], 'cancelled' => ['Cancelled', 'mute']],

    'notify_events' => [
        'newOrder' => ['New order', 'orders'], 'risky' => ['Risky customer order (<75%)', 'orders'], 'delay' => ['Parcel no movement 3 days', 'courier'],
        'returnIn' => ['Return received', 'returns'], 'lowStock' => ['Variant below threshold', 'stock'], 'settle' => ['Courier settlement / short paid', 'money'],
        'srUpdate' => ['Supplier return status changed', 'returns'], 'blacklist' => ['Blacklisted customer tried to order', 'orders'],
        'exchange' => ['Exchange old item overdue / refused', 'returns'],
    ],
    'notify_categories' => ['orders' => 'Orders', 'stock' => 'Stock', 'courier' => 'Courier', 'returns' => 'Returns', 'money' => 'Money', 'system' => 'System'],

    'modules' => ['Dashboard', 'Product & Inventory', 'Orders & Packing', 'Couriers', 'Users', 'Accounts & Finance', 'Reports', 'Storefront', 'Settings'],
    'currency' => '৳',
    // the old /admin panel is a read-only archive since the website runs on Ops (App\Support\LegacyAdmin)
    'legacy_admin_read_only' => env('OPS_LEGACY_ADMIN_READ_ONLY', true),
];
