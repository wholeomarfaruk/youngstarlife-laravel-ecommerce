<?php

namespace App\Console\Commands;

use App\Services\Ops\LegacyImporter;
use Illuminate\Console\Command;

/** Moves the old website data into the Ops system (see LegacyImporter). Safe to run again: only new old-rows are copied. */
class OpsImportLegacy extends Command
{
    protected $signature = 'ops:import-legacy {--wipe-demo : first remove the demo data OpsDemoSeeder created (only before the first import)} {--force : no confirmation prompts}';
    protected $description = 'Import products, categories, customers, orders and more from the old website tables into Ops';

    public function handle(LegacyImporter $importer): int
    {
        if ($this->option('wipe-demo') && ! $this->option('force')
            && ! $this->confirm('Delete ALL demo orders, customers, products, suppliers, purchases, batches, returns and transactions from Ops? Settings, couriers, warehouses, accounts, zones and staff stay.')) {
            return self::FAILURE;
        }
        try {
            $report = $importer->run((bool) $this->option('wipe-demo'), fn ($line) => $this->line($line));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->newLine();
        $this->table(['Imported', 'Count'], collect($report)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->info('Done. Old tables were not changed; run this again any time to bring over new old-website rows.');
        return self::SUCCESS;
    }
}
