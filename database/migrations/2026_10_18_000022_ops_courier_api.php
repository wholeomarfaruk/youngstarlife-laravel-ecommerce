<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Courier APIs (Steadfast, Pathao — App\Services\Couriers): booking result on the parcel, a tracking timeline per parcel,
 * every outgoing API call logged, and richer incoming-webhook log rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_shipments', function (Blueprint $t) {
            $t->string('consignment_id', 40)->nullable()->after('tracking')->index();   // courier's own id (Steadfast consignment_id / Pathao consignment_id)
            $t->timestamp('booked_at')->nullable()->after('consignment_id');            // sent to the courier through its API
            $t->string('booking_error', 500)->nullable()->after('booked_at');
            $t->json('courier_meta')->nullable()->after('booking_error');                // pathao city/zone/area, delivery fee, store …
            $t->timestamp('last_tracked_at')->nullable()->after('status_updated_at');
        });

        Schema::create('ops_shipment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->constrained('ops_shipments')->cascadeOnDelete();
            $t->string('source', 12);                    // webhook | sync | api | manual
            $t->string('status', 20)->nullable();        // our courier status (null = message only)
            $t->string('raw_status', 80)->nullable();    // courier's own word
            $t->string('message', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['shipment_id', 'created_at']);
        });

        Schema::create('ops_courier_api_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('courier_id')->nullable()->constrained('ops_couriers')->nullOnDelete();
            $t->foreignId('shipment_id')->nullable()->constrained('ops_shipments')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 30);                    // book | bulk_book | track | balance | test | stores | cities | zones | areas | return_request
            $t->boolean('success');
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->string('error', 500)->nullable();
            $t->json('request')->nullable();
            $t->json('response')->nullable();
            $t->unsignedInteger('ms')->default(0);
            $t->timestamp('created_at')->useCurrent()->index();
        });

        Schema::table('ops_webhook_logs', function (Blueprint $t) {
            $t->foreignId('shipment_id')->nullable()->after('courier_id')->constrained('ops_shipments')->nullOnDelete();
            $t->string('result', 16)->nullable()->after('status_code');   // processed | duplicate | unmatched | unauthorized | ignored | handshake | error
            $t->string('error', 255)->nullable()->after('result');
            $t->string('ip', 45)->nullable()->after('error');
        });

        // every courier gets a webhook secret (Steadfast "Auth token" / Pathao "Webhook secret")
        foreach (\Illuminate\Support\Facades\DB::table('ops_couriers')->whereNull('webhook_secret')->pluck('id') as $id)
            \Illuminate\Support\Facades\DB::table('ops_couriers')->where('id', $id)->update(['webhook_secret' => \Illuminate\Support\Str::random(40)]);
    }

    public function down(): void
    {
        Schema::table('ops_webhook_logs', function (Blueprint $t) { $t->dropConstrainedForeignId('shipment_id'); $t->dropColumn(['result', 'error', 'ip']); });
        Schema::dropIfExists('ops_courier_api_logs');
        Schema::dropIfExists('ops_shipment_events');
        Schema::table('ops_shipments', fn (Blueprint $t) => $t->dropColumn(['consignment_id', 'booked_at', 'booking_error', 'courier_meta', 'last_tracked_at']));
    }
};
