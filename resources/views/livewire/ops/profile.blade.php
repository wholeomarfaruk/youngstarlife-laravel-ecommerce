<div class="flex flex-col gap-3">
    @php($staffRole = $user->ops_role ?: ucfirst($user->role))
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            @if ($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="" class="h-14 w-14 flex-none rounded-full object-cover">
            @else<span class="grid h-14 w-14 flex-none place-items-center rounded-full bg-ink text-lg font-semibold text-white">{{ $user->initials }}</span>@endif
            <div class="flex min-w-0 flex-col gap-0.5">
                <h1 class="truncate text-2xl font-semibold tracking-tight">{{ $user->name }}</h1>
                <div class="flex flex-wrap items-center gap-1.5 text-sm text-ink-muted">
                    <x-ops.badge tone="info">{{ $staffRole }}</x-ops.badge>
                    <span>{{ $user->warehouse?->name ?? 'Main warehouse' }} · joined {{ $user->created_at?->format('M Y') ?? '—' }}</span>
                    <x-ops.badge :tone="$user->hasTwoFactor() ? 'ok' : 'warn'">{{ $user->hasTwoFactor() ? 'Two-factor on' : 'Two-factor off' }}</x-ops.badge>
                </div>
            </div>
        </div>
    </div>

    <div class="flex gap-1 overflow-x-auto border-b border-line-2 [scrollbar-width:none]">
        @foreach (['profile' => 'Profile', 'security' => 'Security', 'notifications' => 'Notifications', 'activity' => 'My activity'] as $k => $l)
            <button type="button" wire:click="$set('tab','{{ $k }}')" @class(['mtab', 'mtab-on' => $tab === $k])>{{ $l }}@if ($k === 'security' && ! $user->hasTwoFactor())<span class="ml-1 inline-block h-1.5 w-1.5 rounded-full bg-warn align-middle"></span>@endif</button>
        @endforeach
    </div>

    @if ($tab === 'profile')
        <div class="grid items-start gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
            <x-ops.card title="Personal details">
                <form wire:submit="saveProfile" class="grid gap-3 sm:grid-cols-2" x-data="{ email: @js((string) $user->email) }">
                    <x-ops.field label="Name" required :error="$errors->first('p.name')"><input wire:model="p.name" class="input" autocomplete="name"></x-ops.field>
                    <x-ops.field label="Phone" :required="(bool) $user->customer" :error="$errors->first('p.phone')"><input wire:model="p.phone" inputmode="tel" class="input" placeholder="01XXXXXXXXX" autocomplete="tel"></x-ops.field>
                    <x-ops.field label="Email" required class="sm:col-span-2" :error="$errors->first('p.email')" hint="You sign in with this email."><input type="email" wire:model="p.email" class="input" autocomplete="email"></x-ops.field>
                    <div x-cloak x-show="$wire.p.email.trim().toLowerCase() !== email.toLowerCase()" class="sm:col-span-2">
                        <x-ops.field label="Your password" required :error="$errors->first('p.current_password')" hint="Needed to change the sign-in email."><input type="password" wire:model="p.current_password" class="input" autocomplete="current-password"></x-ops.field>
                    </div>
                    @if ($user->customer || $user->supplier)<p class="text-xs text-ink-muted sm:col-span-2">Name and phone changes also update your linked {{ collect([$user->customer ? 'customer' : null, $user->supplier ? 'supplier' : null])->filter()->join(' and ') }} profile.</p>@endif
                    <div class="flex justify-end sm:col-span-2"><button class="btn-primary">Save</button></div>
                </form>
            </x-ops.card>

            <div class="flex flex-col gap-3">
                <x-ops.card title="Photo">
                    <div class="flex items-center gap-3">
                        @if ($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="" class="h-16 w-16 rounded-full object-cover">
                        @else<span class="grid h-16 w-16 place-items-center rounded-full bg-soft text-xl font-semibold text-ink-3">{{ $user->initials }}</span>@endif
                        <div class="flex flex-col gap-1.5">
                            <label class="btn-secondary btn-sm cursor-pointer">
                                <span wire:loading.remove wire:target="photo">{{ $user->avatar ? 'Change photo' : 'Upload photo' }}</span><span wire:loading wire:target="photo">Uploading…</span>
                                <input type="file" wire:model="photo" accept="image/*" class="hidden">
                            </label>
                            @if ($user->avatar)<button type="button" wire:click="removePhoto" class="btn-ghost btn-sm text-bad">Remove</button>@endif
                        </div>
                    </div>
                    @error('photo')<p class="mt-2 text-xs text-bad">{{ $message }}</p>@enderror
                </x-ops.card>

                <x-ops.card title="Account">
                    <dl class="grid grid-cols-[100px_1fr] gap-y-1.5 text-sm">
                        <dt class="text-ink-muted">Role</dt><dd>{{ $staffRole }}</dd>
                        <dt class="text-ink-muted">Warehouse</dt><dd>{{ $user->warehouse?->name ?? '—' }}</dd>
                        <dt class="text-ink-muted">Joined</dt><dd>{{ $user->created_at?->format('d M Y') ?? '—' }}</dd>
                        <dt class="text-ink-muted">Password</dt><dd>{{ $user->password_changed_at ? 'changed '.$user->password_changed_at->diffForHumans() : 'never changed here' }}</dd>
                    </dl>
                </x-ops.card>

                <x-ops.card title="My access" subtitle="Set by an admin in Settings → Users & roles">
                    <ul class="flex flex-col gap-1 text-sm">
                        @foreach ($this->access as $module => $ok)
                            <li class="flex items-center gap-2 {{ $ok ? '' : 'text-ink-muted' }}">
                                @if ($ok)<x-heroicon-o-check-circle class="h-4 w-4 text-ok" />@else<x-heroicon-o-minus-circle class="h-4 w-4" />@endif {{ $module }}
                            </li>
                        @endforeach
                    </ul>
                </x-ops.card>
            </div>
        </div>

    @elseif ($tab === 'security')
        @if ($this->failedRecently)
            <div class="flex items-center gap-2 rounded-lg border border-line bg-warn-bg px-3 py-2.5 text-sm">
                <x-heroicon-o-exclamation-triangle class="h-4 w-4 flex-none text-warn" />
                <span class="flex-1">{{ $this->failedRecently }} failed sign-in attempt{{ $this->failedRecently === 1 ? '' : 's' }} on your account in the last 7 days. If that wasn't you, change your password and turn on two-factor.</span>
            </div>
        @endif
        <div class="grid items-start gap-3 lg:grid-cols-2">
            <x-ops.card title="Password" :subtitle="$user->password_changed_at ? 'Last changed '.$user->password_changed_at->diffForHumans() : null">
                <form wire:submit="savePassword" class="grid gap-3">
                    <x-ops.field label="Current password" required :error="$errors->first('pw.current')"><input type="password" wire:model="pw.current" class="input" autocomplete="current-password"></x-ops.field>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ops.field label="New password" required :error="$errors->first('pw.password')" hint="8+ characters with letters and numbers"><input type="password" wire:model="pw.password" class="input" autocomplete="new-password"></x-ops.field>
                        <x-ops.field label="Confirm new password" required><input type="password" wire:model="pw.password_confirmation" class="input" autocomplete="new-password"></x-ops.field>
                    </div>
                    <div class="flex items-center justify-between gap-2"><span class="text-xs text-ink-muted">Your other devices will be signed out.</span><button class="btn-primary">Change password</button></div>
                </form>
            </x-ops.card>

            <x-ops.card title="Two-factor sign-in" subtitle="After your password, ask for a code from an authenticator app (Google Authenticator, Microsoft Authenticator, Authy…).">
                @if ($shownCodes)
                    <div class="flex flex-col gap-2" x-data="{ codes: @js(implode("\n", $shownCodes)) }">
                        <p class="text-sm"><b>Save these recovery codes</b> somewhere safe. Each one signs you in once if you lose your phone. They won't be shown again.</p>
                        <div class="mono grid grid-cols-2 gap-1 rounded-lg border border-line bg-zebra p-3 text-sm">@foreach ($shownCodes as $c)<span>{{ $c }}</span>@endforeach</div>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" class="btn-secondary btn-sm" @click="opsCopy(codes)">Copy</button>
                            <button type="button" class="btn-secondary btn-sm" @click="const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([codes], { type: 'text/plain' })); a.download = 'ops-recovery-codes.txt'; a.click()">Download .txt</button>
                            <button type="button" class="btn-primary btn-sm ml-auto" wire:click="$set('shownCodes', null)">I saved them</button>
                        </div>
                    </div>
                @elseif ($user->hasTwoFactor())
                    <div class="flex flex-col gap-3">
                        <div class="flex flex-wrap items-center gap-2 text-sm"><x-ops.badge tone="ok">On</x-ops.badge><span class="text-ink-muted">since {{ $user->two_factor_confirmed_at->format('d M Y') }} · {{ count($user->twoFactorRecoveryCodes()) }} recovery codes left</span></div>
                        <form wire:submit="newRecoveryCodes" class="flex flex-wrap items-end gap-2">
                            <x-ops.field label="Your password" :error="$errors->first('tfPassword')" class="min-w-[180px] flex-1"><input type="password" wire:model="tfPassword" class="input" autocomplete="current-password"></x-ops.field>
                            <button class="btn-secondary">New recovery codes</button>
                            <button type="button" wire:click="disableTwoFactor" wire:confirm="Turn off two-factor sign-in? Your account will be protected by the password only." class="btn-danger">Turn off</button>
                        </form>
                    </div>
                @elseif ($tfSetup && ($qr = $this->qr))
                    <div class="flex flex-col gap-3">
                        <ol class="list-decimal pl-5 text-sm text-ink-3"><li>Open your authenticator app and scan this code.</li><li>Type the 6-digit code it shows.</li></ol>
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="rounded-lg border border-line bg-white p-2 [&_svg]:h-40 [&_svg]:w-40">{!! $qr['svg'] !!}</div>
                            <div class="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                                <span class="text-ink-muted">Can't scan? Enter this key:</span>
                                <span class="mono break-all font-semibold">{{ $qr['secret'] }}</span>
                                <button type="button" class="btn-ghost btn-sm self-start" onclick="opsCopy('{{ str_replace(' ', '', $qr['secret']) }}')">Copy key</button>
                            </div>
                        </div>
                        <form wire:submit="confirmTwoFactor" class="flex flex-wrap items-end gap-2">
                            <x-ops.field label="Code from the app" :error="$errors->first('tfCode')" class="min-w-[160px] flex-1"><input wire:model="tfCode" inputmode="numeric" autocomplete="one-time-code" maxlength="7" class="input mono tracking-[.2em]" placeholder="000000"></x-ops.field>
                            <button class="btn-primary">Turn on</button>
                            <button type="button" wire:click="cancelTwoFactor" class="btn-ghost">Cancel</button>
                        </form>
                    </div>
                @else
                    <form wire:submit="startTwoFactor" class="flex flex-col gap-3">
                        <div class="text-sm"><x-ops.badge tone="warn">Off</x-ops.badge> <span class="text-ink-muted">Anyone with your password can sign in.</span></div>
                        <div class="flex flex-wrap items-end gap-2">
                            <x-ops.field label="Your password" :error="$errors->first('tfPassword')" class="min-w-[180px] flex-1"><input type="password" wire:model="tfPassword" class="input" autocomplete="current-password"></x-ops.field>
                            <button class="btn-primary">Set up</button>
                        </div>
                    </form>
                @endif
            </x-ops.card>

            <x-ops.card title="Recent sign-ins" subtitle="Last 25 sign-in events on your account" flush class="lg:col-span-2">
                <x-slot:actions>
                    <form wire:submit="logoutOthers" class="flex flex-wrap items-start gap-1.5">
                        <div class="flex flex-col"><input type="password" wire:model="logoutPassword" class="input py-1 text-sm" placeholder="Your password" autocomplete="current-password">@error('logoutPassword')<span class="text-xs text-bad">{{ $message }}</span>@enderror</div>
                        <button class="btn-danger btn-sm">Sign out other devices</button>
                    </form>
                </x-slot:actions>
                @php($events = ['login' => ['Signed in', 'ok'], 'logout' => ['Signed out', 'mute'], 'failed' => ['Wrong password', 'bad'], 'two_factor_failed' => ['Wrong 2FA code', 'bad']])
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <tr><th class="th">When</th><th class="th">Event</th><th class="th">Device</th><th class="th">IP</th></tr>
                        @forelse ($this->logins as $l)
                            <tr class="border-b border-soft last:border-0">
                                <td class="whitespace-nowrap px-3 py-1.5" title="{{ $l->created_at->format('d M Y H:i:s') }}">{{ $l->created_at->format('d M H:i') }} <span class="text-ink-muted">· {{ $l->created_at->diffForHumans(short: true) }}</span></td>
                                <td class="px-3 py-1.5"><x-ops.badge :tone="$events[$l->event][1] ?? 'mute'">{{ $events[$l->event][0] ?? $l->event }}</x-ops.badge></td>
                                <td class="px-3 py-1.5" title="{{ $l->user_agent }}">{{ $l->device }}
                                    @if ($l->user_agent === mb_substr((string) request()->userAgent(), 0, 255))<x-ops.badge tone="info">This browser</x-ops.badge>@endif
                                    @if ($l->new_device)<x-ops.badge tone="warn">New device</x-ops.badge>@endif</td>
                                <td class="mono whitespace-nowrap px-3 py-1.5 text-ink-muted">{{ $l->ip }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-3 text-ink-muted">No sign-ins recorded yet. They show up from your next sign-in.</td></tr>
                        @endforelse
                    </table>
                </div>
            </x-ops.card>
        </div>

    @elseif ($tab === 'notifications')
        <x-ops.card title="My notifications" subtitle="Choose what reaches you. Events an admin turned off for everyone (Settings → Web push) stay off." flush>
            <form wire:submit="saveNotifications">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <tr><th class="th">Event</th><th class="th text-center">Bell</th><th class="th text-center">Phone push</th></tr>
                        @foreach (config('ops.notify_events') as $k => [$label])
                            @php($globalOff = ! (ops_setting("push.matrix.$k.app") ?? true))
                            <tr class="border-b border-soft">
                                <td class="px-3 py-2">{{ $label }} @if ($globalOff)<x-ops.badge>Off for everyone</x-ops.badge>@endif</td>
                                <td class="text-center"><input type="checkbox" wire:model="notify.{{ $k }}.app" @disabled($globalOff) class="h-4 w-4 accent-brand"></td>
                                <td class="text-center">@if ($k === 'newOrder')<input type="checkbox" wire:model="notify.{{ $k }}.push" class="h-4 w-4 accent-brand">@else<span class="text-ink-faint">—</span>@endif</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
                <div class="flex items-center justify-between gap-2 px-3.5 py-3"><span class="text-xs text-ink-muted">Security alerts (new device sign-ins) are always on.</span><button class="btn-primary">Save</button></div>
            </form>
        </x-ops.card>

    @else
        @php($rows = $this->activity)
        <x-ops.card title="My activity" subtitle="Everything you did in the panel" flush>
            @forelse ($rows as $l)
                <div class="flex items-center gap-2 border-t border-soft px-3 py-1.5 text-sm first:border-0"><span class="w-28 flex-none text-ink-muted">{{ $l->created_at->format('d M Y H:i') }}</span><span class="flex-1">{{ $l->message }}</span></div>
            @empty
                <div class="p-3 text-ink-muted">Nothing yet.</div>
            @endforelse
            @if ($rows->hasPages())<x-ops.pagination :paginator="$rows" />@endif
        </x-ops.card>
    @endif
</div>
