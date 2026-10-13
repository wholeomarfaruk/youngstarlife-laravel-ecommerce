<?php

namespace App\Livewire\Ops\Settings;

use App\Models\Ops\{Courier, CourierApiLog, DeliveryZone, WebhookLog};
use App\Services\Couriers\{CourierException, CourierGateway};
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Settings → Couriers: couriers, their API credentials (fields come from the driver — Steadfast keys, Pathao OAuth),
 * real connection test, webhook URL + secret with setup steps, and the API-call / webhook logs.
 */
class CourierSetup extends Component
{
    use WithPagination;

    public ?int $editId = null;
    public array $c = [];
    public array $cred = [];                // driver credential fields
    public array $tested = [];               // courier id => ['ok' => bool, 'message' => …, 'stores' => […]]
    public string $logTab = 'api';           // api | webhooks
    public ?int $logCourier = null;
    public bool $logFailedOnly = false;
    public ?int $viewApi = null; public ?int $viewHook = null;

    public function mount(): void { $this->blank(); }

    protected function blank(): void
    {
        $this->c = ['name' => '', 'code' => '', 'is_active' => true, 'default_zone_id' => null, 'charge' => 0, 'cod_fee_pct' => 1, 'pickup_store' => ''];
        $this->cred = [];
    }

    #[Computed] public function rows() { return Courier::with('zone:id,name')->withCount('shipments as total')->orderBy('id')->get(); }

    /** credential fields of the driver for the code being edited */
    #[Computed] public function fields(): array { return CourierGateway::fields(strtolower(trim((string) ($this->c['code'] ?? '')))); }

    public function edit(?int $id = null): void
    {
        $this->resetValidation(); $this->editId = $id; $this->blank();
        if ($id) {
            $x = Courier::findOrFail($id);
            $this->c = ['name' => $x->name, 'code' => $x->code, 'is_active' => $x->is_active, 'default_zone_id' => $x->default_zone_id, 'charge' => $x->charge, 'cod_fee_pct' => (float) $x->cod_fee_pct, 'pickup_store' => (string) $x->pickup_store];
            foreach (CourierGateway::fields($x->code) as $f) $this->cred[$f['key']] = $f['type'] === 'checkbox' ? (bool) ($x->credentials[$f['key']] ?? false) : '';
            foreach (CourierGateway::fields($x->code) as $f) if ($f['type'] === 'text') $this->cred[$f['key']] = (string) ($x->credentials[$f['key']] ?? '');   // secrets stay blank = unchanged
        }
        $this->dispatch('open-modal', name: 'courier');
    }

    public function save(): void
    {
        $this->validate(['c.name' => 'required|max:60', 'c.code' => 'required|alpha_dash|max:20|unique:ops_couriers,code,'.($this->editId ?? 'NULL'), 'c.charge' => 'nullable|integer|min:0', 'c.cod_fee_pct' => 'nullable|numeric|min:0|max:10', 'c.default_zone_id' => 'nullable|exists:ops_delivery_zones,id']);
        $x = $this->editId ? Courier::findOrFail($this->editId) : new Courier(['webhook_secret' => str()->random(40)]);
        $creds = $x->credentials ?? [];
        foreach (CourierGateway::fields(strtolower($this->c['code'])) as $f) {
            $v = $this->cred[$f['key']] ?? null;
            if ($f['type'] === 'checkbox') $creds[$f['key']] = (bool) $v;
            elseif ($f['type'] === 'password') { if (filled($v)) $creds[$f['key']] = trim($v); }   // blank password = keep the saved one
            else $creds[$f['key']] = filled($v) ? trim($v) : null;
        }
        $changed = $x->exists && $creds !== ($x->credentials ?? []);
        $x->fill(['name' => $this->c['name'], 'code' => strtolower($this->c['code']), 'is_active' => (bool) $this->c['is_active'], 'default_zone_id' => $this->c['default_zone_id'] ?: null, 'charge' => (int) $this->c['charge'],
            'cod_fee_pct' => (float) ($this->c['cod_fee_pct'] ?: 0), 'pickup_store' => $this->c['pickup_store'] ?: null, 'credentials' => array_filter($creds, fn ($v) => $v !== null)])->save();
        if ($changed) unset($this->tested[$x->id]);   // old test result no longer says anything
        ops_log(($this->editId ? 'Updated' : 'Added').' courier '.$x->name, $x);
        unset($this->rows);
        $this->dispatch('close-modal'); $this->dispatch('toast', message: 'Courier saved'.($x->hasApi() && ! $x->isConnected() ? ' · API credentials incomplete' : ''));
    }

    public function toggle(int $id): void
    {
        $x = Courier::findOrFail($id); $x->update(['is_active' => ! $x->is_active]); unset($this->rows);
        ops_log($x->name.' '.($x->is_active ? 'activated' : 'deactivated'), $x);
        $this->dispatch('toast', message: $x->name.' · '.($x->is_active ? 'Active' : 'Off'));
    }

    /** real call: Steadfast balance / Pathao token + stores */
    public function test(int $id, CourierGateway $gw): void
    {
        $x = Courier::findOrFail($id);
        try {
            $r = $gw->test($x);
            $this->tested[$id] = ['ok' => true, 'message' => $r['message'], 'stores' => $r['data']['stores'] ?? []];
            ops_log('Tested '.$x->name.' API · OK', $x);
            $this->dispatch('toast', message: $x->name.' · '.$r['message']);
        } catch (CourierException $e) {
            $this->tested[$id] = ['ok' => false, 'message' => $e->getMessage(), 'stores' => []];
            $this->dispatch('toast', message: $x->name.': '.$e->getMessage(), type: 'bad');
        }
        unset($this->rows);
    }

    /** Pathao: use this store for new parcels */
    public function useStore(int $courierId, int $storeId): void
    {
        $x = Courier::findOrFail($courierId);
        $x->update(['credentials' => array_merge($x->credentials ?? [], ['store_id' => (string) $storeId])]);
        ops_log("{$x->name}: pickup store #$storeId", $x); unset($this->rows);
        $this->dispatch('toast', message: "{$x->name} parcels will use store #$storeId");
    }

    public function rotateSecret(int $id): void
    {
        $x = Courier::findOrFail($id); $x->update(['webhook_secret' => str()->random(40)]); unset($this->rows);
        ops_log('Rotated webhook secret of '.$x->name, $x);
        $this->dispatch('toast', message: 'New secret — paste it in the '.$x->name.' merchant panel too');
    }

    // ── logs

    #[Computed]
    public function apiLogs()
    {
        return CourierApiLog::with('courier:id,name', 'shipment:id,code,order_id', 'user:id,name')->when($this->logCourier, fn ($q, $c) => $q->where('courier_id', $c))
            ->when($this->logFailedOnly, fn ($q) => $q->where('success', false))->latest('id')->paginate(20, pageName: 'apiPage');
    }

    #[Computed]
    public function hookLogs()
    {
        return WebhookLog::with('courier:id,name', 'shipment:id,code,order_id')->when($this->logCourier, fn ($q, $c) => $q->where('courier_id', $c))
            ->when($this->logFailedOnly, fn ($q) => $q->where(fn ($q) => $q->where('status_code', '>=', 400)->orWhereIn('result', ['unmatched', 'error', 'unauthorized'])))->latest('id')->paginate(20, pageName: 'hookPage');
    }

    #[Computed]
    public function health(): array
    {
        $since = now()->subDay();
        return Courier::get(['id'])->mapWithKeys(fn ($c) => [$c->id => [
            'calls' => CourierApiLog::where('courier_id', $c->id)->where('created_at', '>=', $since)->count(),
            'failed' => CourierApiLog::where('courier_id', $c->id)->where('created_at', '>=', $since)->where('success', false)->count(),
            'hooks' => WebhookLog::where('courier_id', $c->id)->where('created_at', '>=', $since)->count(),
            'last_hook' => WebhookLog::where('courier_id', $c->id)->max('created_at'),
            'pending' => \App\Models\Ops\Shipment::where('courier_id', $c->id)->whereNull('booked_at')->whereNotIn('courier_status', \App\Models\Ops\Shipment::FINAL)->whereNull('tracking')->count(),
        ]])->all();
    }

    public function updatedLogCourier(): void { $this->resetPage('apiPage'); $this->resetPage('hookPage'); }
    public function updatedLogFailedOnly(): void { $this->resetPage('apiPage'); $this->resetPage('hookPage'); }
    public function showApi(int $id): void { $this->viewApi = $id; $this->dispatch('open-modal', name: 'log-detail'); }
    public function showHook(int $id): void { $this->viewHook = $id; $this->dispatch('open-modal', name: 'log-detail'); }
    public function closeDetail(): void { $this->viewApi = $this->viewHook = null; }

    public function render()
    {
        return view('livewire.ops.settings.courier-setup', [
            'zones' => DeliveryZone::orderBy('id')->pluck('name', 'id'),
            'detail' => $this->viewApi ? CourierApiLog::with('courier:id,name')->find($this->viewApi) : ($this->viewHook ? WebhookLog::with('courier:id,name')->find($this->viewHook) : null),
        ]);
    }
}
