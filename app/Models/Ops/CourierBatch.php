<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/** A Bulk courier sheet — see App\Livewire\Ops\Orders\BulkCourier. */
class CourierBatch extends OpsModel
{
    public const DRAFT = 'draft';
    public const COMPLETED = 'completed';

    protected $guarded = [];
    protected $casts = ['completed_at' => 'datetime'];

    public function items(): HasMany { return $this->hasMany(CourierBatchItem::class); }
    public function creator(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'created_by'); }

    /** this staff member's open sheet, created on first use */
    public static function draftFor(int $userId): self { return static::firstOrCreate(['status' => self::DRAFT, 'created_by' => $userId]); }
}
