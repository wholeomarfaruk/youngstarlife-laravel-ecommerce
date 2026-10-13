<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use Concerns\LimitsStringLength;

    protected array $stringLimits = ['name' => 255, 'status' => 255];

    protected $fillable = [
        'token', 'name', 'model', 'user_agent', 'ip_address',
        'status', 'customer_id', 'user_id', 'last_seen'
    ];
    protected $casts = ['last_seen' => 'datetime'];
    protected $appends = ['is_blocked']; // optional if you want it in JSON


    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    /** linked once the visitor has an account (first order, login or register) */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function cart()
    {
        return $this->hasOne(Cart::class);
    }
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    public function blackLists()
    {
        return $this->morphMany(BlackList::class, 'black_listable');
    }
    public function getIsBlockedAttribute(): bool
{

    return $this->blackLists()->exists();
}
}
