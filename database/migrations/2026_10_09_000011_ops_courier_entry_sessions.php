<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Courier entry sessions — one meeting with a courier's pickup man:
 *   sent      parcels handed over to the rider
 *   returned  returned parcels received back
 *   rejected  damaged parcels identified but NOT accepted (stay with the courier → claim)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_courier_sessions', function (Blueprint $t) {
            $t->id(); $t->string('code', 30)->unique();                         // ES-1009-01 (legacy RRN-… codes kept)
            $t->foreignId('courier_id')->constrained('ops_couriers');
            $t->string('rider', 80)->nullable(); $t->string('rider_phone', 20)->nullable();
            $t->string('status', 10)->default('open')->index();                 // open | closed
            $t->text('note')->nullable();
            $t->foreignId('opened_by')->nullable()->constrained('users'); $t->timestamp('closed_at')->nullable(); $t->timestamps();
        });
        Schema::create('ops_courier_session_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('courier_session_id')->constrained('ops_courier_sessions')->cascadeOnDelete();
            $t->foreignId('shipment_id')->constrained('ops_shipments');
            $t->string('type', 10)->index();                                    // sent | returned | rejected
            $t->foreignId('customer_return_id')->nullable()->constrained('ops_customer_returns')->nullOnDelete();
            $t->string('note')->nullable(); $t->foreignId('user_id')->nullable()->constrained('users');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['shipment_id', 'type']);
        });

        \App\Services\Ops\EntrySessions::backfill();   // existing handovers + return receipts → sessions
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_courier_session_items');
        Schema::dropIfExists('ops_courier_sessions');
    }
};
