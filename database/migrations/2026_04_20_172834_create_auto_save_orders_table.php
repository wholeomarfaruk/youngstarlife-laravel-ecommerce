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
        Schema::create('auto_save_orders', function (Blueprint $table) {
            $table->id();
            $table->decimal('subtotal')->default(0);
            $table->decimal('discount')->default(0);
            $table->decimal('fee')->default(0);
            $table->decimal('total')->nullable();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('delivery_area_id')->nullable();
            $table->decimal('cod_percentage')->default(0);
            $table->decimal('cod_charge')->default(0);
            $table->string('status')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('ip_address')->nullable();
            $table->json('extra_data')->nullable();
            $table->json('json_data')->nullable();
            $table->text('notes')->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();

            //foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_save_orders');
    }
};
