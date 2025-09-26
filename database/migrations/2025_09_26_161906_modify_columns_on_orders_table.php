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
        Schema::table('orders', function (Blueprint $table) {
            $table->longText('user_agent')->nullable()->change();
            $table->text('ip_address')->nullable()->change();
            $table->longText('tracking_url')->nullable()->change();
            $table->longText('address')->nullable()->change();
            $table->longText('note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('user_agent')->nullable()->change();
            $table->string('ip_address')->nullable()->change();
            $table->string('tracking_url')->nullable()->change();
            $table->string('address')->nullable()->change();
            $table->dropColumn('note');
        });
    }
};
