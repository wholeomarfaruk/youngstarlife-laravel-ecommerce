<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class CustomerReturnItem extends OpsModel
{
    protected $guarded = [];
    public $timestamps = false; // table has no created_at/updated_at
    public function customerReturn(): BelongsTo { return $this->belongsTo(CustomerReturn::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function supplierReturnItem(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(SupplierReturnItem::class); }
    public function batch(): BelongsTo { return $this->belongsTo(Batch::class); }
}
