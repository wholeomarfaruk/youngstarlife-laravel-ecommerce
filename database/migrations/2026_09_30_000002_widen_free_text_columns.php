<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free text that customers/admins type can pass 255 chars; in strict mode that fails the save.
 * - orders.notes: admin notes on an order
 * - auto_save_orders.address: abandoned-lead address (orders.address is already LONGTEXT)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('notes')->nullable()->change();
        });
        Schema::table('auto_save_orders', function (Blueprint $table) {
            $table->text('address')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('notes')->nullable()->change();
        });
        Schema::table('auto_save_orders', function (Blueprint $table) {
            $table->string('address')->nullable()->change();
        });
    }
};
