<?php

namespace App\Actions\Ops;

use App\Models\Ops\{Account, Customer, DeliveryZone, Order, OrderItem, Product, ProductVariant, Transaction};
use App\Services\Ops\{Blacklist, OrderWorkflow};
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * One order from the bulk sheet (ported from ecomx-automation's PlaceBulkOrder) → an Ops order: customer by phone,
 * items on variants (a product without a picked variant uses its own single variant), zone charge unless typed,
 * advance into an account, then the Ops automations. Throws InvalidArgumentException with a row-level message.
 */
class PlaceBulkOrder
{
    /** @param array{phone:string, name:?string, address:?string, items:list<array>, shipping_method_id:?int, delivery_charge:float, delivery_manual:bool, discount:float, advance:float, advance_account_id:?int, source:string, status:string, customer_note:?string, admin_note:?string} $d */
    public function handle(array $d): Order
    {
        $phone = Phone::normalize($d['phone'] ?? '') ?? throw new InvalidArgumentException('Phone number is not a valid BD mobile');
        $existing = Customer::where('phone', $phone)->first();
        if ($e = app(Blacklist::class)->blocking('bulk', ['phone' => $phone, 'name' => $d['name'] ?? $existing?->name]))
            throw new InvalidArgumentException('This customer is blacklisted ('.config('ops.blacklist_reasons.'.$e->reason, 'blocked').') — remove them in Users → Blacklist to order');
        $name = trim((string) ($d['name'] ?? '')) ?: (string) $existing?->name;
        $address = trim((string) ($d['address'] ?? '')) ?: (string) $existing?->address;
        if ($name === '') throw new InvalidArgumentException('Customer name is missing');
        if (mb_strlen($address) < 6) throw new InvalidArgumentException('Address is missing');
        $zone = DeliveryZone::where('is_active', true)->find($d['shipping_method_id'] ?? 0) ?? throw new InvalidArgumentException('Choose the delivery zone');

        $lines = [];
        foreach ($d['items'] ?? [] as $i) {
            $product = Product::where('status', 'active')->with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('id')])->find($i['product_id'] ?? 0)
                ?? throw new InvalidArgumentException('A product on this row is not available');
            $variant = ! empty($i['variant_id']) ? $product->variants->firstWhere('id', (int) $i['variant_id']) : null;
            if (! $variant && $product->type === 'variable') throw new InvalidArgumentException('Pick a size / option for '.$product->name);
            $variant ??= $product->variants->first() ?? throw new InvalidArgumentException($product->name.' has no sellable variant');
            $qty = (int) round((float) ($i['quantity'] ?? 0));
            if ($qty < 1) continue;
            $price = isset($i['unit_price']) && is_numeric($i['unit_price']) ? (int) round((float) $i['unit_price']) : (int) $variant->setRelation('product', $product)->effective_price;
            $lines[] = ['variant_id' => $variant->id, 'qty' => $qty, 'unit_price' => max(0, $price)];
        }
        if (! $lines) throw new InvalidArgumentException('Add at least one product');

        $sub = array_sum(array_map(fn ($l) => $l['qty'] * $l['unit_price'], $lines));
        $delivery = ! empty($d['delivery_manual']) ? (int) round((float) $d['delivery_charge']) : (int) $zone->charge;
        $discount = min($sub, max(0, (int) round((float) ($d['discount'] ?? 0))));
        $total = max(0, $sub - $discount + $delivery);
        $advance = min($total, max(0, (int) round((float) ($d['advance'] ?? 0))));
        $account = $advance && ! empty($d['advance_account_id']) ? Account::find($d['advance_account_id']) : null;

        $order = DB::transaction(function () use ($d, $phone, $name, $address, $zone, $lines, $sub, $delivery, $discount, $total, $advance, $account, $existing) {
            $c = $existing ?? Customer::create(['phone' => $phone, 'name' => $name, 'source' => $d['source']]);
            $o = Order::create([
                'number' => Order::nextNumber(), 'customer_id' => $c->id, 'source' => $d['source'], 'status' => 'pending',
                'payment_status' => $advance >= $total && $total > 0 ? 'paid' : ($advance > 0 ? 'partial' : 'pending'), 'is_draft' => false,
                'ship_name' => mb_substr($name, 0, 255), 'ship_phone' => $phone, 'ship_address' => mb_substr($address, 0, 255), 'delivery_zone_id' => $zone->id,
                'subtotal' => $sub, 'discount' => $discount, 'delivery_charge' => $delivery, 'total' => $total, 'advance' => $advance, 'cod' => $total - $advance,
                'note' => trim(implode("\n", array_filter([$d['customer_note'] ?? null, mb_strlen($address) > 255 ? 'Full address: '.$address : null]))) ?: null,
                'placed_at' => now(), 'created_by' => auth()->id(),
            ]);
            foreach ($lines as $l) OrderItem::create($l + ['order_id' => $o->id]);
            $o->log('created', trim((string) ($d['admin_note'] ?? 'Bulk order')));
            if ($account) Transaction::create(['account_id' => $account->id, 'direction' => 'in', 'category' => 'Sales', 'amount' => $advance,
                'description' => 'Advance · '.$o->number.' · '.$name, 'date' => today(), 'ref_type' => $o->getMorphClass(), 'ref_id' => $o->id, 'user_id' => auth()->id()]);
            $c->update(['name' => $c->orders_count ? $c->name : $name, 'address' => $c->address ?: mb_substr($address, 0, 255), 'area' => $c->area ?: $zone->name,
                'orders_count' => $c->orders_count + 1, 'total_spent' => $c->total_spent + $total, 'last_order_at' => now()]);
            return $o;
        });

        $workflow = app(OrderWorkflow::class);
        try { $workflow->placed($order->fresh()); } catch (\Throwable $e) { report($e); }   // risky flag, auto courier, notification
        if (($d['status'] ?? 'pending') === 'confirmed' && $order->fresh()->status === 'pending') $workflow->setStatus($order->fresh(), 'confirmed');
        ops_log('Created order '.$order->number.' from bulk order', $order);
        return $order->fresh();
    }
}
