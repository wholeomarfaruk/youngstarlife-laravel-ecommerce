<div class="flex flex-col gap-3">
    <x-admin.page-header title="Money" :subtitle="'Total '.tk($this->accounts->sum('balance'))"><button x-data @click="$dispatch('open-modal','tx')" class="btn-primary">+ Add transaction</button></x-admin.page-header>
    <x-admin.kpis>@foreach ($this->accounts as $a)<x-admin.kpi :label="$a->name" :value="tk($a->balance)" />@endforeach</x-admin.kpis>
    <section class="card">
        <x-admin.toolbar><x-admin.chips wire:model.live="status" :options="['all' => ['All', ''], 'in' => ['Money in', ''], 'out' => ['Money out', '']]" /><x-admin.search placeholder="Search description…" /><x-admin.date-range /></x-admin.toolbar>
        <x-admin.table min-width="720px">
            <x-slot:head><th class="th">Date</th><th class="th">Description</th><th class="th">Category</th><th class="th">Account</th><th class="th text-right">Amount</th></x-slot:head>
            @forelse ($this->rows as $t)<tr class="tr-hover"><td class="td text-ink-muted">{{ $t->date->format('d M') }}</td><td class="td">{{ $t->description }}</td><td class="td"><x-admin.badge :tone="$t->direction === 'in' ? 'ok' : 'mute'">{{ $t->category }}</x-admin.badge></td><td class="td">{{ $t->account->name }}</td><td @class(['td text-right font-semibold tabular-nums', 'text-ok' => $t->direction === 'in', 'text-bad' => $t->direction === 'out'])>{{ $t->direction === 'in' ? '+' : '−' }} {{ tk($t->amount) }}</td></tr>
            @empty <x-admin.empty colspan="5" /> @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$this->rows" />
    </section>
    <x-admin.modal name="tx" title="Add transaction">
        <div class="grid gap-3 sm:grid-cols-2">
            <x-admin.field label="Type"><select wire:model="tx.direction" class="input"><option value="out">Money out</option><option value="in">Money in</option></select></x-admin.field>
            <x-admin.field label="Amount (৳)" required :error="$errors->first('tx.amount')"><input type="number" wire:model="tx.amount" class="input"></x-admin.field>
            <x-admin.field label="Account" required :error="$errors->first('tx.account_id')"><select wire:model="tx.account_id" class="input"><option value="">Choose…</option>@foreach ($this->accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></x-admin.field>
            <x-admin.field label="Category"><select wire:model="tx.category" class="input">@foreach (['Packaging', 'Marketing', 'Courier', 'Salary', 'Supplier', 'Sales', 'Other'] as $c)<option>{{ $c }}</option>@endforeach</select></x-admin.field>
            <x-admin.field label="Note" required class="sm:col-span-2" :error="$errors->first('tx.description')"><input wire:model="tx.description" class="input"></x-admin.field>
        </div>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="add" class="btn-primary">Save</button></x-slot:footer>
    </x-admin.modal>
</div>
