<?php

namespace App\Livewire\Admin\Batches;

use App\Models\{Batch, Supplier};
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Product & Inventory', 'title' => 'Batches', 'tabs' => 'inventory'])]
#[Title('Batches')]
class Index extends Component
{
    use WithTable;

    #[Computed]
    public function batches()
    {
        $q = Batch::with('variant:id,product_id,sku,color,size', 'variant.product:id,name', 'supplier:id,name', 'purchaseOrder:id,number')
            ->when($this->search, fn ($q, $s) => $q->where('code', 'like', "%$s%")->orWhereHas('purchaseOrder', fn ($p) => $p->where('number', 'like', "%$s%"))->orWhereHas('variant', fn ($v) => $v->where('sku', 'like', "%$s%")))
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->filters['supplier'] ?? null, fn ($q, $s) => $q->where('supplier_id', $s));
        $this->dateRange($q, 'received_at');
        return $q->orderBy($this->sortField === 'id' ? 'received_at' : $this->sortField, $this->sortDir)->paginate($this->perPage);
    }

    #[Computed] public function stats(): array { return cache()->remember('batch.stats', 60, fn () => ['active' => Batch::where('remaining', '>', 0)->count(), 'units' => (int) Batch::sum('remaining'), 'value' => (int) Batch::selectRaw('SUM(remaining*unit_cost) v')->value('v'), 'low' => Batch::where('status', 'low')->count(), 'damaged' => (int) Batch::sum('damaged'), 'labels' => Batch::whereNull('labels_printed_at')->count()]); }

    public function render() { return view('livewire.admin.batches.index', ['suppliers' => Supplier::orderBy('name')->pluck('name', 'id')]); }
}
