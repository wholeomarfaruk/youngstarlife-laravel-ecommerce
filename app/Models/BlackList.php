<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlackList extends Model
{
    protected $fillable = ['reason', 'black_listable_id', 'black_listable_type'];

    public function black_listable()
    {
        return $this->morphTo();
    }
    public function decice()
    {
        return $this->belongsTo(Device::class);
    }
}
