<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Repurpose the `slides` table into an image-only Customer Reviews gallery.
     * Keep only `image` (required) + an optional `title` caption + `status`.
     */
    public function up(): void
    {
        Schema::table('slides', function (Blueprint $table) {
            if (Schema::hasColumn('slides', 'tagline')) {
                $table->dropColumn('tagline');
            }
            if (Schema::hasColumn('slides', 'subtitle')) {
                $table->dropColumn('subtitle');
            }
        });

        Schema::table('slides', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slides', function (Blueprint $table) {
            if (!Schema::hasColumn('slides', 'tagline')) {
                $table->string('tagline')->default('');
            }
            if (!Schema::hasColumn('slides', 'subtitle')) {
                $table->string('subtitle')->default('');
            }
        });

        Schema::table('slides', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};
