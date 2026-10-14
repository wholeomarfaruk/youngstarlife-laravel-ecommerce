<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders → Bulk courier (from ecomx-automation): a sheet of orders to book with couriers in one go.
 * Each staff member has one draft sheet; its items are the editable courier rows (pre-filled from the order,
 * saved as they are edited). "Complete sheet" keeps the booked rows as history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_courier_batches', function (Blueprint $t) {
            $t->id(); $t->string('status', 20)->default('draft')->index();   // draft | completed
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable(); $t->timestamps();
        });
        Schema::create('ops_courier_batch_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('courier_batch_id')->constrained('ops_courier_batches')->cascadeOnDelete();
            $t->foreignId('order_id')->constrained('ops_orders')->cascadeOnDelete();
            $t->foreignId('courier_id')->nullable()->constrained('ops_couriers')->nullOnDelete();
            $t->string('name', 120)->default(''); $t->string('phone', 20)->default(''); $t->string('address')->default('');
            $t->unsignedInteger('cod')->default(0); $t->decimal('weight', 5, 2)->default(0.5); $t->unsignedSmallInteger('qty')->default(1);
            $t->string('description', 250)->nullable(); $t->string('note', 200)->nullable();
            $t->string('status', 10)->default('draft');                       // draft | booked | failed
            $t->foreignId('shipment_id')->nullable()->constrained('ops_shipments')->nullOnDelete();
            $t->string('tracking', 60)->nullable(); $t->string('error', 1000)->nullable();
            $t->unsignedInteger('sort_order')->default(0); $t->timestamp('booked_at')->nullable(); $t->timestamps();
            $t->unique(['courier_batch_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_courier_batch_items');
        Schema::dropIfExists('ops_courier_batches');
    }
};
