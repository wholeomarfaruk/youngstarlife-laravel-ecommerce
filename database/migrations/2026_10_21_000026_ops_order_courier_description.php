<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Parcel contents text edited in Book courier (empty = built from the order items) — CourierGateway::parcel() sends it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_orders', fn (Blueprint $t) => $t->string('courier_description', 250)->nullable()->after('courier_note'));
    }

    public function down(): void
    {
        Schema::table('ops_orders', fn (Blueprint $t) => $t->dropColumn('courier_description'));
    }
};
