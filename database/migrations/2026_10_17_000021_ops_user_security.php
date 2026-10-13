<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/** Ops → My profile: photo, real two-factor sign-in (TOTP + recovery codes), password age, personal preferences, sign-in history. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'avatar')) $t->string('avatar')->nullable()->after('phone');
            if (! Schema::hasColumn('users', 'two_factor_secret')) $t->text('two_factor_secret')->nullable();            // encrypted
            if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) $t->text('two_factor_recovery_codes')->nullable();   // encrypted JSON
            if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) $t->timestamp('two_factor_confirmed_at')->nullable();
            if (! Schema::hasColumn('users', 'password_changed_at')) $t->timestamp('password_changed_at')->nullable();
            if (! Schema::hasColumn('users', 'ops_prefs')) $t->json('ops_prefs')->nullable();                        // {notify: {event: {app, push}}}
        });

        Schema::create('ops_login_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('event', 20);   // login | failed | two_factor_failed | logout
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->boolean('new_device')->default(false);
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'created_at']);
        });

        // the old profile toggle only stored a flag (no secret) — real two-factor starts switched off
        DB::table('ops_settings')->where('key', 'profile_2fa')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_login_events');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['avatar', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'password_changed_at', 'ops_prefs']));
    }
};
