<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/ops.css', 'resources/js/ops.js'])
    @livewireStyles
</head>
<body class="min-h-screen">
<div class="min-h-screen lg:grid lg:grid-cols-[216px_minmax(0,1fr)]" x-data>
    {{-- mobile drawer overlay --}}
    <div x-cloak x-show="$store.ui.nav" @click="$store.ui.nav=false" class="fixed inset-0 z-50 bg-ink/45 lg:hidden"></div>
    @include('partials.ops.sidebar')

    <main class="min-w-0 flex flex-col">
        @include('partials.ops.topbar')
        <div class="flex flex-col gap-3 min-w-0 px-2.5 pt-3 pb-24 sm:px-3.5 lg:px-5 lg:pt-4 lg:pb-10">
            @isset($tabs) <x-ops.module-tabs :group="$tabs" /> @endisset
            {{ $slot }}
        </div>
    </main>
</div>

@include('partials.ops.bottom-nav')
@include('partials.ops.toasts')
@livewireScripts
</body>
</html>
