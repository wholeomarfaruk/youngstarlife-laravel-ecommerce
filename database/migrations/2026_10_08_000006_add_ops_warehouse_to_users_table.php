<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ops panel: staff belong to a warehouse. `users.role` already exists in this project.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'warehouse_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->foreignId('warehouse_id')->nullable()->after('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'warehouse_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('warehouse_id'));
        }
    }
};
