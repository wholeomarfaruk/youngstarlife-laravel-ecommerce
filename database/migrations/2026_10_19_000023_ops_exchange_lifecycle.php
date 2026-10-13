<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Exchange lifecycle (App\Services\Ops\ExchangeService): a state on the exchange order, and the old-item return line
 * becomes "expected" until the courier really brings the old item back (it used to be created as "received").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_orders', function (Blueprint $t) {
            $t->string('exchange_state', 20)->nullable()->after('exchange_of_id')->index();   // config('ops.exchange_state')
        });
        Schema::table('ops_customer_returns', function (Blueprint $t) {
            $t->timestamp('received_at')->nullable()->after('received_by');
        });

        // old-item lines never inspected = still coming back; inspected ones were received when last touched
        DB::table('ops_customer_returns')->whereNotNull('exchange_order_id')->whereNotExists(fn ($q) => $q->from('ops_customer_return_items')->whereColumn('customer_return_id', 'ops_customer_returns.id')->whereNotNull('condition'))
            ->update(['status' => 'expected', 'received_by' => null]);
        DB::table('ops_customer_returns')->where('status', '!=', 'expected')->whereNull('received_at')->update(['received_at' => DB::raw('COALESCE(updated_at, created_at)')]);

        // state for exchanges made before this
        foreach (\App\Models\Ops\Order::whereNotNull('exchange_of_id')->get() as $x) {
            try { app(\App\Services\Ops\ExchangeService::class)->sync($x, quiet: true); } catch (\Throwable $e) { report($e); }
        }
    }

    public function down(): void
    {
        DB::table('ops_customer_returns')->where('status', 'expected')->update(['status' => 'received']);
        Schema::table('ops_customer_returns', fn (Blueprint $t) => $t->dropColumn('received_at'));
        Schema::table('ops_orders', fn (Blueprint $t) => $t->dropColumn('exchange_state'));
    }
};
