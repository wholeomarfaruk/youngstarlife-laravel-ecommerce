<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Storefront cart: one per device (App\Services\Storefront\CartService). */
class Cart extends Model
{
    protected $fillable = ['device_id', 'user_id'];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
