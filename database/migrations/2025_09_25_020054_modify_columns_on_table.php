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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
            $table->decimal('subtotal')->nullable()->change();
            $table->decimal('discount')->default(0)->nullable()->change();
            $table->decimal('fee')->nullable()->change();
            $table->decimal('total')->nullable()->change();
            $table->string('name')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('address')->nullable()->change();
            $table->string('delivery_area_id')->nullable()->change();

            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('notes')->nullable();
            $table->json('json_data')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'Delivered', 'cancelled'])->default('pending')->change();

            $table->decimal('subtotal')->nullable(false)->change();
            $table->decimal('discount')->default(0)->nullable(false)->change();
            $table->decimal('fee')->nullable(false)->change();
            $table->decimal('total')->nullable(false)->change();
            $table->string('name')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
            $table->string('address')->nullable(false)->change();
            $table->string('delivery_area_id')->nullable(false)->change();

            $table->dropColumn('ip_address');
            $table->dropColumn('user_agent');
            $table->dropColumn('notes');
            $table->dropColumn('json_data');

        });
    }
};
