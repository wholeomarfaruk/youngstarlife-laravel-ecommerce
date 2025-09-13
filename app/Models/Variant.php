<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Variant extends Model
{
          public function main()
    {
        return $this->belongsTo(Media::class,'media_id');
    }
}
