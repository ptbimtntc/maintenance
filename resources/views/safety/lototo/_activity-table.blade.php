@php
    $showTechnician = $showTechnician ?? false;
    $currentSpareActivityId = $currentSpareActivityId ?? null;
    // One activity's values, shared by the desktop table and the phone / tablet cards so both always agree.
    $activityRow = function ($activity) use ($showTechnician, $currentSpareActivityId) {
        $isCurrentSpareActivity = $showTechnician && $activity->id === $currentSpareActivityId;
        $spareStartedAt = data_get($activity->out_details, 'spare_started_at');
        $spareEndedAt = data_get($activity->out_details, 'spare_ended_at');
        $startedAt = $showTechnician && $spareStartedAt
            ? \App\Models\LototoActivity::localTime(\Illuminate\Support\Carbon::parse($spareStartedAt))
            : \App\Models\LototoActivity::localTimeFromUtc($activity->started_at);
        $endedAt = $showTechnician && $spareEndedAt
            ? \App\Models\LototoActivity::localTime(\Illuminate\Support\Carbon::parse($spareEndedAt))
            : ($isCurrentSpareActivity
                ? null
                : \App\Models\LototoActivity::localTimeFromUtc($activity->ended_at));
        $durationMinutes = $startedAt && $endedAt
            ? $startedAt->diffInMinutes($endedAt)
            : null;
        $answers = [
            $activity->knows_procedure,
            $activity->follows_lototo,
            $activity->uses_ppe,
            $activity->safe_tools,
            $activity->safe_hands,
        ];

        return compact('isCurrentSpareActivity', 'startedAt', 'endedAt', 'durationMinutes', 'answers');
    };
    $checkLabels = [
        'Saya tahu bagaimana melakukan pekerjaan ini',
        'Saya melakukan standar LOTOTO',
        'Saya menggunakan APD dengan baik dan benar',
        'Tools saya aman untuk digunakan',
        'Jari / tangan saya aman (tidak dalam LoF)',
    ];
@endphp
{{-- Desktop (lg and up): the full table, as before. --}}
<div class="hidden overflow-x-auto lg:block">
    <table class="w-full min-w-[1180px] table-fixed border-collapse text-center text-sm text-neutral-800">
        <thead>
            <tr class="text-xs font-bold text-white">
                @if ($showTechnician)
                    <th class="w-[12%] border border-neutral-900 px-2 py-3" style="background-color: #2874b8;">TEKNISI</th>
                @endif
                <th class="w-[9%] border border-neutral-900 px-2 py-3" style="background-color: #2874b8;">MESIN / LOKASI</th>
                <th class="w-[11%] border border-neutral-900 px-2 py-3" style="background-color: #2874b8;">START</th>
                <th class="w-[11%] border border-neutral-900 px-2 py-3" style="background-color: #2874b8;">END</th>
                <th class="w-[6%] border border-neutral-900 px-2 py-3" style="background-color: #2874b8;">HOURS</th>
                <th class="w-[18%] border border-neutral-900 px-2 py-3" style="background-color: #2874b8;">AKTIVITAS PEKERJAAN</th>
                <th class="w-[8%] border border-neutral-900 px-2 py-3" style="background-color: #ff592e;">SAYA TAHU BAGAIMANA MELAKUKAN PEKERJAAN INI</th>
                <th class="w-[8%] border border-neutral-900 px-2 py-3" style="background-color: #ff592e;">SAYA MELAKUKAN STANDAR LOTOTO</th>
                <th class="w-[8%] border border-neutral-900 px-2 py-3" style="background-color: #ff592e;">SAYA MENGGUNAKAN APD DENGAN BAIK DAN BENAR</th>
                <th class="w-[8%] border border-neutral-900 px-2 py-3" style="background-color: #ff592e;">TOOLS SAYA AMAN UNTUK DIGUNAKAN</th>
                <th class="w-[8%] border border-neutral-900 px-2 py-3" style="background-color: #ff592e;">JARI / TANGAN SAYA AMAN (TIDAK DALAM LoF)</th>
                <th class="w-[5%] border border-neutral-900 px-2 py-3" style="background-color: #ff592e;">FEEDBACK</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($activities as $activity)
                @php
                    extract($activityRow($activity));
                @endphp
                <tr class="border-b border-neutral-200 bg-white align-middle">
                    @if ($showTechnician)
                        <td class="border border-neutral-200 px-2 py-3">
                            <p class="font-bold">{{ $activity->employee->preferred_name ?: $activity->employee->full_name }}</p>
                            <p class="mt-1 text-xs text-neutral-500">LOTOTO {{ $activity->employee->lototo_number }}</p>
                            <p class="text-xs text-neutral-500">{{ $activity->employee->employee_number }}</p>
                        </td>
                    @endif
                    <td class="border border-neutral-200 px-2 py-3">
                        <p class="font-bold">{{ $activity->location }}</p>
                        <span @class([
                            'mt-2 inline-block rounded px-2 py-1 text-xs font-semibold uppercase',
                            'bg-emerald-100 text-emerald-800' => $isCurrentSpareActivity || (! $showTechnician && $activity->status === 'in_progress'),
                            'bg-neutral-100 text-neutral-700' => ! $isCurrentSpareActivity && ($activity->status === 'completed' || $activity->outcome === 'outstanding'),
                        ])>
                            @if ($isCurrentSpareActivity)
                                IN
                            @elseif (! $showTechnician && $activity->status === 'in_progress')
                                IN
                            @elseif ($activity->status === 'completed' || $activity->outcome === 'outstanding')
                                CLOSE
                            @else
                                {{ strtoupper(str_replace('_', ' ', $activity->status)) }}
                            @endif
                        </span>
                    </td>
                    <td class="border border-neutral-200 px-2 py-3 font-semibold">
                        {{ $startedAt?->format('d M Y - H:i') ?? '-' }}
                    </td>
                    <td class="border border-neutral-200 px-2 py-3 font-semibold">
                        {{ $endedAt?->format('d M Y - H:i') ?? '-' }}
                    </td>
                    <td class="border border-neutral-200 px-2 py-3">
                        {{ $durationMinutes !== null ? number_format($durationMinutes / 60, 2).' h' : '-' }}
                    </td>
                    <td class="border border-neutral-200 px-2 py-2 text-left">
                        <p class="font-medium">{{ $activity->activity }}</p>
                        <p class="mt-1 border-t border-neutral-200 pt-1 text-xs text-neutral-600">
                            Risiko dalam pekerjaan ini: {{ implode(', ', (array) $activity->risks) }}
                            @if ($activity->other_risk)
                                , {{ $activity->other_risk }}
                            @endif
                        </p>
                        <p class="mt-1 border-t border-neutral-200 pt-1 text-xs text-neutral-600">
                            Cara saya menghindarinya: {{ $activity->risk_controls }}
                        </p>
                        @if ($activity->spareLock)
                            <p class="mt-1 text-xs font-semibold text-blue-700">Gembok: {{ $activity->spareLock->code }}</p>
                        @endif
                    </td>
                    @foreach ($answers as $answer)
                        <td class="border border-neutral-200 px-2 py-3">
                            @if ($answer)
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border-2 border-emerald-500 text-emerald-600" aria-label="Ya"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6" /></svg></span>
                            @else
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border-2 border-red-500 text-red-600" aria-label="Tidak"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg></span>
                            @endif
                        </td>
                    @endforeach
                    <td class="border border-neutral-200 px-2 py-3 text-left text-xs">
                        @include('safety.lototo._activity-feedback', ['activity' => $activity, 'compact' => true])
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showTechnician ? 12 : 11 }}" class="border border-neutral-200 px-4 py-12 text-center text-sm text-neutral-500">
                        {{ $emptyMessage }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Phone and tablet (below lg): one card per activity, nothing wider than the screen. --}}
<div class="lg:hidden">
    @forelse ($activities as $activity)
        @php
            extract($activityRow($activity));
            $isIn = $isCurrentSpareActivity || (! $showTechnician && $activity->status === 'in_progress');
            $isClosed = ! $isCurrentSpareActivity && ($activity->status === 'completed' || $activity->outcome === 'outstanding');
        @endphp
        <article class="lototo-activity-card border-b border-neutral-200 bg-white px-4 py-4 last:border-b-0" aria-label="Aktivitas LOTOTO {{ $activity->location }}">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    @if ($showTechnician)
                        <p class="text-xs font-semibold uppercase text-neutral-500">
                            {{ $activity->employee->preferred_name ?: $activity->employee->full_name }} · LOTOTO {{ $activity->employee->lototo_number }}
                        </p>
                    @endif
                    <h3 class="break-words text-base font-bold leading-snug text-neutral-900">{{ $activity->location }}</h3>
                </div>
                <span @class([
                    'shrink-0 rounded px-2 py-1 text-xs font-bold uppercase',
                    'bg-emerald-100 text-emerald-800' => $isIn,
                    'bg-neutral-100 text-neutral-700' => ! $isIn,
                ])>
                    {{ $isIn ? 'IN' : ($isClosed ? 'CLOSE' : strtoupper(str_replace('_', ' ', $activity->status))) }}
                </span>
            </div>

            <dl class="mt-3 grid grid-cols-3 gap-2 rounded-lg bg-neutral-50 p-3 text-xs">
                <div class="min-w-0">
                    <dt class="font-semibold uppercase text-neutral-500">Start</dt>
                    <dd class="mt-0.5 font-bold text-neutral-900">{{ $startedAt?->format('d M Y') ?? '-' }}<span class="block">{{ $startedAt?->format('H:i') }}</span></dd>
                </div>
                <div class="min-w-0">
                    <dt class="font-semibold uppercase text-neutral-500">End</dt>
                    <dd class="mt-0.5 font-bold text-neutral-900">{{ $endedAt?->format('d M Y') ?? '-' }}<span class="block">{{ $endedAt?->format('H:i') }}</span></dd>
                </div>
                <div class="min-w-0">
                    <dt class="font-semibold uppercase text-neutral-500">Hours</dt>
                    <dd class="mt-0.5 font-bold text-neutral-900">{{ $durationMinutes !== null ? number_format($durationMinutes / 60, 2).' h' : '-' }}</dd>
                </div>
            </dl>

            <div class="mt-3 text-sm text-neutral-800">
                <p class="text-xs font-semibold uppercase text-neutral-500">Aktivitas pekerjaan</p>
                <p class="mt-0.5 break-words font-medium">{{ $activity->activity }}</p>
                <p class="mt-2 break-words text-xs text-neutral-600">
                    <span class="font-semibold">Risiko:</span> {{ implode(', ', (array) $activity->risks) }}@if ($activity->other_risk), {{ $activity->other_risk }}@endif
                </p>
                <p class="mt-1 break-words text-xs text-neutral-600"><span class="font-semibold">Cara menghindarinya:</span> {{ $activity->risk_controls }}</p>
                @if ($activity->spareLock)
                    <p class="mt-1 text-xs font-semibold text-blue-700">Gembok: {{ $activity->spareLock->code }}</p>
                @endif
            </div>

            <ul class="mt-3 divide-y divide-neutral-100 rounded-lg border border-neutral-200 text-xs">
                @foreach ($answers as $index => $answer)
                    <li class="flex items-center justify-between gap-3 px-3 py-2">
                        <span class="min-w-0 text-neutral-700">{{ $checkLabels[$index] }}</span>
                        @if ($answer)
                            <span class="shrink-0 font-black text-emerald-700" aria-label="Ya">YA</span>
                        @else
                            <span class="shrink-0 font-black text-red-600" aria-label="Tidak">TIDAK</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-neutral-800">
                @include('safety.lototo._activity-feedback', ['activity' => $activity, 'compact' => false])
            </div>
        </article>
    @empty
        <p class="px-4 py-12 text-center text-sm text-neutral-500">{{ $emptyMessage }}</p>
    @endforelse
</div>
