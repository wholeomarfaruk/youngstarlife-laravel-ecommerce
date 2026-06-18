<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public function children()
{
    return $this->hasMany(Category::class, 'parent_id');
}

public function parent()
{
    return $this->belongsTo(Category::class, 'parent_id');
}
public function products()
    {
        return $this->belongsToMany(products::class, 'product_category')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order', 'asc')      // 1st: per-category sort order
            ->orderBy('products.sort_order', 'asc')  // 2nd: global product sort order
            ->orderByDesc('products.id');            // tie-breaker
    }
}
