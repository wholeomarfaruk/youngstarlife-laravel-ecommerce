<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('code', 20)->unique();   // steadfast|pathao|redx
            $t->json('credentials')->nullable(); $t->string('webhook_secret')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
        });

        Schema::create('shipments', function (Blueprint $t) {  // = parcel
            $t->id(); $t->string('code', 20)->unique();                       // P1005
            $t->foreignId('order_id')->constrained()->cascadeOnDelete(); $t->foreignId('courier_id')->constrained();
            $t->string('tracking', 40)->nullable()->index(); $t->string('courier_status', 20)->default('pending')->index();
            $t->unsignedInteger('cod_amount')->default(0); $t->unsignedInteger('charge')->default(0); $t->decimal('weight_kg', 6, 3)->nullable();
            $t->foreignId('packed_by')->nullable()->constrained('users'); $t->timestamp('packed_at')->nullable(); $t->timestamp('handed_over_at')->nullable();
            $t->timestamp('delivered_at')->nullable(); $t->timestamp('status_updated_at')->nullable();
            $t->foreignId('settlement_id')->nullable(); $t->timestamps();
        });

        Schema::create('courier_settlements', function (Blueprint $t) {
            $t->id(); $t->foreignId('courier_id')->constrained(); $t->string('reference')->nullable();
            $t->unsignedInteger('cod_total'); $t->unsignedInteger('charges'); $t->unsignedInteger('received'); $t->date('received_on'); $t->timestamps();
        });

        Schema::create('customer_returns', function (Blueprint $t) {
            $t->id(); $t->string('code', 20)->unique();                       // R1005
            $t->foreignId('order_id')->constrained(); $t->foreignId('shipment_id')->nullable()->constrained();
            $t->foreignId('courier_id')->nullable()->constrained(); $t->string('session', 30)->nullable()->index(); // RRN-0317-01
            $t->string('type', 12);                                            // full|partial|exchange
            $t->string('status', 12)->default('received');                    // received|inspected|closed
            $t->unsignedInteger('refund_amount')->default(0); $t->timestamp('refunded_at')->nullable(); $t->string('refund_via', 20)->nullable();
            $t->text('note')->nullable(); $t->json('photos')->nullable(); $t->foreignId('received_by')->nullable()->constrained('users'); $t->timestamps();
        });

        Schema::create('customer_return_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_return_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_item_id')->constrained(); $t->foreignId('batch_id')->nullable()->constrained();
            $t->unsignedInteger('qty')->default(1); $t->string('reason', 40); $t->string('condition', 12)->nullable(); // null = not inspected
            $t->string('action', 20)->nullable();                              // restock|damaged|courier_claim|investigate
        });

        Schema::create('supplier_returns', function (Blueprint $t) {
            $t->id(); $t->string('code', 20)->unique();                       // SR1002
            $t->foreignId('supplier_id')->constrained();
            $t->string('status', 12)->default('pending')->index();            // pending|sent|received|approved|resolved|rejected|cancelled
            $t->string('resolution', 12)->default('replacement');             // replacement|credit|refund
            $t->unsignedInteger('approved_qty')->nullable(); $t->string('refund_account', 20)->nullable();
            $t->string('ship_via', 30)->nullable(); $t->string('tracking', 40)->nullable(); $t->text('note')->nullable();
            $t->json('step_dates')->nullable(); $t->timestamps();
        });

        Schema::create('supplier_return_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('supplier_return_id')->constrained()->cascadeOnDelete();
            $t->foreignId('batch_id')->constrained(); $t->foreignId('customer_return_item_id')->nullable()->constrained();
            $t->string('source_ref', 20)->nullable();                          // R1005 | QC-014
            $t->unsignedInteger('qty'); $t->unsignedInteger('unit_cost'); $t->string('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
        Schema::dropIfExists('supplier_returns');
        Schema::dropIfExists('customer_return_items');
        Schema::dropIfExists('customer_returns');
        Schema::dropIfExists('courier_settlements');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('couriers');
    }
};
