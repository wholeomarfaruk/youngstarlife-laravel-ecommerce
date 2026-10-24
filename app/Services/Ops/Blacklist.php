<?php

namespace App\Services\Ops;

use App\Models\Device;
use App\Models\Ops\{BlacklistEntry, BlacklistHit, Customer, Order};
use App\Support\Phone;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Users → Blacklist. Every channel that makes an order asks here first:
 *   website checkout + abandoned checkout (CheckoutService), bulk / AI order (PlaceBulkOrder), Draft → order (Drafts),
 *   and OrderWorkflow::placed() tags orders that only match a "flag" entry.
 * A context is ['phone' => ?, 'device_id' => ?, 'ip' => ?, 'email' => ?, 'name' => ?].
 * ops_customers.is_blocked mirrors the active "block" phone entries (old screens and the AI intake read it).
 */
class Blacklist
{
    public const TYPES = ['phone', 'device', 'ip', 'email'];

    /** 01XXXXXXXXX · device id · IP · lower-case email; null = not a valid value for that type */
    public static function normalize(string $type, mixed $value): ?string
    {
        $v = trim((string) $value);
        return match ($type) {
            'phone' => Phone::normalize($v),
            'device' => ctype_digit(ltrim($v, '#')) && (int) ltrim($v, '#') > 0 ? (string) (int) ltrim($v, '#') : null,
            'ip' => filter_var($v, FILTER_VALIDATE_IP) ? $v : null,
            'email' => filter_var($v, FILTER_VALIDATE_EMAIL) ? mb_strtolower($v) : null,
            default => null,
        };
    }

    // ── matching ──────────────────────────────────────────────────────────────────────────────────

    /** All live entries matching the context, "block" first. */
    public function matches(array $ctx)
    {
        $pairs = array_filter([
            'phone' => static::normalize('phone', $ctx['phone'] ?? null), 'device' => ! empty($ctx['device_id']) ? (string) (int) $ctx['device_id'] : null,
            'ip' => static::normalize('ip', $ctx['ip'] ?? null), 'email' => static::normalize('email', $ctx['email'] ?? null),
        ]);
        // a blocked visitor who cleared cookies: a new device with the same browser fingerprint as a blocked one
        if (! empty($ctx['device_id']) && ops_setting('blacklist.match_fingerprint', true) && $same = $this->lookalikes((int) $ctx['device_id'])) $pairs['lookalike'] = $same;
        if (! $pairs) return collect();

        return BlacklistEntry::active()->where(function ($q) use ($pairs) {
            foreach ($pairs as $type => $v) $type === 'lookalike' ? $q->orWhere(fn ($q) => $q->where('type', 'device')->whereIn('value', $v)) : $q->orWhere(fn ($q) => $q->where('type', $type)->where('value', $v));
        })->orderByRaw("action = 'block' desc")->orderBy('id')->get();
    }

    public function find(array $ctx): ?BlacklistEntry { return $this->matches($ctx)->first(); }

    /**
     * Blocked device ids this device looks like (same fingerprint, DeviceManager::fingerprint). Only a device first seen after the block
     * can be its cookie-cleared copy — a returning customer (cookie kept, device older than the entry) is judged by their own device alone.
     */
    protected function lookalikes(int $deviceId): array
    {
        $d = Device::find($deviceId, ['id', 'fingerprint', 'created_at']);
        if (! $d?->fingerprint) return [];
        return BlacklistEntry::active()->where('type', 'device')->where('created_at', '<', $d->created_at)
            ->whereHas('device', fn ($q) => $q->where('fingerprint', $d->fingerprint)->whereKeyNot($d->id))->pluck('value')->all();
    }

    /**
     * Gate for an order channel: a "block" match is logged and returned (the caller refuses the order with message());
     * "flag" matches pass — OrderWorkflow::placed() tags the order afterwards.
     */
    public function blocking(string $channel, array $ctx): ?BlacklistEntry
    {
        $e = $this->find($ctx);
        if (! $e || $e->action !== 'block') return null;
        $this->record($e, $channel, $ctx, true);
        if ($channel !== 'autosave') ops_notify('blacklist', 'Blacklisted '.config('ops.blacklist_types.'.$e->type).' tried to order ('.config('ops.blacklist_channels.'.$channel, $channel).') · '.($ctx['phone'] ?? $e->label), route('ops.blacklist.index', ['view' => 'attempts']));
        return $e;
    }

    /** customer-facing refusal text (Blacklist → Settings) */
    public function message(): string { return site()->text('blacklist.message') ?: 'You are blacklisted from ordering'; }

    /** New live order: "flag" matches tag it for review, log the attempt and notify (block matches made by staff keep their order). */
    public function screen(Order $order): void
    {
        $ctx = ['phone' => $order->ship_phone, 'device_id' => $order->device_id, 'ip' => $order->ip_address, 'email' => $order->customer?->email, 'name' => $order->ship_name];
        if (! $e = $this->find($ctx)) return;
        $order->tag('Blacklist review');
        $order->log('automation', 'Matches blacklist ('.config('ops.blacklist_types.'.$e->type).' '.$e->label.' · '.config('ops.blacklist_reasons.'.$e->reason, 'no reason').') → review before confirming');
        $this->record($e, 'order', $ctx, false, $order);
        ops_notify('blacklist', 'Order '.$order->number.' matches the blacklist · '.$order->ship_name.' ('.$order->ship_phone.')', route('ops.orders.show', $order));
    }

    public function record(BlacklistEntry $e, string $channel, array $ctx, bool $blocked, ?Order $order = null): void
    {
        try {
            BlacklistHit::create(['blacklist_id' => $e->id, 'channel' => $channel, 'blocked' => $blocked, 'phone' => static::normalize('phone', $ctx['phone'] ?? null) ?? mb_substr((string) ($ctx['phone'] ?? ''), 0, 20) ?: null,
                'name' => isset($ctx['name']) ? mb_substr((string) $ctx['name'], 0, 255) : null, 'device_id' => Device::whereKey($ctx['device_id'] ?? 0)->value('id'),
                'ip_address' => $ctx['ip'] ?? null, 'order_id' => $order?->id, 'user_id' => auth()->id(), 'created_at' => now()]);
            $e->forceFill(['hits' => $e->hits + 1, 'last_hit_at' => now()])->saveQuietly();
        } catch (\Throwable $ex) {
            Log::warning('Blacklist hit not recorded: '.$ex->getMessage());   // never turn a refusal into a 500
        }
    }

    // ── managing entries ────────────────────────────────────────────────────────────────────────

    /**
     * Adds (or re-activates and updates) one entry.
     * @param array{type:string, value:mixed, action?:string, reason?:?string, note?:?string, expires_at?:mixed, source?:string, order_id?:?int} $d
     */
    public function add(array $d): BlacklistEntry
    {
        $type = $d['type'] ?? '';
        if (! in_array($type, self::TYPES)) throw new InvalidArgumentException('Unknown blacklist type');
        $value = static::normalize($type, $d['value'] ?? null) ?? throw new InvalidArgumentException('“'.($d['value'] ?? '').'” is not a valid '.strtolower(config("ops.blacklist_types.$type")));
        if ($type === 'device' && ! Device::whereKey($value)->exists()) throw new InvalidArgumentException('Device #'.$value.' does not exist');

        $e = BlacklistEntry::firstOrNew(['type' => $type, 'value' => $value]);
        $e->fill([
            'action' => in_array($d['action'] ?? 'block', ['block', 'flag']) ? ($d['action'] ?? 'block') : 'block',
            'reason' => array_key_exists($d['reason'] ?? '', config('ops.blacklist_reasons')) ? $d['reason'] : ($e->reason ?? 'other'),
            'note' => array_key_exists('note', $d) ? ($d['note'] ?: null) : $e->note, 'expires_at' => ($d['expires_at'] ?? null) ?: null,
            'customer_id' => $type === 'phone' ? Customer::where('phone', $value)->value('id') : ($d['customer_id'] ?? $e->customer_id),
            'device_id' => $type === 'device' ? (int) $value : $e->device_id, 'order_id' => $d['order_id'] ?? $e->order_id,
            'is_active' => true, 'removed_at' => null, 'removed_by' => null,
        ]);
        if (! $e->exists) $e->fill(['source' => $d['source'] ?? 'manual', 'created_by' => auth()->id()]);
        $e->save();
        $this->sync($e);
        ops_log(($e->wasRecentlyCreated ? 'Blacklisted ' : 'Updated blacklist ').config("ops.blacklist_types.$type").' '.$e->label.' ('.config('ops.blacklist_actions.'.$e->action.'.0').')', $e);
        return $e;
    }

    /** Soft remove: kept with its attempt history, can be restored. */
    public function remove(BlacklistEntry $e): void
    {
        $e->update(['is_active' => false, 'removed_at' => now(), 'removed_by' => auth()->id()]);
        $this->sync($e);
        ops_log('Removed '.config('ops.blacklist_types.'.$e->type).' '.$e->label.' from the blacklist', $e);
    }

    public function restore(BlacklistEntry $e): void
    {
        $e->update(['is_active' => true, 'removed_at' => null, 'removed_by' => null, 'expires_at' => $e->expires_at?->isPast() ? null : $e->expires_at]);
        $this->sync($e);
        ops_log('Restored '.config('ops.blacklist_types.'.$e->type).' '.$e->label.' on the blacklist', $e);
    }

    public function delete(BlacklistEntry $e): void
    {
        $label = config('ops.blacklist_types.'.$e->type).' '.$e->label;
        $e->delete(); $this->sync($e);
        ops_log('Deleted blacklist entry '.$label);
    }

    /** Customer → phone entry, plus (optionally) every storefront device that ordered with that phone or belongs to the account. */
    public function blockCustomer(Customer $c, array $d = [], bool $withDevices = false): array
    {
        $entries = [$this->add(['type' => 'phone', 'value' => $c->phone, 'source' => 'customer'] + $d)];
        if ($withDevices) foreach ($this->devicesOf($c) as $id) $entries[] = $this->add(['type' => 'device', 'value' => $id, 'source' => 'customer', 'customer_id' => $c->id] + $d);
        return $entries;
    }

    /** Lifts every live entry tied to the customer (phone + its devices). */
    public function unblockCustomer(Customer $c): int
    {
        $list = BlacklistEntry::where('is_active', true)->where(fn ($q) => $q->where('customer_id', $c->id)->orWhere(fn ($q) => $q->where('type', 'phone')->where('value', $c->phone)))->get();
        $list->each(fn ($e) => $this->remove($e));
        if ($c->is_blocked) $c->update(['is_blocked' => false]);   // a stray flag without an entry
        return $list->count();
    }

    /** storefront devices that placed orders with this phone or are signed in to the customer's account */
    public function devicesOf(Customer $c): array
    {
        return collect(Order::where('customer_id', $c->id)->whereNotNull('device_id')->distinct()->pluck('device_id'))
            ->merge($c->user_id ? Device::where('user_id', $c->user_id)->pluck('id') : [])->unique()->values()->all();
    }

    /**
     * Order → entries for what the staff ticked: its phone, device and/or IP.
     * @return list<BlacklistEntry>
     */
    public function fromOrder(Order $o, array $what, array $d = []): array
    {
        $out = [];
        if (in_array('phone', $what)) $out[] = $this->add(['type' => 'phone', 'value' => $o->ship_phone, 'source' => 'order', 'order_id' => $o->id] + $d);
        if (in_array('device', $what) && $o->device_id) $out[] = $this->add(['type' => 'device', 'value' => $o->device_id, 'source' => 'order', 'order_id' => $o->id, 'customer_id' => $o->customer_id] + $d);
        if (in_array('ip', $what) && $o->ip_address) $out[] = $this->add(['type' => 'ip', 'value' => $o->ip_address, 'source' => 'order', 'order_id' => $o->id, 'customer_id' => $o->customer_id] + $d);
        return $out;
    }

    /** Cancels the still-open orders of a blocked phone (Blacklist → add, "cancel open orders"). */
    public function cancelOpenOrders(string $phone): int
    {
        $n = 0;
        foreach (Order::live()->where('ship_phone', $phone)->whereIn('status', ['pending', 'confirmed', 'on_hold'])->get() as $o) {
            app(OrderWorkflow::class)->setStatus($o, 'cancelled'); $o->log('automation', 'Cancelled: phone added to the blacklist'); $n++;
        }
        return $n;
    }

    /** ops_customers.is_blocked = has a live "block" phone entry */
    public function sync(BlacklistEntry $e): void
    {
        if ($e->type !== 'phone') return;
        $blocked = BlacklistEntry::active()->where('type', 'phone')->where('value', $e->value)->where('action', 'block')->exists();
        Customer::where('phone', $e->value)->where('is_blocked', ! $blocked)->update(['is_blocked' => $blocked]);
    }

    /** Hourly: expired phone entries release their customers again. */
    public function syncAll(): int
    {
        $live = BlacklistEntry::active()->where('type', 'phone')->where('action', 'block')->select('value');
        $off = Customer::where('is_blocked', true)->whereNotIn('phone', $live)->update(['is_blocked' => false]);
        $on = Customer::where('is_blocked', false)->whereIn('phone', $live)->update(['is_blocked' => true]);
        return $off + $on;
    }

    /** Automation (Blacklist → Settings): a returned parcel pushes a customer over the limit → phone added with the chosen action. */
    public function autoCheck(?Customer $c): void
    {
        if (! $c || ! ops_setting('blacklist.auto.on')) return;
        try {
            $c->refresh(); $closed = $c->delivered_count + $c->returned_count;
            $min = (int) ops_setting('blacklist.auto.returns', 3); $rate = (int) ops_setting('blacklist.auto.rate', 34);
            if ($c->returned_count < $min || ! $closed || $c->delivered_count / $closed * 100 >= $rate) return;
            if (BlacklistEntry::where('type', 'phone')->where('value', $c->phone)->exists()) return;   // already listed (or removed by hand — don't re-add)
            $days = (int) ops_setting('blacklist.auto.days', 0);
            $this->add(['type' => 'phone', 'value' => $c->phone, 'action' => ops_setting('blacklist.auto.action', 'flag'), 'reason' => 'refused', 'source' => 'auto',
                'note' => "Auto: {$c->returned_count} returned of $closed parcels (".round($c->delivered_count / $closed * 100).'% delivered)', 'expires_at' => $days ? now()->addDays($days) : null]);
            ops_notify('blacklist', $c->name.' ('.$c->phone.') was auto-added to the blacklist · '.$c->returned_count.' returns', route('ops.blacklist.index', ['search' => $c->phone]));
        } catch (\Throwable $ex) {
            Log::warning('Blacklist auto-check failed: '.$ex->getMessage());
        }
    }
}
