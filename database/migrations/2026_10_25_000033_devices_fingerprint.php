<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Browser fingerprint of a storefront device (frontend/js/device-fp.js → DeviceManager::fingerprint): screen, GPU, canvas, phone model …
 * Blacklist uses it to spot a blocked visitor who cleared cookies — replaces the old "same user agent" rule, which hit every phone on the same Chrome version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', fn (Blueprint $t) => $t->char('fingerprint', 64)->nullable()->index()->after('user_agent'));
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $t) => $t->dropColumn('fingerprint'));
    }
};
