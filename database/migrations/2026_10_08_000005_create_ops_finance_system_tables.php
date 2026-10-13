<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_accounts', function (Blueprint $t) {
            $t->id(); $t->string('name', 30);                                  // Cash | Bank | bKash | Nagad
            $t->integer('balance')->default(0); $t->timestamps();
        });

        Schema::create('ops_transactions', function (Blueprint $t) {
            $t->id(); $t->foreignId('account_id')->constrained('ops_accounts'); $t->string('direction', 3); // in|out
            $t->string('category', 30)->index();       // Sales|Courier payment|Supplier|Refund|Supplier refund|Packaging|Marketing|Salary|Other
            $t->unsignedInteger('amount'); $t->string('description'); $t->nullableMorphs('ref');
            $t->date('date')->index(); $t->foreignId('user_id')->nullable()->constrained('users'); $t->timestamps();
        });

        Schema::create('ops_settings', function (Blueprint $t) {
            $t->string('key')->primary(); $t->json('value')->nullable();
        });

        Schema::create('ops_activity_log', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable()->constrained('users'); $t->string('message'); $t->nullableMorphs('subject');
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_activity_log');
        Schema::dropIfExists('ops_settings');
        Schema::dropIfExists('ops_transactions');
        Schema::dropIfExists('ops_accounts');
    }
};
