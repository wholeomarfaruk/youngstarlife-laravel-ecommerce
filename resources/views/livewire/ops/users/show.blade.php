<div class="flex flex-col gap-3">
    @php($cu = $user->customer) @php($su = $user->supplier) @php($staff = $user->role === 'admin' || $user->ops_role)
    <a href="{{ route('ops.users.index') }}" wire:navigate class="self-start text-sm">← Back to users</a>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            @if ($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="" class="h-12 w-12 flex-none rounded-full object-cover">
            @else<span class="grid h-12 w-12 flex-none place-items-center rounded-full bg-soft text-xl font-semibold text-ink-3">{{ mb_substr($user->name, 0, 1) }}</span>@endif
            <div class="flex min-w-0 flex-col gap-0.5">
                <h1 class="text-2xl font-semibold tracking-tight">{{ $user->name }}</h1>
                <div class="flex flex-wrap items-center gap-1.5 text-sm text-ink-muted">
                    <span>{{ $user->phone ?: 'no phone' }} · {{ $user->email ?: 'no email' }}</span>
                    @if ($staff)<x-ops.badge tone="info">Staff · {{ $user->ops_role ?: 'Admin' }}</x-ops.badge>@endif
                    @if ($cu)<x-ops.badge tone="ok">Customer</x-ops.badge>@endif
                    @if ($su)<x-ops.badge tone="warn">Supplier</x-ops.badge>@endif
                    @unless ($user->is_active ?? true)<x-ops.badge tone="mute">Inactive</x-ops.badge>@endunless
                    @if ($this->blacklist->contains->is_live)<a href="{{ route('ops.blacklist.index', ['search' => $user->phone ?: $cu?->phone, 'status' => 'any']) }}" wire:navigate><x-ops.badge tone="bad">Blacklisted</x-ops.badge></a>@endif
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-1.5">
            @if ($user->phone)<a href="tel:{{ $user->phone }}" class="btn-secondary">Call</a><a href="https://wa.me/88{{ preg_replace('/\D/', '', $user->phone) }}" target="_blank" class="btn-secondary">WhatsApp</a>@endif
            @if ($cu)<a href="{{ route('ops.orders.drafts', ['new' => 1, 'phone' => $cu->phone, 'name' => $cu->name]) }}" wire:navigate class="btn-primary">New order</a>@endif
            @if ($su)<a href="{{ route('ops.purchases.index', ['supplier' => $su->id, 'new' => 1]) }}" wire:navigate class="btn-primary">New purchase order</a>@endif
        </div>
    </div>

    <div class="flex gap-1 overflow-x-auto border-b border-line-2 [scrollbar-width:none]">
        @foreach (['overview' => 'Overview', 'customer' => 'Customer', 'supplier' => 'Supplier', 'activity' => 'Activity'] as $k => $l)
            <button type="button" wire:click="$set('tab','{{ $k }}')" @class(['mtab', 'mtab-on' => $tab === $k])>{{ $l }}
                @if (($k === 'customer' && ! $cu) || ($k === 'supplier' && ! $su))<span class="ml-1 text-ink-faint">+</span>@endif
            </button>
        @endforeach
    </div>

    @if ($tab === 'overview')
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="flex min-w-0 flex-col gap-3">
                <x-ops.card title="Customer profile">
                    @if ($cu)
                        <x-ops.kpis>
                            <x-ops.kpi label="Orders" :value="$cu->orders_count" /><x-ops.kpi label="Total spent" :value="tk($cu->total_spent)" />
                            <x-ops.kpi label="Delivery rate" :value="$cu->delivery_rate === null ? '—' : round($cu->delivery_rate * 100).'%'" :tone="$cu->delivery_rate !== null && $cu->delivery_rate < .75 ? 'bad' : 'ok'" />
                            <x-ops.kpi label="Due" :value="tk($cu->due)" :tone="$cu->due ? 'warn' : 'ink'" />
                        </x-ops.kpis>
                        <button type="button" wire:click="$set('tab','customer')" class="btn-secondary btn-sm mt-3">Open customer profile</button>
                    @else
                        <p class="text-sm text-ink-muted">No customer profile on this account.</p>
                        <button type="button" wire:click="addProfile('customer')" class="btn-secondary btn-sm mt-2">+ Add customer profile</button>
                    @endif
                </x-ops.card>
                <x-ops.card title="Supplier profile">
                    @if ($su && ($sd = $this->supplierData))
                        <x-ops.kpis>
                            <x-ops.kpi label="Purchase orders" :value="$sd['po_count']" /><x-ops.kpi label="Purchased" :value="tk($sd['purchased'])" />
                            <x-ops.kpi label="Paid" :value="tk($sd['paid'])" tone="ok" /><x-ops.kpi label="Due" :value="tk($su->due)" :tone="$su->due ? 'warn' : 'ink'" />
                        </x-ops.kpis>
                        <button type="button" wire:click="$set('tab','supplier')" class="btn-secondary btn-sm mt-3">Open supplier profile</button>
                    @else
                        <p class="text-sm text-ink-muted">No supplier profile on this account.</p>
                        <button type="button" wire:click="addProfile('supplier')" class="btn-secondary btn-sm mt-2">+ Add supplier profile</button>
                    @endif
                </x-ops.card>
            </div>
            <x-ops.card title="Account">
                <div class="flex flex-col gap-3">
                    <x-ops.field label="Name" required :error="$errors->first('a.name')"><input wire:model="a.name" class="input"></x-ops.field>
                    <x-ops.field label="Phone" :required="(bool) $cu" :error="$errors->first('a.phone')"><input wire:model="a.phone" inputmode="tel" class="input"></x-ops.field>
                    <x-ops.field label="Email" :error="$errors->first('a.email')"><input wire:model="a.email" type="email" class="input"></x-ops.field>
                    <dl class="grid grid-cols-[90px_1fr] gap-y-1 text-sm">
                        <dt class="text-ink-muted">Account</dt><dd>{{ $staff ? 'Staff · '.($user->ops_role ?: 'Admin') : ucfirst($user->role) }}</dd>
                        @if ($staff)<dt class="text-ink-muted">Warehouse</dt><dd>{{ $user->warehouse?->name ?? '—' }}</dd><dt class="text-ink-muted">Last seen</dt><dd>{{ $user->last_seen_at?->diffForHumans() ?? '—' }}</dd>
                            <dt class="text-ink-muted">Two-factor</dt><dd class="flex items-center gap-1.5">{{ $user->hasTwoFactor() ? 'On' : 'Off' }}
                                @if ($user->hasTwoFactor() && $user->isNot(auth()->user()) && (auth()->user()->role === 'admin' || auth()->user()->ops_role === 'Admin'))<button type="button" wire:click="resetTwoFactor" wire:confirm="Turn off two-factor for {{ $user->name }}? Use this when they lost their phone and recovery codes." class="btn-ghost btn-sm text-bad">Reset</button>@endif</dd>
                            @php($lastLogin = \App\Models\Ops\LoginEvent::where('user_id', $user->id)->where('event', 'login')->latest('created_at')->first())
                            <dt class="text-ink-muted">Last sign-in</dt><dd>{{ $lastLogin ? $lastLogin->created_at->diffForHumans().' · '.$lastLogin->device : '—' }}</dd>@endif
                        <dt class="text-ink-muted">Joined</dt><dd>{{ $user->created_at?->format('d M Y') ?? '—' }}</dd>
                    </dl>
                    <p class="text-xs text-ink-muted">Name and phone changes also update the linked customer and supplier profiles.@if ($staff) Role and access are managed in <a href="{{ route('ops.settings.index', ['tab' => 'users']) }}" wire:navigate>Settings → Users & roles</a>.@endif</p>
                    <button wire:click="saveAccount" class="btn-primary self-start">Save account</button>
                </div>
            </x-ops.card>
        </div>

    @elseif ($tab === 'customer')
        @if (! $cu)
            <x-ops.card><div class="flex flex-col items-center gap-2 py-6 text-center"><span class="font-semibold">No customer profile</span><span class="text-sm text-ink-muted">Adds a customer profile on this account using its name and phone.</span><button wire:click="addProfile('customer')" class="btn-primary btn-sm">+ Add customer profile</button></div></x-ops.card>
        @else
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="flex min-w-0 flex-col gap-3">
                    <x-ops.kpis>
                        <x-ops.kpi label="Orders" :value="$cu->orders_count" /><x-ops.kpi label="Delivered" :value="$cu->delivered_count" tone="ok" /><x-ops.kpi label="Returned" :value="$cu->returned_count" tone="bad" />
                        <x-ops.kpi label="Total spent" :value="tk($cu->total_spent)" /><x-ops.kpi label="Due" :value="tk($cu->due)" :tone="$cu->due ? 'warn' : 'ink'" />
                    </x-ops.kpis>
                    <x-ops.card title="Recent orders" flush>
                        <x-slot:actions><a href="{{ route('ops.customers.index', ['search' => $cu->phone]) }}" wire:navigate class="btn-secondary btn-sm">Ledger & fraud check</a></x-slot:actions>
                        @forelse ($this->customerData['orders'] as $o)<a href="{{ route('ops.orders.show', $o) }}" wire:navigate class="flex items-center gap-2 border-t border-soft px-3 py-1.5 text-sm text-ink first:border-0 hover:bg-soft"><span class="mono font-semibold">{{ $o->number }}</span><span class="text-ink-muted">{{ $o->placed_at->format('d M Y') }}</span><span class="ml-auto font-semibold">{{ tk($o->total) }}</span><x-ops.badge map="order_status" :status="$o->status" /></a>
                        @empty<div class="p-3 text-ink-muted">No orders yet.</div>@endforelse
                    </x-ops.card>
                </div>
                <x-ops.card title="Customer details">
                    <div class="flex flex-col gap-3">
                        <div class="flex flex-wrap gap-1">@foreach ($cu->segments as $sg)<x-ops.badge :tone="config('ops.segments.'.$sg.'.1')">{{ config('ops.segments.'.$sg.'.0') }}</x-ops.badge>@endforeach @foreach ($cu->tags ?? [] as $t)<span class="chip py-0 text-xs">{{ $t }}</span>@endforeach @if ($cu->is_blocked)<x-ops.badge tone="bad">Blocked</x-ops.badge>@endif</div>
                        <x-ops.field label="Area"><input wire:model="c.area" class="input"></x-ops.field>
                        <x-ops.field label="Address"><input wire:model="c.address" class="input"></x-ops.field>
                        <x-ops.field label="Source" :error="$errors->first('c.source')"><select wire:model="c.source" class="input"><option value="">—</option>@foreach (config('ops.sources') as $key => $l)<option value="{{ $key }}">{{ $l }}</option>@endforeach</select></x-ops.field>
                        <x-ops.field label="Note"><textarea wire:model="c.note" rows="3" class="input"></textarea></x-ops.field>
                        <div class="flex gap-1.5"><button wire:click="saveCustomer" class="btn-primary">Save customer</button><button wire:click="toggleBlock" wire:confirm="{{ $cu->is_blocked ? 'Remove '.$cu->name.' from the blacklist (phone + devices)?' : 'Blacklist '.$cu->name.' — phone and every website device they ordered from?' }}" class="btn-danger">{{ $cu->is_blocked ? 'Unblock' : 'Block' }}</button></div>
                    </div>
                </x-ops.card>
                <x-ops.card title="Blacklist" flush class="lg:col-start-2">
                    <x-slot:actions><a href="{{ route('ops.blacklist.index', ['phone' => $cu->phone]) }}" wire:navigate class="btn-secondary btn-sm">+ Add</a></x-slot:actions>
                    @forelse ($this->blacklist as $b)
                        <a href="{{ route('ops.blacklist.index', ['search' => $b->value, 'status' => 'any']) }}" wire:navigate class="flex items-center gap-2 border-t border-soft px-3 py-1.5 text-sm text-ink first:border-0 hover:bg-soft">
                            <span class="chip py-0 text-xs">{{ config('ops.blacklist_types.'.$b->type) }}</span><span class="mono flex-1 truncate">{{ $b->type === 'device' ? '#'.$b->value : $b->value }}</span>
                            @if ($b->is_live)<x-ops.badge map="blacklist_actions" :status="$b->action" />@else<x-ops.badge map="blacklist_state" :status="$b->state" />@endif
                            <span class="text-xs text-ink-muted">{{ $b->hits }} tries</span></a>
                    @empty<div class="p-3 text-sm text-ink-muted">Not on the blacklist.</div>@endforelse
                </x-ops.card>
            </div>
        @endif

    @elseif ($tab === 'supplier')
        @if (! $su)
            <x-ops.card><div class="flex flex-col items-center gap-2 py-6 text-center"><span class="font-semibold">No supplier profile</span><span class="text-sm text-ink-muted">Adds a supplier profile on this account so it can be picked on purchase orders.</span><button wire:click="addProfile('supplier')" class="btn-primary btn-sm">+ Add supplier profile</button></div></x-ops.card>
        @else
            @php($sd = $this->supplierData)
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="flex min-w-0 flex-col gap-3">
                    <x-ops.kpis>
                        <x-ops.kpi label="Purchased" :value="tk($sd['purchased'])" hint="good units × cost" /><x-ops.kpi label="Paid" :value="tk($sd['paid'])" tone="ok" />
                        <x-ops.kpi label="Due" :value="tk($su->due)" :tone="$su->due ? 'warn' : 'ink'" /><x-ops.kpi label="Credit" :value="tk($su->credit)" tone="ok" />
                        <x-ops.kpi label="Stock from supplier" :value="ops_num($sd['stock'])" /><x-ops.kpi label="Open returns" :value="$sd['open_returns']" :tone="$sd['open_returns'] ? 'bad' : 'ink'" />
                    </x-ops.kpis>
                    <x-ops.card title="Recent purchase orders" flush>
                        <x-slot:actions><a href="{{ route('ops.suppliers.index', ['search' => $su->name]) }}" wire:navigate class="btn-secondary btn-sm">Ledger & payments</a></x-slot:actions>
                        @forelse ($sd['pos'] as $po)<a href="{{ route('ops.purchases.edit', $po) }}" wire:navigate class="flex items-center gap-2 border-t border-soft px-3 py-1.5 text-sm text-ink first:border-0 hover:bg-soft"><span class="mono font-semibold">{{ $po->number }}</span><span class="text-ink-muted">{{ $po->ordered_at?->format('d M Y') }}</span><span class="ml-auto font-semibold">{{ tk($po->total) }}</span><x-ops.badge map="po_status" :status="$po->status" /></a>
                        @empty<div class="p-3 text-ink-muted">No purchase orders yet.</div>@endforelse
                    </x-ops.card>
                </div>
                <x-ops.card title="Supplier details">
                    <div class="flex flex-col gap-3">
                        <x-ops.field label="Contact person"><input wire:model="s.contact_name" class="input"></x-ops.field>
                        <x-ops.field label="Address"><input wire:model="s.address" class="input"></x-ops.field>
                        <x-ops.field label="Categories" hint="e.g. Cargo pant, Polo"><input wire:model="s.categories" class="input"></x-ops.field>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="s.is_active" class="accent-brand"> Active (shown when creating purchase orders)</label>
                        <button wire:click="saveSupplier" class="btn-primary self-start">Save supplier</button>
                    </div>
                </x-ops.card>
            </div>
        @endif

    @else
        <x-ops.card title="Activity" subtitle="Everything logged on this account and its profiles" flush>
            @forelse ($this->activity as $l)<div class="flex items-center gap-2 border-t border-soft px-3 py-1.5 text-sm first:border-0"><span class="w-28 flex-none text-ink-muted">{{ $l->created_at->format('d M Y H:i') }}</span><span class="flex-1">{{ $l->message }}</span><span class="text-xs text-ink-muted">{{ $l->user?->name ?? 'System' }}</span></div>
            @empty<div class="p-3 text-ink-muted">No activity yet.</div>@endforelse
        </x-ops.card>
    @endif

    @if ($flash = session('toast'))<div x-data x-init="setTimeout(() => $dispatch('toast', { message: @js($flash) }))"></div>@endif
</div>
