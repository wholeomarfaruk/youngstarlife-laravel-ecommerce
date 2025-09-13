<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
            protected $fillable = [
        'filename',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'type',
        'disk',
        'path',
        'category',
        'mediable_id',
        'mediable_type',
        'user_id',
        'caption',
        'extra'
    ];

        public function variants()
    {
        return $this->hasMany(Variant::class,'media_id');
    }
    public function mediable()
    {
        return $this->morphTo();
    }
}
