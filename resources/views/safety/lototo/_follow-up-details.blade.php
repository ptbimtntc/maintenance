{{-- A LOTOTO activity as the technician filled it in, read only, then every supervisor reply (history), oldest
     first. Shared by the follow-up page (signed in) and the Supervisor Feedback page of the email link. --}}
@php
    $replies = $activity->supervisorReplies();
@endphp
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <p class="text-xs font-semibold uppercase text-neutral-500">Nama teknisi</p>
        <p class="mt-1 font-bold text-neutral-900">{{ $activity->employee->full_name }}</p>
    </div>
    <div>
        <p class="text-xs font-semibold uppercase text-neutral-500">Nomor LOTOTO</p>
        <p class="mt-1"><span class="inline-block rounded-md bg-red-600 px-2.5 py-0.5 font-black text-white">{{ $activity->employee->lototo_number }}</span></p>
    </div>
    <div>
        <p class="text-xs font-semibold uppercase text-neutral-500">Lokasi / Mesin</p>
        <p class="mt-1 font-bold text-neutral-900">{{ $activity->location }}</p>
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
    <div class="sm:col-span-2">
        <p class="text-xs font-semibold uppercase text-neutral-500">Aktivitas pekerjaan</p>
        <p class="mt-1 break-words text-neutral-800">{{ $activity->activity }}</p>
    </div>
</div>

<div class="border-t border-neutral-200 pt-4">
    <h2 class="font-bold text-neutral-900">Jawaban checklist teknisi</h2>
    <ol class="mt-2 divide-y divide-neutral-100 rounded-xl border border-neutral-200">
        @foreach (\App\Models\LototoActivity::CHECKLIST as $field => $label)
            <li @class(['flex items-center justify-between gap-3 px-3 py-2.5 text-sm', 'bg-red-50' => ! $activity->{$field}])>
                <span @class(['min-w-0', 'font-semibold text-red-800' => ! $activity->{$field}, 'text-neutral-700' => $activity->{$field}])>{{ $loop->iteration }}. {{ $label }}</span>
                <span @class([
                    'shrink-0 rounded px-2 py-0.5 text-xs font-black',
                    'text-emerald-700' => $activity->{$field},
                    'bg-red-600 text-white' => ! $activity->{$field},
                ])>{{ $activity->{$field} ? 'YA' : 'TIDAK' }}</span>
            </li>
        @endforeach
    </ol>
</div>

<div class="border-t border-neutral-200 pt-4">
    <h2 class="font-bold text-neutral-900">Risiko dan pengendalian</h2>
    <p class="mt-2 break-words text-sm text-neutral-700">
        <span class="font-semibold">Risiko:</span>
        {{ implode(', ', (array) $activity->risks) }}@if ($activity->other_risk), {{ $activity->other_risk }}@endif
    </p>
    <p class="mt-1 break-words text-sm text-neutral-700"><span class="font-semibold">Pengendalian:</span> {{ $activity->risk_controls }}</p>
</div>

<div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
    <h2 class="text-sm font-bold text-amber-900">Feedback dari teknisi</h2>
    <p class="mt-1 whitespace-pre-line break-words text-sm text-amber-950">{{ filled($activity->supervisor_feedback) ? $activity->supervisor_feedback : '-' }}</p>
</div>

<div class="rounded-xl border border-neutral-200 p-4" data-supervisor-replies>
    <h2 class="text-sm font-bold text-neutral-900">Feedback / Comment dari Supervisor</h2>
    @forelse ($replies as $reply)
        <div class="mt-3 border-t border-neutral-100 pt-3 first:mt-2 first:border-t-0 first:pt-0">
            <p class="whitespace-pre-line break-words text-sm text-neutral-800">{{ $reply['comment'] }}</p>
            <p class="mt-1 text-xs text-neutral-500">{{ $reply['by'] }}@if ($reply['at']) · {{ $reply['at']->format('d M Y H:i') }}@endif</p>
        </div>
    @empty
        <p class="mt-1 text-sm text-neutral-600">{{ $activity->supervisor_follow_up_status === 'pending' ? 'Menunggu feedback supervisor.' : 'Belum ada feedback supervisor.' }}</p>
    @endforelse
</div>
