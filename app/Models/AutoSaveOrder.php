<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoSaveOrder extends Model
{
    protected $table = 'auto_save_orders';

    protected $fillable = [
        'subtotal',
        'discount',
        'fee',
        'total',
        'name',
        'phone',
        'address',
        'delivery_area_id',
        'cod_percentage',
        'cod_charge',
        'status',
        'ip_address',
        'extra_data',
        'json_data',
        'notes',
        'user_agent',
        'user_id',
        'device_id',
    ];

    protected $casts = [
        'extra_data' => 'array',
        'json_data' => 'array',

    ];

    public function items()
    {
        return $this->hasMany(AutoSaveOrderItem::class);
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
