<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Hash, Schema};
use Illuminate\Support\Str;

/**
 * Every customer / supplier profile belongs to a user account (users row): the account comes first, the profile hangs off it.
 * Customers usually have only a phone → users.email becomes nullable. Existing profiles get an account (draft placeholders stay unlinked).
 * The "Customers & Suppliers" module is now "Users" → saved role permissions are renamed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('email')->nullable()->change());
        foreach (['ops_customers', 'ops_suppliers'] as $table)
            Schema::table($table, fn (Blueprint $t) => $t->foreignId('user_id')->nullable()->unique()->after('id')->constrained('users')->nullOnDelete());

        $password = Hash::make(Str::random(40));   // no one knows it → no login until a reset
        foreach (['ops_customers' => 'customer', 'ops_suppliers' => 'supplier'] as $table => $role) {
            DB::table($table)->whereNull('user_id')->where(fn ($q) => $q->whereNull('phone')->orWhere('phone', 'not like', 'draft-%'))
                ->chunkById(500, function ($rows) use ($table, $role, $password) {
                    foreach ($rows as $r) {
                        $email = $r->email ?? null;   // suppliers have no email column
                        $free = fn ($col, $v) => $v ? DB::table('users')->where($col, $v)->whereNotIn('id', DB::table($table)->whereNotNull('user_id')->select('user_id'))->value('id') : null;
                        $uid = $free('phone', $r->phone) ?? $free('email', $email) ?? DB::table('users')->insertGetId([
                            'name' => $r->name, 'phone' => $r->phone, 'email' => $email && ! DB::table('users')->where('email', $email)->exists() ? $email : null,
                            'password' => $password, 'role' => $role, 'is_active' => true, 'created_at' => $r->created_at, 'updated_at' => now(),
                        ]);
                        DB::table($table)->where('id', $r->id)->update(['user_id' => $uid]);
                    }
                });
        }

        $this->renameModule('Customers & Suppliers', 'Users');
    }

    public function down(): void
    {
        $this->renameModule('Users', 'Customers & Suppliers');
        foreach (['ops_customers', 'ops_suppliers'] as $table)
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));
        // users.email stays nullable and the opened accounts stay: rows without an email would block NOT NULL again
    }

    protected function renameModule(string $from, string $to): void
    {
        if (! $raw = DB::table('ops_settings')->where('key', 'permissions')->value('value')) return;
        $perms = array_map(fn ($mods) => array_values(array_map(fn ($m) => $m === $from ? $to : $m, (array) $mods)), json_decode($raw, true) ?: []);
        DB::table('ops_settings')->where('key', 'permissions')->update(['value' => json_encode($perms)]);
        cache()->forget('setting.permissions');
    }
};
