<?php

namespace App\Console\Commands;

use App\Models\Ops\Setting;
use App\Services\Ops\{Mailer, Reports};
use Illuminate\Console\Command;

/** Ops → Reports → Schedule: emails each due report as CSV (daily 8:00 · weekly Saturday · monthly 1st). */
class OpsSendReports extends Command
{
    protected $signature = 'ops:send-reports {--force : send every scheduled report now}';
    protected $description = 'Email scheduled Ops reports';

    public function handle(Reports $reports, Mailer $mailer): int
    {
        $all = Setting::get('report_schedules', []); $sent = 0;
        foreach ($all as $key => $s) {
            $due = $this->option('force') || match ($s['frequency'] ?? 'off') { 'daily' => true, 'weekly' => now()->isSaturday(), 'monthly' => now()->day === 1, default => false };
            if (! $due || empty($s['email'])) continue;
            [$from, $to] = match ($s['frequency']) { 'daily' => [today()->subDay(), today()->subDay()], 'monthly' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()], default => [today()->subDays(7), today()->subDay()] };
            $r = $reports->run($key, $from->toDateString(), $to->toDateString());
            $title = Reports::title($key);
            try {
                $mailer->send($s['email'], $title.' · '.$from->format('d M').' – '.$to->format('d M Y'), "Attached: $title (".count($r['rows'])." rows).\n\n— YoungStar Ops", [$key.'.csv' => $reports->csv($r)]);
                $all[$key]['last_sent'] = now()->format('d M Y H:i'); $sent++;
                $this->info("Sent $title to {$s['email']}");
            } catch (\Throwable $e) { $this->error("$title: ".$e->getMessage()); report($e); }
        }
        Setting::put('report_schedules', $all);
        $this->info("$sent report(s) sent");
        return self::SUCCESS;
    }
}
