<?php

namespace App\Models\Storefront;

use Illuminate\Database\Eloquent\Model;

/** A storefront menu location (registered in config/storefront.php → menus). */
class Menu extends Model
{
    protected $table = 'storefront_menus';
    protected $fillable = ['location', 'name', 'title'];

    public function items()
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }

    protected static function booted(): void
    {
        static::saved(fn (Menu $m) => \App\Services\Storefront\MenuService::forget($m->location));
    }
}
