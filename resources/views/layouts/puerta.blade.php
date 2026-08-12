<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ \App\Models\Setting::value('business.name') ?: config('app.name') }} · {{ __('admin.puerta.validar.title') }}</title>

    {{-- Reusa el theme custom del panel (decisión #124): mismo bundle Tailwind 4
         con `@source` que escanea `resources/views/livewire/admin/**/*`. Sin esto
         las clases utility de la vista no compilarían. --}}
    @vite(['resources/css/filament/admin/theme.css'])
    @livewireStyles
</head>
<body class="fi-body min-h-screen bg-gray-50 antialiased dark:bg-gray-950">
    <main class="mx-auto flex min-h-screen max-w-2xl items-center justify-center p-4 sm:p-8">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
