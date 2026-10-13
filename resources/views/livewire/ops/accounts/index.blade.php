<div class="flex flex-col gap-3">
    <x-ops.page-header title="Money" :subtitle="'Total balance '.tk($this->accounts->sum('balance'))">
        <button wire:click="export" class="btn-secondary">Export</button>
        <button wire:click="$toggle('adding')" class="btn-primary">+ Add</button>
    </x-ops.page-header>
    <x-ops.kpis>
        <x-ops.kpi label="Total balance" :value="tk($this->accounts->sum('balance'))" class="!border-ink" />
        @foreach ($this->accounts as $a)<x-ops.kpi :label="$a->name" :value="tk($a->balance)" :tone="$a->balance < 0 ? 'bad' : 'ink'" />@endforeach
        @foreach ($this->couriers->filter(fn ($b) => $b['balance'] || $b['parcels']) as $b)<x-ops.kpi :label="$b['courier']->name.' (held)'" :value="tk($b['balance'])" tone="info" hint="with the courier" :href="route('ops.couriers.index')" />@endforeach
    </x-ops.kpis>
    @php($c = $this->cards)
    <div class="grid gap-3 md:grid-cols-3">
        <x-ops.card title="You will receive">
            <div class="text-2xl font-semibold tabular-nums text-ok">{{ tk($c['courier'] + $c['customers']) }}</div>
            <div class="mt-2 flex flex-col gap-1 text-sm">@forelse ($this->couriers->filter(fn ($b) => $b['balance']) as $b)<a href="{{ route('ops.couriers.index') }}" wire:navigate class="flex justify-between text-ink"><span class="text-ink-muted">{{ $b['courier']->name }} (COD after charges)</span><b>{{ tk($b['balance']) }}</b></a>
                @empty<a href="{{ route('ops.couriers.index') }}" wire:navigate class="flex justify-between text-ink"><span class="text-ink-muted">Couriers</span><b>{{ tk(0) }}</b></a>@endforelse<a href="{{ route('ops.customers.index', ['status' => 'due']) }}" wire:navigate class="flex justify-between text-ink"><span class="text-ink-muted">Customers due</span><b>{{ tk($c['customers']) }}</b></a></div>
        </x-ops.card>
        <x-ops.card title="You have to pay">
            <div class="text-2xl font-semibold tabular-nums text-bad">{{ tk($c['suppliers']) }}</div>
            <div class="mt-2 text-sm"><a href="{{ route('ops.suppliers.index', ['status' => 'due']) }}" wire:navigate class="flex justify-between text-ink"><span class="text-ink-muted">Suppliers due</span><b>{{ tk($c['suppliers']) }}</b></a></div>
        </x-ops.card>
        <x-ops.card title="Profit this month">
            <div @class(['text-2xl font-semibold tabular-nums', 'text-ok' => $c['profit'] >= 0, 'text-bad' => $c['profit'] < 0])>{{ tk($c['profit']) }}</div>
            <div class="mt-2 flex flex-col gap-1 text-sm"><span class="flex justify-between"><span class="text-ink-muted">Delivered revenue</span><b>{{ tk($c['revenue']) }}</b></span><span class="flex justify-between"><span class="text-ink-muted">Gross profit (− batch cost − delivery)</span><b>{{ tk($c['gross']) }}</b></span><span class="flex justify-between"><span class="text-ink-muted">Expenses</span><b class="text-bad">− {{ tk($c['expenses']) }}</b></span></div>
        </x-ops.card>
    </div>

    <section class="card">
        @if ($adding)
            <form wire:submit="add" class="grid gap-2.5 border-b border-soft bg-zebra p-3.5 sm:grid-cols-[130px_minmax(0,1fr)_130px_140px_160px_auto] sm:items-end">
                <x-ops.field label="Type"><select wire:model="tx.direction" class="input"><option value="out">Money out</option><option value="in">Money in</option></select></x-ops.field>
                <x-ops.field label="Description" required :error="$errors->first('tx.description')"><input wire:model="tx.description" class="input" placeholder="Poly bags, Facebook ads…"></x-ops.field>
                <x-ops.field label="Amount (৳)" required :error="$errors->first('tx.amount')"><input type="number" min="1" wire:model="tx.amount" class="input"></x-ops.field>
                <x-ops.field label="Account" required :error="$errors->first('tx.account_id')"><select wire:model="tx.account_id" class="input"><option value="">Choose…</option>@foreach ($this->accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></x-ops.field>
                <x-ops.field label="Category"><select wire:model="tx.category" class="input">@foreach (config('ops.transaction_categories') as $cat)<option>{{ $cat }}</option>@endforeach</select></x-ops.field>
                <div class="flex gap-1.5"><button type="button" wire:click="$set('adding', false)" class="btn-secondary">Cancel</button><button class="btn-primary">Save</button></div>
            </form>
        @endif
        <div class="border-b border-soft px-3 py-2"><x-ops.chips wire:model.live="status" :options="['all' => ['All', ''], 'in' => ['Money in', ''], 'out' => ['Money out', '']]" /></div>
        <x-ops.toolbar><x-ops.search placeholder="Search description or category…" />
            <select class="input w-auto" wire:model.live="filters.account"><option value="">All accounts</option>@foreach ($this->accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
            <select class="input w-auto" wire:model.live="filters.category"><option value="">All categories</option>@foreach (config('ops.transaction_categories') as $cat)<option>{{ $cat }}</option>@endforeach</select>
            <x-ops.date-range /></x-ops.toolbar>
        <x-ops.table min-width="720px">
            <x-slot:head><th class="th">Date</th><th class="th">Description</th><th class="th">Category</th><th class="th">Account</th><th class="th text-right">Amount</th></x-slot:head>
            @forelse ($this->rows as $t)<tr class="tr-hover"><td class="td text-ink-muted">{{ $t->date->format('d M') }}</td><td class="td">{{ $t->description }}</td><td class="td"><x-ops.badge :tone="$t->direction === 'in' ? 'ok' : 'mute'">{{ $t->category }}</x-ops.badge></td><td class="td">{{ $t->account->name }}</td><td @class(['td text-right font-semibold tabular-nums', 'text-ok' => $t->direction === 'in', 'text-bad' => $t->direction === 'out'])>{{ $t->direction === 'in' ? '+' : '−' }} {{ tk($t->amount) }}</td></tr>
            @empty <x-ops.empty colspan="5" /> @endforelse
        </x-ops.table>
        <x-ops.pagination :paginator="$this->rows" />
    </section>
</div>
