<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

        <title>{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        {{-- Restore the collapsed-sidebar preference before first paint so the
             sidebar doesn't flash open (width is driven by html[data-sidebar] in app.css). --}}
        <script>
            try { if (localStorage.getItem('sidebar-collapsed') === '1') document.documentElement.dataset.sidebar = 'collapsed'; } catch (e) {}
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-neutral-100" x-data="{ sidebarOpen: false }">
        <div class="flex h-screen overflow-hidden">
            <!-- Desktop sidebar -->
            <div id="desktop-sidebar" class="hidden overflow-hidden lg:flex lg:shrink-0">
                @include('layouts.sidebar', ['mobile' => false])
            </div>

            <!-- Mobile sidebar (off-canvas) -->
            <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 flex lg:hidden" role="dialog" aria-modal="true">
                <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-neutral-900/70" @click="sidebarOpen = false"></div>

                <div x-show="sidebarOpen" x-transition:enter="transition ease-in-out duration-200 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-150 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="relative flex w-72 max-w-[85%] flex-1">
                    <div class="absolute right-0 top-0 -mr-12 pt-2">
                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full" @click="sidebarOpen = false">
                            <span class="sr-only">Close sidebar</span>
                            <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    @include('layouts.sidebar', ['mobile' => true])
                </div>
            </div>

            <!-- Main column -->
            <div class="flex flex-1 flex-col overflow-hidden">
                @include('layouts.topbar', ['header' => $header ?? null])

                <main class="flex-1 overflow-y-auto">
                    <div @class([
                        'mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8',
                        'lototo-activity-layout' => request()->routeIs('safety.lototo.show', 'safety.lototo.spare-locks.show'),
                    ])>
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        <x-confirm-dialog />
        <x-menu-search />

        {{-- Tooltip for icon-only (collapsed) sidebar items; fixed so the nav's scroll area can't clip it. --}}
        <div x-data="{ tip: null }"
             x-on:rail-tip.window="tip = $event.detail"
             x-show="tip"
             x-cloak
             role="tooltip"
             class="pointer-events-none fixed z-[70] -translate-y-1/2 whitespace-nowrap rounded-md bg-neutral-800 px-2.5 py-1.5 text-xs font-medium text-white shadow-lg ring-1 ring-white/10"
             :style="tip ? `top: ${tip.top}px; left: ${tip.left}px` : ''"
             x-text="tip ? tip.text : ''"></div>
    </body>
</html>
