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
        Schema::table('products', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('status');
        });

        // Seed an initial global order for existing products (newest gets lowest number).
        $order = 1;
        foreach (\DB::table('products')->orderByDesc('id')->pluck('id') as $id) {
            \DB::table('products')->where('id', $id)->update(['sort_order' => $order++]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
