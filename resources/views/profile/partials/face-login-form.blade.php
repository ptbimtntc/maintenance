@php $enrolled = auth()->user()->hasFaceLoginEnabled(); @endphp

<section>
    <header>
        <h2 class="text-lg font-medium text-neutral-900">Face Login</h2>
        <p class="mt-1 text-sm text-neutral-600">
            A quick sign-in shortcut for this device only - it never replaces your password, which is still required
            for password changes and other sensitive actions. Your face photo/video is never stored, only a numeric
            comparison value computed in your browser.
        </p>
    </header>

    @if ($enrolled)
        <div class="mt-4 rounded-md border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800">
            Face Login is enabled, enrolled {{ auth()->user()->face_enrolled_at->format('d M Y') }}.
            Disabling it removes access from <strong>every</strong> device this was enrolled on, not just this one.
        </div>

        <form method="POST" action="{{ route('face-login.disable') }}" class="mt-4"
              onsubmit="return confirm('Disable Face Login on all devices?');">
            @csrf
            <button type="submit" class="rounded-md border border-danger-300 px-4 py-2 text-sm font-medium text-danger-700 hover:bg-danger-50">
                Disable Face Login
            </button>
        </form>
    @else
        <div id="face-login-enroll-panel" class="mt-4">
            <video data-face-video autoplay muted playsinline class="hidden mb-2 w-full max-w-sm rounded-md bg-black"></video>
            <p data-face-status class="mb-2 text-sm text-neutral-600"></p>

            <button type="button" data-face-start class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
                Start Camera
            </button>

            <div class="mt-3 space-y-3" data-face-enroll-url="{{ route('face-login.enroll') }}">
                <label class="flex items-start gap-2 text-sm text-neutral-700">
                    <input type="checkbox" data-face-consent class="mt-0.5 rounded border-neutral-300">
                    <span>
                        I consent to a numeric representation of my face being stored to enable Face Login on this
                        device, in accordance with the company's personal data policy (UU PDP). This can be revoked
                        at any time from this page.
                    </span>
                </label>

                <button type="button" data-face-save disabled
                        class="hidden rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Capture Face
                </button>
            </div>
        </div>
    @endif
</section>
