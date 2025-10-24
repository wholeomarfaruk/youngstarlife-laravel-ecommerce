<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Order_Item;
use App\Models\delivery_areas;
use Illuminate\Notifications\Notifiable;

class Order extends Model
{
      use Notifiable;
      protected $casts = [
        'json_data' => 'array', // automatically converts to/from JSON
    ];
    public function Order_Item()
    {
        return $this->hasMany(Order_Item::class, 'order_id');
    }

    public function delivery_area()
    {
        return $this->belongsTo(delivery_areas::class, 'delivery_area_id');
    }
}
