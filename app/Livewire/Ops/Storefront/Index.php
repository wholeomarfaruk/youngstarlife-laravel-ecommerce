<?php

namespace App\Livewire\Ops\Storefront;

use App\Models\Ops\{Order, Setting};
use App\Support\OpsDefaults;
use Illuminate\Support\Facades\{DB, Schema};
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Storefront', 'title' => 'Storefront'])]
#[Title('Storefront')]
class Index extends Component
{
    public const NAV = [
        'Store' => ['overview' => 'Overview', 'homepage' => 'Homepage', 'menus' => 'Navigation menus', 'pages' => 'Pages'],
        'Selling' => ['coupons' => 'Coupons'],
        'Growth' => ['social' => 'Social & chat', 'announcement' => 'Announcement & popup'],
    ];

    #[Url] public string $tab = 'overview';
    public array $data = [];
    public ?int $page = null;   // page being edited

    public function mount(): void
    {
        if (! collect(self::NAV)->flatMap(fn ($g) => array_keys($g))->contains($this->tab)) $this->tab = 'overview';
        $this->load();
    }
    protected function load(): void { $this->data = OpsDefaults::merge(OpsDefaults::get('storefront'), Setting::get('storefront', [])); $this->dispatch('saved'); }
    public function tabLabel(): string { foreach (self::NAV as $g) if (isset($g[$this->tab])) return $g[$this->tab]; return 'Storefront'; }

    /** traffic from the current shop's visit log (if present) */
    #[Computed]
    public function overview(): array
    {
        $visits = Schema::hasTable('visits') ? (int) DB::table('visits')->where('created_at', '>=', now()->subDays(7))->count() : null;
        $orders = Order::live()->where('source', 'website')->where('placed_at', '>=', now()->subDays(7))->count();
        $d = $this->data;
        $steps = [
            'Logo uploaded' => (bool) ops_setting('branding.logo'), 'At least one banner on' => collect($d['banners'])->contains('on', true),
            'Homepage sections set' => collect($d['sections'])->contains('on', true), 'Policy pages written' => collect($d['pages'])->filter(fn ($p) => trim((string) $p['body']) !== '')->count() >= 3,
            'A coupon is active' => collect($d['coupons'])->contains('on', true), 'Social links added' => (bool) collect($d['social'])->only(['facebook', 'instagram', 'youtube', 'tiktok'])->filter()->count(),
            'Announcement bar set' => (bool) ($d['announcement']['on'] && $d['announcement']['text']),
        ];
        return ['visits' => $visits, 'orders' => $orders, 'conversion' => $visits ? round($orders / max(1, $visits) * 100, 1) : null, 'steps' => $steps, 'progress' => (int) round(collect($steps)->filter()->count() / count($steps) * 100)];
    }

    // homepage
    public function addBanner(): void { $this->data['banners'][] = ['title' => '', 'link' => '/', 'on' => true]; }
    public function removeBanner(int $i): void { unset($this->data['banners'][$i]); $this->data['banners'] = array_values($this->data['banners']); }
    public function moveBanner(int $i, int $dir): void
    {
        $j = $i + $dir; $b = $this->data['banners']; if (! isset($b[$j])) return;
        [$b[$i], $b[$j]] = [$b[$j], $b[$i]]; $this->data['banners'] = $b;
    }
    // menus: own component (Ops\Storefront\Menus, storefront_menus tables)
    // coupons
    public function addCoupon(): void { $this->data['coupons'][] = ['code' => '', 'type' => 'pct', 'value' => 10, 'min' => 0, 'limit' => null, 'used' => 0, 'expires' => null, 'on' => true]; }
    public function removeCoupon(int $i): void { unset($this->data['coupons'][$i]); $this->data['coupons'] = array_values($this->data['coupons']); }

    public function discard(): void { $this->load(); $this->dispatch('toast', message: 'Changes discarded'); }

    public function save(): void
    {
        $this->validate([
            'data.banners.*.title' => 'required|max:120', 'data.pages.*.title' => 'required|max:120', 'data.pages.*.slug' => 'required|alpha_dash|max:60',
            'data.coupons.*.code' => 'required|alpha_dash|max:30|distinct', 'data.coupons.*.type' => 'required|in:pct,flat,ship,bogo', 'data.coupons.*.value' => 'nullable|integer|min:0', 'data.coupons.*.min' => 'nullable|integer|min:0', 'data.coupons.*.limit' => 'nullable|integer|min:0', 'data.coupons.*.expires' => 'nullable|date',
            'data.popup.delay' => 'nullable|integer|min:0|max:120', 'data.social.whatsapp' => ['nullable', 'regex:/^(\+?88)?01[3-9]\d{8}$/'], 'data.social.phone' => 'nullable|max:20', 'data.social.messenger' => 'nullable|max:120',
        ], ['data.coupons.*.code.required' => 'Every coupon needs a code.', 'data.coupons.*.code.distinct' => 'Coupon codes must be unique.', 'data.banners.*.title.required' => 'Every banner needs a title.', 'data.social.whatsapp.regex' => 'WhatsApp number looks like 01XXXXXXXXX.']);
        foreach ($this->data['coupons'] as $i => $c) $this->data['coupons'][$i]['code'] = strtoupper($c['code']);
        Setting::put('storefront', $this->data);
        ops_log('Storefront published · '.$this->tabLabel());
        $this->load(); unset($this->overview);
        $this->dispatch('toast', message: 'Published');
    }

    public function render() { return view('livewire.ops.storefront.index'); }
}
