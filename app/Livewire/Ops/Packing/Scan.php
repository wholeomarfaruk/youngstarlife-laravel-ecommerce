<?php

namespace App\Livewire\Ops\Packing;

use App\Models\Ops\{Batch, Order, ProductVariant, Shipment};
use App\Services\Ops\BatchAllocator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

/**
 * Orders → Packing scan. Open an order (scan #1042 / parcel P1005, or pick it from the queue), then put each product
 * in the parcel: scan its barcode / SKU / batch label (SKU-B014) / batch code (B014), or tick it by hand (marked "manual").
 * A combo is checked part by part. A product that isn't on the order gives a warning — staff may still add it (free or charged).
 * "Mark packed" → BatchAllocator::pack (stock out, cost, parcel); then the next order in the queue opens.
 */
#[Layout('layouts.ops', ['module' => 'Orders & Packing', 'title' => 'Packing scan', 'tabs' => 'orders'])]
#[Title('Packing scan')]
class Scan extends Component
{
    public const PACKABLE = ['pending', 'confirmed'];   // "Pack & scan" is offered from pending too (stock is reserved at pack)

    #[Url] public ?string $order = null;
    public array $scanned = [];          // line key => units scanned
    public array $manual = [];           // line key => units ticked by hand (not scanned)
    public array $history = [];          // line keys in scan order (undo)
    public ?array $flash = null;         // ['tone' => ok|bad|warn|info, 'title', 'sub']
    public ?int $extraId = null;         // scanned product that is NOT on the order → add it?
    public ?int $pick = null;            // "Add product to parcel" picker
    public bool $autoNext = true;        // open the next order in the queue after packing
    public ?array $done = null;          // last packed: ['number', 'id', 'parcel']
    public array $choices = [];          // the order has several parcels to pack → [['number', 'label', 'units', 'parcel']] to choose from

    public function mount(): void { if ($this->order) $this->openOrder($this->order); }

    #[Computed]
    public function current(): ?Order
    {
        return $this->order ? Order::live()->numbered($this->order)
            ->with('customer:id,name,phone,delivered_count,returned_count', 'zone:id,name', 'items.variant.product.comboItems.variant.product', 'items.allocations.batch:id,code,variant_id')->first() : null;
    }

    /**
     * What physically goes in the parcel, one line per product: a combo becomes its parts (the sizes chosen on the website,
     * else the combo's components). [key => [key, item_id, variant_id, label, sku, barcode, image, need, combo, batches]]
     */
    #[Computed]
    public function lines(): array
    {
        $o = $this->current; if (! $o) return [];
        $chosen = ProductVariant::with('product:id,name,featured_image')->whereIn('id', $o->items->flatMap(fn ($i) => collect($i->combo_selection ?? [])->pluck('variant_id'))->filter()->unique())->get()->keyBy('id');
        $out = [];
        foreach ($o->items as $i) {
            $p = $i->variant->product;
            $parts = match (true) {
                (bool) $i->combo_selection => collect($i->combo_selection)->groupBy('variant_id')->map(fn ($g, $vid) => ['v' => $chosen[$vid] ?? null, 'qty' => $g->sum(fn ($c) => (int) ($c['qty'] ?? 1)) * $i->qty])->values(),
                $p?->type === 'combo' && $p->comboItems->isNotEmpty() => $p->comboItems->map(fn ($c) => ['v' => $c->variant, 'qty' => $c->qty * $i->qty]),
                default => collect([['v' => $i->variant, 'qty' => $i->qty]]),
            };
            $isCombo = $parts->count() > 1 || ($parts->first()['v']?->id ?? null) !== $i->variant_id;
            foreach ($parts as $part) {
                if (! $v = $part['v']) continue;
                $key = 'i'.$i->id.'v'.$v->id;
                $out[$key] = ['key' => $key, 'item_id' => $i->id, 'variant_id' => $v->id, 'label' => $v->label, 'sku' => $v->sku, 'barcode' => $v->barcode,
                    'image' => $v->product?->thumb_url, 'need' => (int) $part['qty'], 'combo' => $isCombo ? $i->variant->label : null, 'free' => (int) $i->unit_price === 0,
                    'batches' => $i->allocations->filter(fn ($a) => $a->batch?->variant_id == $v->id)->pluck('batch.code')->unique()->join(', ')];
            }
        }
        return $out;
    }

    public function needTotal(): int { return array_sum(array_column($this->lines, 'need')); }
    public function doneTotal(): int { return array_sum($this->scanned); }
    public function progress(): string { return $this->doneTotal().' of '.$this->needTotal().' items'; }
    public function isComplete(): bool { return $this->current && $this->needTotal() > 0 && collect($this->lines)->every(fn ($l) => ($this->scanned[$l['key']] ?? 0) >= $l['need']); }

    /** orders waiting to be packed (confirmed first, oldest first) */
    #[Computed]
    public function queue()
    {
        return Order::live()->whereIn('status', self::PACKABLE)->with('customer:id,name')->withSum('items as units', 'qty')
            ->withExists(['shipments as resent' => fn ($s) => $s->whereNotNull('superseded_at')])
            ->orderByRaw("status = 'confirmed' desc")->orderBy('placed_at')->limit(40)->get(['id', 'number', 'status', 'customer_id', 'ship_name', 'placed_at', 'exchange_of_id']);
    }
    #[Computed] public function queueCount(): int { return Order::live()->whereIn('status', self::PACKABLE)->count(); }
    #[Computed] public function packedToday(): int { return Shipment::whereDate('packed_at', today())->count(); }
    #[Computed] public function variants() { return ProductVariant::with('product:id,name,featured_image')->where('is_active', true)->get(); }

    // ── opening an order

    public function open(string $number): void { $this->openOrder($number, true); }   // queue rows are exact parcels

    /** what parcel of the order this record packs: Delivery / Re-send / Exchange N */
    public static function kind(Order $o, ?bool $resent = null): string
    {
        if ($o->exchange_of_id) return 'Exchange '.\Illuminate\Support\Str::afterLast($o->number, '-EX');
        return ($resent ?? $o->shipments()->whereNotNull('superseded_at')->exists()) ? 'Re-send' : 'Delivery';
    }

    /**
     * One order can have several parcels (its delivery / re-send, and each exchange). Opening an order number packs the one
     * waiting; when several are waiting staff choose. An exchange number (#1042-EX1) or a parcel code (P1005) opens exactly that one.
     */
    protected function openOrder(string $code, bool $exact = false): void
    {
        $this->reset('scanned', 'manual', 'history', 'extraId', 'done', 'choices'); $this->order = null; unset($this->current, $this->lines, $this->parcels);
        $o = Order::live()->numbered($code)->first();
        if (! $o) { $this->beep('bad', 'Order not found', $code); return; }
        $exact = $exact || $o->exchange_of_id;   // an exchange number was scanned → that exchange's parcel
        $root = $o->exchange_of_id ? ($o->exchangeOf ?? $o) : $o;
        $targets = $exact ? collect([$o]) : collect([$root])->merge($root->exchanges()->live()->get());
        $waiting = $targets->filter(fn ($t) => in_array($t->status, self::PACKABLE))->values();
        if ($waiting->isEmpty()) {
            $this->beep('warn', ($exact ? self::kind($o).' of ' : 'Nothing to pack in ').$root->number.($exact ? ' is '.strtolower(config("ops.order_status.{$o->status}.0", $o->status)) : ''),
                $targets->map(fn ($t) => self::kind($t).': '.config("ops.order_status.{$t->status}.0", $t->status))->join(' · ').' — only pending or confirmed parcels can be packed');
            return;
        }
        if ($waiting->count() > 1) {
            $this->choices = $waiting->map(fn ($t) => ['number' => $t->number, 'label' => self::kind($t), 'units' => (int) $t->items()->sum('qty'), 'parcel' => $t->shipment?->packed_at ? null : $t->shipment?->code])->all();
            $this->beep('warn', $root->number.' has '.$waiting->count().' parcels to pack', 'Choose which one you are packing now');
            return;
        }
        $t = $waiting->first();
        $this->order = $t->number; unset($this->current, $this->lines, $this->parcels);
        $this->beep('info', 'Packing '.self::kind($t).' of '.$root->number, ($this->packingParcel() ?? 'New parcel').' · scan each product — or tick it by hand');
    }

    /** pick one of several parcels of the order */
    public function choose(string $number): void { $this->openOrder($number, true); }

    /** the parcel this scan fills: an unpacked parcel already booked with the courier, else a new one made at "Mark packed" */
    public function packingParcel(): ?string
    {
        $s = $this->current?->shipment;
        return $s && ! $s->packed_at ? 'Parcel '.$s->code.($s->booked_at ? ' (already booked with '.$s->courier?->name.')' : '') : null;
    }

    /** every parcel of the order (its own, re-sends, exchanges), newest first — so the packer sees the whole picture */
    #[Computed]
    public function parcels()
    {
        $o = $this->current; if (! $o) return collect();
        $root = $o->exchange_of_id ? $o->exchangeOf : $o;
        $ids = $root ? $root->exchanges()->pluck('id')->push($root->id) : collect([$o->id]);
        return Shipment::whereIn('order_id', $ids)->with('order:id,number,exchange_of_id', 'courier:id,name')->latest('id')->get();
    }

    // ── scanning

    /** scanner input (Alpine 'scanner'): order no. / parcel P1005, or a product barcode / SKU / batch label SKU-B014 / batch code B014 */
    public function scan(string $code): void
    {
        $code = trim($code); if ($code === '') return;
        if (preg_match('/^P\d+$/i', $code) && ($parcel = Shipment::where('code', strtoupper($code))->with('order:id,number')->first())) { $this->openOrder($parcel->order->number, true); return; }
        $v = $this->variantFor($code);
        // digits = order number, unless it is a product barcode (EAN / UPC are all digits too)
        if (str_starts_with($code, '#') || (ctype_digit($code) && ! $v) || (! $v && Order::live()->numbered($code)->exists())) { $this->openOrder($code); return; }
        if (! $this->current) { $this->beep('warn', 'Scan the order first', $code); return; }
        if (! $v) { $this->beep('bad', 'Unknown code', $code.' is not a product, batch or order'); return; }
        $this->count($v, false);
    }

    /** barcode / SKU / SKU-B014 → variant; a bare batch code (B014) → that batch's variant */
    protected function variantFor(string $code): ?ProductVariant
    {
        return ProductVariant::findByScan($code) ?? (($b = Batch::where('code', strtoupper($code))->first()) ? ProductVariant::find($b->variant_id) : null);
    }

    /** one unit of $v into the parcel: the first open line for it, else the "not on this order" warning */
    protected function count(ProductVariant $v, bool $byHand): void
    {
        $lines = collect($this->lines)->where('variant_id', $v->id);
        $line = $lines->first(fn ($l) => ($this->scanned[$l['key']] ?? 0) < $l['need']);
        if (! $line) {
            if ($lines->isNotEmpty()) { $this->beep('warn', 'Already complete', $v->label.' — '.$lines->sum('need').' needed, all in'); return; }
            $this->extraId = $v->id;
            $this->beep('bad', 'Not on this order', $v->label.' — add it to the parcel anyway?');
            return;
        }
        $this->add($line['key'], $byHand);
        $this->beep('ok', '✓ '.$v->label.($byHand ? ' (ticked by hand)' : ''), $this->progress());
    }

    protected function add(string $key, bool $byHand): void
    {
        $this->scanned[$key] = ($this->scanned[$key] ?? 0) + 1;
        if ($byHand) $this->manual[$key] = ($this->manual[$key] ?? 0) + 1;
        $this->history[] = [$key, $byHand];
    }

    // ── by hand

    public function plus(string $key): void
    {
        $l = $this->lines[$key] ?? null; if (! $l) return;
        if (($this->scanned[$key] ?? 0) >= $l['need']) { $this->beep('warn', 'Already complete', $l['label']); return; }
        $this->add($key, true);
        $this->beep('ok', '✓ '.$l['label'].' (ticked by hand)', $this->progress());
    }

    public function minus(string $key): void
    {
        if (($this->scanned[$key] ?? 0) < 1) return;
        $this->scanned[$key]--;
        if (($this->manual[$key] ?? 0) > 0) $this->manual[$key]--;
        foreach (array_reverse(array_keys($this->history)) as $k) if ($this->history[$k][0] === $key) { unset($this->history[$k]); break; }
        $this->history = array_values($this->history);
        $this->flash = ['tone' => 'info', 'title' => 'Removed one', 'sub' => ($this->lines[$key]['label'] ?? '').' · '.$this->progress()];
    }

    public function tickAll(): void
    {
        foreach ($this->lines as $key => $l) while (($this->scanned[$key] ?? 0) < $l['need']) $this->add($key, true);
        $this->beep('warn', 'Everything ticked by hand', 'Check the parcel before you mark it packed');
    }

    public function undo(): void
    {
        if (! $last = array_pop($this->history)) return;
        [$key, $byHand] = $last;
        $this->scanned[$key] = max(0, ($this->scanned[$key] ?? 0) - 1);
        if ($byHand) $this->manual[$key] = max(0, ($this->manual[$key] ?? 0) - 1);
        $this->flash = ['tone' => 'info', 'title' => 'Undone', 'sub' => ($this->lines[$key]['label'] ?? '').' · '.$this->progress()];
    }

    public function resetScans(): void { $this->reset('scanned', 'manual', 'history', 'extraId'); $this->flash = ['tone' => 'info', 'title' => 'Scans cleared', 'sub' => 'Start again']; }

    // ── a product that is not on the order

    /** "Add product to parcel" picker (with images) → same warning as scanning it */
    public function updatedPick($id): void
    {
        if (! $id || ! $this->current || ! ($v = ProductVariant::find($id))) return;
        $this->pick = null;
        $this->count($v, true);
    }

    /** add the extra product to the order (free = gift / replacement, or charged = total and COD go up) and count it in */
    public function addExtra(bool $charge): void
    {
        $o = $this->current; $v = $this->extraId ? ProductVariant::with('product')->find($this->extraId) : null;
        if (! $o || ! $v || ! in_array($o->status, self::PACKABLE)) return;
        $price = $charge ? $v->effective_price : 0;
        DB::transaction(function () use ($o, $v, $price) {
            $o->items()->create(['variant_id' => $v->id, 'qty' => 1, 'unit_price' => $price]);
            if ($price) $o->update(['subtotal' => $o->subtotal + $price, 'total' => $o->total + $price, 'cod' => $o->cod + $price]);
            $o->log('edit', 'Extra item added at packing: '.$v->label.($price ? ' · charged '.tk($price).' (COD now '.tk($o->cod).')' : ' · free'));
            ops_log("Packing {$o->number}: extra {$v->label}".($price ? ' charged '.tk($price) : ' free'), $o);
        });
        $this->extraId = null; unset($this->current, $this->lines);
        $line = collect($this->lines)->first(fn ($l) => $l['variant_id'] === $v->id && ($this->scanned[$l['key']] ?? 0) < $l['need']);
        if ($line) $this->add($line['key'], false);
        $this->beep('warn', 'Added to the parcel: '.$v->label, ($price ? 'Charged '.tk($price).' — COD is now '.tk($o->fresh()->cod) : 'Free (gift / replacement)').' · '.$this->progress());
    }

    public function skipExtra(): void { $this->extraId = null; $this->flash = ['tone' => 'info', 'title' => 'Not added', 'sub' => 'Keep it out of the parcel']; }

    // ── packing

    public function complete(): void
    {
        $o = $this->current;
        if (! $o || ! $this->isComplete()) { $this->beep('warn', 'Put every item in first', $this->progress()); return; }
        if (! in_array($o->fresh()->status, self::PACKABLE)) { $this->beep('warn', 'Order '.$o->number.' can no longer be packed', 'Status: '.$o->fresh()->status); return; }
        $allocator = app(BatchAllocator::class);
        $allocator->allocate($o);
        if ($short = $allocator->shortfall($o)) { $this->beep('bad', 'Not enough stock in batches', $short.' unit(s) have no batch — receive stock in Purchases first'); return; }
        $allocator->pack($o);
        if ($byHand = array_sum($this->manual)) $o->log('packed', $byHand.' item(s) ticked by hand at packing (not scanned)');
        $parcel = $o->shipment()->value('code');
        $this->done = ['number' => $o->number, 'id' => $o->id, 'parcel' => $parcel];
        $this->dispatch('toast', message: $o->number.' packed'.($parcel ? ' · parcel '.$parcel : ''));
        $this->dispatch('ops-beep', tone: 'done');
        $done = $this->done;
        $this->reset('order', 'scanned', 'manual', 'history', 'extraId', 'flash', 'choices'); unset($this->current, $this->lines, $this->parcels, $this->queue, $this->queueCount, $this->packedToday);
        $this->done = $done;
        if ($this->autoNext && ($next = $this->queue->first())) { $this->openOrder($next->number, true); $this->done = $done; }
    }

    protected function beep(string $tone, string $title, string $sub = ''): void
    {
        $this->flash = ['tone' => $tone, 'title' => $title, 'sub' => $sub];
        if ($tone !== 'info') $this->dispatch('ops-beep', tone: $tone);
    }

    public function render() { return view('livewire.ops.packing.scan'); }
}
