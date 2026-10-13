<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Users → Blacklist: phone / device / IP / email entries that block (or flag) orders from every channel, plus a log of every attempt.
 * Backfills the old blocks: ops_customers.is_blocked, and the old website's black_lists (customers by phone, devices by device).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_blacklist', function (Blueprint $t) {
            $t->id();
            $t->string('type', 10);                       // phone | device | ip | email
            $t->string('value', 191);                     // normalized: 01XXXXXXXXX · device id · IP · lower-case email
            $t->string('action', 10)->default('block');   // block = refuse the order · flag = accept, tag + notify
            $t->string('reason', 30)->nullable();         // config('ops.blacklist_reasons')
            $t->text('note')->nullable();
            $t->foreignId('customer_id')->nullable()->constrained('ops_customers')->nullOnDelete();
            $t->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $t->foreignId('order_id')->nullable()->constrained('ops_orders')->nullOnDelete();   // the order it was added from
            $t->boolean('is_active')->default(true);
            $t->timestamp('expires_at')->nullable();
            $t->unsignedInteger('hits')->default(0);
            $t->timestamp('last_hit_at')->nullable();
            $t->string('source', 20)->default('manual');  // manual | order | customer | import | auto | legacy
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('removed_at')->nullable();
            $t->timestamps();
            $t->unique(['type', 'value']);
            $t->index(['is_active', 'expires_at']);
        });

        Schema::create('ops_blacklist_hits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('blacklist_id')->constrained('ops_blacklist')->cascadeOnDelete();
            $t->string('channel', 20);                    // website | autosave | bulk | draft | order (automations)
            $t->boolean('blocked');                       // true = order refused · false = flagged and accepted
            $t->string('phone', 20)->nullable(); $t->string('name')->nullable();
            $t->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $t->string('ip_address', 45)->nullable();
            $t->foreignId('order_id')->nullable()->constrained('ops_orders')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();   // staff who tried (Ops channels)
            $t->timestamp('created_at')->useCurrent();
            $t->index('created_at');
        });

        $this->backfill();
    }

    protected function backfill(): void
    {
        $now = now(); $rows = [];
        $add = function (string $type, string $value, array $extra = []) use (&$rows, $now) {
            $rows["$type:$value"] = array_merge($rows["$type:$value"] ?? ['type' => $type, 'value' => $value, 'action' => 'block', 'reason' => 'other', 'source' => 'legacy',
                'is_active' => true, 'note' => null, 'customer_id' => null, 'device_id' => null, 'created_at' => $now, 'updated_at' => $now], array_filter($extra, fn ($v) => $v !== null));
        };

        foreach (DB::table('ops_customers')->where('is_blocked', true)->get(['id', 'phone']) as $c)
            if ($p = Phone::normalize($c->phone)) $add('phone', $p, ['customer_id' => $c->id, 'note' => 'Blocked customer (before the Blacklist module)']);

        if (Schema::hasTable('black_lists')) {
            foreach (DB::table('black_lists')->get() as $b) {
                $note = $b->reason ? mb_substr('Old website: '.$b->reason, 0, 1000) : 'Old website blacklist';
                if ($b->black_listable_type === 'App\\Models\\Device' && DB::table('devices')->where('id', $b->black_listable_id)->exists()) {
                    $add('device', (string) $b->black_listable_id, ['device_id' => $b->black_listable_id, 'note' => $note]);
                } elseif ($b->black_listable_type === 'App\\Models\\Customer' && Schema::hasTable('customers')) {
                    $p = Phone::normalize(DB::table('customers')->where('id', $b->black_listable_id)->value('phone'));
                    if ($p) $add('phone', $p, ['customer_id' => DB::table('ops_customers')->where('phone', $p)->value('id'), 'note' => $note]);
                }
            }
        }

        foreach (array_chunk(array_values($rows), 500) as $chunk) DB::table('ops_blacklist')->insertOrIgnore($chunk);
        // every blocked phone → its customer is blocked too (is_blocked mirrors the active phone entries)
        DB::table('ops_customers')->whereIn('phone', DB::table('ops_blacklist')->where('type', 'phone')->where('action', 'block')->select('value'))->update(['is_blocked' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_blacklist_hits');
        Schema::dropIfExists('ops_blacklist');
    }
};
