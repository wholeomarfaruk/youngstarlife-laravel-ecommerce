<div class="flex flex-col gap-3">
    <x-admin.page-header :title="'Good morning, '.str(auth()->user()->name)->before(' ')" :subtitle="now()->format('l, j F Y').' · '.config('app.name')">
        <div class="flex gap-0.5 rounded-md bg-soft p-0.5">
            @foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days'] as $k => $l)
                <button wire:click="$set('range','{{ $k }}')" @class(['rounded px-3 py-1', 'bg-white font-semibold shadow-seg' => $range === $k, 'text-ink-muted' => $range !== $k])>{{ $l }}</button>
            @endforeach
        </div>
        <a href="{{ route('admin.orders.drafts') }}" wire:navigate class="btn-primary">New order</a>
        <a href="{{ route('admin.purchases.index') }}" wire:navigate class="btn-secondary">Stock in</a>
        <a href="{{ route('admin.returns.receive') }}" wire:navigate class="btn-secondary">Receive returns</a>
        <a href="{{ route('admin.packing.scan') }}" wire:navigate class="btn-secondary">Pack orders</a>
    </x-admin.page-header>

    @php($k = $this->kpis)
    <div class="grid grid-cols-[repeat(auto-fit,minmax(210px,1fr))] gap-3">
        <x-admin.kpi label="Total sales" :value="tk($k['sales'])" hint="booked in range" :href="route('admin.accounts.index')" class="!p-4 [&>span:nth-child(2)]:text-3xl" />
        <x-admin.kpi label="Orders" :value="number_format($k['orders'])" hint="all channels" :href="route('admin.orders.index')" class="!p-4 [&>span:nth-child(2)]:text-3xl" />
        <x-admin.kpi label="Delivered revenue" :value="tk($k['revenue'])" hint="COD collected by couriers" tone="ok" :href="route('admin.orders.index', ['status' => 'delivered'])" class="!p-4 [&>span:nth-child(2)]:text-3xl" />
        <x-admin.kpi label="Avg order value" :value="tk($k['aov'])" hint="per order" class="!p-4 [&>span:nth-child(2)]:text-3xl" />
    </div>
    <x-admin.kpis>
        <x-admin.kpi label="COD in courier" :value="tk($k['cod_in_courier'])" hint="not settled yet" tone="warn" :href="route('admin.orders.in-courier')" />
        <x-admin.kpi label="Supplier due" :value="tk($k['supplier_due'])" tone="warn" :href="route('admin.suppliers.index', ['status' => 'due'])" />
        <x-admin.kpi label="Customer due" :value="tk($k['customer_due'])" tone="warn" :href="route('admin.customers.index', ['status' => 'due'])" />
        <x-admin.kpi label="Supplier returns open" :value="$k['pending_supplier_returns']" tone="bad" :href="route('admin.supplier-returns.index')" />
    </x-admin.kpis>

    <div class="flex flex-wrap gap-3">
        <x-admin.card title="Sales · last 14 days" class="flex-[2_1_480px]">
            @php($max = max(1, collect($this->chart)->max(fn ($d) => $d->delivered + $d->booked)))
            <div class="flex h-[180px] items-end gap-1.5 border-b border-soft">
                @foreach ($this->chart as $d)
                    <div class="flex h-full flex-1 flex-col justify-end gap-px" title="{{ $d->d }} · {{ tk($d->delivered) }}">
                        <span class="rounded-t bg-brand-pale" style="height: {{ $d->booked / $max * 100 }}%"></span>
                        <span class="bg-brand" style="height: {{ $d->delivered / $max * 100 }}%"></span>
                    </div>
                @endforeach
            </div>
        </x-admin.card>
        <x-admin.card title="Recent orders" flush class="flex-[1_1_300px]">
            <x-slot:actions><a href="{{ route('admin.orders.index') }}" wire:navigate class="text-sm">View all →</a></x-slot:actions>
            @foreach ($this->recent as $o)
                <a href="{{ route('admin.orders.show', $o) }}" wire:navigate class="flex items-center gap-2.5 border-t border-soft px-3.5 py-2 first:border-0 hover:bg-zebra">
                    <span class="mono w-14 font-semibold text-brand">{{ $o->number }}</span>
                    <span class="min-w-0 flex-1 truncate text-ink">{{ $o->customer->name }}</span>
                    <span class="font-semibold tabular-nums text-ink">{{ tk($o->total) }}</span>
                    <x-admin.badge map="order_status" :status="$o->status" />
                </a>
            @endforeach
        </x-admin.card>
    </div>

    @foreach (['Order status' => ['order_status', $this->orderStatus, 'admin.orders.index'], 'Courier status' => ['courier_status', $this->courierStatus, 'admin.orders.in-courier']] as $title => [$map, $counts, $route])
        <x-admin.card :title="$title" flush>
            <div class="grid grid-cols-[repeat(auto-fill,minmax(120px,1fr))]">
                @foreach (config("admin.$map") as $code => [$label, $tone])
                    <a href="{{ route($route, ['status' => $code]) }}" wire:navigate class="flex flex-col gap-1 border-b border-r border-soft px-3 py-2.5 hover:bg-zebra">
                        <span class="text-sm text-ink">{{ $label }}</span>
                        <span class="text-2xl font-semibold tabular-nums text-{{ $tone }}">{{ $counts[$code] ?? 0 }}</span>
                        <span class="mono break-all text-2xs text-ink-muted">{{ $code }}</span>
                    </a>
                @endforeach
            </div>
        </x-admin.card>
    @endforeach

    <x-admin.card title="Low stock" flush>
        @forelse ($this->lowStock as $v)
            <div class="flex items-center justify-between border-t border-soft px-3.5 py-2 first:border-0"><span>{{ $v->label }} <span class="mono text-xs text-ink-muted">{{ $v->sku }}</span></span><span class="font-semibold text-bad">{{ $v->stock }} left</span></div>
        @empty <div class="px-3.5 py-4 text-ink-muted">Nothing below threshold.</div> @endforelse
    </x-admin.card>
</div>
