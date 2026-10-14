<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The money accounts every payment screen offers (config('ops.accounts')) — on databases where OpsSeeder never ran. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('ops.accounts') as $name)
            if (! DB::table('ops_accounts')->where('name', $name)->exists()) DB::table('ops_accounts')->insert(['name' => $name, 'balance' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void {}
};
