<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

/**
 * Storefront: every visitor gets a device (cookie token) on the first visit; the cart and orders hang off the device,
 * and the device is linked to a user account once one exists (first order / login / register).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $t) {
            $t->char('token', 36)->nullable()->unique()->after('id');   // value of the ys_device cookie
            $t->foreignId('user_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
        });
        DB::table('devices')->whereNull('token')->orderBy('id')->each(fn ($d) => DB::table('devices')->where('id', $d->id)->update(['token' => (string) Str::uuid()]));

        // one cart per device; user_id is filled once the device belongs to an account
        Schema::table('carts', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->nullable()->change();
            $t->unique('device_id');
            $t->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('cart_items', function (Blueprint $t) {
            $t->string('size', 50)->default('')->after('product_id');   // '' = product without sizes
            $t->unique(['cart_id', 'product_id', 'size']);
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->foreignId('device_id')->nullable()->after('user_agent')->constrained('devices')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->after('device_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) { $t->dropConstrainedForeignId('user_id'); $t->dropConstrainedForeignId('device_id'); });
        Schema::table('cart_items', function (Blueprint $t) { $t->dropUnique(['cart_id', 'product_id', 'size']); $t->dropColumn('size'); });
        Schema::table('carts', function (Blueprint $t) { $t->dropForeign(['user_id']); $t->dropUnique(['device_id']); });
        Schema::table('devices', function (Blueprint $t) { $t->dropConstrainedForeignId('user_id'); $t->dropUnique(['token']); $t->dropColumn('token'); });
    }
};
