<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ops panel — columns/tables required by the full design spec (YoungStar Ops).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'ops_role')) $t->string('ops_role', 20)->nullable()->after('role');   // Admin | Manager | Packer | Accounts
            if (! Schema::hasColumn('users', 'phone')) $t->string('phone', 20)->nullable()->after('email');
            if (! Schema::hasColumn('users', 'is_active')) $t->boolean('is_active')->default(true);
            if (! Schema::hasColumn('users', 'last_seen_at')) $t->timestamp('last_seen_at')->nullable();
        });

        Schema::table('ops_products', function (Blueprint $t) {
            $t->date('sale_starts_at')->nullable()->after('sale_price'); $t->date('sale_ends_at')->nullable()->after('sale_starts_at');
            $t->string('tax_class', 30)->nullable()->after('sale_ends_at'); $t->json('tags')->nullable(); $t->boolean('requires_shipping')->default(true);
        });

        Schema::table('ops_couriers', function (Blueprint $t) {
            $t->foreignId('default_zone_id')->nullable()->constrained('ops_delivery_zones')->nullOnDelete();
            $t->unsignedInteger('charge')->default(0); $t->string('pickup_store')->nullable(); $t->timestamp('last_synced_at')->nullable();
        });

        Schema::table('ops_customers', function (Blueprint $t) { $t->json('tags')->nullable(); });

        Schema::table('ops_orders', function (Blueprint $t) {
            $t->foreignId('exchange_of_id')->nullable()->constrained('ops_orders')->nullOnDelete();   // exchange order → original order
        });

        Schema::table('ops_customer_returns', function (Blueprint $t) {
            $t->string('rider')->nullable(); $t->foreignId('exchange_order_id')->nullable()->constrained('ops_orders')->nullOnDelete();
            $t->unsignedInteger('exchange_charge')->default(0);
        });

        Schema::table('ops_supplier_returns', function (Blueprint $t) { $t->timestamp('resolved_at')->nullable(); });

        Schema::table('ops_shipments', function (Blueprint $t) { $t->unsignedTinyInteger('failed_attempts')->default(0); $t->text('complaint')->nullable(); });

        // incoming courier webhook calls (Settings → Integrations → Webhooks · delivery log)
        Schema::create('ops_webhook_logs', function (Blueprint $t) {
            $t->id(); $t->foreignId('courier_id')->nullable()->constrained('ops_couriers')->nullOnDelete();
            $t->string('event', 40)->nullable(); $t->unsignedSmallInteger('status_code'); $t->unsignedInteger('ms')->default(0);
            $t->json('payload')->nullable(); $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_webhook_logs');
        Schema::table('ops_shipments', fn (Blueprint $t) => $t->dropColumn(['failed_attempts', 'complaint']));
        Schema::table('ops_supplier_returns', fn (Blueprint $t) => $t->dropColumn('resolved_at'));
        Schema::table('ops_customer_returns', function (Blueprint $t) { $t->dropConstrainedForeignId('exchange_order_id'); $t->dropColumn(['rider', 'exchange_charge']); });
        Schema::table('ops_orders', fn (Blueprint $t) => $t->dropConstrainedForeignId('exchange_of_id'));
        Schema::table('ops_customers', fn (Blueprint $t) => $t->dropColumn('tags'));
        Schema::table('ops_couriers', function (Blueprint $t) { $t->dropConstrainedForeignId('default_zone_id'); $t->dropColumn(['charge', 'pickup_store', 'last_synced_at']); });
        Schema::table('ops_products', fn (Blueprint $t) => $t->dropColumn(['sale_starts_at', 'sale_ends_at', 'tax_class', 'tags', 'requires_shipping']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['ops_role', 'phone', 'is_active', 'last_seen_at']));
    }
};
