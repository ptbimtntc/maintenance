{{-- Supervisor Feedback page of the follow-up email link (signed, no login): the activity as the technician filled
     it in (read only), then Supervisor Comment / Feedback. Laid out like the LOTOTO form. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Supervisor Feedback LOTOTO</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-100 font-sans antialiased">
    <div class="min-h-screen px-4 py-5 sm:px-6 sm:py-8">
    <div class="mx-auto max-w-4xl space-y-5">
        <header class="overflow-hidden rounded-2xl bg-neutral-900 text-white shadow-lg">
            <div class="border-l-4 border-yellow-400 px-5 py-6 sm:px-8 sm:py-8">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-yellow-300">LOTOTO &amp; LMRA Feedback</p>
                <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">Supervisor Feedback</h1>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-neutral-300">
                    {{ $supervisor->full_name }}, tinjau pemeriksaan LOTOTO teknisi Anda di bawah ini lalu kirim komentar atau arahan Anda.
                </p>
            </div>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-bold">Feedback belum dapat dikirim.</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="space-y-4 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
            @include('safety.lototo._follow-up-details', ['activity' => $activity])
            @include('safety.lototo._follow-up-form', ['activity' => $activity, 'formAction' => $formAction])
        </section>
    </div>
    </div>
</body>
</html>
