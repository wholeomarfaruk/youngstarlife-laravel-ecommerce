<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Setting extends Model
{
    protected $guarded = [];
    public $incrementing = false; protected $primaryKey = 'key'; protected $keyType = 'string'; public $timestamps = false;
    protected $casts = ['value' => 'array'];
    public static function get(string $key, $default = null) { return cache()->rememberForever("setting.$key", fn () => static::find($key)?->value ?? $default); }
    public static function put(string $key, $value): void { static::updateOrCreate(['key' => $key], ['value' => $value]); cache()->forget("setting.$key"); }
}
