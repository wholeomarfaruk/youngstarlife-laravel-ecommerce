<?php

namespace App\Livewire\Ops;

use App\Http\Middleware\OpsAccess;
use App\Models\Ops\{ActivityLog, LoginEvent};
use App\Services\Ops\TwoFactor;
use Illuminate\Support\Facades\{Auth, DB, Hash, Storage};
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Intervention\Image\Laravel\Facades\Image;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\{Component, WithFileUploads, WithPagination};

/** The signed-in person's own account: details + photo, password, two-factor sign-in, sign-in history, notifications, own activity. */
#[Layout('layouts.ops', ['module' => 'Settings', 'title' => 'My profile'])]
#[Title('My profile')]
class Profile extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(except: 'profile')] public string $tab = 'profile';   // profile | security | notifications | activity
    public array $p = ['name' => '', 'email' => '', 'phone' => '', 'current_password' => ''];
    public $photo;
    public array $pw = ['current' => '', 'password' => '', 'password_confirmation' => ''];
    public string $logoutPassword = '';
    public string $tfPassword = '';      // confirms turning two-factor on / off and new recovery codes
    public string $tfCode = '';
    public bool $tfSetup = false;        // QR step (pending secret lives in the session, not in this public state)
    public ?array $shownCodes = null;    // recovery codes, shown once
    public array $notify = [];           // event => [app, push]

    public function mount(): void
    {
        if (! in_array($this->tab, ['profile', 'security', 'notifications', 'activity'])) $this->tab = 'profile';
        $this->fillForms();
        $this->tfSetup = session()->has('ops.2fa_setup') && ! auth()->user()->hasTwoFactor();
    }

    protected function fillForms(): void
    {
        $u = auth()->user();
        $this->p = ['name' => $u->name, 'email' => (string) $u->email, 'phone' => (string) $u->phone, 'current_password' => ''];
        foreach (config('ops.notify_events') as $k => $_) $this->notify[$k] = ['app' => $u->wantsOps($k), 'push' => $u->wantsOps($k, 'push')];
    }

    // ── Profile

    public function saveProfile(): void
    {
        $u = auth()->user(); $cid = $u->customer?->id;   // a linked customer profile needs a unique phone (same rules as Users → user)
        $emailChanged = mb_strtolower(trim($this->p['email'])) !== mb_strtolower((string) $u->email);
        $this->validate([
            'p.name' => 'required|max:120',
            'p.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($u->id)],
            'p.phone' => array_filter([$cid ? 'required' : 'nullable', 'max:20', Rule::unique('users', 'phone')->ignore($u->id), $cid ? Rule::unique('ops_customers', 'phone')->ignore($cid) : null]),
            'p.current_password' => $emailChanged ? 'required|current_password' : 'nullable',
        ], ['p.phone.unique' => 'This phone is already used by another user or customer.', 'p.current_password.required' => 'Enter your password to change the sign-in email.', 'p.current_password.current_password' => 'Password is wrong.']);

        $v = ['name' => trim($this->p['name']), 'email' => trim($this->p['email']), 'phone' => trim($this->p['phone']) ?: null];
        DB::transaction(function () use ($u, $v) {
            $u->forceFill($v)->save();
            $u->customer?->update($v);   // linked profiles follow the account
            $u->supplier?->update(['name' => $v['name'], 'phone' => $v['phone']]);
        });
        ops_log('Updated own profile'.($emailChanged ? ' (sign-in email changed)' : ''), $u);
        $this->fillForms(); $this->toast('Profile saved');
    }

    public function updatedPhoto(): void
    {
        $this->validate(['photo' => 'image|max:4096'], ['photo.max' => 'Photo must be 4 MB or smaller.']);
        $u = auth()->user();
        $path = 'avatars/'.$u->id.'-'.str()->random(8).'.webp';
        Storage::disk('public')->put($path, Image::read($this->photo->getRealPath())->cover(256, 256)->toWebp(85)->toString());
        if ($u->avatar) Storage::disk('public')->delete($u->avatar);
        $u->forceFill(['avatar' => $path])->save();
        $this->reset('photo'); $this->toast('Photo updated');
    }

    public function removePhoto(): void
    {
        $u = auth()->user();
        if ($u->avatar) Storage::disk('public')->delete($u->avatar);
        $u->forceFill(['avatar' => null])->save(); $this->toast('Photo removed');
    }

    /** modules this account can open (Settings → Users & roles) */
    #[Computed]
    public function access(): array
    {
        return collect(config('ops.nav'))->mapWithKeys(fn ($i) => [$i['label'] => OpsAccess::allowed(auth()->user(), $i['label'])])->all();
    }

    // ── Security

    public function savePassword(): void
    {
        $this->validate([
            'pw.current' => 'required|current_password',
            'pw.password' => ['required', 'confirmed', 'different:pw.current', Password::min(8)->letters()->numbers()],
        ], ['pw.current.current_password' => 'Current password is wrong.', 'pw.password.different' => 'Pick a password you are not using now.']);
        auth()->user()->forceFill(['password' => Hash::make($this->pw['password']), 'password_changed_at' => now()])->save();   // other sessions end (authenticated sessions)
        $this->reset('pw'); ops_log('Changed password', auth()->user()); $this->toast('Password changed · other devices signed out');
    }

    public function logoutOthers(): void
    {
        $this->validate(['logoutPassword' => 'required|current_password'], ['logoutPassword.current_password' => 'Password is wrong.']);
        Auth::logoutOtherDevices($this->logoutPassword); $this->reset('logoutPassword');
        ops_log('Logged out other devices', auth()->user()); $this->toast('Other devices signed out');
    }

    public function startTwoFactor(TwoFactor $tf): void
    {
        $this->validate(['tfPassword' => 'required|current_password'], ['tfPassword.current_password' => 'Password is wrong.']);
        session()->put('ops.2fa_setup', encrypt($tf->generateSecret()));
        $this->reset('tfPassword', 'tfCode'); $this->tfSetup = true;
    }

    public function confirmTwoFactor(TwoFactor $tf): void
    {
        $secret = session()->has('ops.2fa_setup') ? decrypt(session('ops.2fa_setup')) : null;
        if (! $secret) { $this->tfSetup = false; return; }
        $this->resetErrorBag('tfCode');
        if (! $tf->verify($secret, $this->tfCode)) { $this->addError('tfCode', 'That code is not right. Check the time on your phone and try the newest code.'); return; }
        $codes = $tf->recoveryCodes();
        auth()->user()->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_recovery_codes' => encrypt(json_encode($codes)), 'two_factor_confirmed_at' => now()])->save();
        session()->forget('ops.2fa_setup');
        $this->reset('tfCode', 'tfSetup'); $this->shownCodes = $codes;
        ops_log('Turned on two-factor sign-in', auth()->user()); $this->toast('Two-factor sign-in is on');
    }

    public function cancelTwoFactor(): void { session()->forget('ops.2fa_setup'); $this->reset('tfCode', 'tfSetup'); }

    public function newRecoveryCodes(TwoFactor $tf): void
    {
        $this->validate(['tfPassword' => 'required|current_password'], ['tfPassword.current_password' => 'Password is wrong.']);
        $codes = $tf->recoveryCodes();
        auth()->user()->forceFill(['two_factor_recovery_codes' => encrypt(json_encode($codes))])->save();
        $this->reset('tfPassword'); $this->shownCodes = $codes;
        ops_log('Made new two-factor recovery codes', auth()->user()); $this->toast('New recovery codes · the old ones stopped working');
    }

    public function disableTwoFactor(): void
    {
        $this->validate(['tfPassword' => 'required|current_password'], ['tfPassword.current_password' => 'Password is wrong.']);
        auth()->user()->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $this->reset('tfPassword', 'shownCodes');
        ops_log('Turned off two-factor sign-in', auth()->user()); $this->toast('Two-factor sign-in is off', 'warn');
    }

    #[Computed]
    public function qr(): ?array
    {
        if (! $this->tfSetup || ! session()->has('ops.2fa_setup')) return null;
        $tf = app(TwoFactor::class); $secret = decrypt(session('ops.2fa_setup'));
        return ['svg' => $tf->qrSvg($tf->otpauthUrl(auth()->user(), $secret)), 'secret' => trim(chunk_split($secret, 4, ' '))];
    }

    #[Computed]
    public function logins()
    {
        return LoginEvent::where('user_id', auth()->id())->latest('created_at')->latest('id')->limit(25)->get();
    }

    #[Computed]
    public function failedRecently(): int
    {
        return LoginEvent::where('user_id', auth()->id())->whereIn('event', ['failed', 'two_factor_failed'])->where('created_at', '>=', now()->subDays(7))->count();
    }

    // ── Notifications

    public function saveNotifications(): void
    {
        $prefs = auth()->user()->ops_prefs ?? [];
        $prefs['notify'] = collect($this->notify)->map(fn ($c) => ['app' => (bool) ($c['app'] ?? true), 'push' => (bool) ($c['push'] ?? true)])->all();
        auth()->user()->forceFill(['ops_prefs' => $prefs])->save();
        $this->toast('Notification settings saved');
    }

    // ── Activity

    #[Computed]
    public function activity()
    {
        return ActivityLog::where('user_id', auth()->id())->latest('created_at')->latest('id')->paginate(25);
    }

    public function updatedTab(): void { $this->resetPage(); $this->resetErrorBag(); }

    protected function toast(string $message, string $type = 'ok'): void { $this->dispatch('toast', message: $message, type: $type); }

    public function render() { return view('livewire.ops.profile', ['user' => auth()->user()->load('warehouse:id,name')]); }
}
