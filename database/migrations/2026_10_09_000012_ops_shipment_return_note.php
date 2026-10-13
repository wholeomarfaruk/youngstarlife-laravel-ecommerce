<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** note set on a returning parcel BEFORE it is received, so the receiver knows to inspect / keep it apart */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_shipments', function (Blueprint $t) {
            $t->string('return_flag', 10)->nullable()->after('complaint');   // damaged | inspect | separate
            $t->string('return_note', 255)->nullable()->after('return_flag');
        });
    }

    public function down(): void { Schema::table('ops_shipments', fn (Blueprint $t) => $t->dropColumn(['return_flag', 'return_note'])); }
};
