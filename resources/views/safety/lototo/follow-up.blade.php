<x-app-layout>
    <x-slot name="header">LOTOTO Follow-up</x-slot>

    @php
        $checks = [
            'knows_procedure' => 'Saya tahu bagaimana melakukan pekerjaan ini',
            'follows_lototo' => 'Saya melakukan standar LOTOTO',
            'uses_ppe' => 'Saya menggunakan APD dengan baik dan benar',
            'safe_tools' => 'Saya memiliki tools dengan kondisi aman untuk digunakan',
            'safe_hands' => 'Jari / tangan saya AMAN (tidak dalam LoF)',
        ];
    @endphp

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
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase text-neutral-500">Karyawan / No. LOTOTO</p>
                    <p class="mt-1 font-bold text-neutral-900">
                        {{ $activity->employee->full_name }} / {{ $activity->employee->lototo_number }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-neutral-500">Board / Gembok</p>
                    <p class="mt-1 font-bold text-neutral-900">
                        {{ strtoupper(str_replace('_', ' ', $activity->board)) }}
                        @if ($activity->spareLock)
                            / {{ $activity->spareLock->code }}
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-neutral-500">Lokasi / Mesin</p>
                    <p class="mt-1 font-bold text-neutral-900">{{ $activity->location }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-neutral-500">Aktivitas pekerjaan</p>
                    <p class="mt-1 text-neutral-800">{{ $activity->activity }}</p>
                </div>
            </div>

            <div class="border-t border-neutral-200 pt-4">
                <h2 class="font-bold text-neutral-900">Pemeriksaan kesiapan kerja</h2>
                <dl class="mt-2 divide-y divide-neutral-100">
                    @foreach ($checks as $field => $label)
                        <div class="flex items-center justify-between gap-3 py-2 text-sm">
                            <dt class="text-neutral-700">{{ $label }}</dt>
                            <dd @class([
                                'font-bold',
                                'text-emerald-700' => $activity->{$field},
                                'text-red-700' => ! $activity->{$field},
                            ])>
                                {{ $activity->{$field} ? 'YA' : 'TIDAK' }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="border-t border-neutral-200 pt-4">
                <h2 class="font-bold text-neutral-900">Risiko dan pengendalian</h2>
                <p class="mt-2 text-sm text-neutral-700">
                    <span class="font-semibold">Risiko:</span>
                    {{ implode(', ', (array) $activity->risks) }}
                    @if ($activity->other_risk)
                        , {{ $activity->other_risk }}
                    @endif
                </p>
                <p class="mt-1 text-sm text-neutral-700">
                    <span class="font-semibold">Pengendalian:</span> {{ $activity->risk_controls }}
                </p>
            </div>

            @if ($activity->supervisor_feedback)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <h2 class="text-sm font-bold text-amber-900">Feedback dari karyawan</h2>
                    <p class="mt-1 whitespace-pre-line text-sm text-amber-950">{{ $activity->supervisor_feedback }}</p>
                </div>
            @endif

            @if ($activity->supervisor_follow_up_status === 'pending')
                <form method="POST" action="{{ route('safety.lototo.follow-up.store', $activity) }}" class="border-t border-neutral-200 pt-4">
                    @csrf
                    <label for="supervisor_follow_up" class="font-bold text-neutral-900">Feedback / tindakan supervisor</label>
                    <p class="mt-1 text-sm text-neutral-600">Catat arahan atau tindak lanjut untuk aktivitas ini.</p>
                    <textarea
                        id="supervisor_follow_up"
                        name="supervisor_follow_up"
                        rows="4"
                        required
                        maxlength="5000"
                        class="mt-3 block w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                    >{{ old('supervisor_follow_up') }}</textarea>
                    <button type="submit" class="mt-3 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-bold text-white hover:bg-neutral-700">
                        Kirim feedback dan tutup follow-up
                    </button>
                </form>
            @elseif ($activity->supervisor_follow_up_status === 'completed')
                <div class="border-t border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-sm font-bold text-emerald-800">STATUS: CLOSED</p>
                    <h2 class="mt-2 text-sm font-bold text-neutral-900">Feedback supervisor</h2>
                    <p class="mt-1 whitespace-pre-line text-sm text-neutral-800">{{ $activity->supervisor_follow_up }}</p>
                    <p class="mt-2 text-xs text-neutral-600">
                        {{ $activity->supervisorFollowUpBy?->name ?? 'Supervisor' }}
                        @if ($activity->supervisor_follow_up_at)
                            · {{ \App\Models\LototoActivity::localTimeFromUtc($activity->supervisor_follow_up_at)->format('d M Y H:i') }}
                        @endif
                    </p>
                </div>
            @else
                <div class="border-t border-neutral-200 pt-4 text-sm text-neutral-600">
                    Aktivitas ini tidak memiliki tindak lanjut supervisor yang tertunda.
                </div>
            @endif
        </section>

        <a href="{{ route('safety.lototo.show', $activity->employee) }}" class="inline-flex text-sm font-semibold text-blue-700 hover:underline">
            Kembali ke riwayat LOTOTO karyawan
        </a>
    </main>
</x-app-layout>