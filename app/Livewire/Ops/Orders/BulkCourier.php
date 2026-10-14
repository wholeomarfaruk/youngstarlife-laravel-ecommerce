<?php

namespace App\Livewire\Ops\Orders;

use App\Models\Ops\{CourierBatch, CourierBatchItem, Order};
use App\Services\Couriers\{CourierException, CourierGateway};
use App\Services\Ops\{CourierBooking, OrderWorkflow};
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Livewire\Attributes\{Layout, On, Renderless, Title, Url};
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Orders → Bulk courier (ported from ecomx-automation's Bulk Courier Entry).
 * Top: orders still waiting for a courier — (+) adds one to the sheet. Bottom: this staff member's draft sheet,
 * one editable row per order pre-filled like the Book courier modal; every edit is saved at once, so the sheet survives
 * reloads and other devices. "Book all" books the rows one at a time from the browser (bookItem per row) so each
 * row's result shows as it lands and one bad row never blocks the rest. Booking itself = CourierBooking (same as the modal).
 */
#[Layout('layouts.ops', ['module' => 'Orders & Packing', 'title' => 'Bulk courier', 'tabs' => 'orders'])]
#[Title('Bulk courier')]
class BulkCourier extends Component
{
    use WithPagination;

    private const EDITABLE = ['courier_id', 'name', 'phone', 'address', 'cod', 'weight', 'qty', 'description', 'note'];
    private const READY = ['pending', 'confirmed', 'packed'];

    #[Url(except: '')] public string $search = '';
    #[Url(except: '')] public string $filterStatus = '';
    #[Url(except: '')] public string $filterSource = '';
    public ?int $bulkCourierId = null;   // "courier for all rows"
    public array $rows = [];             // editable sheet rows keyed "r{itemId}"
    public ?int $historyId = null;

    private ?CourierBatch $draft = null;

    public function mount(): void
    {
        // Orders list → bulk bar "Courier sheet": ?add=12,11,9 puts those orders on the sheet
        if ($add = (string) request()->query('add')) { $this->addPage($add); return; }
        $this->loadRows();
    }
    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }
    public function updatingFilterSource(): void { $this->resetPage(); }
    public function clearFilters(): void { $this->reset(['search', 'filterStatus', 'filterSource']); $this->resetPage(); }

    protected function sheet(): CourierBatch { return $this->draft ??= CourierBatch::draftFor(auth()->id()); }
    protected function couriers(): Collection { return once(fn () => app(CourierBooking::class)->bookable()); }

    protected function loadRows(): void
    {
        $this->rows = $this->sheet()->items()->ordered()->get()->mapWithKeys(fn (CourierBatchItem $i) => ['r'.$i->id => [
            'courier_id' => $i->courier_id, 'name' => $i->name, 'phone' => $i->phone, 'address' => $i->address, 'cod' => (string) $i->cod,
            'weight' => rtrim(rtrim(number_format($i->weight, 2, '.', ''), '0'), '.'), 'qty' => (string) $i->qty, 'description' => (string) $i->description, 'note' => (string) $i->note,
        ]])->all();
    }

    /** orders that still need a courier: being worked on, no parcel with a courier yet */
    protected function eligible()
    {
        return Order::live()->whereIn('status', self::READY)->whereDoesntHave('shipments', fn ($q) => $q->whereNotNull('booked_at')->whereNull('superseded_at'));   // a re-sent order's old parcel doesn't count
    }

    /** order ids sitting unbooked on someone else's draft sheet → their name */
    protected function lockedByOthers(array $ids): Collection
    {
        if (! $ids) return collect();
        return CourierBatchItem::whereIn('order_id', $ids)->where('status', '!=', CourierBatchItem::BOOKED)
            ->whereHas('batch', fn ($q) => $q->where('status', CourierBatch::DRAFT)->where('created_by', '!=', auth()->id()))
            ->with('batch.creator:id,name')->get()->mapWithKeys(fn ($i) => [$i->order_id => $i->batch->creator?->name ?? 'someone else']);
    }

    public function addOrder(int $id): void
    {
        if ($why = $this->addToSheet($id)) $this->dispatch('toast', message: $why, type: 'bad');
        $this->loadRows();
    }

    /** every addable order on the current page of the list ("12,11,9") */
    public function addPage(string $ids): void
    {
        $n = 0;
        foreach (array_slice(array_filter(array_map('intval', explode(',', $ids))), 0, 100) as $id) if ($this->addToSheet($id) === null) $n++;
        $this->loadRows();
        $this->dispatch('toast', message: "$n order(s) added to the sheet");
    }

    /** @return string|null why it wasn't added */
    protected function addToSheet(int $id): ?string
    {
        $o = $this->eligible()->with('items.variant.product:id,name')->find($id);
        if (! $o) return 'That order is already with a courier or not ready to ship.';
        $sheet = $this->sheet();
        if ($sheet->items()->where('order_id', $id)->exists()) return "{$o->number} is already on the sheet.";
        if ($who = $this->lockedByOthers([$id])->first()) return "{$o->number} is on {$who}’s courier sheet.";
        $courier = app(OrderWorkflow::class)->courierFor($o);
        $courierId = $courier && $this->couriers()->contains('id', $courier->id) ? $courier->id : ($this->bulkCourierId ?: $this->couriers()->first()?->id);
        try {
            $sheet->items()->create([
                'order_id' => $o->id, 'courier_id' => $courierId, 'name' => (string) $o->ship_name, 'phone' => (string) $o->ship_phone, 'address' => (string) $o->ship_address,
                'cod' => (int) $o->cod, 'weight' => (float) ($o->shipment?->weight_kg ?: 0.5), 'qty' => max(1, (int) $o->items->sum('qty')),
                'description' => mb_substr($o->courier_description ?: app(CourierGateway::class)->itemDescription($o), 0, 250), 'note' => $o->courier_note,
                'sort_order' => (int) CourierBatchItem::where('courier_batch_id', $sheet->id)->max('sort_order') + 1,
            ]);
        } catch (QueryException) {
            return "{$o->number} is already on the sheet.";   // unique (sheet, order): a double click raced the first add
        }
        return null;
    }

    public function removeItem(int $id): void
    {
        $this->sheet()->items()->whereKey($id)->where('status', '!=', CourierBatchItem::BOOKED)->delete();
        $this->loadRows();
    }

    /** re-fill a row from its order (undo edits), keeping its courier and place */
    public function resetItem(int $id): void
    {
        $i = $this->sheet()->items()->whereKey($id)->where('status', '!=', CourierBatchItem::BOOKED)->first();
        if (! $i) return;
        $keep = ['courier_id' => $i->courier_id, 'sort_order' => $i->sort_order]; $orderId = $i->order_id;
        $i->delete();
        if ($why = $this->addToSheet($orderId)) $this->dispatch('toast', message: $why, type: 'bad');
        else $this->sheet()->items()->where('order_id', $orderId)->update($keep);
        $this->loadRows();
    }

    /** autosave: every sheet edit goes straight to its row */
    public function updated(string $name, $value): void
    {
        if (! preg_match('/^rows\.r(\d+)\.(\w+)$/', $name, $m) || ! in_array($m[2], self::EDITABLE, true)) return;
        $i = $this->sheet()->items()->whereKey((int) $m[1])->first();
        if (! $i || $i->isBooked()) { $this->loadRows(); return; }
        $value = is_string($value) ? trim($value) : $value;
        $i->update([$m[2] => match ($m[2]) {
            'courier_id' => $value ? (int) $value : null,
            'cod' => max(0, (int) $value), 'weight' => min(30, max(0, (float) $value)), 'qty' => max(1, (int) $value),
            'description' => mb_substr((string) $value, 0, 250), 'note' => mb_substr((string) $value, 0, 200) ?: null,
            'name' => mb_substr((string) $value, 0, 120), 'phone' => mb_substr(preg_replace('/[^\d+]/', '', (string) $value), 0, 20), 'address' => mb_substr((string) $value, 0, 255),
        }] + ($i->status === CourierBatchItem::FAILED ? ['status' => CourierBatchItem::DRAFT, 'error' => null] : []));
    }

    public function applyCourierToAll(): void
    {
        if (! $this->bulkCourierId) return;
        $this->sheet()->items()->where('status', '!=', CourierBatchItem::BOOKED)->update(['courier_id' => $this->bulkCourierId]);
        $this->loadRows();
        $this->dispatch('toast', message: 'Courier set on every unbooked row');
    }

    /** ids the browser should book, in sheet order */
    #[Renderless]
    public function bookableIds(): array { return $this->sheet()->items()->ordered()->where('status', '!=', CourierBatchItem::BOOKED)->pluck('id')->all(); }

    /**
     * Book one row. Called per row by "Book all" (and the row's own Book button), so progress shows live
     * and a failure only marks that row.  @return array{ok: bool, message: string}
     */
    public function bookItem(int $id, CourierBooking $booking): array
    {
        $i = $this->sheet()->items()->whereKey($id)->with('courier', 'order')->first();
        if (! $i || $i->isBooked()) return ['ok' => true, 'message' => 'Already booked.'];
        if ($why = $this->rowError($i)) return $this->fail($i, $why);
        $o = $i->order;
        if ($why = $booking->blocked($o)) return $this->fail($i, $why);
        try {
            $booking->saveDetails($o, $i->courier, ['name' => $i->name, 'phone' => $i->phone, 'address' => $i->address, 'cod' => $i->cod, 'note' => $i->note, 'description' => $i->description, 'weight' => $i->weight]);
            $s = $booking->send($o->fresh(), $i->courier, ['weight' => $i->weight, 'qty' => $i->qty]);
        } catch (CourierException $e) {
            return $this->fail($i, $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return $this->fail($i, 'Booking error: '.$e->getMessage());
        }
        $i->update(['status' => CourierBatchItem::BOOKED, 'shipment_id' => $s->id, 'tracking' => $s->tracking ?: $s->consignment_id, 'error' => null, 'booked_at' => now()]);
        return ['ok' => true, 'message' => "{$o->number} booked — ".($s->tracking ?: $s->code)];
    }

    private function fail(CourierBatchItem $i, string $message): array
    {
        $i->update(['status' => CourierBatchItem::FAILED, 'error' => mb_substr($message, 0, 1000)]);
        return ['ok' => false, 'message' => ($i->order?->number ?? '#'.$i->order_id).': '.$message];
    }

    /** checks done before spending a courier API call */
    protected function rowError(CourierBatchItem $i): ?string
    {
        return match (true) {
            ! $i->courier => 'Pick a courier.',
            ! $this->couriers()->contains('id', $i->courier_id) => $i->courier->name.' API can’t book (Settings → Couriers).',
            trim($i->name) === '' => 'Name is required.',
            ! preg_match('/^01[3-9]\d{8}$/', $i->phone) => 'Phone must be an 11-digit mobile (01XXXXXXXXX).',
            mb_strlen(trim($i->address)) < 10 => 'Address is too short.',
            $i->weight <= 0 => 'Weight must be more than 0.',
            default => null,
        };
    }

    /** end this sheet: booked rows stay in it as history, anything not booked moves to a fresh draft */
    public function completeSheet(): void
    {
        $sheet = $this->sheet();
        if (! $sheet->items()->where('status', CourierBatchItem::BOOKED)->exists()) { $this->dispatch('toast', message: 'Nothing booked on this sheet yet', type: 'bad'); return; }
        $open = $sheet->items()->where('status', '!=', CourierBatchItem::BOOKED)->pluck('id');
        $sheet->update(['status' => CourierBatch::COMPLETED, 'completed_at' => now()]);
        $this->draft = null;
        if ($open->isNotEmpty()) CourierBatchItem::whereIn('id', $open)->update(['courier_batch_id' => $this->sheet()->id]);
        ops_log('Bulk courier sheet #'.$sheet->id.' completed');
        $this->loadRows();
        $this->dispatch('toast', message: 'Sheet completed and saved to history');
    }

    public function clearSheet(): void
    {
        $this->sheet()->items()->where('status', '!=', CourierBatchItem::BOOKED)->delete();
        $this->loadRows();
    }

    public function showHistory(?int $id): void { $this->historyId = $id; }

    /** Book courier modal / order menu changed an order → fresh lists */
    #[On('ops-order-updated')]
    public function refreshList(): void {}

    public function render()
    {
        $couriers = $this->couriers();
        $items = $this->sheet()->items()->ordered()->with('order:id,number,status,customer_id', 'order.customer:id,delivered_count,returned_count', 'courier:id,name')->get();
        $sheetIds = $items->pluck('order_id')->all();
        $orders = $this->eligible()->select(['id', 'number', 'customer_id', 'source', 'status', 'ship_name', 'ship_phone', 'ship_address', 'cod', 'total', 'placed_at'])
            ->with('customer:id,delivered_count,returned_count')->withSum('items as qty', 'qty')
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterSource !== '', fn ($q) => $q->where('source', $this->filterSource))
            ->when(trim($this->search) !== '', function ($q) {
                $s = trim($this->search); $n = ltrim($s, '#');
                $q->where(fn ($w) => $w->where('number', 'like', "%$n%")->orWhere('ship_phone', 'like', "%$s%")->orWhere('ship_name', 'like', "%$s%"));
            })
            ->latest('placed_at')->paginate(20);
        $open = $items->where('status', '!=', CourierBatchItem::BOOKED);
        $risky = fn ($o) => ($r = $o?->customer?->delivery_rate) !== null && $r < .75;

        return view('livewire.ops.orders.bulk-courier', [
            'orders' => $orders, 'items' => $items, 'sheetIds' => $sheetIds, 'couriers' => $couriers, 'risky' => $risky,
            'lockedByOthers' => $this->lockedByOthers($orders->getCollection()->pluck('id')->all()),
            'stats' => [
                'rows' => $items->count(), 'open' => $open->count(), 'cod' => (int) $open->sum('cod'),
                'booked' => $items->where('status', CourierBatchItem::BOOKED)->count(), 'failed' => $items->where('status', CourierBatchItem::FAILED)->count(),
                'risky' => $open->filter(fn ($i) => $risky($i->order))->count(),
                'byCourier' => $open->groupBy(fn ($i) => $i->courier?->name ?? 'No courier')->map->count(),
            ],
            'history' => CourierBatch::where('status', CourierBatch::COMPLETED)
                ->withCount(['items as booked_count' => fn ($q) => $q->where('status', CourierBatchItem::BOOKED)])
                ->withSum(['items as booked_cod' => fn ($q) => $q->where('status', CourierBatchItem::BOOKED)], 'cod')
                ->with('creator:id,name')->latest('completed_at')->limit(10)->get(),
            'historyItems' => $this->historyId ? CourierBatchItem::where('courier_batch_id', $this->historyId)->ordered()->with('courier:id,name', 'order:id,number')->get() : collect(),
        ]);
    }
}
