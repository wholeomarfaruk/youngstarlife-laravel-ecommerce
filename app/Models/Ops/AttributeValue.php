<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeValue extends OpsModel
{
    protected $guarded = [];
    public function attribute(): BelongsTo { return $this->belongsTo(Attribute::class); }
}
