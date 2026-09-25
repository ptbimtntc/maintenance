<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-neutral-900 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-brand-50 via-neutral-50 to-accent-50 px-4 py-10">
            <div class="flex flex-col items-center">
                <a href="/" class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white shadow-md">
                    <x-application-logo class="h-10 w-10 text-brand-500" />
                </a>
                <p class="mt-4 text-sm font-semibold text-neutral-800">{{ config('app.name') }}</p>
                <p class="text-xs text-neutral-500">PT Bekaert Indonesia</p>
            </div>

            <div class="mt-6 w-full overflow-hidden rounded-xl border border-neutral-200 border-t-4 border-t-brand-500 bg-white px-6 py-6 shadow-lg sm:max-w-md sm:px-8 sm:py-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
