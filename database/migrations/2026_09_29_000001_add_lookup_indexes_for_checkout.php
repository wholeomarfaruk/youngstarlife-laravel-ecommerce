<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * place_order looks up orders, customers, devices and auto saved orders by phone / user agent
 * on every checkout. Without indexes each lookup scans the whole table.
 */
return new class extends Migration
{
    private array $indexes = [
        'orders' => [
            'orders_phone_status_index' => ['phone', 'status'],
            'orders_status_index' => ['status'],
            'orders_created_at_index' => ['created_at'],
        ],
        'customers' => [
            'customers_phone_index' => ['phone'],
        ],
        'auto_save_orders' => [
            'auto_save_orders_phone_index' => ['phone'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (!Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn(Blueprint $t) => $t->index($columns, $name));
                }
            }
        }

        // user_agent is TEXT, so MySQL needs a prefix length
        if (DB::getDriverName() === 'mysql' && !Schema::hasIndex('devices', 'devices_user_agent_index')) {
            DB::statement('CREATE INDEX devices_user_agent_index ON devices (user_agent(255))');
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn(Blueprint $t) => $t->dropIndex($name));
                }
            }
        }

        if (Schema::hasIndex('devices', 'devices_user_agent_index')) {
            Schema::table('devices', fn(Blueprint $t) => $t->dropIndex('devices_user_agent_index'));
        }
    }
};
