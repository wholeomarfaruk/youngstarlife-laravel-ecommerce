<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Supplier extends Model
{
    protected $guarded = [];
    public function batches(): HasMany { return $this->hasMany(Batch::class); }
    public function purchaseOrders(): HasMany { return $this->hasMany(PurchaseOrder::class); }
    public function returns(): HasMany { return $this->hasMany(SupplierReturn::class); }
}
