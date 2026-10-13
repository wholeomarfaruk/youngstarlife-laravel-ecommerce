<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasPushSubscriptions;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /** Ops admin panel: the warehouse this staff member works from. */
    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Ops\Warehouse::class);
    }

    /** Ops admin panel: customer / supplier profiles hang off a user account. */
    public function customer()
    {
        return $this->hasOne(\App\Models\Ops\Customer::class);
    }

    public function supplier()
    {
        return $this->hasOne(\App\Models\Ops\Supplier::class);
    }

    /** Storefront: browsers this account has used (linked on first order / login / register). */
    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    /** Ops admin panel: site admins and staff with an Ops role (customer / supplier accounts are not staff). */
    public function scopeStaff($q)
    {
        return $q->where(fn ($q) => $q->where('role', 'admin')->orWhereNotNull('ops_role'));
    }

    /** Ops → My profile: two-factor sign-in is on once an authenticator code was confirmed */
    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at && $this->two_factor_secret;
    }

    public function twoFactorSecret(): ?string
    {
        return $this->two_factor_secret ? decrypt($this->two_factor_secret) : null;
    }

    /** @return string[] unused recovery codes */
    public function twoFactorRecoveryCodes(): array
    {
        return $this->two_factor_recovery_codes ? json_decode(decrypt($this->two_factor_recovery_codes), true) : [];
    }

    /** Ops → My profile → Notifications: an event is on unless the person switched it off (channel: app | push) */
    public function wantsOps(string $event, string $channel = 'app'): bool
    {
        return (bool) data_get($this->ops_prefs, "notify.$event.$channel", true);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/'.$this->avatar) : null;
    }

    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', trim($this->name)))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen_at' => 'datetime',   // Ops panel
            'is_active' => 'bool',
            'two_factor_confirmed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'ops_prefs' => 'array',
        ];
    }
}
