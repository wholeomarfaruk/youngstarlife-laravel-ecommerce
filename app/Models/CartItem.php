<?php

namespace App\Models;

use App\Models\Ops\ProductVariant;
use Illuminate\Database\Eloquent\Model;

/**
 * One cart line per variant (Ops product + size). `price` is the last seen price; totals always use the live price.
 * Combos with a size per item are one line per size combination (selection_key "32|34", components in combo_selection).
 */
class CartItem extends Model
{
    protected $fillable = ['cart_id', 'variant_id', 'selection_key', 'combo_selection', 'quantity', 'price'];
    protected $casts = ['combo_selection' => 'array'];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
