<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
              $table->text('model')->nullable()->change();
            $table->text('user_agent')->nullable()->change();
            $table->text('ip_address')->nullable()->change();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('model')->nullable()->change();
            $table->string('user_agent')->nullable()->change();
            $table->string('ip_address')->nullable()->change();
        });
    }
};
