<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'status', 'user_id'
    ];
    protected $appends = ['is_blocked']; // optional if you want it in JSON


    public function user()
    {
        return $this->belongsTo(User::class);
    }
     public function blackLists()
    {
        return $this->morphMany(BlackList::class, 'black_listable');
    }
    public function devices()
    {
        return $this->hasMany(Device::class);
    }

public function getIsBlockedAttribute(): bool
{

    return $this->blackLists()->exists();
}

}
