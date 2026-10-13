<?php

namespace App\Models\Storefront;

use Illuminate\Database\Eloquent\Model;

/** One link in a storefront menu; `type` decides how the URL is built (App\Services\Storefront\MenuService::url). */
class MenuItem extends Model
{
    protected $table = 'storefront_menu_items';
    protected $fillable = ['menu_id', 'parent_id', 'type', 'label', 'value', 'ref_id', 'icon', 'new_tab', 'is_active', 'sort_order'];
    protected $casts = ['new_tab' => 'bool', 'is_active' => 'bool'];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    protected static function booted(): void
    {
        $flush = fn (MenuItem $i) => \App\Services\Storefront\MenuService::forget(Menu::whereKey($i->menu_id)->value('location'));
        static::saved($flush);
        static::deleted($flush);
    }
}
