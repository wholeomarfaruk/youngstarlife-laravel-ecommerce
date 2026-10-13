<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('contact_name')->nullable(); $t->string('phone', 20)->nullable();
            $t->string('address')->nullable(); $t->string('categories')->nullable();
            $t->integer('due')->default(0); $t->integer('credit')->default(0);   // running balances
            $t->boolean('is_active')->default(true)->index(); $t->timestamps();
        });

        // supplier-specific name/code/image for a product ("Supplier 5 calls it CG-22")
        Schema::create('product_supplier', function (Blueprint $t) {
            $t->id(); $t->foreignId('product_id')->constrained()->cascadeOnDelete(); $t->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $t->string('supplier_code', 60)->nullable(); $t->string('supplier_name')->nullable(); $t->string('image')->nullable();
            $t->unique(['product_id', 'supplier_id']);
        });

        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id(); $t->string('number', 20)->unique();                    // PO-106
            $t->foreignId('supplier_id')->constrained(); $t->foreignId('warehouse_id')->constrained();
            $t->date('ordered_at'); $t->date('expected_at')->nullable();
            $t->string('status', 12)->default('draft')->index();             // draft|sent|partial|received|cancelled
            $t->boolean('closed_short')->default(false);
            $t->unsignedInteger('paid')->default(0); $t->string('paid_from', 20)->nullable();
            $t->text('note')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users'); $t->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained('product_variants');
            $t->unsignedInteger('qty'); $t->unsignedInteger('unit_cost');
            $t->unsignedInteger('received')->default(0); $t->unsignedInteger('damaged')->default(0);
        });

        // Goods received note: one per receiving session
        Schema::create('goods_receipts', function (Blueprint $t) {
            $t->id(); $t->string('number', 20)->unique();                    // GRN-031
            $t->foreignId('purchase_order_id')->constrained(); $t->foreignId('received_by')->constrained('users'); $t->timestamps();
        });

        // A batch is created per variant per receipt. Stock ONLY enters through batches.
        Schema::create('batches', function (Blueprint $t) {
            $t->id(); $t->string('code', 20)->unique();                      // B014
            $t->foreignId('variant_id')->constrained('product_variants');
            $t->foreignId('supplier_id')->constrained(); $t->foreignId('purchase_order_id')->nullable()->constrained();
            $t->foreignId('goods_receipt_id')->nullable()->constrained(); $t->foreignId('warehouse_id')->constrained();
            $t->unsignedInteger('unit_cost'); $t->unsignedInteger('qty'); $t->integer('remaining'); $t->unsignedInteger('damaged')->default(0);
            $t->timestamp('received_at')->index(); $t->timestamp('labels_printed_at')->nullable();
            $t->string('status', 10)->default('active');                     // active|low|depleted
            $t->timestamps();
            $t->index(['variant_id', 'remaining', 'received_at']);            // FIFO allocation lookup
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id(); $t->foreignId('batch_id')->constrained(); $t->foreignId('variant_id')->constrained('product_variants');
            $t->string('type', 20)->index();      // stock_in|sale|return_restock|damage|adjust|transfer|supplier_return|replacement
            $t->integer('qty');                    // + in / − out
            $t->nullableMorphs('ref');             // Order, CustomerReturn, SupplierReturn, GoodsReceipt…
            $t->string('note')->nullable(); $t->foreignId('user_id')->nullable()->constrained(); $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('product_supplier');
        Schema::dropIfExists('suppliers');
    }
};
