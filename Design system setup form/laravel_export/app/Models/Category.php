<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Category extends Model
{
    protected $guarded = [];
    public function parent(): BelongsTo { return $this->belongsTo(Category::class, 'parent_id'); }
    public function products(): BelongsToMany { return $this->belongsToMany(Product::class); }
}
