<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlackList extends Model
{
    use Concerns\LimitsStringLength;

    protected $fillable = ['reason', 'black_listable_id', 'black_listable_type'];

    // reason = "Blacklist from Order by " + admin name, which can pass 255
    protected array $stringLimits = ['reason' => 255];

    public function black_listable()
    {
        return $this->morphTo();
    }
    public function decice()
    {
        return $this->belongsTo(Device::class);
    }
}
