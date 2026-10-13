<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_warehouses', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('address')->nullable(); $t->boolean('is_default')->default(false); $t->timestamps();
        });

        Schema::create('ops_categories', function (Blueprint $t) {
            $t->id(); $t->foreignId('parent_id')->nullable()->constrained('ops_categories')->nullOnDelete();
            $t->string('name'); $t->string('slug')->unique(); $t->unsignedInteger('sort')->default(0); $t->timestamps();
        });

        Schema::create('ops_brands', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->unique(); $t->timestamps();
        });

        Schema::create('ops_attributes', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->json('values'); $t->timestamps(); // Color: [Blue, Black]; Size: [M, L, XL]
        });

        Schema::create('ops_products', function (Blueprint $t) {
            $t->id();
            $t->string('type', 10)->default('variable');            // simple | variable | combo
            $t->string('name'); $t->string('slug')->unique(); $t->string('code', 40)->unique();
            $t->foreignId('brand_id')->nullable()->constrained('ops_brands')->nullOnDelete();
            $t->text('short_description')->nullable(); $t->longText('description')->nullable(); $t->text('size_chart')->nullable();
            $t->unsignedInteger('price')->default(0); $t->unsignedInteger('sale_price')->nullable();
            $t->unsignedInteger('low_stock_threshold')->default(10);
            $t->decimal('weight_kg', 6, 3)->nullable(); $t->json('dimensions')->nullable();
            $t->string('status', 12)->default('draft')->index();    // active | draft | archived
            $t->string('featured_image')->nullable(); $t->json('gallery')->nullable(); $t->string('video_url')->nullable();
            $t->string('meta_title')->nullable(); $t->string('meta_description', 300)->nullable();
            $t->timestamps(); $t->softDeletes();
        });

        Schema::create('ops_category_product', function (Blueprint $t) {
            $t->foreignId('category_id')->constrained('ops_categories')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('ops_products')->cascadeOnDelete();
            $t->primary(['category_id', 'product_id']);
        });

        Schema::create('ops_product_variants', function (Blueprint $t) {
            $t->id(); $t->foreignId('product_id')->constrained('ops_products')->cascadeOnDelete();
            $t->string('sku', 60)->unique(); $t->string('barcode', 40)->nullable()->unique();
            $t->string('color', 40)->nullable(); $t->string('size', 20)->nullable();
            $t->unsignedInteger('price')->nullable(); $t->unsignedInteger('sale_price')->nullable(); // null = product price
            // denormalised counters (kept in sync by StockService) → list pages never SUM batches
            $t->integer('stock')->default(0); $t->integer('reserved')->default(0); $t->integer('damaged')->default(0);
            $t->boolean('is_active')->default(true); $t->timestamps();
            $t->index(['product_id', 'is_active']);
        });

        Schema::create('ops_combo_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('product_id')->constrained('ops_products')->cascadeOnDelete();           // the combo
            $t->foreignId('variant_id')->constrained('ops_product_variants')->cascadeOnDelete();  // component
            $t->unsignedInteger('qty')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_combo_items');
        Schema::dropIfExists('ops_product_variants');
        Schema::dropIfExists('ops_category_product');
        Schema::dropIfExists('ops_products');
        Schema::dropIfExists('ops_attributes');
        Schema::dropIfExists('ops_brands');
        Schema::dropIfExists('ops_categories');
        Schema::dropIfExists('ops_warehouses');
    }
};
