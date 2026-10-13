@props(['group'])
@php($counts = [
    'ops.orders.drafts' => fn () => cache()->remember('drafts.count', 60, fn () => \App\Models\Ops\Order::drafts()->count()),
    'ops.users.index' => fn () => cache()->remember('users.count', 60, fn () => \App\Models\User::count()),
    'ops.customers.index' => fn () => cache()->remember('customers.count', 60, fn () => \App\Models\Ops\Customer::real()->count()),
    'ops.blacklist.index' => fn () => cache()->remember('blacklist.count', 60, fn () => \App\Models\Ops\BlacklistEntry::active()->count()),
    'ops.suppliers.index' => fn () => cache()->remember('suppliers.count', 60, fn () => \App\Models\Ops\Supplier::count()),
])
<div class="-mt-1 flex gap-1 overflow-x-auto border-b border-line-2 [scrollbar-width:none] max-sm:-mx-2.5 max-sm:px-2.5">
    @foreach (config("ops.tabs.$group") as [$label, $route])
        <a href="{{ route($route) }}" wire:navigate @class(['mtab', 'mtab-on' => request()->routeIs($route)])>
            {{ $label }}
            @isset($counts[$route]) <span class="ml-1 rounded-full bg-soft px-1.5 text-xs text-ink-3">{{ ops_num($counts[$route]()) }}</span> @endisset
        </a>
    @endforeach
</div>
