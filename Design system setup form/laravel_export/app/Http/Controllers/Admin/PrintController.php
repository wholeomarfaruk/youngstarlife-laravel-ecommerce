<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Batch, Order};
use Illuminate\Http\Request;

/** Plain Blade print pages (no Livewire) → instant, print-friendly. */
class PrintController extends Controller
{
    public function invoices(Request $r)
    {
        $orders = Order::with('customer', 'items.variant.product:id,name', 'zone')->whereIn('id', explode(',', (string) $r->query('ids')))->get();
        return view('admin.print.invoices', compact('orders'));
    }

    public function labels(Request $r)
    {
        $batches = Batch::with('variant.product:id,name')->where('purchase_order_id', $r->query('po'))->get();
        return view('admin.print.labels', compact('batches'));
    }
}
