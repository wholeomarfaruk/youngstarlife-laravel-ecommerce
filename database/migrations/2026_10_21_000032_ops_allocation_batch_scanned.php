<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packing scan reads the stock-in batch sticker (SKU-B014): the allocation then points at the batch that physically went in
 * the parcel. batch_scanned = confirmed at packing, by that scan or picked by hand (false = chosen automatically, oldest stock first) — shown in Trace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_order_item_allocations', fn (Blueprint $t) => $t->boolean('batch_scanned')->default(false)->after('is_manual'));
    }

    public function down(): void
    {
        Schema::table('ops_order_item_allocations', fn (Blueprint $t) => $t->dropColumn('batch_scanned'));
    }
};
