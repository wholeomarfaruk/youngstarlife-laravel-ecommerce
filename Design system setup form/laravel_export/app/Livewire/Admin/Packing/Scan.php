<?php

namespace App\Livewire\Admin\Packing;

use App\Models\{Order, ProductVariant};
use App\Services\BatchAllocator;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Orders & Packing', 'title' => 'Packing scan', 'tabs' => 'orders'])]
#[Title('Packing scan')]
class Scan extends Component
{
    #[Url] public ?string $order = null;
    public array $scanned = [];          // variant_id => qty
    public ?array $flash = null;         // ['tone'=>'ok|bad|warn','title'=>'','sub'=>'']

    #[Computed]
    public function current(): ?Order
    {
        return $this->order ? Order::live()->where('number', '#'.ltrim($this->order, '#'))->with('items.variant.product:id,name', 'items.allocations.batch:id,code')->first() : null;
    }

    /** Called by the scanner input (Alpine 'scanner'). Accepts order no., parcel code or product barcode/SKU. */
    public function scan(string $code): void
    {
        $code = trim($code);
        if (str_starts_with($code, '#') || ctype_digit($code)) { $this->order = $code; $this->scanned = []; unset($this->current); $this->flash = $this->current ? ['tone' => 'info', 'title' => 'Order '.$this->current->number.' opened', 'sub' => 'Scan each product'] : ['tone' => 'bad', 'title' => 'Order not found', 'sub' => $code]; return; }
        if (! $this->current) { $this->flash = ['tone' => 'warn', 'title' => 'Scan the order first', 'sub' => $code]; return; }
        $v = ProductVariant::where('barcode', $code)->orWhere('sku', $code)->first();
        $item = $v ? $this->current->items->firstWhere('variant_id', $v->id) : null;
        if (! $item) { $this->flash = ['tone' => 'bad', 'title' => 'Wrong product', 'sub' => ($v?->label ?? $code).' is not in this order']; return; }
        if (($this->scanned[$v->id] ?? 0) >= $item->qty) { $this->flash = ['tone' => 'warn', 'title' => 'Already scanned', 'sub' => $v->label]; return; }
        $this->scanned[$v->id] = ($this->scanned[$v->id] ?? 0) + 1;
        $this->flash = ['tone' => 'ok', 'title' => '✓ '.$v->label, 'sub' => $this->progress()];
    }

    public function progress(): string { $need = $this->current?->items->sum('qty') ?? 0; return array_sum($this->scanned).' of '.$need.' items'; }

    public function complete(): void
    {
        abort_unless($this->current && array_sum($this->scanned) === $this->current->items->sum('qty'), 422);
        app(BatchAllocator::class)->pack($this->current);
        $this->dispatch('toast', message: $this->current->number.' packed · cost captured');
        $this->reset('order', 'scanned', 'flash'); unset($this->current);
    }

    public function render() { return view('livewire.admin.packing.scan'); }
}
