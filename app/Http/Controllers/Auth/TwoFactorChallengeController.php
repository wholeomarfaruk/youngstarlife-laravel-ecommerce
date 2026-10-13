<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ops\{LoginHistory, TwoFactor};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Second sign-in step for accounts with two-factor on (LoginController::attemptLogin parks the user in session 'login.2fa' for 5 minutes). */
class TwoFactorChallengeController extends Controller
{
    public function show(Request $request)
    {
        if (! $this->pending($request)) return redirect()->route('login')->withErrors(['email' => 'Sign in again.']);
        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactor $tf)
    {
        if (! $user = $this->pending($request)) return redirect()->route('login')->withErrors(['email' => 'The code step timed out. Sign in again.']);
        $request->validate(['code' => 'nullable|string|max:20', 'recovery_code' => 'nullable|string|max:20']);

        $ok = $request->filled('recovery_code') ? $tf->useRecoveryCode($user, $request->recovery_code) : $tf->verify($user->twoFactorSecret(), (string) $request->code, $user->id);
        if (! $ok) {
            app(LoginHistory::class)->record($user, 'two_factor_failed');
            return back()->withErrors([$request->filled('recovery_code') ? 'recovery_code' : 'code' => 'That code is not right.'])->withInput(['recovery' => $request->filled('recovery_code')]);
        }

        $remember = $request->session()->pull('login.2fa')['remember'] ?? false;
        Auth::login($user, $remember);   // Login event → sign-in history + device link
        $request->session()->regenerate();
        if ($request->filled('recovery_code')) $request->session()->flash('toast', 'Recovery code used — '.count($user->fresh()->twoFactorRecoveryCodes()).' left.');
        return redirect()->intended('/admin/dashboard');   // same landing as LoginController
    }

    protected function pending(Request $request): ?User
    {
        $p = $request->session()->get('login.2fa');
        if (! $p || $p['at'] < time() - 300) { $request->session()->forget('login.2fa'); return null; }
        $user = User::find($p['id']);
        return $user?->hasTwoFactor() ? $user : null;
    }
}
