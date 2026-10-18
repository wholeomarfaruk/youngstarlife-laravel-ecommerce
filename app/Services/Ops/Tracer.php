<?php

namespace App\Services\Ops;

use App\Models\Ops\{Batch, Customer, CustomerReturn, CustomerReturnItem, Order, OrderItemAllocation, ProductVariant, PurchaseOrder, Shipment, Supplier, SupplierReturn, SupplierReturnItem};

/**
 * Global search + trace. resolve(): top results for the topbar dropdown (exact match first).
 * trace(): full chain Supplier → PO → Batch → Product → Order → Parcel → Courier → Outcome → Return → Supplier return → Resolution.
 */
class Tracer
{
    /** @return array<int, array{type:string,label:string,sub:string,url:string,exact:bool}> */
    public function resolve(string $q, int $limit = 8): array
    {
        $q = trim($q); if (mb_strlen($q) < 2 && ! ctype_digit($q)) return [];
        $out = []; $add = function ($type, $label, $sub, $url, $exact = false) use (&$out) { $out[] = compact('type', 'label', 'sub', 'url', 'exact'); };
        $up = strtoupper($q); $num = ltrim($q, '#');

        if (ctype_digit(preg_replace('/^\D+/', '', $num)) && ($o = Order::live()->numbered($q)->first(['id', 'number', 'ship_name', 'total']))) $add('Order', $o->number, $o->ship_name.' · '.tk($o->total), route('ops.orders.show', $o), true);
        foreach (Shipment::where('code', $up)->orWhere('tracking', $q)->with('order:id,number')->limit(2)->get() as $s) $add('Parcel', $s->code, $s->order->number.' · '.$s->tracking, route('ops.orders.show', $s->order_id), true);
        $batchCode = preg_match('/-(B\d+)$/i', $q, $m) ? strtoupper($m[1]) : $up;
        if ($b = Batch::where('code', $batchCode)->with('variant.product:id,name')->first()) $add('Batch', $b->code, $b->variant->label, route('ops.trace', ['q' => $b->code]), true);
        if ($po = PurchaseOrder::where('number', $up)->with('supplier:id,name')->first()) $add('PO', $po->number, $po->supplier->name, route('ops.purchases.edit', $po), true);
        if ($r = CustomerReturn::where('code', $up)->with('order:id,number')->first()) $add('Return', $r->code, $r->order->number, route('ops.trace', ['q' => $r->code]), true);
        if ($sr = SupplierReturn::where('code', $up)->with('supplier:id,name')->first()) $add('Supplier return', $sr->code, $sr->supplier->name, route('ops.supplier-returns.index', ['open' => $sr->id]), true);
        if ($v = ProductVariant::findByScan($q)) $add('Product', $v->label, $v->sku, route('ops.products.edit', $v->product_id), true);
        foreach (ProductVariant::with('product:id,name')->where('sku', 'like', "$q%")->limit(3)->get() as $v) $add('Product', $v->label, $v->sku, route('ops.products.edit', $v->product_id));
        foreach (\App\Models\Ops\Product::search($q)->limit(3)->get(['id', 'name', 'code']) as $p) $add('Product', $p->name, $p->code, route('ops.products.edit', $p));
        foreach (Customer::real()->where(fn ($c) => $c->where('phone', 'like', "%$q%")->orWhere('name', 'like', "%$q%"))->limit(3)->get(['id', 'name', 'phone']) as $c) $add('Customer', $c->name, $c->phone, route('ops.customers.index', ['search' => $c->phone]), $c->phone === $q);
        foreach (Supplier::where('name', 'like', "%$q%")->limit(2)->get(['id', 'name']) as $s) $add('Supplier', $s->name, '', route('ops.suppliers.index', ['search' => $s->name]));
        if (! ctype_digit($num)) foreach (Order::live()->where('number', 'like', "%$num%")->limit(2)->get(['id', 'number', 'ship_name']) as $o) $add('Order', $o->number, $o->ship_name, route('ops.orders.show', $o));

        return collect($out)->unique(fn ($r) => $r['type'].$r['label'])->sortByDesc('exact')->take($limit)->values()->all();
    }

    /** @return array{anchor: ?string, chains: array} each chain = list of nodes [step, label, sub, url, tone] */
    public function trace(string $q): array
    {
        $q = trim($q); if ($q === '') return ['anchor' => null, 'chains' => []];
        $up = strtoupper($q); $num = ltrim($q, '#');
        $allocs = collect(); $anchor = null; $bare = collect();

        if (($o = Order::live()->numbered($q)->first())) { $anchor = 'Order '.$o->number; $allocs = $this->allocsWhere(fn ($a) => $a->whereIn('order_item_id', $o->items()->pluck('id'))); }
        elseif ($s = Shipment::where('code', $up)->orWhere('tracking', $q)->first()) { $anchor = 'Parcel '.$s->code; $allocs = $this->allocsWhere(fn ($a) => $a->whereIn('order_item_id', $s->order->items()->pluck('id'))); }
        elseif ($b = Batch::where('code', preg_match('/-(B\d+)$/i', $q, $m) ? strtoupper($m[1]) : $up)->first()) { $anchor = 'Batch '.$b->code; $allocs = $this->allocsWhere(fn ($a) => $a->where('batch_id', $b->id)); $bare = collect([$b]); }
        elseif ($po = PurchaseOrder::where('number', $up)->first()) { $anchor = 'PO '.$po->number; $ids = $po->batches()->pluck('id'); $allocs = $this->allocsWhere(fn ($a) => $a->whereIn('batch_id', $ids)); $bare = $po->batches()->get(); }
        elseif ($r = CustomerReturn::where('code', $up)->first()) { $anchor = 'Return '.$r->code; $allocs = $this->allocsWhere(fn ($a) => $a->whereIn('order_item_id', $r->items()->pluck('order_item_id'))); }
        elseif ($sr = SupplierReturn::where('code', $up)->first()) { $anchor = 'Supplier return '.$sr->code; $items = CustomerReturnItem::whereIn('id', $sr->items()->pluck('customer_return_item_id')->filter())->pluck('order_item_id'); $allocs = $this->allocsWhere(fn ($a) => $a->whereIn('order_item_id', $items)); $bare = Batch::whereIn('id', $sr->items()->pluck('batch_id'))->get(); }
        elseif ($v = ProductVariant::findByScan($q)) { $anchor = 'Product '.$v->label; $ids = $v->batches()->pluck('id'); $allocs = $this->allocsWhere(fn ($a) => $a->whereIn('batch_id', $ids)); $bare = $v->batches()->get(); }
        elseif ($c = Customer::real()->where('phone', $q)->first()) { $anchor = 'Customer '.$c->name; $items = \App\Models\Ops\OrderItem::whereIn('order_id', $c->orders()->live()->pluck('id'))->pluck('id'); $allocs = $this->allocsWhere(fn ($a) => $a->whereIn('order_item_id', $items)); }

        $chains = $allocs->map(fn ($a) => $this->chain($a->batch, $a))->all();
        foreach ($bare->filter(fn ($b) => ! $allocs->contains('batch_id', $b->id)) as $b) $chains[] = $this->chain($b->load('variant.product', 'supplier', 'purchaseOrder'), null);
        return ['anchor' => $anchor, 'chains' => $chains];
    }

    protected function allocsWhere(callable $scope)
    {
        return $scope(OrderItemAllocation::query())->with('batch.supplier', 'batch.purchaseOrder', 'batch.variant.product', 'item.order.shipment.courier', 'item.order.customer')->latest('id')->limit(40)->get();
    }

    protected function chain(Batch $b, ?OrderItemAllocation $a): array
    {
        $n = fn ($step, $label, $sub = '', $url = null, $tone = 'ink') => compact('step', 'label', 'sub', 'url', 'tone');
        $nodes = [
            $n('Supplier', $b->supplier->name, '', route('ops.suppliers.index', ['search' => $b->supplier->name])),
            $b->purchaseOrder ? $n('PO', $b->purchaseOrder->number, ops_date($b->purchaseOrder->ordered_at), route('ops.purchases.edit', $b->purchase_order_id)) : $n('PO', '—', 'no PO'),
            $n('Batch', $b->code, tk($b->unit_cost).' · '.$b->remaining.'/'.$b->qty.' left'.($a && $a->packed_at ? ($a->batch_scanned ? ' · ✓ batch confirmed at packing' : ' · auto: oldest first') : ''), route('ops.batches.index', ['search' => $b->code]), $a && $a->batch_scanned ? 'ok' : 'ink'),
            $n('Product', $b->variant->label, $b->variant->sku, route('ops.products.edit', ['product' => $b->variant->product_id, 'tab' => 'stock'])),
        ];
        if (! $a) return array_merge($nodes, [$n('Order', 'Not sold yet', '', null, 'mute')]);
        $o = $a->item->order; $s = $o->shipment;
        $nodes[] = $n('Order', $o->number, $o->customer?->name.' · ×'.$a->qty, route('ops.orders.show', $o));
        $nodes[] = $s ? $n('Parcel', $s->code, $s->tracking ?: 'no tracking', route('ops.orders.show', $o)) : $n('Parcel', 'Not packed', '', null, 'mute');
        if ($s) $nodes[] = $n('Courier', $s->courier->name, config("ops.courier_status.{$s->courier_status}.0"), route('ops.orders.in-courier', ['search' => $s->code]));
        $outcome = match (true) { in_array($o->status, ['delivered']) => ['Delivered', 'ok'], in_array($o->status, ['returned', 'returning']) => [ucfirst($o->status), 'bad'], $o->status === 'partially_delivered' => ['Partially delivered', 'warn'], default => [config("ops.order_status.{$o->status}.0"), 'info'] };
        $nodes[] = $n('Outcome', $outcome[0], '', null, $outcome[1]);
        // returns are recorded per batch: this batch's row first, else (older returns) any row of the line
        $ri = CustomerReturnItem::where('order_item_id', $a->order_item_id)->where('batch_id', $a->batch_id)->with('customerReturn:id,code')->first()
            ?? CustomerReturnItem::where('order_item_id', $a->order_item_id)->whereNull('batch_id')->with('customerReturn:id,code')->first();
        if ($ri) {
            $nodes[] = $n('Return', $ri->customerReturn->code, $ri->reason.' · '.config("ops.return_conditions.{$ri->condition}.0", 'Not inspected'), route('ops.trace', ['q' => $ri->customerReturn->code]), $ri->condition === 'good' ? 'ok' : 'bad');
            $sri = SupplierReturnItem::where('customer_return_item_id', $ri->id)->with('supplierReturn')->first();
            if ($sri) {
                $sr = $sri->supplierReturn;
                $nodes[] = $n('Supplier return', $sr->code, config("ops.supplier_return_status.{$sr->status}.0"), route('ops.supplier-returns.index', ['open' => $sr->id]), 'warn');
                $nodes[] = $n('Resolution', ucfirst($sr->resolution), $sr->status === 'resolved' ? 'resolved '.ops_date($sr->resolved_at) : 'open', null, $sr->status === 'resolved' ? 'ok' : 'mute');
            }
        }
        return $nodes;
    }
}
