<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Order_Item;
use App\Models\delivery_areas;

class Order extends Model
{
    public function Order_Item()
    {
        return $this->hasMany(Order_Item::class, 'order_id');
    }

    public function delivery_areas()
    {
        return $this->belongsTo(delivery_areas::class, 'delivery_area_id');
    }
}
