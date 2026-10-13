<?php
// Storefront (public site) registries. Menus: every menu location the site renders — the admin menu builder
// (Ops → Storefront → Navigation menus) shows exactly these, and App\Services\Storefront\MenuService renders them.
return [
    'menus' => [
        'topbar' => ['label' => 'Top bar', 'description' => 'Contact links above the header', 'depth' => 1],
        'header' => ['label' => 'Main menu', 'description' => 'Header navigation (desktop + mobile). Items can have a dropdown.', 'depth' => 2],
        'footer' => ['label' => 'Footer', 'description' => 'Heading and contact links in the footer', 'depth' => 1],
    ],

    // what a menu item can point to
    'menu_item_types' => [
        'route' => 'Site page', 'category' => 'Category', 'product' => 'Product', 'url' => 'Custom link', 'whatsapp' => 'WhatsApp', 'phone' => 'Phone call',
    ],

    // fixed site pages a menu can link to: key => [label, route name]
    'menu_routes' => [
        'home' => ['Home', 'home'], 'shop' => ['All products', 'shop'], 'reviews' => ['Customer reviews', 'reviews'], 'checkout' => ['Cart / checkout', 'checkout'],
    ],

    // quick picks in the icon field (Font Awesome 7 classes, already loaded by the storefront)
    'menu_icons' => [
        'fa-brands fa-whatsapp' => 'WhatsApp', 'fa-solid fa-phone' => 'Phone', 'fa-brands fa-facebook' => 'Facebook', 'fa-brands fa-facebook-messenger' => 'Messenger',
        'fa-brands fa-instagram' => 'Instagram', 'fa-brands fa-youtube' => 'YouTube', 'fa-brands fa-tiktok' => 'TikTok', 'fa-solid fa-envelope' => 'Email',
        'fa-solid fa-location-dot' => 'Location', 'fa-solid fa-bag-shopping' => 'Bag', 'fa-solid fa-tag' => 'Tag', 'fa-solid fa-fire' => 'Hot', 'fa-solid fa-star' => 'Star',
    ],
];
