<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_order_extractions', function (Blueprint $table) {
            $table->renameColumn('image_path', 'image_paths');
        });

        Schema::table('ai_order_extractions', function (Blueprint $table) {
            $table->json('image_paths')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_order_extractions', function (Blueprint $table) {
            $table->string('image_paths')->nullable()->change();
        });

        Schema::table('ai_order_extractions', function (Blueprint $table) {
            $table->renameColumn('image_paths', 'image_path');
        });
    }
};
