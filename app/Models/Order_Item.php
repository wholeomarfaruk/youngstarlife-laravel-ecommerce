<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order_Item extends Model

{
    protected $fillable = ['order_id', 'product_id', 'quantity', 'price','options','rstatus'];
    use HasFactory;
    public function order()
    {
        return $this->belongsTo(Order::class,'order_id');
    }

    public function product()
    {
        return $this->belongsTo(products::class,'product_id');
    }
}
