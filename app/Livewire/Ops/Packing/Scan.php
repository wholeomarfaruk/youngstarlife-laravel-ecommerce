<?php

namespace App\Livewire\Ops\Packing;

use App\Models\Ops\{Order, ProductVariant};
use App\Services\Ops\BatchAllocator;
use App\Livewire\Ops\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Orders & Packing', 'title' => 'Packing scan', 'tabs' => 'orders'])]
#[Title('Packing scan')]
class Scan extends Component
{
    #[Url] public ?string $order = null;
    public array $scanned = [];          // variant_id => qty
    public ?array $flash = null;         // ['tone'=>'ok|bad|warn','title'=>'','sub'=>'']

    #[Computed]
    public function current(): ?Order
    {
        return $this->order ? Order::live()->numbered($this->order)->with('items.variant.product:id,name', 'items.allocations.batch:id,code')->first() : null;
    }

    public const PACKABLE = ['pending', 'confirmed', 'processing'];   // "Pack & scan" is offered from pending too (stock is reserved at pack)

    /** Opened from "Pack & scan" (?order=#1042): only orders waiting to be packed may be packed. */
    public function mount(): void { if ($this->order) $this->openOrder($this->order); }

    protected function openOrder(string $code): void
    {
        $this->order = $code; $this->scanned = []; unset($this->current);
        if (! $o = $this->current) { $this->flash = ['tone' => 'bad', 'title' => 'Order not found', 'sub' => $code]; $this->order = null; unset($this->current); return; }
        if (! in_array($o->status, self::PACKABLE)) {
            $this->flash = ['tone' => 'warn', 'title' => 'Order '.$o->number.' is '.strtolower(config("ops.order_status.{$o->status}.0", $o->status)), 'sub' => 'Only confirmed or processing orders can be packed'];
            $this->order = null; unset($this->current); return;
        }
        $this->flash = ['tone' => 'info', 'title' => 'Order '.$o->number.' opened', 'sub' => 'Scan each product'];
    }

    /** Called by the scanner input (Alpine 'scanner'). Accepts order no., product barcode, SKU or batch label (SKU-B014). */
    public function scan(string $code): void
    {
        $code = trim($code);
        if ($code === '') return;
        if (preg_match('/^P\d+$/i', $code) && ($parcel = \App\Models\Ops\Shipment::where('code', strtoupper($code))->with('order:id,number')->first())) { $this->openOrder($parcel->order->number); return; }
        $v = ProductVariant::findByScan($code);
        // digits = order number, unless it is a product barcode (EAN/UPC barcodes are all digits too)
        if (str_starts_with($code, '#') || (ctype_digit($code) && ! $v)) { $this->openOrder($code); return; }
        if (! $this->current) { $this->flash = ['tone' => 'warn', 'title' => 'Scan the order first', 'sub' => $code]; return; }
        $item = $v ? $this->current->items->firstWhere('variant_id', $v->id) : null;
        if (! $item) { $this->flash = ['tone' => 'bad', 'title' => 'Wrong product', 'sub' => ($v?->label ?? $code).' is not in this order']; return; }
        if (($this->scanned[$v->id] ?? 0) >= $item->qty) { $this->flash = ['tone' => 'warn', 'title' => 'Already scanned', 'sub' => $v->label]; return; }
        $this->scanned[$v->id] = ($this->scanned[$v->id] ?? 0) + 1;
        $this->flash = ['tone' => 'ok', 'title' => '✓ '.$v->label, 'sub' => $this->progress()];
    }

    public function progress(): string { $need = $this->current?->items->sum('qty') ?? 0; return array_sum($this->scanned).' of '.$need.' items'; }

    public function complete(): void
    {
        $o = $this->current;
        if (! $o || array_sum($this->scanned) !== $o->items->sum('qty')) { $this->flash = ['tone' => 'warn', 'title' => 'Scan every item first', 'sub' => $this->progress()]; return; }
        if (! in_array($o->status, self::PACKABLE)) { $this->flash = ['tone' => 'warn', 'title' => 'Order '.$o->number.' can no longer be packed', 'sub' => 'Status: '.$o->status]; return; }
        $allocator = app(BatchAllocator::class);
        $allocator->allocate($o);
        if ($short = $allocator->shortfall($o)) { $this->flash = ['tone' => 'bad', 'title' => 'Not enough stock in batches', 'sub' => $short.' unit(s) have no batch — receive stock in Purchases first']; return; }
        $allocator->pack($o);
        $parcel = $o->shipment()->value('code');
        $this->dispatch('toast', message: $o->number.' packed · cost captured'.($parcel ? ' · parcel '.$parcel : ''));
        $this->reset('order', 'scanned', 'flash'); unset($this->current);
    }

    public function render() { return view('livewire.ops.packing.scan'); }
}
