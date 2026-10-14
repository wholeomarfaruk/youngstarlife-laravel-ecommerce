<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * One order, many parcels (OrderWorkflow::resend):
 *  - ops_shipments.superseded_at  a parcel replaced by a re-send (still tracked, but it no longer drives the order)
 *  - ops_shipments.kind           delivery | resend  (exchange parcels live on the exchange's own record)
 *  - ops_order_item_allocations.shipment_id  the parcel that took these units — so a re-send reserves fresh stock
 *    and the old parcel's return puts back exactly its own units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_shipments', function (Blueprint $t) {
            $t->string('kind', 10)->default('delivery')->after('code');
            $t->timestamp('superseded_at')->nullable()->after('kind')->index();
        });
        Schema::table('ops_order_item_allocations', function (Blueprint $t) {
            $t->foreignId('shipment_id')->nullable()->after('batch_id')->constrained('ops_shipments')->nullOnDelete();
        });
        // packed units so far belong to the order's (only / latest) parcel
        DB::statement('UPDATE ops_order_item_allocations a
            JOIN ops_order_items i ON i.id = a.order_item_id
            SET a.shipment_id = (SELECT MAX(s.id) FROM ops_shipments s WHERE s.order_id = i.order_id)
            WHERE a.packed_at IS NOT NULL AND a.shipment_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('ops_order_item_allocations', fn (Blueprint $t) => $t->dropConstrainedForeignId('shipment_id'));
        Schema::table('ops_shipments', fn (Blueprint $t) => $t->dropColumn(['kind', 'superseded_at']));
    }
};
