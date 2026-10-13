<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;

/**
 * Every storefront visit registers a device; clients that drop cookies (some in-app browsers, privacy modes, scripts)
 * register one per request. Removes devices that never came back and own nothing: no cart lines, orders, account,
 * customer or blacklist entry.
 */
class PruneStorefrontDevices extends Command
{
    protected $signature = 'storefront:prune-devices {--days=30 : only devices older than this}';
    protected $description = 'Delete one-visit storefront devices that own nothing';

    public function handle(): int
    {
        $deleted = 0;
        Device::query()
            ->where('created_at', '<', now()->subDays((int) $this->option('days')))
            ->whereNull('user_id')->whereNull('customer_id')
            ->whereColumn('last_seen', '<=', 'created_at')   // never seen again after the first request
            ->whereDoesntHave('orders')->whereDoesntHave('blackLists')
            ->whereNotIn('id', \App\Models\Ops\BlacklistEntry::whereNotNull('device_id')->select('device_id'))   // Ops blacklist + its attempts
            ->whereNotIn('id', \App\Models\Ops\BlacklistHit::whereNotNull('device_id')->select('device_id'))
            ->whereDoesntHave('cart', fn ($q) => $q->has('items'))
            ->chunkById(500, function ($devices) use (&$deleted) {
                $deleted += Device::whereIn('id', $devices->modelKeys())->delete();   // empty carts go with them (FK nulls device_id)
            });
        \App\Models\Cart::whereNull('device_id')->whereNull('user_id')->doesntHave('items')->delete();
        $this->info("Pruned $deleted device(s).");
        return self::SUCCESS;
    }
}
