<x-app-layout>
    <x-slot name="header">Face Check-in — {{ $trainingSession->session_title ?? $trainingSession->trainingProgram->title }}</x-slot>

    <div class="space-y-6">
        <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
            <p class="text-sm text-neutral-500">{{ $trainingSession->start_date->format('d M Y') }} &ndash; {{ $trainingSession->end_date->format('d M Y') }} &middot; {{ $trainingSession->location?->name ?? 'No location set' }}</p>
            <p class="mt-2 text-sm text-neutral-600">
                Leave this open on a device at the training venue. Each participant looks at the camera in turn -
                a match marks them as attended, using the Face Login they enrolled on their own Profile page. Anyone
                not enrolled, or not recognized, should be marked present manually from the session page instead.
            </p>
        </div>

        <div id="training-checkin-panel" class="mx-auto max-w-md rounded-lg border border-neutral-200 bg-white p-6 text-center shadow-md"
             data-attempt-url="{{ route('training.sessions.check-in.attempt', $trainingSession) }}">
            <video data-face-video autoplay muted playsinline class="hidden mb-4 w-full rounded-md bg-black"></video>

            <p data-face-status class="mb-4 min-h-[3rem] text-sm font-medium text-neutral-700"></p>

            <button type="button" data-face-start class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
                Start Check-in
            </button>
        </div>

        <div class="text-center">
            <a href="{{ route('training.sessions.show', $trainingSession) }}" class="text-sm text-neutral-600 hover:underline">&larr; Back to session</a>
        </div>
    </div>
</x-app-layout>
