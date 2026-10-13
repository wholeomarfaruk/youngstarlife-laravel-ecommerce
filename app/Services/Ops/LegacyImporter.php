<?php

namespace App\Services\Ops;

use App\Models\Ops\{Attribute, AttributeValue, Campaign, Category, Courier, Customer, DeliveryZone, Order, Product, ProductVariant, Shipment, Setting};
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

/**
 * One-time move of the old website data (products, categories, sizes, images, delivery areas, customers, blacklist, orders,
 * abandoned checkouts, coupons, menu links) into the Ops tables. Old tables are only read. Every copied row is recorded in
 * ops_legacy_links (old id → new id), so running it again only brings over what is new.
 */
class LegacyImporter
{
    protected array $links = [];
    protected array $report = [];
    protected $out;

    /** demo rows created by OpsDemoSeeder; setup (settings, couriers, warehouses, accounts, zones, attributes, staff) stays */
    public const DEMO_TABLES = [
        'ops_order_events', 'ops_order_item_allocations', 'ops_customer_return_items', 'ops_customer_returns', 'ops_courier_session_items', 'ops_courier_sessions',
        'ops_shipments', 'ops_courier_settlements', 'ops_order_items', 'ops_orders', 'ops_campaigns', 'ops_transactions', 'ops_supplier_return_items',
        'ops_supplier_returns', 'ops_stock_movements', 'ops_batches', 'ops_goods_receipts', 'ops_purchase_order_items', 'ops_purchase_orders',
        'ops_product_supplier', 'ops_combo_items', 'ops_category_product', 'ops_product_variants', 'ops_products', 'ops_categories',
        'ops_customers', 'ops_suppliers', 'ops_webhook_logs', 'ops_activity_log', 'cart_items',
    ];

    protected const STATUS = [
        'pending' => 'pending', 'confirmed' => 'confirmed', 'processing' => 'processing', 'in_review' => 'processing', 'ready' => 'processing',
        'in_transit' => 'shipped', 'delivery_in_review' => 'shipped', 'delivered' => 'delivered', 'on_hold' => 'on_hold', 'cancelled' => 'cancelled', 'returned' => 'returned',
    ];
    protected const COURIER_STATUS = ['pending' => 'pending', 'rider_assigned' => 'picked_up', 'in_transit' => 'in_transit', 'returning' => 'returning', 'delivered' => 'delivered', 'returned' => 'returned'];

    public function run(bool $wipeDemo = false, ?callable $out = null): array
    {
        $this->out = $out;
        if ($wipeDemo) $this->wipeDemo();
        foreach (['zones', 'categories', 'products', 'customers', 'orders', 'drafts', 'coupons', 'menus', 'recountCustomers'] as $step) {
            $this->say("· $step");
            $this->{$step}();
        }
        foreach (['customers.count', 'suppliers.count', 'users.count', 'drafts.count'] as $k) cache()->forget($k);
        \App\Services\Storefront\MenuService::forget();
        Attribute::flush();
        return $this->report;
    }

    // ── demo wipe ───────────────────────────────────────────────────────────────────────────────────
    public function wipeDemo(): void
    {
        if (DB::table('ops_legacy_links')->exists()) throw new \RuntimeException('Real data has already been imported — refusing to wipe. (Demo wipe is only for the first import.)');
        // accounts the demo customers / suppliers got; staff accounts stay
        $demoUsers = DB::table('users')->whereIn('role', ['customer', 'supplier'])
            ->where(fn ($q) => $q->whereIn('id', DB::table('ops_customers')->whereNotNull('user_id')->select('user_id'))->orWhereIn('id', DB::table('ops_suppliers')->whereNotNull('user_id')->select('user_id')))->pluck('id');
        Schema::disableForeignKeyConstraints();
        try {
            foreach (self::DEMO_TABLES as $t) if (Schema::hasTable($t)) DB::table($t)->truncate();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        $this->report['demo users removed'] = DB::table('users')->whereIn('id', $demoUsers)->whereNotIn('id', DB::table('orders')->whereNotNull('user_id')->select('user_id'))->delete();
        $this->report['demo tables cleared'] = count(self::DEMO_TABLES);
        $this->say('  demo data removed');
    }

    // ── delivery areas → zones ──────────────────────────────────────────────────────────────────────
    protected function zones(): void
    {
        if (! Schema::hasTable('delivery_areas')) return;
        $n = 0;
        foreach (DB::table('delivery_areas')->orderBy('id')->get() as $a) {
            if ($this->link('delivery_area', $a->id)) continue;
            $zone = DeliveryZone::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($a->name))])->first();
            $zone ? $zone->update(['charge' => (int) round((float) $a->charge), 'is_active' => true])
                  : $zone = DeliveryZone::create(['name' => trim($a->name), 'charge' => (int) round((float) $a->charge), 'is_active' => true]);
            $this->remember('delivery_area', $a->id, $zone->id); $n++;
        }
        // the website offers exactly the old areas; other zones are switched off (Settings → Delivery zones to re-enable)
        $used = DB::table('ops_legacy_links')->where('source', 'delivery_area')->pluck('ops_id');
        if ($used->isNotEmpty()) {
            $off = DeliveryZone::whereNotIn('id', $used)->where('is_active', true)->pluck('name');
            DeliveryZone::whereNotIn('id', $used)->update(['is_active' => false]);
            if ($off->isNotEmpty()) $this->report['zones switched off'] = $off->join(', ');
        }
        $this->report['delivery zones'] = $n;
    }

    // ── categories ──────────────────────────────────────────────────────────────────────────────────
    protected function categories(): void
    {
        if (! Schema::hasTable('categories')) return;
        $rows = DB::table('categories')->orderBy('id')->get(); $n = 0;
        foreach ($rows as $c) {
            if ($this->link('category', $c->id)) continue;
            $slug = $c->slug ?: Str::slug($c->name).'-'.$c->id;
            $cat = Category::where('slug', $slug)->first() ?? Category::create([
                'name' => $c->name, 'slug' => $slug, 'sort' => (int) ($c->display_order ?? 0), 'description' => $c->description,
                'image' => $c->image ? 'images/category/'.ltrim($c->image, '/') : null, 'show_on_home' => (bool) ($c->is_homepage_show ?? false),
            ]);
            $this->remember('category', $c->id, $cat->id); $n++;
        }
        foreach ($rows->whereNotNull('parent_id') as $c)
            if (($id = $this->link('category', $c->id)) && ($parent = $this->link('category', $c->parent_id))) Category::whereKey($id)->update(['parent_id' => $parent]);
        $this->report['categories'] = $n;
    }

    // ── products + sizes (→ variants) + images + category order ─────────────────────────────────────
    protected function products(): void
    {
        if (! Schema::hasTable('products')) return;
        $size = Attribute::firstOrCreate(['slug' => 'size'], ['name' => 'Size', 'type' => 'size', 'sort' => 2, 'is_filterable' => true]);
        $n = 0; $v = 0;
        foreach (DB::table('products')->orderBy('id')->get() as $p) {
            if ($this->link('product', $p->id)) continue;
            DB::transaction(function () use ($p, $size, &$n, &$v) {
                $sizes = Schema::hasTable('sizes') ? DB::table('sizes')->where('products_id', $p->id)->orderBy('id')->pluck('name')->map(fn ($s) => trim((string) $s))->filter()->unique()->values() : collect();
                $media = DB::table('media')->where('mediable_type', 'App\\Models\\products')->where('mediable_id', $p->id)->orderBy('id')->get();
                $disk = fn ($path) => $path ? preg_replace('#/+#', '/', preg_replace('#^/?storage/#', '', $path)) : null;   // public URL path → public-disk path

                $slug = $p->slug ?: Str::slug($p->name).'-'.$p->id;
                if (Product::withTrashed()->where('slug', $slug)->exists()) $slug .= '-'.$p->id;
                $code = trim((string) $p->sku) ?: 'YS-'.$p->id;
                if (Product::withTrashed()->where('code', $code)->exists()) $code .= '-'.$p->id;
                $price = (int) round((float) $p->price); $sale = (float) $p->discount_price > 0 ? (int) round((float) $p->discount_price) : null;

                $termIds = $sizes->map(fn ($s) => AttributeValue::firstOrCreate(['attribute_id' => $size->id, 'value' => $s], ['slug' => Str::slug($s) ?: $s, 'sort' => 50])->id)->all();
                $product = Product::create([
                    'type' => $sizes->isEmpty() ? 'simple' : 'variable', 'name' => $p->name, 'slug' => $slug, 'code' => $code,
                    'short_description' => $p->short_description, 'description' => $p->description,
                    'price' => $price, 'sale_price' => $sale && $sale < $price ? $sale : null,
                    'status' => (int) $p->status === 1 ? 'active' : 'draft', 'sort_order' => (int) ($p->sort_order ?? 0), 'sold_out' => $p->stock_status === 'out_of_stock',
                    'featured_image' => $p->image ? 'images/products/'.$p->image : null,
                    'gallery' => $media->where('category', 'product_images')->pluck('path')->map($disk)->filter()->unique()->values()->all(),
                    'size_chart_image' => $disk($media->firstWhere('category', 'sizechart')?->path),
                    'options' => $termIds ? [['attribute_id' => $size->id, 'values' => $termIds]] : null,
                    'created_at' => $p->created_at, 'updated_at' => $p->updated_at,
                ]);
                foreach ($sizes->isEmpty() ? [null] : $sizes as $s) {
                    $sku = $code.($s !== null ? '-'.(Str::upper(Str::slug($s)) ?: $s) : '');
                    if (ProductVariant::where('sku', $sku)->exists()) $sku .= '-'.$p->id;
                    ProductVariant::create(['product_id' => $product->id, 'sku' => $sku, 'size' => $s, 'options' => $s !== null ? ['Size' => $s] : null, 'is_active' => true]);
                    $v++;
                }
                if (Schema::hasTable('product_category'))
                    foreach (DB::table('product_category')->where('products_id', $p->id)->get() as $pc)
                        if ($cat = $this->link('category', $pc->category_id))
                            DB::table('ops_category_product')->insertOrIgnore(['category_id' => $cat, 'product_id' => $product->id, 'sort_order' => (int) ($pc->sort_order ?? 0)]);
                $this->remember('product', $p->id, $product->id); $n++;
            });
        }
        $this->report['products'] = $n; $this->report['variants'] = $v;
    }

    /** old product id + size → variant (creates an archived placeholder when the old product is gone) */
    protected function variantFor(?int $legacyProduct, ?string $size, string $name = ''): int
    {
        $pid = $legacyProduct ? $this->link('product', $legacyProduct) : null;
        if (! $pid) {
            $pid = $this->link('missing_product', (int) $legacyProduct);
            if (! $pid) {
                $code = 'LEGACY-'.(int) $legacyProduct;
                $pid = Product::withTrashed()->firstOrCreate(['code' => $code], ['type' => 'simple', 'name' => $name ?: 'Deleted product #'.(int) $legacyProduct, 'slug' => Str::lower($code), 'status' => 'archived', 'price' => 0])->id;
                $this->remember('missing_product', (int) $legacyProduct, $pid);
            }
        }
        $size = trim((string) $size);
        $variants = ProductVariant::where('product_id', $pid)->get(['id', 'size', 'sku']);
        $hit = $size !== ''
            ? $variants->first(fn ($x) => strcasecmp((string) $x->size, $size) === 0)
            : $variants->first(fn ($x) => $x->size === null || $x->size === '');   // no size chosen ↔ the size-less variant only
        if ($hit) return $hit->id;
        // a size that no longer exists, or "no size" on a sized product: kept as an inactive variant so the order line stays exact
        $base = Product::withTrashed()->whereKey($pid)->value('code');
        return ProductVariant::create(['product_id' => $pid, 'sku' => $base.'-'.($size !== '' ? (Str::upper(Str::slug($size)) ?: 'X') : 'NOSIZE').'-'.Str::random(4),
            'size' => $size !== '' ? $size : null, 'options' => $size !== '' ? ['Size' => $size] : null, 'is_active' => false])->id;
    }

    /** size from an old order line's options: JSON, or JSON double-encoded as a string by older code */
    protected function sizeOf($options): ?string
    {
        $o = json_decode((string) $options, true);
        if (is_string($o)) $o = json_decode($o, true);
        $size = is_array($o) ? trim((string) ($o['size'] ?? '')) : '';
        return $size !== '' ? $size : null;
    }

    // ── customers (old customers + every phone that ordered) and blacklist ──────────────────────────
    protected function customers(): void
    {
        $n = 0;
        if (Schema::hasTable('customers'))
            foreach (DB::table('customers')->orderBy('id')->get() as $c) {
                if ($this->link('customer', $c->id) || ! trim((string) $c->phone)) continue;
                $cust = $this->customerFor($c->phone, $c->name, $c->created_at, $c->email);
                $this->remember('customer', $c->id, $cust->id); $n++;
            }
        // blacklist: old customers blocked → ops customer blocked (device blocks stay on the device)
        if (Schema::hasTable('black_lists'))
            foreach (DB::table('black_lists')->where('black_listable_type', 'App\\Models\\Customer')->pluck('black_listable_id') as $old)
                if (($id = $this->link('customer', $old)) && ($c = Customer::find($id)))
                    try { app(Blacklist::class)->add(['type' => 'phone', 'value' => $c->phone, 'reason' => 'other', 'source' => 'legacy', 'note' => 'Old website blacklist']); }
                    catch (\InvalidArgumentException) { $c->update(['is_blocked' => true]); }   // phone not a valid BD mobile → old flag only
        $this->report['customers'] = $n;
    }

    protected function customerFor(?string $phone, ?string $name, $at = null, ?string $email = null): Customer
    {
        $raw = trim((string) $phone);
        $key = Phone::normalize($raw) ?? (mb_substr(preg_replace('/\s+/', '', $raw), 0, 20) ?: 'unknown-'.Str::random(8));
        return Customer::firstOrCreate(['phone' => $key], array_filter([
            'name' => trim((string) $name) ?: 'Customer', 'email' => $email ?: null, 'source' => 'website', 'created_at' => $at, 'updated_at' => $at,
        ], fn ($v) => $v !== null));
    }

    // ── orders (+ items, parcel, attribution) ───────────────────────────────────────────────────────
    protected function orders(): void
    {
        if (! Schema::hasTable('orders')) return;
        $prefix = (string) ops_setting('orders.prefix', '#');
        $done = 0; $skipped = 0;
        DB::table('orders')->orderBy('id')->chunkById(200, function ($orders) use ($prefix, &$done, &$skipped) {
            foreach ($orders as $o) {
                if ($this->link('order', $o->id)) continue;
                if ($o->status === 'deleted') { $skipped++; continue; }   // removed by staff in the old admin
                if ($o->status === 'autosave') { $this->draftFrom($o, DB::table('order__items')->where('order_id', $o->id)->get(), 'order'); continue; }
                DB::transaction(function () use ($o, $prefix, &$done) {
                    $customer = $this->customerFor($o->phone, $o->name, $o->created_at);
                    $items = DB::table('order__items')->where('order_id', $o->id)->get();
                    $status = self::STATUS[$o->status] ?? 'pending';
                    $total = (int) round((float) $o->total); $fee = (int) round((float) $o->fee);
                    $sub = (int) round($items->sum(fn ($i) => (float) $i->price * (int) $i->quantity));
                    $number = $prefix.$o->id;
                    if (Order::where('number', $number)->exists()) $number = $prefix.'L'.$o->id;
                    $tags = array_values(array_filter([
                        in_array($o->status, ['in_review', 'ready']) ? 'Ready to ship (old admin)' : null,
                        $o->status === 'delivery_in_review' ? 'Delivery in review (old admin)' : null,
                    ]));

                    $order = Order::create([
                        'number' => $number, 'customer_id' => $customer->id, 'campaign_id' => $this->campaignFor($o),
                        'source' => match (strtolower((string) $o->source)) { 'facebook', 'messenger' => 'facebook', 'instagram' => 'instagram', 'phone' => 'phone', default => 'website' },
                        'status' => $status, 'payment_status' => $o->is_paid ? 'paid' : 'pending', 'is_draft' => false,
                        'ship_name' => mb_substr(trim((string) $o->name) ?: $customer->name, 0, 255), 'ship_phone' => $customer->phone,
                        'ship_address' => mb_substr(trim((string) $o->address) ?: '—', 0, 255), 'delivery_zone_id' => $o->delivery_area_id ? $this->link('delivery_area', $o->delivery_area_id) : null,
                        'subtotal' => $sub ?: max(0, $total - $fee), 'discount' => (int) round((float) $o->discount), 'delivery_charge' => $fee, 'total' => $total,
                        'advance' => 0, 'cod' => $o->is_paid ? 0 : $total,
                        'note' => trim(implode("\n", array_filter([$o->notes, $o->note]))) ?: null, 'tags' => $tags ?: null,
                        'device_id' => $o->device_id ?? null, 'ip_address' => $o->ip_address, 'user_agent' => $o->user_agent, 'attribution' => $this->attribution($o),
                        'placed_at' => $o->created_at, 'created_at' => $o->created_at, 'updated_at' => $o->updated_at,
                    ]);
                    foreach ($items as $i) {
                        DB::table('ops_order_items')->insert(['order_id' => $order->id, 'variant_id' => $this->variantFor($i->product_id, $this->sizeOf($i->options)),
                            'qty' => max(1, (int) $i->quantity), 'unit_price' => (int) round((float) $i->price), 'returned_qty' => (int) ($i->returned_quantity ?? 0)]);
                    }
                    DB::table('ops_order_events')->insert(['order_id' => $order->id, 'type' => 'created', 'message' => 'Imported from the old website (order '.$o->id.', status: '.$o->status.')', 'created_at' => $o->created_at]);
                    $this->shipmentFor($order, $o);
                    $this->remember('order', $o->id, $order->id); $done++;
                });
            }
        });
        $this->report['orders'] = $done; $this->report['deleted orders skipped'] = $skipped;
    }

    protected function attribution(object $o): ?array
    {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'campaign_id', 'campaign_name', 'adset_id', 'adset_name', 'ad_id', 'ad_name', 'placement', 'site_source', 'fbclid', 'gclid', 'fbc', 'fbp', 'landing_page', 'referrer'];
        $a = array_filter(array_intersect_key((array) $o, array_flip($keys)), fn ($v) => $v !== null && $v !== '');
        if (! empty($o->attribution)) $a['touches'] = json_decode((string) $o->attribution, true);
        return $a ?: null;
    }

    /** one Ops campaign per ad campaign seen on old orders (Orders → Campaign summary works on real data) */
    protected function campaignFor(object $o): ?int
    {
        $name = trim((string) ($o->campaign_name ?: $o->utm_campaign));
        if ($name === '') return null;
        $platform = match (true) {
            str_contains(strtolower((string) ($o->site_source.$o->utm_source)), 'ig') || str_contains(strtolower((string) $o->utm_source), 'instagram') => 'Instagram',
            str_contains(strtolower((string) $o->utm_source), 'google') || ! empty($o->gclid) => 'Google',
            default => 'Facebook',
        };
        return Campaign::firstOrCreate(['name' => mb_substr($name, 0, 255)], ['platform' => $platform, 'utm_campaign' => $o->utm_campaign ?: Str::slug($name), 'status' => 'active'])->id;
    }

    /** a parcel for orders that were handed to a courier, so tracking + webhooks keep working */
    protected function shipmentFor(Order $order, object $o): void
    {
        $tracking = trim((string) ($o->tracking_number ?: $o->consignment_id));
        if ($tracking === '' && ! in_array($order->status, ['shipped', 'delivered', 'returned'])) return;
        $partner = strtolower((string) $o->courier_partner);
        $courier = Courier::where('code', 'like', '%'.($partner ?: 'steadfast').'%')->orWhere('name', 'like', '%'.($partner ?: 'steadfast').'%')->first() ?? Courier::orderBy('id')->first();
        if (! $courier) return;
        $cs = self::COURIER_STATUS[$o->courier_status] ?? match ($order->status) { 'delivered' => 'delivered', 'returned' => 'returned', default => 'in_transit' };
        $last = (int) Shipment::selectRaw("MAX(CAST(SUBSTRING(code, 2) AS UNSIGNED)) n")->value('n');
        Shipment::create(['code' => 'P'.max(1001, $last + 1), 'order_id' => $order->id, 'courier_id' => $courier->id, 'tracking' => $tracking ?: null, 'courier_status' => $cs,
            'cod_amount' => $order->cod, 'handed_over_at' => $o->created_at, 'delivered_at' => $cs === 'delivered' ? ($o->delivery_date ?: $o->updated_at) : null,
            'status_updated_at' => $o->updated_at, 'created_at' => $o->created_at, 'updated_at' => $o->updated_at]);
    }

    // ── abandoned checkouts → Ops drafts ────────────────────────────────────────────────────────────
    protected function drafts(): void
    {
        if (! Schema::hasTable('auto_save_orders')) return;
        foreach (DB::table('auto_save_orders')->where('status', 'autosave')->orderBy('id')->get() as $a)
            $this->draftFrom($a, DB::table('auto_save_order_items')->where('auto_save_order_id', $a->id)->get(), 'autosave');
    }

    protected function draftFrom(object $a, $items, string $source): void
    {
        if ($this->link($source, $a->id) || ! trim((string) $a->phone)) return;
        DB::transaction(function () use ($a, $items, $source) {
            $customer = $this->customerFor($a->phone, $a->name, $a->created_at);
            $fee = (int) round((float) $a->fee);
            $o = Order::create([
                'number' => 'D-'.(Order::max('id') + 2001), 'customer_id' => $customer->id, 'source' => 'website', 'status' => 'pending', 'is_draft' => true,
                'ship_name' => mb_substr(trim((string) $a->name) ?: $customer->name, 0, 255), 'ship_phone' => $customer->phone, 'ship_address' => mb_substr(trim((string) $a->address), 0, 255),
                'delivery_zone_id' => $a->delivery_area_id ? $this->link('delivery_area', (int) $a->delivery_area_id) : null,
                'subtotal' => (int) round($items->sum(fn ($i) => (float) $i->price * (int) $i->quantity)), 'delivery_charge' => $fee, 'total' => (int) round((float) $a->total), 'cod' => (int) round((float) $a->total),
                'note' => 'Abandoned checkout (old website)', 'ip_address' => $a->ip_address, 'user_agent' => $a->user_agent,
                'placed_at' => $a->created_at, 'created_at' => $a->created_at, 'updated_at' => $a->updated_at,
            ]);
            foreach ($items as $i) {
                DB::table('ops_order_items')->insert(['order_id' => $o->id, 'variant_id' => $this->variantFor($i->product_id, $this->sizeOf($i->options)), 'qty' => max(1, (int) $i->quantity), 'unit_price' => (int) round((float) $i->price)]);
            }
            $this->remember($source, $a->id, $o->id);
            $this->report['abandoned checkouts → drafts'] = ($this->report['abandoned checkouts → drafts'] ?? 0) + 1;
        });
    }

    // ── coupons → Storefront → Coupons ──────────────────────────────────────────────────────────────
    protected function coupons(): void
    {
        if (! Schema::hasTable('coupons') || ! Schema::hasColumn('coupons', 'code')) return;
        $sf = Setting::get('storefront', []) ?: [];
        $have = collect($sf['coupons'] ?? [])->pluck('code')->map(fn ($c) => strtoupper($c))->all();
        $n = 0;
        foreach (DB::table('coupons')->get() as $c) {
            $code = strtoupper(trim((string) $c->code));
            if ($code === '' || in_array($code, $have)) continue;
            $sf['coupons'][] = ['code' => $code, 'type' => ($c->type ?? 'fixed') === 'percent' ? 'pct' : 'flat', 'value' => (int) ($c->value ?? 0), 'min' => (int) ($c->cart_value ?? 0),
                'limit' => null, 'used' => 0, 'expires' => $c->expiry_date ?? null, 'on' => ! isset($c->expiry_date) || $c->expiry_date >= today()->toDateString()];
            $have[] = $code; $n++;
        }
        if ($n) Setting::put('storefront', $sf);
        $this->report['coupons'] = $n;
    }

    // ── menu links that pointed at old categories / products ────────────────────────────────────────
    protected function menus(): void
    {
        if (! Schema::hasTable('storefront_menu_items')) return;
        $n = 0;
        foreach (DB::table('storefront_menu_items')->whereIn('type', ['category', 'product', 'url'])->get() as $item) {
            if ($this->link('menu_item', $item->id)) continue;   // already moved to Ops ids
            $update = null;
            if ($item->type === 'url' && preg_match('#^/category/([\w-]+)/?$#', (string) $item->value, $m) && ($cat = Category::where('slug', $m[1])->value('id')))
                $update = ['type' => 'category', 'ref_id' => $cat, 'value' => null];
            elseif ($item->type === 'url' && preg_match('#^/product/([\w-]+)/?$#', (string) $item->value, $m) && ($pid = Product::where('slug', $m[1])->value('id')))
                $update = ['type' => 'product', 'ref_id' => $pid, 'value' => null];
            elseif ($item->type === 'category' && ($id = $this->link('category', (int) $item->ref_id))) $update = ['ref_id' => $id];
            elseif ($item->type === 'product' && ($id = $this->link('product', (int) $item->ref_id))) $update = ['ref_id' => $id];
            if ($item->type !== 'url' || $update) $this->remember('menu_item', $item->id, $item->id);
            if ($update) { DB::table('storefront_menu_items')->where('id', $item->id)->update($update); $n++; }
        }
        $this->report['menu links moved'] = $n;
    }

    // ── customer counters from their orders ─────────────────────────────────────────────────────────
    protected function recountCustomers(): void
    {
        DB::statement("
            UPDATE ops_customers c LEFT JOIN (
                SELECT customer_id, SUM(status <> 'cancelled') n, SUM(status = 'delivered') d, SUM(status = 'returned') r,
                       SUM(CASE WHEN status NOT IN ('cancelled', 'returned') THEN total ELSE 0 END) spent, MAX(placed_at) last_at
                FROM ops_orders WHERE is_draft = 0 GROUP BY customer_id
            ) o ON o.customer_id = c.id
            SET c.orders_count = COALESCE(o.n, 0), c.delivered_count = COALESCE(o.d, 0), c.returned_count = COALESCE(o.r, 0),
                c.total_spent = COALESCE(o.spent, 0), c.last_order_at = o.last_at");
    }

    // ── helpers ─────────────────────────────────────────────────────────────────────────────────────
    protected function link(string $source, ?int $legacy): ?int
    {
        if (! $legacy) return null;
        return $this->links[$source][$legacy] ??= DB::table('ops_legacy_links')->where('source', $source)->where('legacy_id', $legacy)->value('ops_id');
    }

    protected function remember(string $source, int $legacy, int $ops): void
    {
        DB::table('ops_legacy_links')->updateOrInsert(['source' => $source, 'legacy_id' => $legacy], ['ops_id' => $ops]);
        $this->links[$source][$legacy] = $ops;
    }

    protected function say(string $line): void { if ($this->out) ($this->out)($line); }
}
