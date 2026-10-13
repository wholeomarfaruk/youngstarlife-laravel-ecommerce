<div class="flex flex-col gap-3">
    @php($meta = ['sales_by_day' => ['Sales by day', 'Orders', 'Sales'], 'sales_by_product' => ['Sales by product', 'Units', 'Revenue'], 'courier_performance' => ['Courier performance', 'Delivered', 'Returned'], 'profit' => ['Gross profit (delivered)', 'Revenue', 'Gross profit']][$report])
    <section class="card flex flex-wrap items-center gap-2 px-3 py-2.5">
        <select wire:model.live="report" class="input w-auto">@foreach (['sales_by_day' => 'Sales by day', 'sales_by_product' => 'Sales by product', 'courier_performance' => 'Courier performance', 'profit' => 'Gross profit'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
        <x-admin.date-range /><button onclick="window.print()" class="btn-secondary ml-auto">Print</button>
    </section>
    <x-admin.card :title="$meta[0]" flush>
        <x-admin.table min-width="480px"><x-slot:head><th class="th">{{ in_array($report, ['sales_by_day', 'profit']) ? 'Date' : 'Name' }}</th><th class="th text-right">{{ $meta[1] }}</th><th class="th text-right">{{ $meta[2] }}</th></x-slot:head>
            @forelse ($this->rows as $r)<tr class="tr-hover"><td class="td">{{ $r->label }}</td><td class="td text-right tabular-nums">{{ in_array($report, ['profit']) ? tk($r->a) : number_format($r->a) }}</td><td class="td text-right font-semibold tabular-nums">{{ in_array($report, ['courier_performance']) ? number_format($r->b) : tk($r->b) }}</td></tr>
            @empty <x-admin.empty colspan="3" /> @endforelse
        </x-admin.table>
    </x-admin.card>
</div>
