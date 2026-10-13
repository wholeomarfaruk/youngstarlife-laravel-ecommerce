<?php

namespace App\Console\Commands;

use App\Models\Ops\Shipment;
use App\Services\Couriers\{CourierException, CourierGateway};
use Illuminate\Console\Command;

/**
 * Settings → Automations "Courier status sync": asks the courier API about parcels that are still moving and
 * have had no update for a while — the safety net for a missed / switched-off webhook. Scheduled every 30 minutes.
 */
class OpsCourierSync extends Command
{
    protected $signature = 'ops:courier-sync {--parcel= : one parcel code} {--limit=}';
    protected $description = 'Pull parcel statuses from courier APIs (Steadfast, Pathao)';

    public function handle(CourierGateway $gateway): int
    {
        if (! $this->option('parcel') && ! (ops_setting('automations.courier_poll.on') ?? true)) { $this->info('Courier status sync is off (Settings → Automations).'); return self::SUCCESS; }
        $q = Shipment::with('courier', 'order')->whereNotIn('courier_status', Shipment::FINAL)
            ->whereHas('courier', fn ($q) => $q->where('is_active', true)->whereIn('code', array_keys(config('couriers.drivers'))))
            ->where(fn ($q) => $q->whereNotNull('consignment_id')->orWhereNotNull('tracking'));
        if ($code = $this->option('parcel')) $q->where('code', $code);
        else $q->where(fn ($q) => $q->whereNull('last_tracked_at')->orWhere('last_tracked_at', '<', now()->subMinutes((int) config('couriers.sync_after_minutes', 120))))
            ->where(fn ($q) => $q->whereNull('status_updated_at')->orWhere('status_updated_at', '<', now()->subMinutes((int) config('couriers.sync_after_minutes', 120))))
            ->orderByRaw('last_tracked_at is not null, last_tracked_at');
        $changed = 0; $failed = 0; $n = 0;
        foreach ($q->limit((int) ($this->option('limit') ?: config('couriers.sync_batch', 100)))->get() as $s) {
            if (! $s->courier->isConnected()) continue;
            $n++;
            try { if ($gateway->sync($s)) $changed++; }
            catch (CourierException $e) { $failed++; $s->update(['last_tracked_at' => now()]); $this->warn("{$s->code}: {$e->getMessage()}"); if ($e->reason === 'auth') break; }
        }
        $this->info("Checked $n parcels · $changed changed · $failed failed");
        return self::SUCCESS;
    }
}
