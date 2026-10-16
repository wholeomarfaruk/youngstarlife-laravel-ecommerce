<?php

namespace App\Livewire\Ops\Settings;

use App\Models\Ops\{ActivityLog, Courier, DeliveryZone, OrderEvent, Order, Setting, Warehouse, WebhookLog};
use App\Models\User;
use App\Services\Ops\Mailer;
use App\Support\OpsDefaults;
use Illuminate\Support\Facades\{Hash, Password};
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;
use Livewire\{WithFileUploads, WithPagination};

#[Layout('layouts.ops', ['module' => 'Settings', 'title' => 'Settings'])]
#[Title('Settings')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    public const NAV = [
        'General' => ['company' => 'Company', 'orders' => 'Orders & delivery', 'delivery' => 'Delivery areas', 'payments' => 'Payment methods'],
        'Marketing' => ['branding' => 'Branding', 'seo' => 'SEO', 'pixels' => 'Pixels & tracking', 'content' => 'Website texts'],
        'Integrations' => ['webhooks' => 'Webhooks', 'push' => 'Web push', 'smtp' => 'Email SMTP'],
        'Operations' => ['automations' => 'Automations', 'batch' => 'Batch & stock', 'barcode' => 'Barcode & labels', 'ai_order' => 'AI order entry', 'returns' => 'Returns', 'couriers' => 'Couriers'],
        'Team' => ['users' => 'Users & roles', 'sms' => 'SMS & notifications', 'activity' => 'Activity log'],
        'System' => ['system' => 'Cache & backup'],
    ];
    /** sections stored as one JSON settings group each */
    public const GROUPS = ['company', 'orders', 'payments', 'branding', 'seo', 'pixels', 'content', 'webhooks', 'push', 'smtp', 'automations', 'batch', 'barcode', 'returns', 'sms', 'permissions'];
    /** left out of a backup unless asked for; an import that has them empty keeps the current value */
    public const SECRETS = ['smtp.password', 'pixels.capi_token'];

    #[Url] public string $tab = 'company';
    public array $s = [];                   // every settings group
    public array $zones = []; public array $couriers = []; public array $warehouses = [];
    public $logo; public $favicon; public $shareImage; public $backup;
    public bool $withSecrets = false;
    public string $newReason = ''; public string $newWarehouse = '';
    public array $invite = ['name' => '', 'email' => '', 'role' => 'Packer'];
    public string $activitySearch = ''; public ?int $activityUser = null;
    public ?string $tempPassword = null;

    public function mount(): void
    {
        if (! collect(self::NAV)->flatMap(fn ($g) => array_keys($g))->contains($this->tab)) $this->tab = 'company';
        $this->load();
    }

    protected function load(): void
    {
        foreach (self::GROUPS as $g) $this->s[$g] = OpsDefaults::merge(OpsDefaults::get($g), Setting::get($g, []));
        $this->s['permissions'] = array_merge(OpsDefaults::get('permissions'), (array) Setting::get('permissions', []));   // role lists replace whole
        $this->zones = DeliveryZone::orderBy('id')->get(['id', 'name', 'charge', 'per_kg', 'eta', 'default_courier', 'areas', 'is_active'])->map(fn ($z) => ['areas' => implode(', ', $z->areas ?? [])] + $z->toArray())->all();
        $this->couriers = Courier::with('zone:id,name')->orderBy('id')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'code' => $c->code, 'is_active' => $c->is_active, 'webhook_on' => $c->credentials['webhook_on'] ?? true, 'events' => $c->credentials['events'] ?? array_keys(config('ops.courier_status')),
            'secret' => $c->webhook_secret, 'url' => $c->webhookUrl(), 'secret_label' => match ($c->code) { 'steadfast' => 'Auth token (Steadfast sends it as Bearer)', 'pathao' => 'Webhook secret (Pathao sends it in X-PATHAO-Signature)', default => 'Secret (?secret= in the URL or X-Webhook-Secret header)' },
            'last' => $c->webhookLogs()->latest('created_at')->value('created_at'), 'key' => $c->masked_key, 'zone' => $c->zone?->name, 'charge' => $c->charge, 'pickup_store' => $c->pickup_store, 'show' => false])->all();
        $this->warehouses = Warehouse::orderBy('id')->get(['id', 'name', 'address', 'is_default'])->toArray();
        $this->dispatch('saved');
    }

    public function updatedTab(): void { $this->resetValidation(); $this->resetPage(); }

    /** Discard: reload everything from the database */
    public function discard(): void { $this->load(); $this->reset('logo', 'favicon'); $this->dispatch('toast', message: 'Changes discarded'); }

    public function save(): void
    {
        $rules = match ($this->tab) {
            'company' => ['s.company.name' => 'required|max:120', 's.company.email' => 'nullable|email', 's.company.invoice_prefix' => 'nullable|max:10'],
            'orders' => ['s.orders.prefix' => 'required|max:6', 's.orders.auto_cancel_hours' => 'nullable|integer|min:0', 's.orders.free_delivery_above' => 'nullable|integer|min:0', 's.orders.exchange_charge' => 'required|integer|min:0'],
            'delivery' => ['zones.*.name' => 'required|max:120', 'zones.*.charge' => 'required|integer|min:0', 'zones.*.per_kg' => 'nullable|integer|min:0'],
            'branding' => ['s.branding.store_name' => 'required|max:120', 'logo' => 'nullable|image|max:2048', 'favicon' => 'nullable|image|max:512', 's.branding.primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 's.branding.footer_note' => 'nullable|max:200'],
            'seo' => ['s.seo.title' => 'required|max:120', 's.seo.title_format' => ['required', 'max:60', 'regex:/\{page\}/'], 's.seo.description' => 'nullable|max:300', 'shareImage' => 'nullable|image|max:2048'],
            'pixels' => ['s.pixels.gtm' => ['nullable', 'regex:/^GTM-[A-Z0-9]+$/'], 's.pixels.ga4' => ['nullable', 'regex:/^G-[A-Z0-9]+$/'], 's.pixels.fb_pixel' => 'nullable|digits_between:10,20', 's.pixels.item_brand' => 'nullable|max:60', 's.pixels.item_category' => 'nullable|max:60'],
            'content' => ['s.content.faq_title' => 'nullable|max:120', 's.content.faq.*' => 'nullable|max:500', 's.content.product_note.text' => 'nullable|max:200', 's.content.order_duplicate' => 'required|max:600', 's.content.order_failed' => 'required|max:400', 's.content.maintenance' => 'required|max:400'],
            'smtp' => ['s.smtp.port' => 'nullable|integer', 's.smtp.from_email' => 'nullable|email', 's.smtp.reply_to' => 'nullable|email'],
            'batch' => ['s.batch.format' => ['required', 'regex:/\{#+\}/'], 's.batch.low_threshold' => 'required|integer|min:0'],
            default => [],
        };
        if ($rules) $this->validate($rules, ['zones.*.name.required' => 'Every delivery area needs a name.', 'zones.*.charge.*' => 'Delivery charge must be a whole number.', 's.batch.format.regex' => 'Batch format needs a number placeholder like B{###}.',
            's.branding.primary_color.regex' => 'Brand colour must look like #525754.', 's.seo.title_format.regex' => 'Title format needs {page}, e.g. {page} · {site}.',
            's.pixels.gtm.regex' => 'GTM container looks like GTM-XXXXXXX.', 's.pixels.ga4.regex' => 'GA4 ID looks like G-XXXXXXX.', 's.pixels.fb_pixel.digits_between' => 'Facebook Pixel ID is a number.']);
        if ($this->tab === 'content') $this->s['content']['faq'] = array_values(array_filter(array_map('trim', $this->s['content']['faq'] ?? []), 'strlen'));

        match ($this->tab) {
            'delivery' => collect($this->zones)->each(fn ($z) => DeliveryZone::updateOrCreate(['id' => $z['id'] ?? null], ['name' => $z['name'], 'charge' => (int) $z['charge'], 'per_kg' => (int) ($z['per_kg'] ?? 0), 'eta' => $z['eta'] ?: null,
                'default_courier' => $z['default_courier'] ?: null, 'areas' => array_values(array_filter(array_map('trim', explode(',', (string) ($z['areas'] ?? ''))))), 'is_active' => (bool) $z['is_active']])),
            'webhooks' => collect($this->couriers)->each(function ($c) {
                $x = Courier::find($c['id']); if (! $x) return;
                $x->update(['is_active' => (bool) $c['is_active'], 'credentials' => array_merge($x->credentials ?? [], ['webhook_on' => (bool) $c['webhook_on'], 'events' => array_values($c['events'] ?? [])])]);
            }),
            'company' => collect($this->warehouses)->each(fn ($w) => Warehouse::whereKey($w['id'])->update(['name' => $w['name'], 'address' => $w['address'] ?: null, 'is_default' => (int) ($this->s['company']['warehouse_id'] ?? 0) === $w['id']])),
            default => null,
        };
        if ($this->tab === 'branding') {
            if ($this->logo) $this->s['branding']['logo'] = $this->logo->store('branding', 'public');
            if ($this->favicon) $this->s['branding']['favicon'] = $this->favicon->store('branding', 'public');
        }
        if ($this->tab === 'seo' && $this->shareImage) { $this->s['seo']['share_image_path'] = $this->shareImage->store('branding', 'public'); $this->s['seo']['share_image'] = 'custom'; }
        foreach (self::GROUPS as $g) if ($this->sectionGroup() === $g || ($this->tab === 'users' && $g === 'permissions') || ($this->tab === 'webhooks' && $g === 'webhooks')) Setting::put($g, $this->s[$g]);
        cache()->forget('zones');
        ops_log('Settings saved · '.$this->tabLabel());
        $this->load(); $this->reset('logo', 'favicon', 'shareImage');
        $this->dispatch('toast', message: 'Settings saved');
    }

    /** Branding: back to the built-in logo / favicon */
    public function clearImage(string $field): void
    {
        abort_unless(in_array($field, ['logo', 'favicon']), 422);
        $this->s['branding'][$field] = null; Setting::put('branding', $this->s['branding']);
        ops_log('Settings · removed '.$field); $this->dispatch('toast', message: ucfirst($field).' removed');
    }

    // ── Orders & delivery · website cart on/off (saved at once, after the confirm — never mixed with unsaved form edits)
    public function setCart(bool $on): void
    {
        $orders = (array) Setting::get('orders', []); $orders['cart_enabled'] = $on;
        Setting::put('orders', $orders);
        $this->s['orders']['cart_enabled'] = $on;
        ops_log('Website cart '.($on ? 'enabled' : 'disabled'));
        $this->dispatch('toast', message: $on ? 'Cart enabled — customers can add to cart again' : 'Cart disabled — customers order from the product page');
    }

    // ── Website texts · FAQ lines
    public function addFaq(): void { $this->s['content']['faq'][] = ''; }
    public function removeFaq(int $i): void { unset($this->s['content']['faq'][$i]); $this->s['content']['faq'] = array_values($this->s['content']['faq']); }
    public function moveFaq(int $i, int $dir): void
    {
        $f = $this->s['content']['faq']; $j = $i + $dir; if (! isset($f[$j])) return;
        [$f[$i], $f[$j]] = [$f[$j], $f[$i]]; $this->s['content']['faq'] = $f;
    }

    // ── System: cache & backup
    #[Computed]
    public function system(): array
    {
        $stored = Setting::query()->get(['key']);
        return ['store' => config('cache.default'), 'groups' => $stored->count(), 'keys' => $stored->pluck('key')->sort()->values()->all(),
            'cached' => cache()->has(\App\Support\SiteSettings::CACHE_KEY)];
    }

    public function clearCache(): void
    {
        site()->flush(); cache()->forget('zones'); cache()->forget('sitemap.xml'); \App\Services\Storefront\MenuService::forget();
        ops_log('Cleared settings cache'); unset($this->system); $this->dispatch('toast', message: 'Settings cache cleared — the website reads fresh values');
    }

    public function exportSettings()
    {
        $all = Setting::query()->orderBy('key')->get()->mapWithKeys(fn ($r) => [$r->key => $r->value])->all();
        if (! $this->withSecrets) foreach (self::SECRETS as $path) if (data_get($all, $path) !== null) data_set($all, $path, null);
        $json = json_encode(['app' => config('app.name'), 'exported_at' => now()->toIso8601String(), 'settings' => $all], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ops_log('Exported settings'.($this->withSecrets ? ' (with secrets)' : ''));
        return response()->streamDownload(fn () => print($json), 'settings-'.now()->format('Y-m-d-His').'.json', ['Content-Type' => 'application/json']);
    }

    public function importSettings(): void
    {
        $this->validate(['backup' => 'required|file|max:1024'], ['backup.required' => 'Choose a settings .json file first.']);
        $data = json_decode((string) file_get_contents($this->backup->getRealPath()), true);
        $groups = is_array($data['settings'] ?? null) ? $data['settings'] : null;
        if (! $groups) { $this->addError('backup', 'This is not a settings backup file.'); return; }
        $known = array_unique(array_merge(array_keys(OpsDefaults::all()), Setting::query()->pluck('key')->all(), ['report_schedules', 'ai_order']));
        $n = 0;
        foreach ($groups as $key => $value) {
            if (! in_array($key, $known, true) || ! is_array($value)) continue;
            foreach (self::SECRETS as $path) if (str_starts_with($path, $key.'.') && data_get($value, substr($path, strlen($key) + 1)) === null)
                data_set($value, substr($path, strlen($key) + 1), data_get(Setting::get($key, []), substr($path, strlen($key) + 1)));   // keep the current secret
            Setting::put($key, $value); $n++;
        }
        ops_log("Imported settings ($n groups)");
        $this->reset('backup'); $this->load(); unset($this->system);
        $this->dispatch('toast', message: "$n settings groups imported");
    }

    protected function sectionGroup(): ?string { return in_array($this->tab, self::GROUPS) ? $this->tab : null; }
    public function tabLabel(): string { foreach (self::NAV as $g) if (isset($g[$this->tab])) return $g[$this->tab]; return 'Settings'; }

    // ── Company · warehouses
    public function addWarehouse(): void
    {
        $name = trim($this->newWarehouse); if ($name === '') return;
        Warehouse::create(['name' => $name, 'is_default' => ! Warehouse::exists()]); $this->newWarehouse = '';
        $this->warehouses = Warehouse::orderBy('id')->get(['id', 'name', 'address', 'is_default'])->toArray(); $this->dispatch('toast', message: 'Warehouse added');
    }

    // ── Delivery areas
    public function addZone(): void { $this->zones[] = ['id' => null, 'name' => '', 'charge' => 0, 'per_kg' => 0, 'eta' => '', 'default_courier' => null, 'areas' => '', 'is_active' => true]; }

    // ── Webhooks
    public function rotateSecret(int $id): void { Courier::whereKey($id)->update(['webhook_secret' => str()->random(32)]); ops_log('Rotated webhook secret'); $this->load(); $this->dispatch('toast', message: 'New secret generated — update it in the courier panel'); }
    #[Computed] public function webhookLog() { return WebhookLog::with('courier:id,name')->latest('created_at')->limit(20)->get(); }

    // ── SMTP
    public function smtpPreset(string $p): void
    {
        $this->s['smtp'] = array_merge($this->s['smtp'], ['preset' => $p] + (['gmail' => ['host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls'], 'zoho' => ['host' => 'smtp.zoho.com', 'port' => 465, 'encryption' => 'ssl'],
            'outlook' => ['host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls'], 'cpanel' => ['host' => 'mail.'.parse_url(config('app.url'), PHP_URL_HOST), 'port' => 465, 'encryption' => 'ssl']][$p] ?? []));
    }
    public function testSmtp(Mailer $mailer): void
    {
        Setting::put('smtp', $this->s['smtp']);
        try { $mailer->send(auth()->user()->email, 'YoungStar Ops · SMTP test', 'If you can read this, Ops email works.'); $this->s['smtp']['verified'] = true; $this->dispatch('toast', message: 'Test email sent to '.auth()->user()->email); }
        catch (\Throwable $e) { $this->s['smtp']['verified'] = false; $this->dispatch('toast', message: 'Not verified: '.\Illuminate\Support\Str::limit($e->getMessage(), 90), type: 'bad'); }
        Setting::put('smtp', $this->s['smtp']);
    }

    // ── Automations stats
    #[Computed]
    public function automationStats(): array
    {
        $today = OrderEvent::where('type', 'automation')->whereDate('created_at', today());
        $per = fn ($like) => [OrderEvent::where('type', 'automation')->where('message', 'like', $like)->whereDate('created_at', today())->count(), OrderEvent::where('type', 'automation')->where('message', 'like', $like)->max('created_at')];
        return ['active' => collect($this->s['automations'])->filter(fn ($r) => is_array($r) && ($r['on'] ?? false))->count(), 'today' => (clone $today)->count(), 'saved' => (clone $today)->count() * 2,
            'human' => Order::live()->where(fn ($q) => $q->where('status', 'on_hold')->orWhere(fn ($q) => $q->where('status', 'pending')->where('placed_at', '<', now()->subDay())))->count(),
            'runs' => ['auto_confirm' => $per('Auto-confirmed%'), 'flag_risky' => $per('Risky customer%'), 'auto_courier' => $per('Auto courier%'), 'auto_hold' => $per('2 failed%')]];
    }

    // ── Returns reasons
    public function addReason(): void { $r = trim($this->newReason); if ($r !== '' && ! in_array($r, $this->s['returns']['reasons'])) $this->s['returns']['reasons'][] = $r; $this->newReason = ''; }
    public function removeReason(int $i): void { unset($this->s['returns']['reasons'][$i]); $this->s['returns']['reasons'] = array_values($this->s['returns']['reasons']); }

    // ── Users & roles
    #[Computed] public function users() { return User::staff()->orderBy('name')->get(); }

    public function inviteUser(): void
    {
        $this->validate(['invite.name' => 'required|max:120', 'invite.email' => 'required|email|unique:users,email', 'invite.role' => 'required|in:'.implode(',', config('ops.roles'))]);
        $temp = str()->password(12, symbols: false);
        $u = User::forceCreate(['name' => $this->invite['name'], 'email' => $this->invite['email'], 'password' => Hash::make($temp), 'role' => 'staff', 'ops_role' => $this->invite['role'], 'is_active' => true]);
        try { Password::sendResetLink(['email' => $u->email]); $this->dispatch('toast', message: 'Invite sent to '.$u->email); $this->tempPassword = null; }
        catch (\Throwable $e) { $this->tempPassword = $u->email.' · temporary password: '.$temp; $this->dispatch('toast', message: 'Email not configured — share the temporary password shown above', type: 'bad'); }
        ops_log('Invited '.$u->name.' as '.$u->ops_role, $u);
        $this->reset('invite'); unset($this->users);
    }

    public function setRole(int $id, string $role): void
    {
        abort_unless(in_array($role, config('ops.roles')), 422);
        $u = User::findOrFail($id); if ($u->id === auth()->id()) { $this->dispatch('toast', message: 'You cannot change your own role', type: 'bad'); return; }
        $u->forceFill(['ops_role' => $role])->save(); ops_log("{$u->name} role → $role", $u); unset($this->users); $this->dispatch('toast', message: $u->name.' is now '.$role);
    }

    public function toggleActive(int $id): void
    {
        $u = User::findOrFail($id); if ($u->id === auth()->id()) { $this->dispatch('toast', message: 'You cannot deactivate yourself', type: 'bad'); return; }
        $u->forceFill(['is_active' => ! $u->is_active])->save(); ops_log(($u->is_active ? 'Activated ' : 'Deactivated ').$u->name, $u); unset($this->users);
    }

    // ── Activity log
    #[Computed]
    public function activity()
    {
        return ActivityLog::with('user:id,name')->when($this->activitySearch, fn ($q, $s) => $q->where('message', 'like', "%$s%"))->when($this->activityUser, fn ($q, $u) => $q->where('user_id', $u))->latest('created_at')->paginate(30);
    }

    public function render() { return view('livewire.ops.settings.index', ['zonesCouriers' => Courier::orderBy('id')->pluck('name', 'code')]); }
}
