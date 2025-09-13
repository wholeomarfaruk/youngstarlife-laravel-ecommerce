<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {

            $table->id();
            $table->string('name');
            $table->string('price');
            $table->string('weight')->nullable();
            $table->string('sku')->nullable();
            $table->enum('stock_status', ['in_stock', 'out_of_stock'])->default('in_stock');
            $table->unsignedInteger('quantity')->default(10);
            $table->string('image')->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('status')->default(true);
            $table->string('slug')->nullable();
            $table->string('discount_price')->nullable();
            $table->enum('discount_type', ['fixed', 'percentage'])->nullable();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
