<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Courier money: COD fee % per courier (Steadfast / Pathao take ~1% of the cash they collect) and the delivery-charge / COD-fee split on each settlement. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_couriers', fn (Blueprint $t) => $t->decimal('cod_fee_pct', 5, 2)->default(1)->after('charge'));
        // charges stays the total deducted; delivery_charges + cod_fee is its breakdown
        Schema::table('ops_courier_settlements', function (Blueprint $t) {
            $t->unsignedInteger('delivery_charges')->default(0)->after('charges');
            $t->unsignedInteger('cod_fee')->default(0)->after('delivery_charges');
            $t->string('account', 20)->nullable()->after('received_on');
            $t->text('note')->nullable()->after('account');
        });
        \Illuminate\Support\Facades\DB::table('ops_courier_settlements')->update(['delivery_charges' => \Illuminate\Support\Facades\DB::raw('charges')]);
    }

    public function down(): void
    {
        Schema::table('ops_courier_settlements', fn (Blueprint $t) => $t->dropColumn(['delivery_charges', 'cod_fee', 'account', 'note']));
        Schema::table('ops_couriers', fn (Blueprint $t) => $t->dropColumn('cod_fee_pct'));
    }
};
