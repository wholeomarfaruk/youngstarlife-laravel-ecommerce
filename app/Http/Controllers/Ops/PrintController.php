<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\Ops\{Batch, CustomerReturn, Order, PurchaseOrder};
use Illuminate\Http\Request;

/** Plain Blade print pages (no Livewire) → instant, print-friendly. */
class PrintController extends Controller
{
    protected function ids(?string $csv): array { return array_values(array_filter(array_map('intval', explode(',', (string) $csv)))); }

    public function invoices(Request $r)
    {
        $orders = Order::with('customer', 'items.variant.product:id,name', 'zone', 'shipment.courier')->whereIn('id', $this->ids($r->query('ids')))->get();
        foreach ($orders as $o) $o->log('print', 'Invoice printed');
        return view('ops.print.invoices', compact('orders'));
    }

    public function parcelLabels(Request $r)
    {
        $orders = Order::with('customer', 'items.variant', 'shipment.courier', 'zone')->whereIn('id', $this->ids($r->query('ids')))->get();
        return view('ops.print.parcel-labels', compact('orders'));
    }

    /** Product stickers. ?po=ID&q[batch]=copies · ?batches=1,2 · ?products=1,2 (copies = remaining) · &size=50x30|40x25|38x25x2 &price=0|1 */
    public function labels(Request $r)
    {
        $qty = array_filter(array_map('intval', (array) $r->query('q', [])));
        $q = Batch::with('variant.product:id,name,price,sale_price');
        if ($r->filled('po')) $q->where('purchase_order_id', $r->query('po'))->when($qty, fn ($q) => $q->whereIn('id', array_keys($qty)));
        elseif ($r->filled('batches')) $q->whereIn('id', $this->ids($r->query('batches')));
        elseif ($r->filled('products')) $q->whereHas('variant', fn ($v) => $v->whereIn('product_id', $this->ids($r->query('products'))))->where('remaining', '>', 0);
        else $q->whereRaw('1 = 0');
        $batches = $q->get()->each(fn ($b) => $b->copies = $qty[$b->id] ?? ($r->filled('po') ? max(0, $b->qty - $b->damaged) : $b->remaining));
        $size = in_array($r->query('size'), ['50x30', '40x25', '38x25x2']) ? $r->query('size') : ops_setting('barcode.size', '50x30');
        $price = $r->has('price') ? $r->boolean('price') : (bool) ops_setting('barcode.price_on_label', true);
        return view('ops.print.labels', compact('batches', 'size', 'price'));
    }

    public function purchaseOrder(PurchaseOrder $po)
    {
        $po->load('supplier', 'items.variant.product:id,name', 'warehouse');
        $po->load(['items.variant.product.suppliers' => fn ($s) => $s->where('ops_suppliers.id', $po->supplier_id)]);
        return view('ops.print.purchase-order', compact('po'));
    }

    public function entrySession(\App\Models\Ops\CourierSession $session)
    {
        $session->load('courier', 'opener:id,name', 'items.shipment.order.customer:id,name', 'items.customerReturn:id,code');
        return view('ops.print.entry-session', compact('session'));
    }

    public function returnReceipt(string $session)
    {
        $returns = CustomerReturn::where('session', $session)->with('order.customer', 'items.orderItem.variant.product:id,name', 'items.batch:id,code', 'courier', 'receiver')->get();
        abort_if($returns->isEmpty(), 404);
        return view('ops.print.return-receipt', compact('returns', 'session'));
    }
}
