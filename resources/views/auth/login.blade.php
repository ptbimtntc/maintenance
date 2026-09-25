<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div id="face-login-panel" class="hidden mb-6 rounded-md border border-brand-200 bg-brand-50 p-4"
         data-status-url="{{ route('face-login.status') }}" data-attempt-url="{{ route('face-login.attempt') }}">
        <p class="mb-2 text-sm font-medium text-neutral-800">This device is enrolled for Face Login</p>
        <video data-face-video autoplay muted playsinline class="hidden mb-2 w-full rounded-md bg-black"></video>
        <p data-face-status class="mb-2 text-xs text-neutral-600"></p>
        <button type="button" data-face-start class="w-full rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
            Login with Face
        </button>
        <p class="mt-2 text-center text-xs text-neutral-400">or use your password below</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Email or NIK" />
            <x-text-input id="email" class="block mt-1 w-full" type="text" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-neutral-300 text-brand-600 shadow-sm focus:ring-brand-500" name="remember">
                <span class="ms-2 text-sm text-neutral-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-neutral-600 hover:text-neutral-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    <div class="mt-6 border-t border-neutral-200 pt-6 text-center">
        <p class="text-sm text-neutral-500">
            <a href="{{ route('guest-login') }}" class="underline hover:text-neutral-700">Continue browsing as guest (read-only)</a>
        </p>
    </div>
</x-guest-layout>
