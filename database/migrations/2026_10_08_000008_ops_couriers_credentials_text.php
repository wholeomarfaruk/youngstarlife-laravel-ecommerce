<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ops_couriers.credentials holds an encrypted string (encrypted:array cast) → must be TEXT, not JSON.
return new class extends Migration
{
    public function up(): void { DB::statement('ALTER TABLE ops_couriers MODIFY credentials TEXT NULL'); }
    public function down(): void { DB::statement('ALTER TABLE ops_couriers MODIFY credentials JSON NULL'); }
};
