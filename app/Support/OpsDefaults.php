<?php

namespace App\Support;

/** Design defaults for every Settings / Storefront section (stored as JSON in ops_settings, key = group). */
class OpsDefaults
{
    public static function get(?string $group): ?array { return $group ? (static::all()[$group] ?? null) : null; }

    /** stored values over defaults, but only where the shape matches (old/partial saves never break a screen) */
    public static function merge(?array $default, $stored): array
    {
        if (! is_array($stored)) return $default ?? [];
        if (! $default) return $stored;
        $out = $default;
        foreach ($stored as $k => $v) {
            if (! array_key_exists($k, $default)) { $out[$k] = $v; continue; }
            $d = $default[$k];
            if (is_array($d) && array_is_list($d)) $out[$k] = is_array($v) ? array_values($v) : $d;        // lists (banners, coupons…) replace
            elseif (is_array($d)) $out[$k] = is_array($v) ? static::merge($d, $v) : $d;                     // nested groups merge
            else $out[$k] = is_array($v) ? $d : $v;                                                          // scalars
        }
        return $out;
    }

    public static function all(): array
    {
        $rule = fn (bool $on, string $when, string $then) => ['on' => $on, 'when' => $when, 'then' => $then];
        return [
            'company' => ['name' => 'YoungStar Life', 'phone' => '01700-000000', 'email' => 'hello@youngstarlife.com', 'address' => 'Mirpur 10, Dhaka', 'warehouse_id' => null, 'currency' => 'BDT (৳)', 'invoice_prefix' => 'INV-', 'timezone' => 'Asia/Dhaka'],
            'orders' => ['prefix' => '#', 'cart_enabled' => true, 'confirm_call' => true, 'auto_cancel_hours' => 48, 'free_delivery_above' => 3000, 'advance_for_risky' => true, 'allow_partial' => true, 'exchange_charge' => 100, 'exchange_overdue_days' => 5, 'parcel_stuck_days' => 3,
                // website order limits: per product (off | qty | value ৳) and the whole cart / order value (0 = no limit)
                'limit' => ['type' => 'off', 'qty' => 5, 'value' => 5000, 'cart_value' => 0]],
            'payments' => ['cash' => true, 'bank' => true, 'bank_account' => 'DBBL 123-456-7890', 'bkash' => true, 'bkash_no' => '01700-000000', 'nagad' => true, 'nagad_no' => '01700-000000', 'cod' => true],
            // website defaults = what the storefront showed before it read settings (nothing changes until someone edits a value)
            'branding' => ['store_name' => 'YOUNGSTAR Life', 'tagline' => 'Everyday fashion, delivered', 'logo' => null, 'favicon' => null, 'primary_color' => '#525754', 'footer_note' => ''],
            'seo' => ['title' => 'YOUNGSTAR Life', 'title_format' => '{page} · {site}', 'description' => '', 'share_image' => 'logo', 'share_image_path' => null, 'sitemap' => true, 'indexable' => true, 'search_console' => ''],
            'pixels' => ['fb_pixel' => '', 'capi_token' => '', 'test_event' => '', 'ga4' => '', 'gtm' => 'GTM-TVMLT6DT', 'tiktok' => '', 'purchase_on' => 'placed',
                'fb_domain_verification' => 'q3e3x73iwktzrop9d227rx2rj9bm8v', 'production_only' => true, 'item_brand' => 'YoungStar Life', 'item_category' => 'Pants'],
            // Settings → Website texts: words the storefront shows ({store} {whatsapp} {phone} {email} are filled in)
            'content' => [
                'faq_title' => 'সচরাচর জিজ্ঞাস্য প্রশ্নাবলি',
                'faq' => [
                    'সারা বাংলাদেশে ক্যাশ অন ডেলিভারি এভেইলেবল',
                    'আপনি যদি আপনার ক্রয়কৃত ড্রেসটি নিয়ে সন্তুষ্ট না হন, তবে শুধু ডেলিভারি চার্জ প্রদান করে ডেলিভারি ম্যানের কাছে সহজেই ফেরত দিতে পারবেন।',
                    'এক্সচেঞ্জ ফ্রি নয় — এক্সচেঞ্জের ক্ষেত্রে ডেলিভারি চার্জ প্রযোজ্য।',
                    'এক্সচেঞ্জ করতে চাইলে প্রোডাক্ট হাতে পাওয়ার সর্বোচ্চ ২৪ ঘণ্টার মধ্যে আমাদের জানাতে হবে।',
                ],
                'faq_on' => ['home' => true, 'shop' => true, 'category' => true, 'product' => true],
                'product_note' => ['on' => true, 'label' => 'বিদ্রঃ', 'text' => 'যে কোন কালার দিয়ে কম্বো করতে WhatsApp করুন', 'whatsapp_text' => 'আমি যে কোন কালার দিয়ে কম্বো করতে চাই'],
                'cod_note' => 'সারা বাংলাদেশে ক্যাশ অন ডেলিভারি',
                'payment_label' => 'ক্যাশ অন ডেলিভারী',
                'order_thanks' => 'আপনার অর্ডারের জন্য আন্তরিক কৃতজ্ঞতা। ২৪ ঘণ্টার মধ্যে আমাদের টিম আপনার সাথে যোগাযোগ করবে এবং খুব শীঘ্রই আপনার অর্ডারটি প্রসেস করা হবে । {store} এর সাথেই থাকুন।',
                'order_whatsapp_text' => 'আমার অর্ডার নম্বর #{invoice}',
                'order_duplicate' => 'আপনার ইতি মধ্যে একটি অর্ডার গ্রহন সফল হয়েছে। অনুগ্রহ করে কোন কিছু পরিবর্তন করতে চাইলে {whatsapp} নাম্বারে ওয়াটসএপ যোগাযোগ করুন। নতুন কোন প্রোডাক্ট এই মুহুর্তে অর্ডার করতে চাইলে ওয়াটসএপ যোগাযোগ করুন অথবা ৩০ মিনিট পর চেষ্টা করুন',
                'order_failed' => 'দুঃখিত, অর্ডারটি সম্পন্ন করা যায়নি। আবার চেষ্টা করুন অথবা {whatsapp} নাম্বারে WhatsApp করুন।',
                'maintenance' => 'আমরা সাইটটি আপডেট করছি। একটু পরে আবার আসুন। জরুরি প্রয়োজনে {whatsapp} নম্বরে WhatsApp করুন।',
            ],
            'webhooks' => ['outgoing' => ['order.created' => true, 'order.status' => true, 'return.received' => false], 'outgoing_url' => ''],
            'push' => ['matrix' => collect(config('ops.notify_events'))->map(fn () => ['app' => true, 'push' => false, 'email' => false])->all()],
            'smtp' => ['preset' => 'gmail', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => '', 'from_name' => 'YoungStar Life', 'from_email' => '', 'reply_to' => '', 'verified' => false],
            'automations' => [
                'auto_confirm' => $rule(true, 'Customer has 3+ orders and ≥90% delivery rate', 'Confirm the order automatically'),
                'flag_risky' => $rule(true, 'Customer delivery rate is below 75%', 'Tag “Advance required” and notify'),
                'auto_courier' => $rule(true, 'Order is confirmed', 'Assign Steadfast inside Dhaka, Pathao outside'),
                'auto_allocate' => $rule(true, 'Order is confirmed', 'Reserve stock from batches (FIFO)'),
                'auto_book' => $rule(false, 'Parcel is packed', 'Send it to the courier through its API (Steadfast / Pathao)'),
                'courier_poll' => $rule(true, 'A parcel has no courier update for 2 hours', 'Ask the courier API for its status'),
                'webhook_sync' => $rule(true, 'Courier sends a status webhook', 'Update parcel and order status'),
                'sms_updates' => $rule(false, 'Parcel status changes', 'Send the customer an SMS'),
                'auto_hold' => $rule(true, '2 failed delivery attempts', 'Put the order on hold'),
                'delay_alert' => $rule(true, 'Parcel has no movement for 3 days', 'Notify the team'),
                'restock_good' => $rule(true, 'Returned item passes QC as Good', 'Restock into its original batch'),
                'draft_supplier_return' => $rule(true, 'Item is marked Damaged / Broken', 'Add it to the supplier-return pool'),
                'low_stock_po' => $rule(false, 'Variant falls below threshold', 'Draft a purchase order (21-day reorder qty)'),
                'courier_claim' => $rule(true, 'Parcel item is missing on return', 'Open a courier claim'),
                'settle_match' => $rule(true, 'Courier settlement is recorded', 'Match parcels and flag short pay'),
                'supplier_reminder' => $rule(true, 'Supplier due is older than 15 days', 'Remind accounts'),
                'allocation' => 'FIFO',
            ],
            'batch' => ['format' => 'B{###}', 'create_on_receive' => true, 'low_threshold' => 15, 'website_stock' => false, 'reserve_when' => 'confirmed', 'allow_negative' => false, 'track_expiry' => false, 'allocation' => 'FIFO', 'scan_at_packing' => true],
            'barcode' => ['product' => 'EAN-13', 'prefix' => '890123', 'batch_label' => 'code128', 'parcel_label' => true, 'size' => '50x30', 'printer' => 'Xprinter XP-365B', 'price_on_label' => true],
            'returns' => ['reasons' => config('ops.return_reasons')],
            'sms' => ['templates' => [
                'confirmed' => ['name' => 'Order confirmed', 'on' => true, 'sent' => 0, 'body' => 'Hi {name}, your order {order} of {amount} is confirmed. Thank you!'],
                'picked' => ['name' => 'Parcel picked up', 'on' => true, 'sent' => 0, 'body' => 'Your order {order} is with {courier}. Tracking: {tracking}'],
                'out' => ['name' => 'Out for delivery', 'on' => false, 'sent' => 0, 'body' => 'Your order {order} is out for delivery today. Please keep {amount} ready.'],
                'delivered' => ['name' => 'Delivered', 'on' => true, 'sent' => 0, 'body' => 'Order {order} delivered. Thank you for shopping with YoungStar Life!'],
                'return' => ['name' => 'Return received', 'on' => false, 'sent' => 0, 'body' => 'We received your return for {order}. Refund (if any) will follow soon.'],
            ]],
            // Users → Blacklist → Settings
            'blacklist' => [
                'message' => 'দুঃখিত, এই নম্বর / ডিভাইস থেকে অর্ডার গ্রহণ করা যাচ্ছে না। প্রয়োজনে {whatsapp} নম্বরে WhatsApp এ যোগাযোগ করুন।',
                'match_user_agent' => true,      // old admin rule: same browser string as a blocked device = blocked
                'skip_autosave' => true,         // no abandoned-checkout drafts for blocked visitors
                'auto' => ['on' => false, 'returns' => 3, 'rate' => 34, 'action' => 'flag', 'days' => 0],
            ],
            'targets' => ['today' => 50000, '7d' => 350000, '30d' => 1200000],
            'storefront' => [
                'status' => 'live', 'banners' => [
                    ['title' => 'Eid Collection 2026', 'link' => '/collections/eid', 'on' => true], ['title' => 'Cargo pants — new colours', 'link' => '/cargo-pant', 'on' => true], ['title' => 'Free delivery over ৳2,000', 'link' => '/shop', 'on' => false]],
                'sections' => [
                    'categories' => ['label' => 'Shop by category', 'on' => true, 'count' => 4], 'new' => ['label' => 'New arrivals', 'on' => true, 'count' => 8],
                    'best' => ['label' => 'Best sellers', 'on' => true, 'count' => 8, 'note' => 'delivered, 30 days'], 'featured' => ['label' => 'Featured products', 'on' => true, 'count' => 4],
                    'reviews' => ['label' => 'Customer reviews', 'on' => true, 'count' => 4]],
                'pages' => [
                    ['title' => 'About us', 'slug' => 'about', 'on' => true, 'body' => ''], ['title' => 'Return policy', 'slug' => 'return-policy', 'on' => true, 'body' => ''],
                    ['title' => 'Privacy policy', 'slug' => 'privacy', 'on' => true, 'body' => ''], ['title' => 'Terms & conditions', 'slug' => 'terms', 'on' => true, 'body' => ''], ['title' => 'Contact', 'slug' => 'contact', 'on' => true, 'body' => '']],
                'coupons' => [
                    ['code' => 'WELCOME10', 'type' => 'pct', 'value' => 10, 'min' => 1000, 'limit' => null, 'used' => 0, 'expires' => null, 'on' => true],
                    ['code' => 'EID25', 'type' => 'flat', 'value' => 250, 'min' => 2500, 'limit' => 500, 'used' => 0, 'expires' => '2026-04-05', 'on' => true],
                    ['code' => 'FREESHIP', 'type' => 'ship', 'value' => 0, 'min' => 2000, 'limit' => null, 'used' => 0, 'expires' => null, 'on' => false]],
                // whatsapp = the number every website message / button uses; *_on = floating chat buttons
                'social' => ['facebook' => '', 'instagram' => '', 'youtube' => '', 'tiktok' => '', 'whatsapp_on' => false, 'whatsapp' => '01613046803', 'messenger_on' => false, 'messenger' => '', 'call_on' => false, 'phone' => ''],
                'announcement' => ['text' => 'Free delivery on orders over ৳2,000', 'link' => '/shop', 'on' => false],
                'popup' => ['on' => false, 'title' => 'Get 10% off your first order', 'body' => 'Use code WELCOME10 at checkout.', 'coupon' => 'WELCOME10', 'delay' => 5, 'frequency' => 'once'],
            ],
            'report_schedules' => [],
            // role × module (labels from config('ops.nav')); Admin always has everything
            'permissions' => [
                'Manager' => ['Dashboard', 'Product & Inventory', 'Orders & Packing', 'Couriers', 'Users', 'Accounts & Finance', 'Reports', 'Storefront'],
                'Packer' => ['Dashboard', 'Product & Inventory', 'Orders & Packing'],
                'Accounts' => ['Dashboard', 'Couriers', 'Users', 'Accounts & Finance', 'Reports'],
            ],
        ];
    }
}
