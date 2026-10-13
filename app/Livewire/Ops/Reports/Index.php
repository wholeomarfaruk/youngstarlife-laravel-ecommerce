<?php

namespace App\Livewire\Ops\Reports;

use App\Models\Ops\Setting;
use App\Services\Ops\Reports;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Reports', 'title' => 'Reports'])]
#[Title('Reports')]
class Index extends Component
{
    #[Url] public string $report = 'sales_summary';
    #[Url] public string $from = ''; #[Url] public string $to = '';
    #[Url(except: '30d')] public string $preset = '30d';      // today | 7d | 30d | month | last_month | year | custom
    #[Url(except: 'day')] public string $group = 'day';       // day | week | month (groupable reports)
    public array $schedule = ['frequency' => 'weekly', 'email' => ''];

    public function mount(): void
    {
        if (! in_array($this->report, collect(Reports::catalog())->flatMap(fn ($g) => array_keys($g))->all())) $this->report = 'sales_summary';
        if (! $this->from) $this->updatedPreset($this->preset);
        $this->loadSchedule();
    }

    public function updatedPreset(string $p): void
    {
        [$this->from, $this->to] = match ($p) {
            'today' => [today(), today()], '7d' => [today()->subDays(6), today()], 'month' => [now()->startOfMonth(), today()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()], 'year' => [now()->startOfYear(), today()],
            default => [today()->subDays(29), today()],
        };
        $this->from = \Illuminate\Support\Carbon::parse($this->from)->toDateString(); $this->to = \Illuminate\Support\Carbon::parse($this->to)->toDateString();
    }
    public function updatedFrom(): void { $this->preset = 'custom'; }
    public function updatedTo(): void { $this->preset = 'custom'; }
    public function updatedReport(): void { $this->loadSchedule(); }

    protected function loadSchedule(): void { $this->schedule = array_merge(['frequency' => 'weekly', 'email' => auth()->user()->email], Setting::get('report_schedules', [])[$this->report] ?? []); }

    #[Computed] public function result(): array { return app(Reports::class)->run($this->report, $this->from ?: today()->toDateString(), $this->to ?: today()->toDateString(), $this->group); }

    public function export()
    {
        $csv = app(Reports::class)->csv($this->result);
        return response()->streamDownload(fn () => print($csv), $this->report.'-'.$this->from.'-'.$this->to.'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Schedule: frequency + email; sent by `php artisan ops:send-reports` (scheduled daily) through the SMTP settings */
    public function saveSchedule(): void
    {
        $this->validate(['schedule.frequency' => 'required|in:off,daily,weekly,monthly', 'schedule.email' => 'required_unless:schedule.frequency,off|nullable|email']);
        $all = Setting::get('report_schedules', []);
        if ($this->schedule['frequency'] === 'off') unset($all[$this->report]); else $all[$this->report] = $this->schedule + ['last_sent' => $all[$this->report]['last_sent'] ?? null];
        Setting::put('report_schedules', $all);
        ops_log('Report schedule '.Reports::title($this->report).' → '.$this->schedule['frequency']);
        $this->dispatch('close-modal'); $this->dispatch('toast', message: $this->schedule['frequency'] === 'off' ? 'Schedule removed' : 'Scheduled '.$this->schedule['frequency'].' to '.$this->schedule['email']);
    }

    public function render() { return view('livewire.ops.reports.index', ['catalog' => Reports::catalog(), 'title' => Reports::title($this->report), 'scheduled' => Setting::get('report_schedules', [])]); }
}
