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

     public function customer()
    {
        return $this->hasOne(Customer::class, 'phone', 'phone');
        // matches customer.phone = order.phone
    }
     public function device()
    {
        return $this->hasOne(Device::class, 'user_agent', 'user_agent');
        // matches device.user_agent = order.user_agent
    }
}
