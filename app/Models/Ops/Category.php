<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

class Category extends OpsModel
{
    protected $guarded = [];
    protected $casts = ['show_on_home' => 'bool'];
    public function parent(): BelongsTo { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Category::class, 'parent_id')->orderBy('sort')->orderBy('name'); }
    public function products(): BelongsToMany { return $this->belongsToMany(Product::class, 'ops_category_product')->withPivot('sort_order'); }
    public function getImageUrlAttribute(): ?string { return Product::mediaUrl($this->image); }
}
