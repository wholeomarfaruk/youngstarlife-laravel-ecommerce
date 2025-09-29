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
        return $this->belongsToMany(products::class, 'product_category');
    }
}
