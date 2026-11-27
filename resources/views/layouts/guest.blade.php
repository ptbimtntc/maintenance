<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

        {{-- Opt in to page transitions inline: from the external stylesheet alone,
             Chrome often has not applied it yet when the new page is revealed
             and aborts the transition. --}}
        <style>@media (prefers-reduced-motion: no-preference) { @@view-transition { navigation: auto; } }</style>

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        $features = [
            ['title' => 'Skill Matrix & Competency Gap', 'text' => 'See who is ready, and where to develop next.', 'icon' => 'M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6'],
            ['title' => 'Training & Certificates', 'text' => 'Plan sessions, track hours, renew on time.', 'icon' => 'M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342'],
            ['title' => 'Safety & LOTOTO', 'text' => 'Lock-out, tag-out, try-out, done right.', 'icon' => 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z'],
        ];
    @endphp
    <body class="font-sans text-neutral-900 antialiased bg-neutral-50">
        <div class="relative min-h-screen lg:grid lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">

            {{-- Brand panel (desktop only). Decorative motion is CSS-only and
                 switched off under prefers-reduced-motion (resources/css/motion.css). --}}
            <aside class="relative hidden overflow-hidden bg-neutral-900 text-white lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
                <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                    <div class="motion-blob absolute -left-24 -top-24 h-96 w-96 rounded-full bg-brand-500/40 blur-3xl"></div>
                    <div class="motion-blob motion-blob--slow absolute -bottom-32 right-[-6rem] h-[28rem] w-[28rem] rounded-full bg-accent-500/30 blur-3xl"></div>
                    <div class="motion-blob absolute left-1/3 top-1/2 h-64 w-64 rounded-full bg-warning-400/20 blur-3xl" style="animation-delay: -9s"></div>
                    <div class="motion-grid-bg absolute inset-0 [mask-image:radial-gradient(ellipse_at_center,black_35%,transparent_75%)]"></div>
                </div>

                <div class="motion-enter relative flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-white shadow-lg shadow-black/20">
                        <x-application-logo class="h-8 w-8" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold">PT Bekaert Indonesia</p>
                        <p class="text-xs text-white/60">Maintenance Department</p>
                    </div>
                </div>

                <div class="relative max-w-xl">
                    <p class="motion-enter text-xs font-semibold uppercase tracking-[0.2em] text-brand-300" style="--d: 100ms">{{ config('app.name') }}</p>
                    <h1 class="mt-4 text-4xl font-extrabold leading-tight tracking-tight xl:text-5xl">
                        <span class="motion-enter block" style="--d: 180ms">Build Competent People.</span>
                        <span class="motion-enter block" style="--d: 300ms">Improve Performance.</span>
                        <span class="motion-enter motion-gradient-text block bg-gradient-to-r from-brand-400 via-warning-300 to-accent-400 bg-clip-text text-transparent" style="--d: 420ms">Ensure Reliability.</span>
                    </h1>

                    <ul class="mt-10 space-y-4">
                        @foreach ($features as $feature)
                            <li class="motion-enter flex items-start gap-4 rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm transition-colors hover:bg-white/10" style="--d: {{ 600 + $loop->index * 120 }}ms">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-500/20 text-brand-300 ring-1 ring-brand-400/30">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $feature['icon'] }}" />
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold">{{ $feature['title'] }}</p>
                                    <p class="mt-0.5 text-xs text-white/60">{{ $feature['text'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- "Equipment heartbeat" line: drawn once, then the dot keeps pulsing. --}}
                <div class="relative">
                    <svg viewBox="0 0 600 60" class="h-auto w-full text-brand-400" fill="none" aria-hidden="true">
                        <path class="motion-draw" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                              d="M0 30 H170 L185 30 L195 10 L210 52 L222 18 L232 30 H360 L372 30 L380 22 L390 38 L398 30 H600" />
                        <circle class="motion-pulse-dot" cx="596" cy="30" r="4" fill="currentColor" />
                    </svg>
                    <p class="mt-3 text-[11px] text-white/40">&copy; {{ now()->year }} PT Bekaert Indonesia &middot; Maintenance People Development</p>
                </div>
            </aside>

            {{-- Form column --}}
            <main class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-10 sm:px-6">
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-gradient-to-br from-brand-50 via-neutral-50 to-accent-50">
                    <div class="motion-blob absolute -right-20 -top-20 h-72 w-72 rounded-full bg-brand-200/50 blur-3xl"></div>
                    <div class="motion-blob motion-blob--slow absolute -bottom-24 -left-16 h-80 w-80 rounded-full bg-accent-200/50 blur-3xl"></div>
                </div>

                <div class="relative w-full sm:max-w-md">
                    {{-- Compact logo + name; the brand panel shows this on desktop. --}}
                    <div class="motion-enter mb-6 flex flex-col items-center lg:hidden">
                        <a href="/" class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white shadow-md">
                            <x-application-logo class="h-10 w-10 text-brand-500" />
                        </a>
                        <p class="mt-4 text-sm font-semibold text-neutral-800">{{ config('app.name') }}</p>
                        <p class="text-xs text-neutral-500">PT Bekaert Indonesia</p>
                    </div>

                    @if (request()->routeIs('login'))
                        <div class="motion-enter mb-6 hidden lg:block" style="--d: 150ms">
                            <h2 class="text-2xl font-bold tracking-tight text-neutral-900">Welcome back</h2>
                            <p class="mt-1 text-sm text-neutral-500">Sign in to continue to {{ config('app.name') }}.</p>
                        </div>
                    @endif

                    <div class="glass-auth motion-enter w-full overflow-hidden rounded-2xl border border-neutral-200/80 bg-white/90 px-6 py-6 shadow-xl shadow-neutral-900/5 backdrop-blur sm:px-8 sm:py-8" style="--d: 250ms">
                        <div aria-hidden="true" class="motion-gradient-text -mx-6 -mt-6 mb-6 h-1 bg-gradient-to-r from-brand-500 via-warning-400 to-accent-500 sm:-mx-8 sm:-mt-8"></div>
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
