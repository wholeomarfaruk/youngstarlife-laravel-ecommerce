<?php

namespace App\Livewire\Admin\Storefront;

use App\Models\Setting;
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Storefront', 'title' => 'Storefront'])]
#[Title('Storefront')]
class Index extends Component
{
    #[Url] public string $tab = 'homepage'; // homepage | navigation | pages | coupons | announcement
    public array $data = [];
    public function mount(): void { $this->data = Setting::get('storefront', ['announcement' => '', 'hero_title' => '', 'hero_subtitle' => '', 'coupons' => []]); }
    public function addCoupon(): void { $this->data['coupons'][] = ['code' => '', 'type' => 'percent', 'value' => 10, 'min' => 0]; }
    public function save(): void { Setting::put('storefront', $this->data); $this->dispatch('toast', message: 'Published'); }
    public function render() { return view('livewire.admin.storefront.index'); }
}
