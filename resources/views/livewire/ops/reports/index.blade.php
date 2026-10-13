<div class="grid items-start gap-3 lg:grid-cols-[230px_minmax(0,1fr)]">
    <nav class="card flex max-h-[260px] flex-col gap-0.5 overflow-y-auto p-1.5 print:hidden lg:sticky lg:top-[72px] lg:max-h-[calc(100vh-90px)]">
        @foreach ($catalog as $groupName => $items)
            <span class="px-2.5 pb-0.5 pt-2 text-2xs font-semibold uppercase tracking-wide text-ink-muted">{{ $groupName }}</span>
            @foreach ($items as $k => $l)<button wire:click="$set('report','{{ $k }}')" @class(['flex items-center justify-between rounded px-2.5 py-1.5 text-left', 'bg-soft font-semibold' => $report === $k, 'text-ink-3 hover:bg-zebra' => $report !== $k])>{{ $l }}@isset($scheduled[$k])<span class="text-2xs text-brand" title="Scheduled">⏱</span>@endisset</button>@endforeach
        @endforeach
    </nav>
    <div class="flex min-w-0 flex-col gap-3">
        @php($r = $this->result)
        <x-ops.page-header :title="$title" :subtitle="\Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')">
            <button wire:click="export" class="btn-secondary print:hidden">Export CSV</button>
            <button onclick="window.print()" class="btn-secondary print:hidden">Print</button>
            <button x-data @click="$dispatch('open-modal','schedule')" class="btn-secondary print:hidden">Schedule</button>
        </x-ops.page-header>
        <section class="card flex flex-wrap items-center gap-1.5 px-3 py-2.5 print:hidden">
            @foreach (['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year'] as $k => $l)<button wire:click="$set('preset','{{ $k }}')" @class(['chip', 'chip-on' => $preset === $k])>{{ $l }}</button>@endforeach
            <x-ops.date-range />
            @if ($r['groupable'] ?? false)<select wire:model.live="group" class="input ml-auto w-auto"><option value="day">Group by day</option><option value="week">Group by week</option><option value="month">Group by month</option></select>@endif
        </section>
        @if ($r['kpis'])<x-ops.kpis>@foreach ($r['kpis'] as [$label, $value, $tone])<x-ops.kpi :label="$label" :value="$value" :tone="$tone" />@endforeach</x-ops.kpis>@endif
        <x-ops.card :title="$title" :subtitle="count($r['rows']).' rows'" flush>
            @php($cols = $r['columns'])
            <x-ops.table min-width="640px">
                <x-slot:head>@foreach ($cols as $k => [$label, $type])<th @class(['th', 'text-right' => in_array($type, ['money', 'num', 'pct'])])>{{ $label }}</th>@endforeach</x-slot:head>
                @forelse ($r['rows'] as $row)
                    <tr class="tr-hover">@foreach ($cols as $k => [$label, $type])
                        @php($v = $row[$k] ?? null)
                        <td @class(['td tabular-nums', 'text-right' => in_array($type, ['money', 'num', 'pct']), 'font-semibold' => $type === 'money', 'text-bad' => $type === 'money' && (int) $v < 0])>{{ $v === null || $v === '' ? '—' : match ($type) { 'money' => tk($v), 'num' => ops_num($v), 'pct' => $v.'%', 'date' => \Carbon\Carbon::parse($v)->format('d M Y'), default => $v } }}</td>
                    @endforeach</tr>
                @empty <x-ops.empty :colspan="count($cols)" title="No data in this range" hint="Try a wider date range." /> @endforelse
                @if (count($r['rows']) && ! ($r['nototals'] ?? false))
                    <tr class="bg-zebra font-semibold">@foreach ($cols as $k => [$label, $type])<td @class(['td tabular-nums', 'text-right' => in_array($type, ['money', 'num', 'pct'])])>@if ($loop->first) Total @elseif ($type === 'money'){{ tk(collect($r['rows'])->sum($k)) }}@elseif ($type === 'num'){{ ops_num(collect($r['rows'])->sum($k)) }}@endif</td>@endforeach</tr>
                @endif
            </x-ops.table>
        </x-ops.card>
    </div>

    <x-ops.modal name="schedule" :title="'Schedule · '.$title">
        <div class="grid gap-3 sm:grid-cols-2">
            <x-ops.field label="Frequency"><select wire:model="schedule.frequency" class="input"><option value="off">Off</option><option value="daily">Daily (8:00)</option><option value="weekly">Weekly (Saturday)</option><option value="monthly">Monthly (1st)</option></select></x-ops.field>
            <x-ops.field label="Email to" :error="$errors->first('schedule.email')"><input type="email" wire:model="schedule.email" class="input"></x-ops.field>
        </div>
        <p class="mt-2 text-xs text-ink-muted">Sent as CSV through Settings → Integrations → Email SMTP.{{ isset($scheduled[$report]['last_sent']) ? ' Last sent '.$scheduled[$report]['last_sent'].'.' : '' }}</p>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="saveSchedule" class="btn-primary">Save schedule</button></x-slot:footer>
    </x-ops.modal>
</div>
