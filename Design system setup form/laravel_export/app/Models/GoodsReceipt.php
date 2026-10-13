<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class GoodsReceipt extends Model
{
    protected $guarded = [];
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function batches(): HasMany { return $this->hasMany(Batch::class); }
}
