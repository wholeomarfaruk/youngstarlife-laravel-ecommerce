<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Make sure column type matches parent table
            $table->unsignedBigInteger('delivery_area_id')->nullable()->change();
            $table->string('courier_partner')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('consignment_id')->nullable();

            // Add foreign key
            $table->foreign('delivery_area_id')->references('id')->on('delivery_areas');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop foreign key
            $table->dropForeign(['delivery_area_id']);
            $table->string('delivery_area_id')->nullable()->change();
            $table->dropColumn('courier_partner');
            $table->dropColumn('tracking_number');
            $table->dropColumn('tracking_url');
            $table->dropColumn('consignment_id');
        });
    }
};

