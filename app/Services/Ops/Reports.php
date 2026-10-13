<?php

namespace App\Services\Ops;

use App\Models\Ops\{Account, Batch, Courier, CourierSettlement, Customer, CustomerReturnItem, Order, OrderItem, OrderItemAllocation, ProductVariant, Shipment, StockMovement, Supplier, SupplierReturn, Transaction};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every report on the Reports page. run() → ['columns' => [key => [label, type]], 'rows' => [...], 'kpis' => [[label, value, tone]]]
 * Column types: text | money | num | pct | date (money/num columns get a totals row).
 */
class Reports
{
    public static function catalog(): array
    {
        return [
            'Sales' => ['sales_summary' => 'Sales summary', 'sales_by_channel' => 'Sales by channel', 'sales_by_product' => 'Sales by product', 'sales_by_area' => 'Sales by area'],
            'Orders' => ['order_status' => 'Order status report', 'cancelled_orders' => 'Cancelled orders', 'fulfilment_time' => 'Fulfilment time'],
            'Products & stock' => ['stock_summary' => 'Stock summary', 'low_stock' => 'Low & out of stock', 'stock_movement' => 'Stock movement', 'slow_stock' => 'Slow & dead stock'],
            'Batches & trace' => ['batch_sell_through' => 'Batch sell-through', 'batch_trace' => 'Batch → order trace', 'supplier_quality' => 'Supplier quality'],
            'Couriers' => ['courier_performance' => 'Courier performance', 'cod_reconciliation' => 'COD reconciliation', 'delays' => 'Delays & failed attempts'],
            'Returns' => ['return_reasons' => 'Return reasons', 'returns_by_product' => 'Returns by product', 'exchanges' => 'Exchanges'],
            'Customers' => ['top_customers' => 'Top customers', 'risky_customers' => 'Risky customers', 'new_vs_repeat' => 'New vs repeat'],
            'Suppliers' => ['supplier_purchases' => 'Supplier purchases & due', 'supplier_returns' => 'Supplier returns'],
            'Finance' => ['profit_loss' => 'Profit & loss', 'cash_flow' => 'Cash flow by account', 'expenses' => 'Expenses', 'aging' => 'Receivables & payables aging'],
        ];
    }

    public static function title(string $key): string { foreach (static::catalog() as $g) if (isset($g[$key])) return $g[$key]; return 'Report'; }

    public function run(string $key, string $from, string $to, string $group = 'day'): array
    {
        $a = Carbon::parse($from)->startOfDay(); $b = Carbon::parse($to)->endOfDay();
        $method = str($key)->camel()->toString();
        abort_unless(method_exists($this, $method), 404);
        $r = $this->$method($a, $b, $group);
        $r['rows'] = collect($r['rows'])->map(fn ($x) => (array) $x)->values()->all();
        $r['kpis'] ??= [];
        return $r;
    }

    protected function orders(Carbon $a, Carbon $b) { return Order::live()->whereBetween('placed_at', [$a, $b]); }
    protected function fmt(string $group): string { return ['week' => '%x-W%v', 'month' => '%Y-%m'][$group] ?? '%Y-%m-%d'; }

    // ── Sales
    protected function salesSummary($a, $b, $group): array
    {
        $rows = $this->orders($a, $b)->whereNot('status', 'cancelled')->selectRaw("DATE_FORMAT(placed_at, '{$this->fmt($group)}') period, COUNT(*) orders, SUM(subtotal) gross, SUM(discount) discount, SUM(delivery_charge) delivery, SUM(total) net, SUM(CASE WHEN status='delivered' THEN total ELSE 0 END) delivered")->groupBy('period')->orderBy('period')->get();
        return ['columns' => ['period' => [ucfirst($group), 'text'], 'orders' => ['Orders', 'num'], 'gross' => ['Gross sales', 'money'], 'discount' => ['Discount', 'money'], 'delivery' => ['Delivery', 'money'], 'net' => ['Net sales', 'money'], 'delivered' => ['Delivered', 'money']], 'rows' => $rows,
            'kpis' => [['Net sales', tk($rows->sum('net')), 'ink'], ['Orders', ops_num($rows->sum('orders')), 'ink'], ['Avg order', tk($rows->sum('orders') ? $rows->sum('net') / $rows->sum('orders') : 0), 'ink'], ['Delivered', tk($rows->sum('delivered')), 'ok']], 'groupable' => true];
    }

    protected function salesByChannel($a, $b): array
    {
        $rows = $this->orders($a, $b)->selectRaw("source channel, COUNT(*) orders, SUM(total) value, SUM(status='delivered') delivered, SUM(status IN ('returned','returning')) returned")->groupBy('source')->orderByDesc('value')->get()
            ->map(fn ($r) => ['channel' => config('ops.sources.'.$r->channel, $r->channel), 'orders' => $r->orders, 'value' => $r->value, 'delivered' => $r->delivered, 'rate' => ($r->delivered + $r->returned) ? round($r->delivered / ($r->delivered + $r->returned) * 100) : null]);
        return ['columns' => ['channel' => ['Channel', 'text'], 'orders' => ['Orders', 'num'], 'value' => ['Value', 'money'], 'delivered' => ['Delivered', 'num'], 'rate' => ['Delivery rate', 'pct']], 'rows' => $rows];
    }

    protected function salesByProduct($a, $b): array
    {
        $rows = OrderItem::joinSub($this->orders($a, $b)->whereNot('status', 'cancelled')->whereNull('exchange_of_id')->select('id'), 'o', 'o.id', '=', 'ops_order_items.order_id')->join('ops_product_variants as v', 'v.id', '=', 'ops_order_items.variant_id')->join('ops_products as p', 'p.id', '=', 'v.product_id')   // exchanges swap an item — not a new sale
            ->selectRaw('p.name product, p.code, SUM(ops_order_items.qty) units, COUNT(DISTINCT ops_order_items.order_id) orders, SUM(ops_order_items.qty*ops_order_items.unit_price) revenue, SUM(ops_order_items.returned_qty) returned')->groupBy('p.name', 'p.code')->orderByDesc('revenue')->get();
        return ['columns' => ['product' => ['Product', 'text'], 'code' => ['Code', 'text'], 'units' => ['Units', 'num'], 'orders' => ['Orders', 'num'], 'revenue' => ['Revenue', 'money'], 'returned' => ['Returned', 'num']], 'rows' => $rows,
            'kpis' => [['Units sold', ops_num($rows->sum('units')), 'ink'], ['Revenue', tk($rows->sum('revenue')), 'ok'], ['Products', $rows->count(), 'ink']]];
    }

    protected function salesByArea($a, $b): array
    {
        $rows = $this->orders($a, $b)->leftJoin('ops_delivery_zones as z', 'z.id', '=', 'ops_orders.delivery_zone_id')->join('ops_customers as c', 'c.id', '=', 'ops_orders.customer_id')
            ->selectRaw("COALESCE(z.name, '—') zone, COALESCE(c.area, '—') area, COUNT(*) orders, SUM(ops_orders.total) value, SUM(ops_orders.status='delivered') delivered")->groupBy('zone', 'area')->orderByDesc('value')->get();
        return ['columns' => ['zone' => ['Zone', 'text'], 'area' => ['Area', 'text'], 'orders' => ['Orders', 'num'], 'value' => ['Value', 'money'], 'delivered' => ['Delivered', 'num']], 'rows' => $rows];
    }

    // ── Orders
    protected function orderStatus($a, $b): array
    {
        $raw = $this->orders($a, $b)->selectRaw('status, COUNT(*) n, SUM(total) value')->groupBy('status')->get()->keyBy('status'); $all = max(1, $raw->sum('n'));
        $rows = collect(config('ops.order_status'))->map(fn ($v, $k) => ['status' => $v[0], 'orders' => (int) ($raw[$k]->n ?? 0), 'value' => (int) ($raw[$k]->value ?? 0), 'share' => round(($raw[$k]->n ?? 0) / $all * 100)])->values();
        return ['columns' => ['status' => ['Status', 'text'], 'orders' => ['Orders', 'num'], 'value' => ['Value', 'money'], 'share' => ['Share', 'pct']], 'rows' => $rows];
    }

    protected function cancelledOrders($a, $b): array
    {
        $rows = $this->orders($a, $b)->where('status', 'cancelled')->with('customer:id,name,phone', 'events')->latest('placed_at')->get()
            ->map(fn ($o) => ['order' => $o->number, 'date' => $o->placed_at->toDateString(), 'customer' => $o->customer->name, 'phone' => $o->customer->phone, 'value' => $o->total, 'reason' => $o->events->firstWhere('type', 'note')?->message ?? '—']);
        return ['columns' => ['order' => ['Order', 'text'], 'date' => ['Date', 'date'], 'customer' => ['Customer', 'text'], 'phone' => ['Phone', 'text'], 'value' => ['Value', 'money'], 'reason' => ['Last note', 'text']], 'rows' => $rows,
            'kpis' => [['Cancelled', $rows->count(), 'bad'], ['Lost value', tk($rows->sum('value')), 'bad']]];
    }

    protected function fulfilmentTime($a, $b): array
    {
        $rows = Shipment::join('ops_orders as o', 'o.id', '=', 'ops_shipments.order_id')->join('ops_couriers as c', 'c.id', '=', 'ops_shipments.courier_id')->whereBetween('o.placed_at', [$a, $b])
            ->selectRaw('c.name courier, COUNT(*) parcels, ROUND(AVG(TIMESTAMPDIFF(MINUTE, o.placed_at, ops_shipments.packed_at))/60, 1) to_pack, ROUND(AVG(TIMESTAMPDIFF(MINUTE, ops_shipments.packed_at, ops_shipments.handed_over_at))/60, 1) to_handover, ROUND(AVG(TIMESTAMPDIFF(HOUR, ops_shipments.handed_over_at, ops_shipments.delivered_at))/24, 1) to_deliver')->groupBy('c.name')->get();
        return ['columns' => ['courier' => ['Courier', 'text'], 'parcels' => ['Parcels', 'num'], 'to_pack' => ['Placed → packed (h)', 'text'], 'to_handover' => ['Packed → handed over (h)', 'text'], 'to_deliver' => ['Handed over → delivered (days)', 'text']], 'rows' => $rows];
    }

    // ── Products & stock
    protected function stockSummary(): array
    {
        $rows = ProductVariant::join('ops_products as p', 'p.id', '=', 'ops_product_variants.product_id')
            ->selectRaw('p.name product, COUNT(*) variants, SUM(ops_product_variants.stock) stock, SUM(ops_product_variants.reserved) reserved, SUM(ops_product_variants.stock - ops_product_variants.reserved) available, SUM(ops_product_variants.damaged) damaged, (select COALESCE(SUM(b.remaining*b.unit_cost),0) from ops_batches b join ops_product_variants vv on vv.id = b.variant_id where vv.product_id = p.id) value')
            ->groupBy('p.id', 'p.name')->orderBy('p.name')->get();
        return ['columns' => ['product' => ['Product', 'text'], 'variants' => ['Variants', 'num'], 'stock' => ['Stock', 'num'], 'reserved' => ['Reserved', 'num'], 'available' => ['Available', 'num'], 'damaged' => ['Damaged', 'num'], 'value' => ['Stock value', 'money']], 'rows' => $rows,
            'kpis' => [['Units', ops_num($rows->sum('stock')), 'ink'], ['Stock value', tk($rows->sum('value')), 'ink'], ['Damaged', ops_num($rows->sum('damaged')), 'bad']]];
    }

    protected function lowStock(): array
    {
        $sold = OrderItem::whereHas('order', fn ($o) => $o->live()->where('placed_at', '>=', now()->subDays(30))->whereNot('status', 'cancelled'))->selectRaw('variant_id, SUM(qty) q')->groupBy('variant_id')->pluck('q', 'variant_id');
        $rows = ProductVariant::with('product:id,name,low_stock_threshold')->get()->filter(fn ($v) => $v->stock < $v->product->low_stock_threshold)
            ->map(function ($v) use ($sold) { $daily = ($sold[$v->id] ?? 0) / 30; return ['product' => $v->label, 'sku' => $v->sku, 'stock' => $v->stock, 'threshold' => $v->product->low_stock_threshold, 'sold_30d' => (int) ($sold[$v->id] ?? 0), 'cover' => $daily ? (string) round($v->stock / $daily, 1) : '∞', 'reorder' => max(0, (int) ceil($daily * 21) - $v->stock, $v->product->low_stock_threshold * 2 - $v->stock)]; })
            ->sortBy('stock')->values();
        return ['columns' => ['product' => ['Variant', 'text'], 'sku' => ['SKU', 'text'], 'stock' => ['Stock', 'num'], 'threshold' => ['Threshold', 'num'], 'sold_30d' => ['Sold 30d', 'num'], 'cover' => ['Days of cover', 'text'], 'reorder' => ['Reorder qty', 'num']], 'rows' => $rows,
            'kpis' => [['Below threshold', $rows->count(), 'warn'], ['Out of stock', $rows->where('stock', '<=', 0)->count(), 'bad']]];
    }

    protected function stockMovement($a, $b): array
    {
        $rows = StockMovement::whereBetween('created_at', [$a, $b])->selectRaw('type, COUNT(*) movements, SUM(CASE WHEN qty > 0 THEN qty ELSE 0 END) qty_in, SUM(CASE WHEN qty < 0 THEN -qty ELSE 0 END) qty_out')->groupBy('type')->get()
            ->map(fn ($r) => ['type' => config('ops.movement_types.'.$r->type, $r->type), 'movements' => $r->movements, 'qty_in' => $r->qty_in, 'qty_out' => $r->qty_out, 'net' => $r->qty_in - $r->qty_out]);
        return ['columns' => ['type' => ['Type', 'text'], 'movements' => ['Movements', 'num'], 'qty_in' => ['Units in', 'num'], 'qty_out' => ['Units out', 'num'], 'net' => ['Net', 'num']], 'rows' => $rows];
    }

    protected function slowStock(): array
    {
        $last = StockMovement::where('type', 'sale')->selectRaw('variant_id, MAX(created_at) at')->groupBy('variant_id')->pluck('at', 'variant_id');
        $rows = ProductVariant::with('product:id,name')->where('stock', '>', 0)->get()->map(function ($v) use ($last) {
            $at = $last[$v->id] ?? null; $days = $at ? (int) Carbon::parse($at)->diffInDays(now()) : null;
            return ['product' => $v->label, 'sku' => $v->sku, 'stock' => $v->stock, 'last_sale' => $at ? Carbon::parse($at)->toDateString() : 'never', 'days' => $days ?? 999, 'value' => (int) Batch::where('variant_id', $v->id)->selectRaw('SUM(remaining*unit_cost) v')->value('v')];
        })->filter(fn ($r) => $r['days'] >= 30)->sortByDesc('days')->values();
        return ['columns' => ['product' => ['Variant', 'text'], 'sku' => ['SKU', 'text'], 'stock' => ['Stock', 'num'], 'last_sale' => ['Last sale', 'text'], 'days' => ['Days since sale', 'num'], 'value' => ['Value', 'money']], 'rows' => $rows,
            'kpis' => [['Slow (30+ days)', $rows->count(), 'warn'], ['Dead (never sold)', $rows->where('last_sale', 'never')->count(), 'bad'], ['Value stuck', tk($rows->sum('value')), 'bad']]];
    }

    // ── Batches & trace
    protected function batchSellThrough($a, $b): array
    {
        $rows = Batch::with('variant.product:id,name', 'supplier:id,name')->withCount('returnItems as returns')->whereBetween('received_at', [$a->copy()->subYears(5), $b])->latest('received_at')->get()
            ->map(fn ($x) => ['batch' => $x->code, 'product' => $x->variant->label, 'supplier' => $x->supplier->name, 'qty' => $x->qty, 'remaining' => $x->remaining, 'sell' => $x->sell_through, 'age' => $x->age_days, 'returns' => $x->returns]);
        return ['columns' => ['batch' => ['Batch', 'text'], 'product' => ['Product', 'text'], 'supplier' => ['Supplier', 'text'], 'qty' => ['Qty', 'num'], 'remaining' => ['Remaining', 'num'], 'sell' => ['Sell-through', 'pct'], 'age' => ['Age (days)', 'num'], 'returns' => ['Returns', 'num']], 'rows' => $rows];
    }

    protected function batchTrace($a, $b): array
    {
        $rows = OrderItemAllocation::whereHas('item.order', fn ($o) => $o->whereBetween('placed_at', [$a, $b]))->with('batch.variant.product:id,name', 'batch.supplier:id,name', 'item.order.customer:id,name')->latest('id')->limit(500)->get()
            ->map(fn ($x) => ['batch' => $x->batch->code, 'supplier' => $x->batch->supplier->name, 'product' => $x->batch->variant->label, 'order' => $x->item->order->number, 'customer' => $x->item->order->customer->name, 'qty' => $x->qty, 'cost' => $x->unit_cost ? $x->qty * $x->unit_cost : null, 'outcome' => config('ops.order_status.'.$x->item->order->status.'.0')]);
        return ['columns' => ['batch' => ['Batch', 'text'], 'supplier' => ['Supplier', 'text'], 'product' => ['Product', 'text'], 'order' => ['Order', 'text'], 'customer' => ['Customer', 'text'], 'qty' => ['Qty', 'num'], 'cost' => ['Cost', 'money'], 'outcome' => ['Outcome', 'text']], 'rows' => $rows];
    }

    protected function supplierQuality(): array
    {
        $cr = CustomerReturnItem::whereIn('condition', ['damaged', 'used', 'broken'])->join('ops_batches as b', 'b.id', '=', 'ops_customer_return_items.batch_id')->selectRaw('b.supplier_id, SUM(ops_customer_return_items.qty) n')->groupBy('b.supplier_id')->pluck('n', 'supplier_id');
        $rows = Supplier::withCount('returns')->get()->map(function ($s) use ($cr) {
            $recv = (int) Batch::where('supplier_id', $s->id)->sum('qty'); $dmg = (int) Batch::where('supplier_id', $s->id)->sum('damaged');
            return ['supplier' => $s->name, 'received' => $recv, 'arrival' => $dmg, 'customer' => (int) ($cr[$s->id] ?? 0), 'defect' => $recv ? round(($dmg + ($cr[$s->id] ?? 0)) / $recv * 100, 1) : 0, 'returns' => $s->returns_count];
        })->sortByDesc('defect')->values();
        return ['columns' => ['supplier' => ['Supplier', 'text'], 'received' => ['Units received', 'num'], 'arrival' => ['Damaged on arrival', 'num'], 'customer' => ['Customer damaged', 'num'], 'defect' => ['Defect rate', 'pct'], 'returns' => ['Supplier returns', 'num']], 'rows' => $rows];
    }

    // ── Couriers
    protected function courierPerformance($a, $b): array
    {
        $rows = Shipment::join('ops_couriers as c', 'c.id', '=', 'ops_shipments.courier_id')->whereBetween('ops_shipments.created_at', [$a, $b])
            ->selectRaw("c.name courier, COUNT(*) parcels, SUM(courier_status='delivered') delivered, SUM(courier_status IN ('returned','returning')) returned, ROUND(AVG(CASE WHEN delivered_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, packed_at, delivered_at)/24 END), 1) days, SUM(CASE WHEN courier_status='delivered' AND settlement_id IS NULL THEN cod_amount ELSE 0 END) receivable")->groupBy('c.name')->get()
            ->map(fn ($r) => (array) $r->getAttributes() + ['rate' => ($r->delivered + $r->returned) ? round($r->delivered / ($r->delivered + $r->returned) * 100) : null]);
        return ['columns' => ['courier' => ['Courier', 'text'], 'parcels' => ['Parcels', 'num'], 'delivered' => ['Delivered', 'num'], 'returned' => ['Returned', 'num'], 'rate' => ['Delivery rate', 'pct'], 'days' => ['Avg days', 'text'], 'receivable' => ['Receivable', 'money']], 'rows' => $rows];
    }

    protected function codReconciliation($a, $b): array
    {
        $rows = Courier::get()->map(function ($c) use ($a, $b) {
            $del = Shipment::where('courier_id', $c->id)->where('courier_status', 'delivered')->whereBetween('delivered_at', [$a, $b]);
            $st = CourierSettlement::where('courier_id', $c->id)->whereBetween('received_on', [$a->toDateString(), $b->toDateString()]);
            return ['courier' => $c->name, 'cod' => (int) (clone $del)->sum('cod_amount'), 'settled_cod' => (int) (clone $st)->sum('cod_total'), 'charges' => (int) (clone $st)->sum('charges'), 'received' => (int) (clone $st)->sum('received'),
                'short' => (int) (clone $st)->get()->sum('short'), 'outstanding' => (int) Shipment::where('courier_id', $c->id)->where('courier_status', 'delivered')->whereNull('settlement_id')->sum('cod_amount')];
        });
        return ['columns' => ['courier' => ['Courier', 'text'], 'cod' => ['COD delivered', 'money'], 'settled_cod' => ['COD settled', 'money'], 'charges' => ['Charges', 'money'], 'received' => ['Received', 'money'], 'short' => ['Short paid', 'money'], 'outstanding' => ['Outstanding', 'money']], 'rows' => $rows];
    }

    protected function delays(): array
    {
        $rows = Shipment::with('order:id,number,customer_id', 'order.customer:id,name', 'courier:id,name')->where(fn ($q) => $q->where('failed_attempts', '>', 0)->orWhere(fn ($q) => $q->whereIn('courier_status', ['pending', 'picked_up', 'in_transit', 'out_for_delivery', 'hold'])->where('status_updated_at', '<', now()->subDays(3))))->get()
            ->map(fn ($s) => ['parcel' => $s->code, 'order' => $s->order->number, 'customer' => $s->order->customer->name, 'courier' => $s->courier->name, 'status' => config('ops.courier_status.'.$s->courier_status.'.0'), 'days' => (int) $s->status_updated_at?->diffInDays(now()), 'attempts' => $s->failed_attempts, 'tracking' => $s->tracking]);
        return ['columns' => ['parcel' => ['Parcel', 'text'], 'order' => ['Order', 'text'], 'customer' => ['Customer', 'text'], 'courier' => ['Courier', 'text'], 'status' => ['Status', 'text'], 'days' => ['Days without update', 'num'], 'attempts' => ['Failed attempts', 'num'], 'tracking' => ['Tracking', 'text']], 'rows' => $rows];
    }

    // ── Returns
    protected function returnReasons($a, $b): array
    {
        $raw = CustomerReturnItem::whereHas('customerReturn', fn ($r) => $r->whereBetween('created_at', [$a, $b]))->join('ops_order_items as oi', 'oi.id', '=', 'ops_customer_return_items.order_item_id')
            ->selectRaw('reason, SUM(ops_customer_return_items.qty) items, SUM(ops_customer_return_items.qty * oi.unit_price) value')->groupBy('reason')->orderByDesc('items')->get();
        $all = max(1, $raw->sum('items'));
        return ['columns' => ['reason' => ['Reason', 'text'], 'items' => ['Items', 'num'], 'share' => ['Share', 'pct'], 'value' => ['Value', 'money']], 'rows' => $raw->map(fn ($r) => ['reason' => $r->reason, 'items' => $r->items, 'share' => round($r->items / $all * 100), 'value' => $r->value])];
    }

    protected function returnsByProduct($a, $b): array
    {
        $rows = OrderItem::join('ops_orders as o', 'o.id', '=', 'ops_order_items.order_id')->where('o.is_draft', false)->whereBetween('o.placed_at', [$a, $b])->join('ops_product_variants as v', 'v.id', '=', 'ops_order_items.variant_id')->join('ops_products as p', 'p.id', '=', 'v.product_id')
            ->selectRaw('p.name product, SUM(ops_order_items.qty) sold, SUM(ops_order_items.returned_qty) returned')->groupBy('p.name')->havingRaw('SUM(ops_order_items.returned_qty) > 0')->orderByDesc('returned')->get()
            ->map(fn ($r) => ['product' => $r->product, 'sold' => $r->sold, 'returned' => $r->returned, 'rate' => $r->sold ? round($r->returned / $r->sold * 100) : 0]);
        return ['columns' => ['product' => ['Product', 'text'], 'sold' => ['Sold', 'num'], 'returned' => ['Returned', 'num'], 'rate' => ['Return rate', 'pct']], 'rows' => $rows];
    }

    protected function exchanges($a, $b): array
    {
        $rows = Order::live()->whereNotNull('exchange_of_id')->whereBetween('placed_at', [$a, $b])->with('exchangeOf:id,number', 'customer:id,name', 'exchangeLine.items.orderItem.variant.product:id,name', 'items.variant.product:id,name')->latest('placed_at')->get()
            ->map(fn ($o) => ['exchange' => $o->number, 'original' => $o->exchangeOf?->number, 'customer' => $o->customer->name, 'date' => $o->placed_at->toDateString(),
                'new' => $o->items->map(fn ($i) => $i->variant->label.' ×'.$i->qty)->join(', '), 'old' => $o->exchangeLine?->items->map(fn ($i) => $i->orderItem->variant->label.' ×'.$i->qty)->join(', '),
                'reason' => $o->exchangeLine?->items->pluck('reason')->unique()->join(', '), 'charge' => $o->total, 'refund' => (int) $o->exchangeLine?->refund_amount,
                'state' => config('ops.exchange_state.'.$o->exchange_state.'.0', $o->exchange_state)]);
        return ['columns' => ['exchange' => ['Exchange', 'text'], 'original' => ['Original', 'text'], 'customer' => ['Customer', 'text'], 'date' => ['Date', 'date'], 'new' => ['New item', 'text'], 'old' => ['Old item', 'text'], 'reason' => ['Reason', 'text'], 'charge' => ['COD', 'money'], 'refund' => ['Refund', 'money'], 'state' => ['Exchange', 'text']], 'rows' => $rows,
            'kpis' => [['Exchanges', $rows->count(), 'ink'], ['Completed', $rows->where('state', config('ops.exchange_state.completed.0'))->count(), 'ok'], ['Refused / failed', $rows->whereIn('state', [config('ops.exchange_state.refused.0'), config('ops.exchange_state.failed.0')])->count(), 'bad'], ['Charges', tk($rows->sum('charge')), 'ok']]];
    }

    // ── Customers
    protected function topCustomers($a, $b): array
    {
        $rows = $this->orders($a, $b)->whereNot('ops_orders.status', 'cancelled')->join('ops_customers as c', 'c.id', '=', 'ops_orders.customer_id')
            ->selectRaw("c.name customer, c.phone, COUNT(*) orders, SUM(ops_orders.total) value, SUM(ops_orders.status='delivered') delivered")->groupBy('c.id', 'c.name', 'c.phone')->orderByDesc('value')->limit(100)->get();
        return ['columns' => ['customer' => ['Customer', 'text'], 'phone' => ['Phone', 'text'], 'orders' => ['Orders', 'num'], 'value' => ['Value', 'money'], 'delivered' => ['Delivered', 'num']], 'rows' => $rows];
    }

    protected function riskyCustomers(): array
    {
        $rows = Customer::real()->whereRaw('delivered_count + returned_count > 0 AND delivered_count < 0.75 * (delivered_count + returned_count)')->orderByRaw('delivered_count / (delivered_count + returned_count)')->get()
            ->map(fn ($c) => ['customer' => $c->name, 'phone' => $c->phone, 'orders' => $c->orders_count, 'delivered' => $c->delivered_count, 'returned' => $c->returned_count, 'rate' => round($c->delivery_rate * 100), 'last' => $c->last_order_at?->toDateString()]);
        return ['columns' => ['customer' => ['Customer', 'text'], 'phone' => ['Phone', 'text'], 'orders' => ['Orders', 'num'], 'delivered' => ['Delivered', 'num'], 'returned' => ['Returned', 'num'], 'rate' => ['Delivery rate', 'pct'], 'last' => ['Last order', 'date']], 'rows' => $rows];
    }

    protected function newVsRepeat($a, $b, $group): array
    {
        $rows = $this->orders($a, $b)->whereNot('status', 'cancelled')
            ->selectRaw("DATE_FORMAT(placed_at, '{$this->fmt($group)}') period, SUM(NOT EXISTS (select 1 from ops_orders p where p.customer_id = ops_orders.customer_id and p.is_draft = 0 and p.placed_at < ops_orders.placed_at)) new_orders, COUNT(*) orders, SUM(total) value")
            ->groupBy('period')->orderBy('period')->get()->map(fn ($r) => ['period' => $r->period, 'new_orders' => $r->new_orders, 'repeat_orders' => $r->orders - $r->new_orders, 'value' => $r->value, 'repeat_share' => $r->orders ? round(($r->orders - $r->new_orders) / $r->orders * 100) : 0]);
        return ['columns' => ['period' => [ucfirst($group), 'text'], 'new_orders' => ['New customer orders', 'num'], 'repeat_orders' => ['Repeat orders', 'num'], 'value' => ['Value', 'money'], 'repeat_share' => ['Repeat share', 'pct']], 'rows' => $rows, 'groupable' => true];
    }

    // ── Suppliers
    protected function supplierPurchases($a, $b): array
    {
        $rows = Supplier::withCount(['purchaseOrders as pos' => fn ($q) => $q->whereBetween('ordered_at', [$a->toDateString(), $b->toDateString()])])->get()->map(fn ($s) => [
            'supplier' => $s->name, 'pos' => $s->pos_count, 'purchased' => (int) Batch::where('supplier_id', $s->id)->whereBetween('received_at', [$a, $b])->selectRaw('COALESCE(SUM((qty-damaged)*unit_cost),0) v')->value('v'),
            'paid' => (int) $s->purchaseOrders()->sum('paid') + (int) Transaction::where('ref_type', $s->getMorphClass())->where('ref_id', $s->id)->where('direction', 'out')->sum('amount'), 'due' => $s->due]);
        return ['columns' => ['supplier' => ['Supplier', 'text'], 'pos' => ['POs', 'num'], 'purchased' => ['Purchased (range)', 'money'], 'paid' => ['Paid (all time)', 'money'], 'due' => ['Due now', 'money']], 'rows' => $rows];
    }

    protected function supplierReturns($a, $b): array
    {
        $rows = SupplierReturn::with('supplier:id,name', 'items')->whereBetween('created_at', [$a, $b])->latest()->get()
            ->map(fn ($r) => ['code' => $r->code, 'supplier' => $r->supplier->name, 'status' => config('ops.supplier_return_status.'.$r->status.'.0'), 'resolution' => ucfirst($r->resolution), 'units' => $r->items->sum('qty'), 'value' => $r->value, 'created' => $r->created_at->toDateString()]);
        return ['columns' => ['code' => ['SR', 'text'], 'supplier' => ['Supplier', 'text'], 'status' => ['Status', 'text'], 'resolution' => ['Resolution', 'text'], 'units' => ['Units', 'num'], 'value' => ['Value', 'money'], 'created' => ['Created', 'date']], 'rows' => $rows];
    }

    // ── Finance
    protected function profitLoss($a, $b): array
    {
        $d = $this->orders($a, $b)->where('status', 'delivered')->selectRaw('COALESCE(SUM(total),0) revenue, COALESCE(SUM(cost),0) cost, COALESCE(SUM(delivery_charge),0) delivery, COALESCE(SUM(discount),0) discount')->first();
        $exp = Transaction::where('direction', 'out')->whereNotIn('category', ['Supplier', 'Refund'])->whereBetween('date', [$a->toDateString(), $b->toDateString()])->selectRaw('category, SUM(amount) a')->groupBy('category')->pluck('a', 'category');
        $refunds = (int) Transaction::where('category', 'Refund')->whereBetween('date', [$a->toDateString(), $b->toDateString()])->sum('amount');
        $gross = $d->revenue - $d->cost - $d->delivery - $refunds;
        $rows = collect([['line' => 'Delivered revenue', 'amount' => (int) $d->revenue], ['line' => '− Cost of goods (batch cost at packing)', 'amount' => -(int) $d->cost], ['line' => '− Delivery charges', 'amount' => -(int) $d->delivery], ['line' => '− Refunds', 'amount' => -$refunds], ['line' => '= Gross profit', 'amount' => $gross]]);
        foreach ($exp as $cat => $amt) $rows->push(['line' => '− '.$cat, 'amount' => -(int) $amt]);
        $rows->push(['line' => '= Net profit', 'amount' => $gross - (int) $exp->sum()]);
        return ['columns' => ['line' => ['Line', 'text'], 'amount' => ['Amount', 'money']], 'rows' => $rows, 'nototals' => true,
            'kpis' => [['Revenue', tk($d->revenue), 'ink'], ['Gross profit', tk($gross), $gross >= 0 ? 'ok' : 'bad'], ['Expenses', tk($exp->sum()), 'bad'], ['Net profit', tk($gross - $exp->sum()), ($gross - $exp->sum()) >= 0 ? 'ok' : 'bad']]];
    }

    protected function cashFlow($a, $b): array
    {
        $rows = Account::orderBy('id')->get()->map(function ($acc) use ($a, $b) {
            $net = fn ($q) => (int) $q->selectRaw("COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE -amount END),0) n")->value('n');
            $after = $net(Transaction::where('account_id', $acc->id)->where('date', '>', $b->toDateString()));
            $in = (int) Transaction::where('account_id', $acc->id)->where('direction', 'in')->whereBetween('date', [$a->toDateString(), $b->toDateString()])->sum('amount');
            $out = (int) Transaction::where('account_id', $acc->id)->where('direction', 'out')->whereBetween('date', [$a->toDateString(), $b->toDateString()])->sum('amount');
            $closing = $acc->balance - $after;
            return ['account' => $acc->name, 'opening' => $closing - $in + $out, 'in' => $in, 'out' => $out, 'closing' => $closing];
        });
        return ['columns' => ['account' => ['Account', 'text'], 'opening' => ['Opening', 'money'], 'in' => ['Money in', 'money'], 'out' => ['Money out', 'money'], 'closing' => ['Closing', 'money']], 'rows' => $rows];
    }

    protected function expenses($a, $b): array
    {
        $rows = Transaction::where('direction', 'out')->whereBetween('date', [$a->toDateString(), $b->toDateString()])->selectRaw('category, COUNT(*) entries, SUM(amount) amount')->groupBy('category')->orderByDesc('amount')->get();
        return ['columns' => ['category' => ['Category', 'text'], 'entries' => ['Entries', 'num'], 'amount' => ['Amount', 'money']], 'rows' => $rows];
    }

    protected function aging(): array
    {
        $bucket = fn ($days) => $days <= 7 ? 'd7' : ($days <= 15 ? 'd15' : ($days <= 30 ? 'd30' : 'd30p'));
        $rows = collect();
        foreach (Courier::get() as $c) {
            $r = ['party' => 'Courier (receivable)', 'name' => $c->name, 'd7' => 0, 'd15' => 0, 'd30' => 0, 'd30p' => 0];
            foreach (Shipment::where('courier_id', $c->id)->where('courier_status', 'delivered')->whereNull('settlement_id')->get(['cod_amount', 'delivered_at']) as $s) $r[$bucket((int) $s->delivered_at?->diffInDays(now()))] += $s->cod_amount;
            if ($r['d7'] + $r['d15'] + $r['d30'] + $r['d30p']) $rows->push($r);
        }
        foreach (Customer::real()->where('due', '>', 0)->get() as $c) { $r = ['party' => 'Customer (receivable)', 'name' => $c->name, 'd7' => 0, 'd15' => 0, 'd30' => 0, 'd30p' => 0]; $r[$bucket((int) $c->last_order_at?->diffInDays(now()))] = $c->due; $rows->push($r); }
        foreach (Supplier::where('due', '>', 0)->get() as $s) { $last = Batch::where('supplier_id', $s->id)->max('received_at'); $r = ['party' => 'Supplier (payable)', 'name' => $s->name, 'd7' => 0, 'd15' => 0, 'd30' => 0, 'd30p' => 0]; $r[$bucket($last ? (int) Carbon::parse($last)->diffInDays(now()) : 999)] = $s->due; $rows->push($r); }
        $rows = $rows->map(fn ($r) => $r + ['total' => $r['d7'] + $r['d15'] + $r['d30'] + $r['d30p']]);
        return ['columns' => ['party' => ['Type', 'text'], 'name' => ['Name', 'text'], 'd7' => ['0–7 days', 'money'], 'd15' => ['8–15 days', 'money'], 'd30' => ['16–30 days', 'money'], 'd30p' => ['30+ days', 'money'], 'total' => ['Total', 'money']], 'rows' => $rows];
    }

    /** CSV for Export / scheduled email */
    public function csv(array $r): string
    {
        $f = fopen('php://temp', 'r+');
        fputcsv($f, collect($r['columns'])->pluck(0)->all());
        foreach ($r['rows'] as $row) fputcsv($f, collect($r['columns'])->keys()->map(fn ($k) => $row[$k] ?? '')->all());
        rewind($f); $csv = stream_get_contents($f); fclose($f);
        return $csv;
    }
}
