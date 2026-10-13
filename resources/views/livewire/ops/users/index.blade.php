<div class="flex flex-col gap-3">
    @php($k = $this->kpis)
    <x-ops.kpis>
        <x-ops.kpi label="Users" :value="ops_num($k['users'])" /><x-ops.kpi label="New this month" :value="$k['new']" tone="info" />
        <x-ops.kpi label="Customer profiles" :value="ops_num($k['customers'])" :href="route('ops.customers.index')" /><x-ops.kpi label="Supplier profiles" :value="$k['suppliers']" :href="route('ops.suppliers.index')" />
        <x-ops.kpi label="Staff" :value="$k['staff']" hint="Settings → Users & roles" />
    </x-ops.kpis>
    <section class="card">
        @php($c = $this->counts)
        <div class="border-b border-soft px-3 py-2"><x-ops.chips wire:model.live="status" :options="['all' => ['All', $c['all']], 'customers' => ['Customers', $c['customers']], 'suppliers' => ['Suppliers', $c['suppliers']], 'staff' => ['Staff', $c['staff']], 'blocked' => ['Blacklisted', $c['blocked']], 'none' => ['No profile', $c['none']]]" /></div>
        <x-ops.toolbar>
            <x-ops.search placeholder="Name, email, phone…" />
            <select class="input w-auto" wire:model.live="sort"><option value="new">Newest</option><option value="name">Name A–Z</option></select>
            <span class="ml-auto text-sm text-ink-muted">{{ ops_num($this->rows->total()) }} users</span>
            <button x-data @click="$wire.edit(); $dispatch('open-modal', 'user')" class="btn-primary">+ Add user</button>
        </x-ops.toolbar>
        <x-ops.table min-width="980px">
            <x-slot:head><th class="th">User</th><th class="th">Profiles</th><th class="th">Customer</th><th class="th">Supplier</th><th class="th">Joined</th><th class="th"></th></x-slot:head>
            @forelse ($this->rows as $r)
                <tr wire:key="r{{ $r->id }}" class="tr-hover">
                    <td class="td"><div class="flex items-center gap-2.5"><span class="grid h-8 w-8 flex-none place-items-center rounded-full bg-soft text-sm font-semibold text-ink-3">{{ mb_substr($r->name, 0, 1) }}</span><div><div class="font-semibold"><a href="{{ route('ops.users.show', $r) }}" wire:navigate class="text-ink hover:underline">{{ $r->name }}</a> @unless ($r->is_active ?? true)<x-ops.badge tone="mute">Inactive</x-ops.badge>@endunless</div><div class="text-xs text-ink-muted">{{ $r->phone ?: '—' }} · {{ $r->email ?: 'no email' }}</div></div></div></td>
                    <td class="td"><div class="flex flex-wrap gap-1">
                        @if ($r->role === 'admin' || $r->ops_role)<x-ops.badge tone="info">Staff · {{ $r->ops_role ?: 'Admin' }}</x-ops.badge>@endif
                        @if ($r->customer)<x-ops.badge tone="ok">Customer</x-ops.badge>@endif
                        @if ($r->supplier)<x-ops.badge tone="warn">Supplier</x-ops.badge>@endif
                        @if (! $r->customer && ! $r->supplier && $r->role !== 'admin' && ! $r->ops_role)<span class="text-sm text-ink-muted">—</span>@endif
                    </div></td>
                    <td class="td text-sm">@if ($cu = $r->customer)<a href="{{ route('ops.users.show', [$r, 'tab' => 'customer']) }}" wire:navigate>{{ $cu->orders_count }} orders · {{ tk($cu->total_spent) }}</a> @if ($cu->is_blocked)<x-ops.badge tone="bad">Blocked</x-ops.badge>@endif @else<span class="text-ink-muted">—</span>@endif</td>
                    <td class="td text-sm">@if ($su = $r->supplier)<a href="{{ route('ops.users.show', [$r, 'tab' => 'supplier']) }}" wire:navigate>{{ $su->due ? 'Due '.tk($su->due) : 'No due' }}</a> @unless ($su->is_active)<x-ops.badge tone="mute">Inactive</x-ops.badge>@endunless @else<span class="text-ink-muted">—</span>@endif</td>
                    <td class="td text-sm text-ink-muted">{{ $r->created_at?->format('d M Y') ?? '—' }}</td>
                    <td class="td"><x-ops.row-menu>
                        <a href="{{ route('ops.users.show', $r) }}" wire:navigate class="flex items-center rounded px-2 py-1.5 text-ink hover:bg-soft">Open profile</a>
                        @if ($r->customer)<a href="{{ route('ops.users.show', [$r, 'tab' => 'customer']) }}" wire:navigate class="flex items-center rounded px-2 py-1.5 text-ink hover:bg-soft">Customer profile</a>
                        @else<button type="button" wire:click="addProfile({{ $r->id }}, 'customer')" class="flex items-center rounded px-2 py-1.5 text-left text-ink hover:bg-soft">+ Customer profile</button>@endif
                        @if ($r->supplier)<a href="{{ route('ops.users.show', [$r, 'tab' => 'supplier']) }}" wire:navigate class="flex items-center rounded px-2 py-1.5 text-ink hover:bg-soft">Supplier profile</a>
                        @else<button type="button" wire:click="addProfile({{ $r->id }}, 'supplier')" class="flex items-center rounded px-2 py-1.5 text-left text-ink hover:bg-soft">+ Supplier profile</button>@endif
                    </x-ops.row-menu></td>
                </tr>
            @empty <x-ops.empty colspan="6"><button wire:click="clearFilters" class="btn-secondary btn-sm">Clear filters</button></x-ops.empty> @endforelse
        </x-ops.table>
        <x-ops.pagination :paginator="$this->rows" />
    </section>

    <x-ops.modal name="user" title="Add user">
        <div class="grid gap-3 sm:grid-cols-2">
                <p class="text-sm text-ink-muted sm:col-span-2">Creates the user account first, then the customer or supplier profile linked to it.</p>
            <x-ops.field label="Name" required class="sm:col-span-2" :error="$errors->first('u.name')"><input wire:model="u.name" class="input"></x-ops.field>
            <x-ops.field label="Phone" :required="$u['profile'] === 'customer'" :error="$errors->first('u.phone')"><input wire:model="u.phone" inputmode="tel" class="input" placeholder="01XXXXXXXXX"></x-ops.field>
            <x-ops.field label="Email" :error="$errors->first('u.email')"><input wire:model="u.email" type="email" class="input"></x-ops.field>
                <div class="label sm:col-span-2"><span>Profile <span class="text-bad">*</span></span>
                    <div class="flex gap-1.5">
                        @foreach (['customer' => 'Customer', 'supplier' => 'Supplier'] as $key => $l)
                            <button type="button" wire:click="$set('u.profile', '{{ $key }}')" @class(['chip', 'chip-on' => $u['profile'] === $key])>{{ $l }}</button>
                        @endforeach
                    </div>
                </div>
                @if ($u['profile'] === 'customer')
                    <x-ops.field label="Area"><input wire:model="u.area" class="input" placeholder="Mirpur, Dhanmondi…"></x-ops.field>
                    <x-ops.field label="Source" :error="$errors->first('u.source')"><select wire:model="u.source" class="input"><option value="">—</option>@foreach (config('ops.sources') as $key => $l)<option value="{{ $key }}">{{ $l }}</option>@endforeach</select></x-ops.field>
                @else
                    <x-ops.field label="Contact person"><input wire:model="u.contact_name" class="input"></x-ops.field>
                    <x-ops.field label="Categories" hint="e.g. Cargo pant, Polo"><input wire:model="u.categories" class="input"></x-ops.field>
                @endif
                <x-ops.field label="Address" class="sm:col-span-2"><input wire:model="u.address" class="input"></x-ops.field>
        </div>
        <x-slot:footer><button x-on:click="$dispatch('close-modal')" class="btn-secondary">Cancel</button><button wire:click="save" class="btn-primary">Create user & {{ $u['profile'] }}</button></x-slot:footer>
    </x-ops.modal>
    @if ($autoOpen)<div x-data x-init="setTimeout(() => $dispatch('open-modal', 'user'))"></div>@endif
</div>
