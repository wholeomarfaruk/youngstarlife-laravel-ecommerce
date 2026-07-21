<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StickerController extends Controller
{

    protected static function parseOptions($options)
    {
        if (is_string($options)) {
            $options = json_decode($options, true);
        }

        return is_array($options) ? $options : [];
    }

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
                        $options = self::parseOptions($item->options);
                        $size = $options['size'] ?? '';
                        $size2 = $size ? ' (' . $size . ') ' : '';
                        $items .= Str::limit($item->product->name, 25) . $size2 . ' - ' . $item->quantity . " Qty ,\n";
                        if ($size) {
                            $size .= $size . ', ';
                        }
                    }
                } elseif ($order->Order_Item->count() == 1) {
                    $firstItem = $order->Order_Item->first();
                    $options = self::parseOptions($firstItem->options);
                    $size = (isset($options['size']) && !empty($options['size'])) ? ' (' . $options['size'] . ') ' : '';
                    $items .= Str::limit($firstItem->product->name, 55) . $size . ' x ' . $firstItem->quantity . ' Qty';

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
