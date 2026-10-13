<?php

namespace App\Services\Ops;

use App\Models\Ops\{Customer, Supplier};
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Every customer / supplier profile hangs off a user account. Finds the account by phone → email, or opens one (no login until a password reset). */
class UserAccounts
{
    protected static ?string $password = null;

    public function resolve(Customer|Supplier $profile): User
    {
        // an account already holding another profile of the same kind is not reused
        $taken = $profile::query()->whereNotNull('user_id')->when($profile->exists, fn ($q) => $q->whereKeyNot($profile->getKey()))->select('user_id');
        $find = fn ($col, $v) => $v ? User::where($col, $v)->whereNotIn('id', $taken)->first() : null;
        $email = $profile instanceof Customer ? $profile->email : null;

        return $find('phone', $profile->phone) ?? $find('email', $email) ?? $this->open($profile instanceof Supplier ? 'supplier' : 'customer', $profile->name, $profile->phone, $email);
    }

    /** Adds the missing Customer / Supplier profile to an account, filled from its details. Throws with a user-facing message when it can't. */
    public function attach(User $user, string $kind): Customer|Supplier
    {
        if ($kind === 'supplier') return $user->supplier ?? Supplier::create(['user_id' => $user->id, 'name' => $user->name, 'phone' => $user->phone]);
        abort_unless($kind === 'customer', 422);
        if ($user->customer) return $user->customer;
        if (! $user->phone) throw new \InvalidArgumentException('Add a phone number to '.$user->name.' first — customers are matched by phone');
        if (! $c = Customer::where('phone', $user->phone)->first()) return Customer::create(['user_id' => $user->id, 'name' => $user->name, 'phone' => $user->phone, 'email' => $user->email]);
        if ($c->user_id) throw new \InvalidArgumentException('Customer '.$user->phone.' is already linked to another user');
        $c->update(['user_id' => $user->id]);   // unlinked customer with this phone → link it instead of a duplicate
        return $c;
    }

    /** New customer / supplier account. An email already in use is left off rather than failing. */
    public function open(string $role, string $name, ?string $phone, ?string $email = null): User
    {
        return User::forceCreate([
            'name' => $name, 'phone' => $phone ?: null, 'email' => $email && ! User::where('email', $email)->exists() ? $email : null,
            'password' => static::$password ??= Hash::make(Str::random(40)), 'role' => $role, 'is_active' => true,
        ]);
    }
}
