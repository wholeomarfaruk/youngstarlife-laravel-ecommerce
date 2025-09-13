<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionRecord extends Model
{
       protected $fillable = [
        'session_id',
        'page_url',
        'events',
    ];

    protected $casts = [
        'events' => 'array',
    ];
}
