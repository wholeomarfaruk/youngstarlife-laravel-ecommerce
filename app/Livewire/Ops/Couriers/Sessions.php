<?php

namespace App\Livewire\Ops\Couriers;

use App\Livewire\Ops\Concerns\WithTable;
use App\Models\Ops\{Courier, CourierSession};
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

/** Entry sessions: every meeting with a courier's pickup man — parcels sent, returns received, damaged parcels not accepted. */
#[Layout('layouts.ops', ['module' => 'Couriers', 'title' => 'Entry sessions', 'tabs' => 'couriers'])]
#[Title('Entry sessions')]
class Sessions extends Component
{
    use WithTable;
    #[Url(except: '')] public string $courier = '';
    public array $new = ['courier_id' => null, 'rider' => '', 'rider_phone' => ''];

    protected function base()
    {
        $s = $this->search;
        $q = CourierSession::query()
            ->when($this->courier, fn ($q, $c) => $q->where('courier_id', $c))
            ->when($s, fn ($q) => $q->where(fn ($x) => $x->where('code', 'like', "%$s%")->orWhere('rider', 'like', "%$s%")->orWhere('rider_phone', 'like', "%$s%")
                ->orWhereHas('items.shipment', fn ($p) => $p->where('code', 'like', "%$s%")->orWhereHas('order', fn ($o) => $o->where('number', 'like', "%$s%")))));
        return $this->dateRange($q, 'ops_courier_sessions.created_at');
    }

    protected function applyStatus($q, string $st)
    {
        return match ($st) {
            'open' => $q->where('status', 'open'), 'closed' => $q->where('status', 'closed'),
            'sent', 'returned', 'rejected' => $q->whereHas('items', fn ($i) => $i->where('type', $st)),
            default => $q,
        };
    }

    #[Computed] public function counts(): array { return collect(['all', 'open', 'sent', 'returned', 'rejected', 'closed'])->mapWithKeys(fn ($k) => [$k => $this->applyStatus($this->base(), $k)->count()])->all(); }

    #[Computed]
    public function sessions()
    {
        return $this->applyStatus($this->base(), $this->status)->with('courier:id,name', 'opener:id,name')
            ->withCount(['items as sent' => fn ($q) => $q->where('type', 'sent'), 'items as returned' => fn ($q) => $q->where('type', 'returned'), 'items as rejected' => fn ($q) => $q->where('type', 'rejected')])
            ->addSelect(['cod_sent' => \App\Models\Ops\Shipment::selectRaw('COALESCE(SUM(cod_amount),0)')->join('ops_courier_session_items as i', 'i.shipment_id', '=', 'ops_shipments.id')
                ->whereColumn('i.courier_session_id', 'ops_courier_sessions.id')->where('i.type', 'sent')])
            ->orderByRaw("status = 'open' desc")->latest('created_at')->latest('id')->paginate($this->perPage);
    }

    #[Computed] public function couriers() { return Courier::where('is_active', true)->orderBy('id')->pluck('name', 'id'); }

    public function startNew(): void { $this->resetValidation(); $this->new = ['courier_id' => $this->courier ?: $this->couriers->keys()->first(), 'rider' => '', 'rider_phone' => '']; $this->dispatch('open-modal', name: 'new-session'); }

    public function create()
    {
        $this->validate(['new.courier_id' => 'required|exists:ops_couriers,id', 'new.rider' => 'nullable|max:80', 'new.rider_phone' => 'nullable|max:20'], ['new.courier_id.required' => 'Pick the courier.']);
        $s = CourierSession::create(['code' => CourierSession::nextCode(), 'courier_id' => $this->new['courier_id'], 'rider' => trim($this->new['rider']) ?: null, 'rider_phone' => trim($this->new['rider_phone']) ?: null, 'opened_by' => auth()->id()]);
        ops_log('Opened entry session '.$s->code.' · '.$s->courier->name, $s);
        return $this->redirectRoute('ops.couriers.session', $s, navigate: true);
    }

    public function clearFilters(): void { $this->reset('search', 'status', 'from', 'to', 'filters', 'courier'); $this->resetPage(); }
    public function updatingCourier(): void { $this->resetPage(); }

    public function render() { return view('livewire.ops.couriers.sessions'); }
}
