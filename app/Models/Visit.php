<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
       protected $fillable = [
        'session_id','ip_address','device','browser','os',
        'referrer','page_url','country','city','timezone'
    ];
}
