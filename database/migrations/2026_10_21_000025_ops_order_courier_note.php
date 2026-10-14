<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Note for the rider, set in Book courier before packing — CourierGateway::parcel() sends it with the parcel. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_orders', fn (Blueprint $t) => $t->string('courier_note', 200)->nullable()->after('note'));
    }

    public function down(): void
    {
        Schema::table('ops_orders', fn (Blueprint $t) => $t->dropColumn('courier_note'));
    }
};
