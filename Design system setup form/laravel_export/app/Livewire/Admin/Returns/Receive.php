<?php

namespace App\Livewire\Admin\Returns;

use App\Models\{Batch, Courier, CustomerReturn, ProductVariant, Shipment, Transaction, Account};
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Orders & Packing', 'title' => 'Return receive', 'tabs' => 'orders'])]
#[Title('Return receive')]
class Receive extends Component
{
    use WithTable;

    #[Url] public ?int $courier = null;
    public bool $fast = false;                // parcel scan = all items good
    public ?int $activeId = null;             // shipment being checked
    public array $lines = [];                 // order_item_id => [got, condition, reason]
    public string $note = '';
    public ?array $flash = null;

    public function mount(): void { $this->courier ??= Courier::where('is_active', true)->value('id'); }

    #[Computed] public function couriers() { return Courier::where('is_active', true)->withCount(['shipments as returning' => fn ($q) => $q->whereIn('courier_status', ['returning', 'cancelled', 'partial_delivered'])->whereDoesntHave('order.returns')])->get(['id', 'name']); }

    /** Parcels this courier is sending back (not yet received here). */
    #[Computed]
    public function expected()
    {
        return Shipment::where('courier_id', $this->courier)->whereIn('courier_status', ['returning', 'cancelled', 'partial_delivered'])
            ->whereNotExists(fn ($q) => $q->from('customer_returns')->whereColumn('customer_returns.shipment_id', 'shipments.id'))
            ->with('order:id,number,customer_id,status', 'order.customer:id,name', 'order.items.variant.product:id,name')->get();
    }

    #[Computed] public function active(): ?Shipment { return $this->activeId ? $this->expected->firstWhere('id', $this->activeId) : null; }

    #[Computed] public function received() { return CustomerReturn::with('order:id,number,customer_id', 'order.customer:id,name', 'items.batch:id,code', 'items.orderItem.variant.product:id,name')->when($this->search, fn ($q, $s) => $q->where('code', 'like', "%$s%")->orWhereHas('order', fn ($o) => $o->where('number', 'like', "%$s%")))->latest()->limit(30)->get(); }

    public function scan(string $code): void
    {
        $s = $this->expected->first(fn ($p) => in_array(strtoupper($code), [strtoupper($p->code), strtoupper((string) $p->tracking), strtoupper($p->order->number), '#'.strtoupper($code)]));
        if ($s) {
            $this->activeId = $s->id;
            $this->lines = $s->order->items->mapWithKeys(fn ($i) => [$i->id => ['got' => $this->fast, 'condition' => 'good', 'reason' => 'Customer Changed Mind']])->all();
            $this->flash = ['tone' => 'info', 'title' => 'Parcel matched · '.$s->order->number, 'sub' => 'Scan each product inside'];
            if ($this->fast) $this->confirm();
            return;
        }
        if ($this->active && ($v = ProductVariant::where('barcode', $code)->first())) {
            $item = $this->active->order->items->firstWhere('variant_id', $v->id);
            if ($item) { $this->lines[$item->id]['got'] = true; $this->flash = ['tone' => 'ok', 'title' => '✓ '.$v->label, 'sub' => 'Item verified']; return; }
            $this->flash = ['tone' => 'bad', 'title' => 'Wrong item for this parcel', 'sub' => $v->label]; return;
        }
        $this->flash = ['tone' => 'bad', 'title' => 'Not in this courier\'s return list', 'sub' => $code];
    }

    public function confirm(StockService $stock = null): void
    {
        $stock ??= app(StockService::class);
        $s = $this->active; abort_unless($s, 422);
        DB::transaction(function () use ($s, $stock) {
            $o = $s->order; $all = collect($this->lines)->every(fn ($l) => $l['got']);
            $ret = CustomerReturn::create(['code' => 'R'.(CustomerReturn::max('id') + 1001), 'order_id' => $o->id, 'shipment_id' => $s->id, 'courier_id' => $s->courier_id,
                'type' => $all ? 'full' : 'partial', 'status' => 'inspected', 'note' => $this->note, 'received_by' => auth()->id(),
                'refund_amount' => max(0, $o->advance - $o->delivery_charge)]);
            foreach ($o->items as $i) {
                $l = $this->lines[$i->id] ?? null; if (! $l) continue;
                $cond = $l['got'] ? $l['condition'] : 'missing';
                $batch = $i->allocations()->value('batch_id');
                $action = ['good' => 'restock', 'damaged' => 'damaged', 'used' => 'damaged', 'wrong_item' => 'investigate', 'missing' => 'courier_claim'][$cond];
                $ret->items()->create(['order_item_id' => $i->id, 'batch_id' => $batch, 'qty' => $i->qty, 'reason' => $l['reason'], 'condition' => $cond, 'action' => $action]);
                if ($batch && $cond === 'good') $stock->move(Batch::find($batch), 'return_restock', $i->qty, $ret);   // back into the ORIGINAL batch
                if ($batch && $action === 'damaged') $stock->damage(Batch::find($batch), $i->qty, $ret);
                $i->increment('returned_qty', $l['got'] ? $i->qty : 0);
            }
            $o->update(['status' => 'returned']); $o->log('return', 'Return '.$ret->code.' received');
        });
        $this->dispatch('toast', message: 'Parcel received · stock updated');
        $this->reset('activeId', 'lines', 'note'); unset($this->expected, $this->received);
    }

    public function refund(int $id, string $via): void
    {
        $r = CustomerReturn::findOrFail($id); abort_if($r->refunded_at, 422);
        Transaction::create(['account_id' => Account::where('name', $via)->value('id'), 'direction' => 'out', 'category' => 'Refund', 'amount' => $r->refund_amount, 'description' => 'Refund · '.$r->code, 'date' => today(), 'ref_type' => $r->getMorphClass(), 'ref_id' => $r->id, 'user_id' => auth()->id()]);
        $r->update(['refunded_at' => now(), 'refund_via' => $via, 'status' => 'closed']);
        $this->dispatch('toast', message: 'Refunded '.tk($r->refund_amount));
    }

    public function render() { return view('livewire.admin.returns.receive'); }
}
