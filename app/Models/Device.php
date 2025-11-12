<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'name', 'model', 'user_agent', 'ip_address',
        'status', 'customer_id', 'last_seen'
    ];
    protected $appends = ['is_blocked']; // optional if you want it in JSON


    public function customer()
    {
        return $this->belongsTo(Customer::class);
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
