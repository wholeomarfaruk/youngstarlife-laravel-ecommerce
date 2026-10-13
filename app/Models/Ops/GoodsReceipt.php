<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class GoodsReceipt extends OpsModel
{
    protected $guarded = [];
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function batches(): HasMany { return $this->hasMany(Batch::class); }
}
