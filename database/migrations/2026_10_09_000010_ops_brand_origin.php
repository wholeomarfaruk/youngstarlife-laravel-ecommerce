<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_brands', function (Blueprint $t) {
            $t->string('origin', 60)->nullable()->after('slug');   // "Bangladesh"
            $t->string('note', 120)->nullable()->after('origin');  // "house brand"
        });
    }

    public function down(): void
    {
        Schema::table('ops_brands', fn (Blueprint $t) => $t->dropColumn(['origin', 'note']));
    }
};
