<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * The public website moves onto the Ops tables (products, categories, orders, customers, zones).
 * - ops_legacy_links: old id → new id for everything `php artisan ops:import-legacy` copies (re-runs are idempotent)
 * - storefront fields the Ops catalog didn't have (display order, size-chart image, "stock out" switch)
 * - website order metadata on ops_orders (device, ad attribution)
 * - the cart holds variants (product + size) instead of old product ids
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ops_legacy_links')) Schema::create('ops_legacy_links', function (Blueprint $t) {
            $t->id(); $t->string('source', 30); $t->unsignedBigInteger('legacy_id'); $t->unsignedBigInteger('ops_id');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['source', 'legacy_id']); $t->index(['source', 'ops_id']);
        });

        if (! Schema::hasColumn('ops_products', 'sort_order')) Schema::table('ops_products', function (Blueprint $t) {
            $t->integer('sort_order')->default(0)->after('status')->index();          // storefront listing order (lower first)
            $t->boolean('sold_out')->default(false)->after('sort_order');             // "Stock out" on the website, whatever the batches say
            $t->string('size_chart_image')->nullable()->after('size_chart');
        });
        if (! Schema::hasColumn('ops_category_product', 'sort_order')) Schema::table('ops_category_product', fn (Blueprint $t) => $t->integer('sort_order')->default(0));
        if (! Schema::hasColumn('ops_categories', 'image')) Schema::table('ops_categories', function (Blueprint $t) {
            $t->string('image')->nullable()->after('slug'); $t->text('description')->nullable()->after('image');
            $t->boolean('show_on_home')->default(false)->after('sort');
        });

        if (! Schema::hasColumn('ops_orders', 'device_id')) Schema::table('ops_orders', function (Blueprint $t) {
            $t->foreignId('device_id')->nullable()->after('created_by')->constrained('devices')->nullOnDelete();
            $t->string('ip_address', 45)->nullable()->after('device_id');
            $t->text('user_agent')->nullable()->after('ip_address');
            $t->json('attribution')->nullable()->after('user_agent');                 // utm_*, campaign/ad ids, fbclid, fbp/fbc, landing page
        });

        // cart lines now point at a variant (product + size); carts are short-lived, so existing lines are dropped
        if (Schema::hasColumn('cart_items', 'product_id')) {
            DB::table('cart_items')->delete();
            // the cart_id foreign key leans on the old (cart_id, product_id, size) index: give it its own first
            Schema::table('cart_items', fn (Blueprint $t) => $t->index('cart_id', 'cart_items_cart_id_index'));
            Schema::table('cart_items', function (Blueprint $t) {
                $t->dropUnique(['cart_id', 'product_id', 'size']);
                $t->dropConstrainedForeignId('product_id');
                $t->dropColumn('size');
            });
        }
        if (! Schema::hasColumn('cart_items', 'variant_id')) Schema::table('cart_items', function (Blueprint $t) {
            $t->foreignId('variant_id')->after('cart_id')->constrained('ops_product_variants')->cascadeOnDelete();
            $t->unique(['cart_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        DB::table('cart_items')->delete();
        Schema::table('cart_items', function (Blueprint $t) { $t->dropUnique(['cart_id', 'variant_id']); $t->dropConstrainedForeignId('variant_id'); });
        Schema::table('cart_items', function (Blueprint $t) {
            $t->foreignId('product_id')->after('cart_id')->constrained()->cascadeOnDelete(); $t->string('size', 50)->default('')->after('product_id');
            $t->unique(['cart_id', 'product_id', 'size']);
        });
        Schema::table('ops_orders', function (Blueprint $t) { $t->dropConstrainedForeignId('device_id'); $t->dropColumn(['ip_address', 'user_agent', 'attribution']); });
        Schema::table('ops_categories', fn (Blueprint $t) => $t->dropColumn(['image', 'description', 'show_on_home']));
        Schema::table('ops_category_product', fn (Blueprint $t) => $t->dropColumn('sort_order'));
        Schema::table('ops_products', fn (Blueprint $t) => $t->dropColumn(['sort_order', 'sold_out', 'size_chart_image']));
        Schema::dropIfExists('ops_legacy_links');
    }
};
