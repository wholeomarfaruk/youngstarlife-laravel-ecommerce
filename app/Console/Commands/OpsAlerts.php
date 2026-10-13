<?php

namespace App\Console\Commands;

use App\Models\Ops\{Batch, Order, OrderItem, ProductVariant, PurchaseOrder, Shipment, Supplier, Warehouse};
use App\Services\Ops\OrderWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Daily Ops automations (Settings → Operations → Automations). */
class OpsAlerts extends Command
{
    protected $signature = 'ops:alerts';
    protected $description = 'Ops automations: delay alerts, auto-cancel, supplier reminders, low-stock purchase drafts';

    protected function on(string $rule): bool { return (bool) (ops_setting("automations.$rule.on") ?? false); }

    public function handle(OrderWorkflow $wf): int
    {
        if ($this->on('delay_alert')) {
            $stuck = Shipment::whereIn('courier_status', ['pending', 'picked_up', 'in_transit', 'out_for_delivery'])->where('status_updated_at', '<', now()->subDays(3))->count();
            if ($stuck) ops_notify('delay', "$stuck parcels with no movement for 3 days", route('ops.orders.in-courier', ['status' => 'in_transit']));
            $this->info("delay: $stuck");
        }

        // exchanges: new item delivered, old item still not back after N days (Settings → Orders)
        $late = app(\App\Services\Ops\ExchangeService::class)->overdueQuery()->count();
        if ($late) ops_notify('exchange', "$late exchange".($late === 1 ? '' : 's').': old item not back for '.(int) ops_setting('orders.exchange_overdue_days', 5).'+ days', route('ops.exchanges.index', ['state' => 'overdue']));
        $this->info("exchange overdue: $late");

        if (($h = (int) ops_setting('orders.auto_cancel_hours', 0)) > 0) {
            $n = 0;
            foreach (Order::live()->where('status', 'pending')->where('placed_at', '<', now()->subHours($h))->get() as $o) { $wf->setStatus($o, 'cancelled'); $o->log('automation', "Not confirmed within {$h} h → cancelled"); $n++; }
            $this->info("auto-cancel: $n");
        }

        if ($this->on('supplier_reminder')) {
            foreach (Supplier::where('due', '>', 0)->get() as $s) {
                $last = Batch::where('supplier_id', $s->id)->max('received_at');
                if ($last && now()->diffInDays($last) > 15) ops_notify('settle', "Supplier {$s->name} due ".tk($s->due).' for more than 15 days', route('ops.suppliers.index', ['search' => $s->name]));
            }
        }

        if ($this->on('low_stock_po')) {   // 21-day reorder qty, one draft PO per supplier of the last batch
            $low = ProductVariant::with('product:id,low_stock_threshold')->whereColumn('stock', '<', DB::raw('(select low_stock_threshold from ops_products where ops_products.id = ops_product_variants.product_id)'))->get();
            $bySupplier = [];
            foreach ($low as $v) {
                $b = Batch::where('variant_id', $v->id)->latest('received_at')->first(); if (! $b) continue;
                if (PurchaseOrder::whereIn('status', ['draft', 'sent', 'partial'])->whereHas('items', fn ($i) => $i->where('variant_id', $v->id))->exists()) continue;
                $sold = (int) OrderItem::where('variant_id', $v->id)->whereHas('order', fn ($o) => $o->live()->where('placed_at', '>=', now()->subDays(30)))->sum('qty');
                $bySupplier[$b->supplier_id][] = ['variant_id' => $v->id, 'qty' => max((int) ceil($sold / 30 * 21) - $v->stock, $v->product->low_stock_threshold * 2 - $v->stock, 5), 'unit_cost' => $b->unit_cost];
            }
            foreach ($bySupplier as $sid => $items) {
                $po = PurchaseOrder::create(['number' => 'PO-'.(PurchaseOrder::max('id') + 101), 'supplier_id' => $sid, 'warehouse_id' => Warehouse::where('is_default', true)->value('id') ?? Warehouse::value('id'), 'ordered_at' => today(), 'expected_at' => today()->addWeek(), 'note' => 'Auto-drafted: low stock']);
                foreach ($items as $i) $po->items()->create($i);
                ops_log('Low-stock purchase draft '.$po->number, $po);
            }
            $this->info('low-stock drafts: '.count($bySupplier));
        }
        return self::SUCCESS;
    }
}
