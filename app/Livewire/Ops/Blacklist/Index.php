<?php

namespace App\Livewire\Ops\Blacklist;

use App\Livewire\Ops\Concerns\WithTable;
use App\Models\Device;
use App\Models\Ops\{BlacklistEntry, BlacklistHit, Customer, Order, Setting};
use App\Services\Ops\Blacklist;
use App\Support\OpsDefaults;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

/**
 * Users → Blacklist: phones, devices, IPs and emails that may not order (block) or whose orders need a look first (flag).
 * Checked by website checkout, bulk / AI order and Draft → order (App\Services\Ops\Blacklist); every match is logged under Attempts.
 */
#[Layout('layouts.ops', ['module' => 'Users', 'title' => 'Blacklist', 'tabs' => 'parties'])]
#[Title('Blacklist')]
class Index extends Component
{
    use WithTable;

    #[Url(except: 'entries')] public string $view = 'entries';   // entries | attempts
    #[Url(except: 'new')] public string $sort = 'new';          // new | hits | last
    #[Url(except: 0)] public int $entry = 0;                     // attempts of one entry
    public ?int $editingId = null;
    public array $f = [];                                        // add / edit form
    public ?int $orderId = null; public array $fromOrder = [];   // "Blacklist customer" from an order
    public array $imp = ['list' => '', 'type' => 'phone', 'action' => 'block', 'reason' => 'fake_order', 'note' => ''];
    public ?array $impResult = null;
    public string $check = '';
    public array $cfg = [];
    protected bool $autoOpen = false;

    protected const BLANK = ['type' => 'phone', 'value' => '', 'action' => 'block', 'reason' => 'fake_order', 'note' => '', 'expiry' => 'never', 'expires_at' => '', 'devices' => true, 'cancel' => false];

    public function mount(): void
    {
        if (! in_array($this->view, ['entries', 'attempts'])) $this->view = 'entries';
        $this->f = self::BLANK;
        $this->cfg = OpsDefaults::merge(OpsDefaults::get('blacklist'), Setting::get('blacklist', []));
        // ?order=ID (order ⋯ menu) · ?phone=… (customer / user pages) open the add form prefilled
        if ($id = (int) request('order')) $this->autoOpen = $this->openFromOrder($id);
        elseif ($p = request('phone')) { $this->edit(); $this->f['value'] = (string) $p; $this->autoOpen = true; }
    }

    public function updatedView(): void { $this->resetPage(); $this->selected = []; $this->reset('search', 'filters'); $this->status = 'all'; $this->entry = 0; }

    // ── entries ─────────────────────────────────────────────────────────────────────────────────

    protected function base()
    {
        return BlacklistEntry::query()
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('value', 'like', '%'.(Blacklist::normalize('phone', $s) ?? $s).'%')->orWhere('note', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%"))->orWhereHas('device', fn ($d) => $d->where('name', 'like', "%$s%"))))
            ->when($this->filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($this->filters['reason'] ?? null, fn ($q, $r) => $q->where('reason', $r))
            ->when($this->filters['source'] ?? null, fn ($q, $s) => $q->where('source', $s));
    }

    protected function applyStatus($q, string $s)
    {
        return match ($s) {
            'all' => $q->active(), 'block' => $q->active()->where('action', 'block'), 'flag' => $q->active()->where('action', 'flag'),
            'hit' => $q->active()->where('hits', '>', 0), 'expired' => $q->expired(), 'removed' => $q->where('is_active', false), default => $q,   // any = everything
        };
    }

    #[Computed] public function counts(): array { return collect(['all', 'block', 'flag', 'hit', 'expired', 'removed', 'any'])->mapWithKeys(fn ($s) => [$s => $this->applyStatus($this->base(), $s)->count()])->all(); }

    #[Computed]
    public function rows()
    {
        $q = $this->applyStatus($this->base(), $this->status)->with('customer:id,name,phone,user_id,orders_count,delivered_count,returned_count', 'device:id,name,ip_address,last_seen', 'order:id,number', 'creator:id,name', 'remover:id,name');
        $q = match ($this->sort) { 'hits' => $q->orderByDesc('hits'), 'last' => $q->orderByRaw('last_hit_at IS NULL, last_hit_at desc'), default => $q->latest('id') };
        return $q->paginate($this->perPage);
    }

    #[Computed]
    public function kpis(): array
    {
        $a = BlacklistEntry::active();
        return [
            'active' => (clone $a)->count(), 'phones' => (clone $a)->where('type', 'phone')->count(), 'devices' => (clone $a)->whereIn('type', ['device', 'ip'])->count(),
            'flag' => (clone $a)->where('action', 'flag')->count(), 'today' => BlacklistHit::where('created_at', '>=', today())->count(),
            'blocked30' => BlacklistHit::where('blocked', true)->where('created_at', '>=', now()->subDays(30))->count(),
            'flagged30' => BlacklistHit::where('blocked', false)->where('created_at', '>=', now()->subDays(30))->count(),
            'expiring' => (clone $a)->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays(7))->count(),
        ];
    }

    /** Opens the add / edit form (modal opened by Alpine). */
    public function edit(?int $id = null): void
    {
        $this->resetValidation(); $this->editingId = $id; $this->orderId = null;
        if (! $id) { $this->f = self::BLANK; return; }
        $e = BlacklistEntry::findOrFail($id);
        $this->f = ['type' => $e->type, 'value' => $e->value, 'action' => $e->action, 'reason' => (string) $e->reason, 'note' => (string) $e->note,
            'expiry' => $e->expires_at ? 'date' : 'never', 'expires_at' => (string) $e->expires_at?->toDateString(), 'devices' => false, 'cancel' => false];
    }

    protected function expiresAt(): ?\Illuminate\Support\Carbon
    {
        return match ($this->f['expiry']) { '7', '30', '90', '365' => now()->addDays((int) $this->f['expiry']), 'date' => $this->f['expires_at'] ? \Illuminate\Support\Carbon::parse($this->f['expires_at'])->endOfDay() : null, default => null };
    }

    protected function formRules(): array
    {
        return [
            'f.action' => 'required|in:block,flag', 'f.reason' => 'required|in:'.implode(',', array_keys(config('ops.blacklist_reasons'))), 'f.note' => 'nullable|max:1000',
            'f.expiry' => 'required|in:never,7,30,90,365,date', 'f.expires_at' => 'nullable|required_if:f.expiry,date|date|after:today',
        ];
    }

    public function save(Blacklist $bl): void
    {
        $this->validate($this->formRules() + ($this->orderId ? ['fromOrder' => 'required|array|min:1'] : ['f.type' => 'required|in:'.implode(',', Blacklist::TYPES), 'f.value' => 'required|max:191']),
            ['fromOrder.required' => 'Tick at least one thing to blacklist.', 'f.expires_at.after' => 'Pick a date after today.']);
        $d = ['action' => $this->f['action'], 'reason' => $this->f['reason'], 'note' => trim($this->f['note']) ?: null, 'expires_at' => $this->expiresAt()];
        try {
            $entries = DB::transaction(function () use ($bl, $d) {
                if ($this->orderId) return $bl->fromOrder(Order::findOrFail($this->orderId), $this->fromOrder, $d);
                $e = $this->editingId ? BlacklistEntry::findOrFail($this->editingId) : null;   // type and value are fixed once added
                $main = $bl->add(['type' => $e->type ?? $this->f['type'], 'value' => $e->value ?? $this->f['value']] + $d);
                $more = [];
                if (! $this->editingId && $main->type === 'phone' && $this->f['devices'] && ($c = Customer::where('phone', $main->value)->first()))
                    foreach ($bl->devicesOf($c) as $id) $more[] = $bl->add(['type' => 'device', 'value' => $id, 'source' => 'customer', 'customer_id' => $c->id] + $d);
                return [$main, ...$more];
            });
        } catch (InvalidArgumentException $e) {
            $this->addError($this->orderId ? 'fromOrder' : 'f.value', $e->getMessage()); return;
        }
        $phone = collect($entries)->firstWhere('type', 'phone')?->value;
        $cancelled = $this->f['cancel'] && $phone && $this->f['action'] === 'block' ? $bl->cancelOpenOrders($phone) : 0;
        $this->dispatch('close-modal');
        $this->toast(($this->editingId ? 'Blacklist entry updated' : count($entries).' blacklist entr'.(count($entries) === 1 ? 'y' : 'ies').' saved').($cancelled ? " · $cancelled open order(s) cancelled" : ''));
        $this->reset('editingId', 'orderId', 'fromOrder'); $this->f = self::BLANK; $this->forget();
    }

    /** "Blacklist customer" on an order: tick its phone / device / IP. */
    protected function openFromOrder(int $id): bool
    {
        $o = Order::find($id); if (! $o) return false;
        $this->resetValidation(); $this->editingId = null; $this->orderId = $o->id; $this->f = self::BLANK;
        $this->fromOrder = array_values(array_filter(['phone', $o->device_id ? 'device' : null]));
        return true;
    }

    #[Computed] public function sourceOrder(): ?Order { return $this->orderId ? Order::with('device:id,name', 'customer:id,name,phone,orders_count,delivered_count,returned_count')->find($this->orderId) : null; }

    public function remove(int $id, Blacklist $bl): void { $bl->remove(BlacklistEntry::findOrFail($id)); $this->toast('Removed from the blacklist'); $this->forget(); }
    public function restore(int $id, Blacklist $bl): void { $bl->restore(BlacklistEntry::findOrFail($id)); $this->toast('Back on the blacklist'); $this->forget(); }
    public function destroy(int $id, Blacklist $bl): void { $bl->delete(BlacklistEntry::findOrFail($id)); $this->toast('Entry and its attempt history deleted'); $this->forget(); }

    public function bulk(string $do, Blacklist $bl): void
    {
        $list = BlacklistEntry::whereIn('id', array_map('intval', $this->selected))->get();
        foreach ($list as $e) {
            if ($do === 'remove' && $e->is_active) $bl->remove($e);
            elseif ($do === 'restore' && ! $e->is_live) $bl->restore($e);
            elseif ($do === 'delete') $bl->delete($e);
            elseif (in_array($do, ['block', 'flag']) && $e->action !== $do) { $e->update(['action' => $do]); $bl->sync($e); }
        }
        if (in_array($do, ['block', 'flag'])) ops_log($list->count().' blacklist entries set to '.config("ops.blacklist_actions.$do.0"));
        $this->toast($list->count().' entr'.($list->count() === 1 ? 'y' : 'ies').' updated'); $this->selected = []; $this->forget();
    }

    /** Paste a list (one per line, or comma separated) → one entry each; invalid lines reported back. */
    public function import(Blacklist $bl): void
    {
        $this->validate(['imp.list' => 'required|max:20000', 'imp.type' => 'required|in:'.implode(',', Blacklist::TYPES), 'imp.action' => 'required|in:block,flag',
            'imp.reason' => 'required|in:'.implode(',', array_keys(config('ops.blacklist_reasons'))), 'imp.note' => 'nullable|max:1000']);
        $items = array_unique(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $this->imp['list']))));
        $added = 0; $bad = [];
        foreach (array_slice($items, 0, 1000) as $v) {
            try { $bl->add(['type' => $this->imp['type'], 'value' => $v, 'action' => $this->imp['action'], 'reason' => $this->imp['reason'], 'note' => $this->imp['note'] ?: null, 'source' => 'import']); $added++; }
            catch (InvalidArgumentException) { $bad[] = $v; }
        }
        $this->impResult = ['added' => $added, 'bad' => $bad];
        if (! $bad) { $this->dispatch('close-modal'); $this->imp['list'] = ''; }
        $this->toast("$added entr".($added === 1 ? 'y' : 'ies').' imported'.($bad ? ' · '.count($bad).' invalid' : ''), $bad ? 'bad' : 'ok'); $this->forget();
    }

    public function export()
    {
        $rows = $this->applyStatus($this->base(), $this->status)->when($this->selected, fn ($q) => $q->whereIn('id', $this->selected))->with('customer:id,name', 'creator:id,name', 'device:id,name')->latest('id')->get();
        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w'); fputcsv($f, ['Type', 'Value', 'Device', 'Customer', 'Action', 'Reason', 'Note', 'Status', 'Attempts', 'Last attempt', 'Expires', 'Source', 'Added by', 'Added']);
            foreach ($rows as $e) fputcsv($f, [$e->type, $e->value, $e->device?->name, $e->customer?->name, $e->action, config('ops.blacklist_reasons.'.$e->reason, $e->reason), $e->note, $e->state, $e->hits,
                $e->last_hit_at?->toDateTimeString(), $e->expires_at?->toDateString(), $e->source, $e->creator?->name, $e->created_at->toDateTimeString()]);
            fclose($f);
        }, 'blacklist-'.now()->format('Ymd-His').'.csv');
    }

    // ── check tool ──────────────────────────────────────────────────────────────────────────────

    /** Any phone / IP / email / device id → matching entries + the customer's record. */
    #[Computed]
    public function checkResult(): ?array
    {
        $v = trim($this->check); if ($v === '') return null;
        $phone = Blacklist::normalize('phone', $v);
        $ctx = ['phone' => $phone, 'ip' => Blacklist::normalize('ip', $v), 'email' => Blacklist::normalize('email', $v), 'device_id' => ! $phone && ($id = Blacklist::normalize('device', $v)) ? $id : null];
        $all = BlacklistEntry::where(function ($q) use ($ctx) {
            foreach (['phone' => 'phone', 'ip' => 'ip', 'email' => 'email', 'device' => 'device_id'] as $type => $k) if ($ctx[$k]) $q->orWhere(fn ($q) => $q->where('type', $type)->where('value', $ctx[$k]));
        })->get();
        return ['valid' => (bool) array_filter($ctx), 'live' => app(Blacklist::class)->matches($ctx), 'other' => $all->reject->is_live,
            'customer' => $phone ? Customer::where('phone', $phone)->first() : null, 'device' => $ctx['device_id'] ? Device::find($ctx['device_id']) : null];
    }

    // ── attempts ────────────────────────────────────────────────────────────────────────────────

    protected function hitsBase()
    {
        $q = BlacklistHit::query()
            ->when($this->entry, fn ($q, $id) => $q->where('blacklist_id', $id))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('phone', 'like', '%'.(Blacklist::normalize('phone', $s) ?? $s).'%')->orWhere('name', 'like', "%$s%")->orWhere('ip_address', 'like', "%$s%")))
            ->when($this->filters['channel'] ?? null, fn ($q, $c) => $q->where('channel', $c));
        return $this->dateRange($q, 'created_at');
    }

    protected function applyHitStatus($q, string $s) { return match ($s) { 'blocked' => $q->where('blocked', true), 'flagged' => $q->where('blocked', false), default => $q }; }

    #[Computed] public function hitCounts(): array { return collect(['all', 'blocked', 'flagged'])->mapWithKeys(fn ($s) => [$s => $this->applyHitStatus($this->hitsBase(), $s)->count()])->all(); }
    #[Computed] public function hits() { return $this->applyHitStatus($this->hitsBase(), $this->status)->with('entry:id,type,value,action,device_id', 'entry.device:id,name', 'order:id,number,status', 'user:id,name', 'device:id,name')->latest('id')->paginate($this->perPage); }
    #[Computed] public function entryRow(): ?BlacklistEntry { return $this->entry ? BlacklistEntry::with('device:id,name')->find($this->entry) : null; }

    public function attemptsOf(int $id): void { $this->entry = $id; $this->view = 'attempts'; $this->status = 'all'; $this->reset('search', 'filters'); $this->resetPage(); }

    // ── settings ────────────────────────────────────────────────────────────────────────────────

    public function saveSettings(Blacklist $bl): void
    {
        $this->validate(['cfg.message' => 'required|max:500', 'cfg.auto.returns' => 'required|integer|min:1|max:50', 'cfg.auto.rate' => 'required|integer|min:1|max:100',
            'cfg.auto.action' => 'required|in:block,flag', 'cfg.auto.days' => 'required|integer|min:0|max:3650']);
        $c = $this->cfg;
        Setting::put('blacklist', ['message' => trim($c['message']), 'match_fingerprint' => (bool) $c['match_fingerprint'], 'skip_autosave' => (bool) $c['skip_autosave'],
            'auto' => ['on' => (bool) $c['auto']['on'], 'returns' => (int) $c['auto']['returns'], 'rate' => (int) $c['auto']['rate'], 'action' => $c['auto']['action'], 'days' => (int) $c['auto']['days']]]);
        $bl->syncAll();
        ops_log('Updated blacklist settings');
        $this->dispatch('close-modal'); $this->toast('Blacklist settings saved');
    }

    protected function forget(): void { cache()->forget('blacklist.count'); unset($this->rows, $this->counts, $this->kpis, $this->hits, $this->hitCounts, $this->checkResult); }

    public function render() { return view('livewire.ops.blacklist.index', ['autoOpen' => $this->autoOpen]); }
}
