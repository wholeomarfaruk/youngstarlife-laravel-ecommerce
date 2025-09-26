<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrderExport implements FromCollection, WithHeadings, WithMapping
{
    protected $status;

    // Accept status in constructor
    public function __construct($status = null)
    {
        $this->status = $status;
    }
    /**
     * Return the data collection for the export
     */
    public function collection()
    {
        if ($this->status) {
            return Order::with('Order_Item')
                ->where('status', $this->status)
                ->select('id', 'name', 'address', 'total', 'updated_at')
                ->get();
        }else{
            return Order::with('Order_Item')->select('id', 'name', 'address', 'total', 'updated_at')->get();
        }
    }

    public function map($order): array
    {
        $firstItem = $order->order_item->first();
        $size = $firstItem ? json_decode($firstItem->options, true)['size'] ?? '' : '';
        $productName = $firstItem ? $firstItem->product->name : '';
        if ($size) {
            $productName .= " ($size)";
        }

        return [
            $order->updated_at,
            $order->id,
            $order->name,
            $order->address,
            $order->total,
            $firstItem ? $productName : '',        // Item Description
            $firstItem ? $size : '',     // Size
        ];
    }



    /**
     * Return the header row for the Excel file
     */
    public function headings(): array
    {
        return ['Date', 'ID', 'Customer Name', 'Address', 'Total', 'Item Description', 'Size'];
    }
}
