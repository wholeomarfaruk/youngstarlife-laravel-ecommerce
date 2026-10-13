<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Setting extends OpsModel
{
    protected $guarded = [];
    public $incrementing = false; protected $primaryKey = 'key'; protected $keyType = 'string'; public $timestamps = false;
    protected $casts = ['value' => 'array'];
    /** raw stored group (no defaults) from the one site-settings cache entry (App\Support\SiteSettings) */
    public static function get(string $key, $default = null) { return site()->raw($key, $default); }
    public static function put(string $key, $value): void { static::updateOrCreate(['key' => $key], ['value' => $value]); site()->flush(); }
    public static function forget(string $key): void { static::whereKey($key)->delete(); site()->flush(); }
}
