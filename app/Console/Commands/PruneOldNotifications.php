<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOldNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:prune {--days=7 : Delete notifications older than this many days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete notifications older than the given number of days (default 7)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $deleted = DB::table('notifications')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} notification(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
