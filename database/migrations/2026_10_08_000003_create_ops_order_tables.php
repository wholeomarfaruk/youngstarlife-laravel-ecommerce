<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_customers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('phone', 20)->unique(); $t->string('email')->nullable();
            $t->string('address')->nullable(); $t->string('area')->nullable(); $t->string('source', 20)->nullable();
            $t->unsignedInteger('orders_count')->default(0); $t->unsignedInteger('delivered_count')->default(0); $t->unsignedInteger('returned_count')->default(0);
            $t->unsignedInteger('total_spent')->default(0); $t->integer('due')->default(0);
            $t->boolean('is_blocked')->default(false); $t->text('note')->nullable(); $t->timestamp('last_order_at')->nullable()->index();
            $t->timestamps();
        });

        Schema::create('ops_delivery_zones', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedInteger('charge'); $t->unsignedInteger('per_kg')->default(0);
            $t->string('eta')->nullable(); $t->string('default_courier', 20)->nullable(); $t->json('areas')->nullable();
            $t->boolean('is_active')->default(true); $t->timestamps();
        });

        Schema::create('ops_campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('platform', 20);         // Facebook|Instagram|Google|Organic
            $t->string('utm_campaign')->nullable()->index(); $t->string('status', 10)->default('active');
            $t->date('starts_at')->nullable(); $t->date('ends_at')->nullable(); $t->unsignedInteger('spend')->default(0); $t->timestamps();
        });

        Schema::create('ops_orders', function (Blueprint $t) {
            $t->id(); $t->string('number', 20)->unique();                    // #1042
            $t->foreignId('customer_id')->constrained('ops_customers'); $t->foreignId('campaign_id')->nullable()->constrained('ops_campaigns')->nullOnDelete();
            $t->string('source', 20)->default('website');                     // website|facebook|instagram|phone
            $t->string('status', 20)->default('pending');                     // see config/admin.php order_status
            $t->string('payment_status', 10)->default('pending');
            $t->boolean('is_draft')->default(false);                          // auto-saved drafts / abandoned checkouts
            $t->string('ship_name'); $t->string('ship_phone', 20); $t->string('ship_address'); $t->foreignId('delivery_zone_id')->nullable()->constrained('ops_delivery_zones');
            $t->unsignedInteger('subtotal')->default(0); $t->unsignedInteger('discount')->default(0); $t->unsignedInteger('delivery_charge')->default(0);
            $t->unsignedInteger('total')->default(0); $t->unsignedInteger('advance')->default(0); $t->unsignedInteger('cod')->default(0);
            $t->unsignedInteger('cost')->default(0);                           // set at packing (sum of batch costs)
            $t->text('note')->nullable(); $t->json('tags')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamp('placed_at')->index(); $t->timestamps();
            $t->index(['is_draft', 'status', 'placed_at']); $t->index(['customer_id', 'placed_at']);
        });

        Schema::create('ops_order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained('ops_orders')->cascadeOnDelete(); $t->foreignId('variant_id')->constrained('ops_product_variants');
            $t->unsignedInteger('qty'); $t->unsignedInteger('unit_price');
            $t->unsignedInteger('returned_qty')->default(0);
        });

        // Which batch each unit came from + purchase price captured at PACKING time
        Schema::create('ops_order_item_allocations', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_item_id')->constrained('ops_order_items')->cascadeOnDelete(); $t->foreignId('batch_id')->constrained('ops_batches');
            $t->unsignedInteger('qty'); $t->unsignedInteger('unit_cost')->nullable(); // null until packed
            $t->boolean('is_manual')->default(false); $t->timestamp('packed_at')->nullable();
            $t->index('batch_id');
        });

        Schema::create('ops_order_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained('ops_orders')->cascadeOnDelete(); $t->string('type', 30); $t->string('message');
            $t->foreignId('user_id')->nullable()->constrained('users'); $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_order_events');
        Schema::dropIfExists('ops_order_item_allocations');
        Schema::dropIfExists('ops_order_items');
        Schema::dropIfExists('ops_orders');
        Schema::dropIfExists('ops_campaigns');
        Schema::dropIfExists('ops_delivery_zones');
        Schema::dropIfExists('ops_customers');
    }
};
