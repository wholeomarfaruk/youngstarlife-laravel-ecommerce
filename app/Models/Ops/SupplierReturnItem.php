<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturnItem extends OpsModel
{
    protected $guarded = [];
    public $timestamps = false; // table has no created_at/updated_at
    public function batch(): BelongsTo { return $this->belongsTo(Batch::class); }
    public function supplierReturn(): BelongsTo { return $this->belongsTo(SupplierReturn::class); }
    public function customerReturnItem(): BelongsTo { return $this->belongsTo(CustomerReturnItem::class); }
}
