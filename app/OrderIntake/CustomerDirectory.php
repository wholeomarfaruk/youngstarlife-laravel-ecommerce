<?php

namespace App\OrderIntake;

use App\Models\Ops\{BlacklistEntry, Customer, Order};

/**
 * Known customers for phones found in an intake (Ops customers): name, last address, order history and a
 * recent order (possible duplicate) — shown next to each extracted order.
 */
final class CustomerDirectory
{
    /**
     * @param  list<string>  $phones  national numbers — keys of the result
     * @return array<string, array<string, mixed>>
     */
    public static function lookup(array $phones): array
    {
        $byNational = collect($phones)->filter(fn ($p) => is_string($p) && trim($p) !== '')->take(500)
            ->mapWithKeys(fn ($p) => [$p => PhoneExtractor::national($p)])->filter(fn ($n) => strlen((string) $n) >= 6);
        if ($byNational->isEmpty()) return [];

        // Ops stores 01XXXXXXXXX; match both forms
        $local = $byNational->map(fn ($n) => PhoneExtractor::local($n))->unique()->values();
        $customers = Customer::whereIn('phone', $local->merge($byNational->values())->unique()->all())->get()
            ->keyBy(fn ($c) => PhoneExtractor::national($c->phone));
        $recent = Order::live()->whereIn('customer_id', $customers->pluck('id'))->where('placed_at', '>=', now()->subDay())
            ->where('status', '!=', 'cancelled')->orderByDesc('id')->get(['id', 'number', 'customer_id', 'placed_at'])->groupBy('customer_id');

        // Users → Blacklist: live phone entries ("block" wins over "flag")
        $blacklist = BlacklistEntry::active()->where('type', 'phone')->whereIn('value', $local->all())->orderByRaw("action = 'block' asc")->pluck('action', 'value')
            ->mapWithKeys(fn ($a, $p) => [PhoneExtractor::national($p) => $a]);

        return $byNational->map(function ($national) use ($customers, $recent, $blacklist) {
            $c = $customers->get($national);
            if (! $c) return ['found' => false, 'national' => $national, 'blacklist' => $blacklist->get($national)];
            $last = $recent->get($c->id)?->first();
            return [
                'found' => true, 'national' => $national, 'id' => $c->id, 'name' => $c->name, 'address' => $c->address,
                'orders' => (int) $c->orders_count, 'cancelled' => (int) $c->returned_count, 'blocked' => (bool) $c->is_blocked, 'blacklist' => $blacklist->get($national),
                'recent' => $last ? ['id' => preg_replace('/^\D+/', '', $last->number), 'number' => $last->number, 'ago' => $last->placed_at->diffForHumans(), 'url' => route('ops.orders.show', $last->id)] : null,
                'url' => route('ops.customers.index', ['search' => $c->phone]),
            ];
        })->all();
    }
}
