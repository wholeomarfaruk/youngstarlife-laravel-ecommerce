<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/admin/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /** two-factor accounts: a right password only opens the code step (TwoFactorChallengeController), not the session */
    protected function attemptLogin(Request $request)
    {
        $provider = $this->guard()->getProvider();
        $user = $provider->retrieveByCredentials($this->credentials($request));
        if ($user?->hasTwoFactor() && $provider->validateCredentials($user, $this->credentials($request))) {
            $request->session()->put('login.2fa', ['id' => $user->id, 'remember' => $request->boolean('remember'), 'at' => time()]);
            return true;
        }
        return $this->guard()->attempt($this->credentials($request), $request->boolean('remember'));
    }

    protected function authenticated(Request $request, $user)
    {
        if (! $user && $request->session()->has('login.2fa')) return redirect()->route('two-factor.challenge');
    }
}
