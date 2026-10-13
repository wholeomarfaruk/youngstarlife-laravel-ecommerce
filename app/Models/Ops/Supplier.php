<?php

namespace App\Models\Ops;

use App\Models\Ops\Concerns\HasUserAccount;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends OpsModel
{
    use HasUserAccount;
    protected $guarded = [];
    protected $casts = ['is_active' => 'bool'];
    public function batches(): HasMany { return $this->hasMany(Batch::class); }
    public function purchaseOrders(): HasMany { return $this->hasMany(PurchaseOrder::class); }
    public function returns(): HasMany { return $this->hasMany(SupplierReturn::class); }
}
