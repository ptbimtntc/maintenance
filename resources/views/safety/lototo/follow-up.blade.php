<x-app-layout>
    <x-slot name="header">LOTOTO Follow-up</x-slot>


    <main class="mx-auto max-w-4xl space-y-5 px-4 py-6 sm:px-6">
        <header class="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-amber-800">LOTOTO Safety Follow-up</p>
            <h1 class="mt-1 text-xl font-black text-neutral-900">Tindak lanjut pemeriksaan keselamatan</h1>
            <p class="mt-2 text-sm text-neutral-700">
                Tinjau informasi pekerjaan dan berikan arahan atau tindakan supervisor.
            </p>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="space-y-4 rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
            @include('safety.lototo._follow-up-details', ['activity' => $activity])
            @include('safety.lototo._follow-up-form', ['activity' => $activity, 'formAction' => route('safety.lototo.follow-up.store', $activity)])
        </section>

        <a href="{{ route('safety.lototo.show', $activity->employee) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-blue-700 hover:underline lg:min-h-0">
            Kembali ke riwayat LOTOTO karyawan
        </a>
    </main>
</x-app-layout>