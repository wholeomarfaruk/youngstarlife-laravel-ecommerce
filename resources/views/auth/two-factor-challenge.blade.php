<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Two-factor sign-in · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/ops.css', 'resources/js/ops.js'])
</head>
<body class="grid min-h-screen place-items-center bg-zebra px-4">
<div x-data="{ recovery: @js((bool) old('recovery')) }" class="card w-full max-w-sm">
    <div class="flex flex-col gap-3 p-5">
        <div class="flex flex-col gap-1">
            <span class="text-lg font-semibold">Two-factor sign-in</span>
            <span class="text-sm text-ink-muted" x-show="! recovery">Open your authenticator app and enter the 6-digit code for {{ config('app.name') }} Ops.</span>
            <span class="text-sm text-ink-muted" x-cloak x-show="recovery">Enter one of the recovery codes you saved when you turned on two-factor. Each code works once.</span>
        </div>
        <form method="POST" action="{{ route('two-factor.challenge') }}" class="flex flex-col gap-3">
            @csrf
            <template x-if="! recovery">
                <x-ops.field label="Code" :error="$errors->first('code')"><input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus class="input mono text-center text-xl tracking-[.3em]" placeholder="000000"></x-ops.field>
            </template>
            <template x-if="recovery">
                <x-ops.field label="Recovery code" :error="$errors->first('recovery_code')"><input name="recovery_code" autocomplete="off" class="input mono" placeholder="abcde-12345"></x-ops.field>
            </template>
            <button class="btn-primary">Sign in</button>
        </form>
        <button type="button" @click="recovery = ! recovery" class="self-start text-sm text-ink-muted underline" x-text="recovery ? 'Use the authenticator code' : 'Lost your phone? Use a recovery code'"></button>
        <a href="{{ route('login') }}" class="text-sm text-ink-muted">← Back to sign in</a>
    </div>
</div>
@livewireScripts {{-- Alpine --}}
</body>
</html>
