<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoSaveOrderItem extends Model
{
    protected $fillable = [
        'auto_save_order_id',
        'product_id',
        'quantity',
        'price',
        'options',
    ];
    

    protected $casts = [
        'options' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(AutoSaveOrder::class);
    }
    
    public function product()
    {
        return $this->belongsTo(products::class,'product_id');
    }
}
