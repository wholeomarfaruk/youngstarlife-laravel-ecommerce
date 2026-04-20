<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auto_save_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auto_save_order_id')->unsigned();
            $table->unsignedBigInteger('product_id')->unsigned();
            $table->integer('quantity');
            $table->decimal('price');
            $table->json('options')->nullable();
            $table->timestamps();
            $table->foreign('auto_save_order_id')->references('id')->on('auto_save_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_save_order_items');
    }
};
