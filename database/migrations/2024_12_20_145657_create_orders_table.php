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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->decimal('subtotal');
            $table->decimal('discount')->default(0);
            $table->decimal('fee');
            $table->decimal('total');
            $table->string('name');
            $table->string('phone');
            $table->string('address');
            $table->string('delivery_area_id');
            $table->decimal('cod_percentage')->default(0);
            $table->decimal('cod_charge')->default(0);
            $table->enum('status', ['pending', 'processing', 'Delivered', 'cancelled'])->default('pending');
            $table->boolean('is_shipping_different')->default(false);
            $table->boolean('is_paid')->default(false);
            $table->date('delivery_date')->nullable();
            $table->date('cancelled_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
