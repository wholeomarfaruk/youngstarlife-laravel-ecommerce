<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ops order flow without "Processing": pending → confirmed → packed → shipped → delivered / returning → return received.
 * Processing only sat between Confirmed and Pack & scan (stock is already reserved at Confirmed) — those orders become Confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ops_orders')->where('status', 'processing')->update(['status' => 'confirmed']);
    }

    public function down(): void {}   // nothing to restore: Confirmed and Processing meant the same stock state
};
