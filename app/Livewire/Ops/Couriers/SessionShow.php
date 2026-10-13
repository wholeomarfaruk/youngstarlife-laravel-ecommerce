<?php

namespace App\Livewire\Ops\Couriers;

use App\Models\Ops\{CourierSession, CourierSessionItem};
use App\Services\Ops\EntrySessions;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\{Computed, Layout};
use Livewire\Component;

/** One entry session: scan parcels to send, receive returns, or reject damaged parcels. */
#[Layout('layouts.ops', ['module' => 'Couriers', 'title' => 'Entry session', 'tabs' => 'couriers'])]
class SessionShow extends Component
{
    public CourierSession $session;
    public string $mode = 'sent';          // sent | returned | rejected
    public string $note = '';              // damage description (rejected) / optional return note
    public ?array $flash = null;
    public ?int $rejectId = null;          // parcel picked for "damaged · not received"

    public function mount(CourierSession $session): void { $this->session = $session->load('courier'); }

    #[Computed] public function toSend() { return EntrySessions::toSend($this->session->courier_id); }
    #[Computed] public function toReceive() { return EntrySessions::toReceive($this->session->courier_id); }
    #[Computed] public function items() { return $this->session->items()->with('shipment.order:id,number,customer_id', 'shipment.order.customer:id,name', 'customerReturn:id,code,refund_amount,refunded_at', 'customerReturn.items:id,customer_return_id,condition', 'user:id,name')->get(); }
    /** parcels already rejected in this session (still expected back) */
    #[Computed] public function rejectedIds() { return $this->items->where('type', 'rejected')->pluck('shipment_id')->all(); }

    public function scan(string $code): void
    {
        $p = EntrySessions::find($code);
        if (! $p) { $this->flash = ['bad', 'Not found', $code.' is not a parcel code, tracking or order number']; return; }
        $this->act($p->id);
    }

    public function act(int $shipmentId, ?string $mode = null): void
    {
        if ($mode && in_array($mode, ['sent', 'returned', 'rejected'])) $this->mode = $mode;
        $p = \App\Models\Ops\Shipment::with('order', 'courier')->findOrFail($shipmentId);
        $svc = app(EntrySessions::class);
        try {
            if ($this->mode === 'rejected' && trim($this->note) === '') { $this->rejectId = $p->id; $this->flash = ['warn', $p->code.' · '.$p->order->number, 'Describe the damage, then press "Not received"']; return; }
            match ($this->mode) {
                'sent' => $svc->send($this->session, $p),
                'returned' => $svc->receive($this->session, $p, $this->note ?: null),
                'rejected' => $svc->reject($this->session, $p, $this->note),
            };
        } catch (ValidationException $e) {
            $this->flash = ['bad', $p->code.' · '.$p->order->number, collect($e->errors())->flatten()->first()]; return;
        }
        $this->flash = match ($this->mode) {
            'sent' => ['info', '✓ '.$p->code.' handed over', $p->order->number.' · '.tk($p->cod_amount).' COD · order → Shipped'],
            'returned' => ($w = EntrySessions::flagText($p)) ? ['warn', '✓ '.$p->code.' received · ⚠ '.$w, $p->order->number.' · keep it apart and inspect it in Returns'] : ['ok', '✓ '.$p->code.' received back', $p->order->number.' · return created — inspect it in Returns'],
            'rejected' => ['bad', '✗ '.$p->code.' not received (damaged)', 'Complaint recorded · parcel stays with '.$this->session->courier->name],
        };
        $this->reset('note', 'rejectId'); $this->refresh();
    }

    /** before receiving: mark a returning parcel as damaged / inspect / keep separate, with a note */
    public function saveReturnNote(int $id, ?string $flag, ?string $note): void
    {
        $p = \App\Models\Ops\Shipment::where('courier_id', $this->session->courier_id)->findOrFail($id);
        $flag = array_key_exists((string) $flag, EntrySessions::FLAGS) ? $flag : null; $note = trim((string) $note) ?: null;
        $p->update(['return_flag' => $flag, 'return_note' => $note ? mb_substr($note, 0, 255) : null]);
        if ($flag || $note) $p->order->log('return', 'Return note on '.$p->code.': '.EntrySessions::flagText($p));
        unset($this->toReceive); $this->dispatch('toast', message: $flag || $note ? 'Note saved on '.$p->code : 'Note cleared');
    }

    public function pickReject(int $id): void { $this->mode = 'rejected'; $this->rejectId = $id; $p = \App\Models\Ops\Shipment::with('order')->find($id); $this->flash = ['warn', $p->code.' · '.$p->order->number, 'Describe the damage, then press "Not received"']; }
    public function rejectPicked(): void { if ($this->rejectId) $this->act($this->rejectId); }

    public function undo(int $itemId): void
    {
        $it = CourierSessionItem::where('courier_session_id', $this->session->id)->findOrFail($itemId);
        try { app(EntrySessions::class)->undo($it); } catch (ValidationException $e) { $this->flash = ['bad', 'Cannot undo', collect($e->errors())->flatten()->first()]; return; }
        $this->flash = ['warn', 'Undone', $it->shipment->code.' · '.CourierSessionItem::TYPES[$it->type][0].' removed']; $this->refresh();
    }

    public function close()
    {
        $this->session->update(['status' => 'closed', 'closed_at' => now()]);
        ops_log('Closed entry session '.$this->session->code, $this->session);
        $this->dispatch('toast', message: 'Session '.$this->session->code.' closed');
        $this->js("window.open('".route('ops.couriers.session.print', $this->session)."', '_blank')");
    }

    public function reopen(): void { $this->session->update(['status' => 'open', 'closed_at' => null]); $this->dispatch('toast', message: 'Session reopened'); }

    public function saveRider(string $rider, string $phone): void { $this->session->update(['rider' => trim($rider) ?: null, 'rider_phone' => trim($phone) ?: null]); $this->dispatch('toast', message: 'Pickup man saved'); }

    protected function refresh(): void { unset($this->toSend, $this->toReceive, $this->items, $this->rejectedIds); }

    public function render() { return view('livewire.ops.couriers.session-show')->title($this->session->code); }
}
