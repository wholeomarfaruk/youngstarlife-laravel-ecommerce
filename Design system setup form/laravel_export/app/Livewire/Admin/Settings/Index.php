<?php

namespace App\Livewire\Admin\Settings;

use App\Models\{Courier, DeliveryZone, Setting, User};
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Settings', 'title' => 'Settings'])]
#[Title('Settings')]
class Index extends Component
{
    #[Url] public string $tab = 'store';  // store | users | delivery | couriers | notifications | seo | pixels | profile
    public array $store = []; public array $zones = []; public array $couriers = []; public array $notify = []; public array $seo = []; public array $pixels = [];

    public function mount(): void
    {
        $this->store = Setting::get('store', ['name' => config('app.name'), 'email' => '', 'phone' => '', 'address' => '', 'order_prefix' => '#']);
        $this->zones = DeliveryZone::orderBy('id')->get(['id', 'name', 'charge', 'eta', 'is_active'])->toArray();
        $this->couriers = Courier::orderBy('id')->get(['id', 'name', 'code', 'is_active'])->toArray();
        $this->notify = Setting::get('notify', ['new_order' => true, 'low_stock' => true, 'delayed' => true, 'daily_email' => false]);
        $this->seo = Setting::get('seo', ['title' => '', 'description' => '']); $this->pixels = Setting::get('pixels', ['meta_pixel' => '', 'ga4' => '', 'gtm' => '']);
    }

    public function addZone(): void { $this->zones[] = ['id' => null, 'name' => '', 'charge' => 0, 'eta' => '', 'is_active' => true]; }

    public function save(): void
    {
        match ($this->tab) {
            'store' => Setting::put('store', $this->store), 'notifications' => Setting::put('notify', $this->notify),
            'seo' => Setting::put('seo', $this->seo), 'pixels' => Setting::put('pixels', $this->pixels),
            'delivery' => collect($this->zones)->each(fn ($z) => DeliveryZone::updateOrCreate(['id' => $z['id']], collect($z)->except('id')->all())),
            'couriers' => collect($this->couriers)->each(fn ($c) => Courier::whereKey($c['id'])->update(['is_active' => $c['is_active']])),
            default => null,
        };
        cache()->forget('zones'); $this->dispatch('toast', message: 'Settings saved');
    }

    public function render() { return view('livewire.admin.settings.index', ['users' => $this->tab === 'users' ? User::orderBy('name')->get(['id', 'name', 'email', 'role']) : collect()]); }
}
