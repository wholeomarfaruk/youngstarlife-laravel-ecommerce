<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 30)->default('staff')->after('email'); // owner|manager|packer|accountant|staff
            $t->foreignId('warehouse_id')->nullable()->after('role');
        });

        Schema::create('warehouses', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('address')->nullable(); $t->boolean('is_default')->default(false); $t->timestamps();
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id(); $t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->string('name'); $t->string('slug')->unique(); $t->unsignedInteger('sort')->default(0); $t->timestamps();
        });

        Schema::create('brands', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->unique(); $t->timestamps();
        });

        Schema::create('attributes', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->json('values'); $t->timestamps(); // Color: [Blue, Black]; Size: [M, L, XL]
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('type', 10)->default('variable');            // simple | variable | combo
            $t->string('name'); $t->string('slug')->unique(); $t->string('code', 40)->unique();
            $t->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $t->text('short_description')->nullable(); $t->longText('description')->nullable(); $t->text('size_chart')->nullable();
            $t->unsignedInteger('price')->default(0); $t->unsignedInteger('sale_price')->nullable();
            $t->unsignedInteger('low_stock_threshold')->default(10);
            $t->decimal('weight_kg', 6, 3)->nullable(); $t->json('dimensions')->nullable();
            $t->string('status', 12)->default('draft')->index();    // active | draft | archived
            $t->string('featured_image')->nullable(); $t->json('gallery')->nullable(); $t->string('video_url')->nullable();
            $t->string('meta_title')->nullable(); $t->string('meta_description', 300)->nullable();
            $t->timestamps(); $t->softDeletes();
        });

        Schema::create('category_product', function (Blueprint $t) {
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->primary(['category_id', 'product_id']);
        });

        Schema::create('product_variants', function (Blueprint $t) {
            $t->id(); $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('sku', 60)->unique(); $t->string('barcode', 40)->nullable()->unique();
            $t->string('color', 40)->nullable(); $t->string('size', 20)->nullable();
            $t->unsignedInteger('price')->nullable(); $t->unsignedInteger('sale_price')->nullable(); // null = product price
            // denormalised counters (kept in sync by StockService) → list pages never SUM batches
            $t->integer('stock')->default(0); $t->integer('reserved')->default(0); $t->integer('damaged')->default(0);
            $t->boolean('is_active')->default(true); $t->timestamps();
            $t->index(['product_id', 'is_active']);
        });

        Schema::create('combo_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('product_id')->constrained()->cascadeOnDelete();           // the combo
            $t->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();  // component
            $t->unsignedInteger('qty')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_items');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('warehouses');
    }
};
