<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class StickerController extends Controller
{

    public static function generate(Request $request)
    {

        $idsString = $request->ids;
        $ids = explode(',', $idsString);

        $stickers = [];

        foreach ($ids as $id) {
            $order = \App\Models\Order::where('id', $id)->where('consignment_id', '!=', null)->first();
            if ($order) {
                $items = '';
                $size = '';
                if ($order->Order_Item->count() > 1) {


                    foreach ($order->Order_Item as $item) {
                        $options = json_decode($item->options, true);
                        $size = $options['size'] ?? '';
                        $size2 = $options['size'] ? ' (' . $options['size'] . ') ' : '';
                        $items .= $item->product->name . $size2 . ' - ' . $item->quantity . " Qty ,\n";
                        if ($size) {
                            $size .= $options['size'] . ', ';
                        }
                    }
                } elseif ($order->Order_Item->count() == 1) {
                    $firstItem = $order->Order_Item->first();
                    $options = json_decode($firstItem->options, true);
                    $size = (isset($options['size']) && !empty($options['size'])) ? ' (' . $options['size'] . ') ' : '';
                    $items .= $firstItem->product->name . $size . ' x ' . $firstItem->quantity . ' Qty';

                } else {
                    $items = 'No items';
                    $size = '';
                }
                $stickers[] = [
                    'id' => $order->id,
                    'name' => $order->name,
                    'phone' => $order->phone,
                    'price' => $order->total,
                    'items' => $items,
                    'consignment_id'=>$order->consignment_id
                ];
            }
        }
        // return view('templates.label', [
        //     'stickers' => $stickers
        // ]);
        $pdf = Pdf::loadView('templates.label', compact('stickers'));

        $pdf->setPaper([0, 0, 200, 280], 'portrait');
        // If you need zero margins for the whole document, try setting options directly
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            // Other options here if needed
        ]);
        return $pdf->stream('label.pdf');
    }
}
