<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class delivery_areas extends Model
{
    public function order()
    {
        return $this->hasMany(Order::class);
    }
}
