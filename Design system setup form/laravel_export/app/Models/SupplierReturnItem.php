<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class SupplierReturnItem extends Model
{
    protected $guarded = [];
    public function batch(): BelongsTo { return $this->belongsTo(Batch::class); }
}
