{{-- Feedback of one activity on the LOTOTO Activity page: the technician's feedback, then the supervisor's replies
     (each with name and time, history kept), or that the supervisor's reply is still awaited. --}}
@php
    $replies = $activity->supervisorReplies();
    $awaiting = $activity->supervisor_follow_up_status === 'pending' && $replies->isEmpty();
@endphp
<div class="space-y-1.5" data-activity-feedback>
    <p class="break-words"><span class="font-semibold">Feedback teknisi:</span> {{ filled($activity->supervisor_feedback) ? $activity->supervisor_feedback : '-' }}</p>
    @if ($replies->isNotEmpty())
        <div class="border-t border-neutral-200 pt-1.5" data-supervisor-reply>
            <p class="font-semibold">Feedback supervisor:</p>
            @foreach ($replies as $reply)
                <p class="mt-1 whitespace-pre-line break-words">{{ $reply['comment'] }}</p>
                <p class="text-[11px] text-neutral-500">{{ $reply['by'] }}@if ($reply['at']) · {{ $reply['at']->format('d M Y H:i') }}@endif</p>
            @endforeach
        </div>
    @elseif ($awaiting)
        <p class="border-t border-neutral-200 pt-1.5 font-semibold text-amber-800" data-supervisor-awaiting>Menunggu feedback supervisor</p>
    @endif
</div>
