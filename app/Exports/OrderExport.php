<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

class OrderExport implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    protected $status;
    protected $totalSum = 0;
    protected $serial = 0;

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
        if($this->status && ($this->status == 'courier_entered')){

            return Order::with('Order_Item')
                ->where('consignment_id', '!=', '')
                ->select('id', 'name', 'address','phone', 'total', 'status', 'consignment_id', 'courier_status', 'notes', 'updated_at')
                ->get();

        }elseif($this->status &&  $this->status == 'courier_not_entered'){

            return Order::with('Order_Item')
                ->where('consignment_id', null)
                ->select('id', 'name', 'address','phone', 'total', 'status', 'consignment_id', 'courier_status', 'notes', 'updated_at')
                ->get();
        }
        if ($this->status) {
            return Order::with('Order_Item')
                ->where('status', $this->status)
                ->select('id', 'name', 'address','phone', 'total', 'status', 'consignment_id', 'courier_status', 'notes', 'updated_at')
                ->get();
        }else{
            return Order::with('Order_Item')->select('id', 'name','phone', 'address', 'total', 'status', 'consignment_id', 'courier_status', 'notes', 'updated_at')->get();
        }
    }

    public function map($order): array
    {
        $itemLines = $order->order_item->map(function ($item) {
            $size = $item->options['size'] ?? null;
            $line = $item->product->name;
            if ($size) {
                $line .= " (size: {$size})";
            }
            return $line . ' x ' . $item->quantity . 'pcs';
        });

        $this->totalSum += (float) $order->total;
        $this->serial++;

        return [
            $this->serial,
            $order->updated_at,
            $order->id,
            $order->name,
            $order->address,
            $order->phone,
            $order->total,
            $order->status ? str_replace('_', ' ', $order->status) : '',
            $order->consignment_id ?: '',
            $order->courier_status ? str_replace('_', ' ', $order->courier_status->value) : '',
            $itemLines->implode(', '),        // Item Description
            $order->notes ?: '',
        ];
    }



    /**
     * Return the header row for the Excel file
     */
    public function headings(): array
    {
        return ['SL', 'Date', 'ID', 'Customer Name', 'Address', 'Phone', 'Total', 'Status', 'Consignment ID', 'Courier Status', 'Item Description', 'Note'];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Make room for the two headline rows above the header row.
                $sheet->insertNewRowBefore(1, 2);

                $sheet->setCellValue('A1', 'YoungStar Life');
                $sheet->mergeCells('A1:L1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                $sheet->setCellValue('A2', 'Export date: ' . now()->format('d M Y, h:i A'));
                $sheet->mergeCells('A2:L2');
                $sheet->getStyle('A2')->getFont()->setItalic(true);

                $lastRow = $sheet->getHighestRow();
                $sumRow = $lastRow + 1;

                $sheet->setCellValue("F{$sumRow}", 'Total');
                $sheet->setCellValue("G{$sumRow}", $this->totalSum);
                $sheet->getStyle("F{$sumRow}:G{$sumRow}")->getFont()->setBold(true);
            },
        ];
    }
}
