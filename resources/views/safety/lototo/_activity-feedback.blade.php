{{-- Feedback of one activity on the LOTOTO Activity page: the technician's feedback, then the supervisor's replies
     (each with name and time, history kept), or that the supervisor's reply is still awaited. --}}
@php
    $replies = $activity->supervisorReplies();
    $awaiting = $activity->supervisorFeedbackState() === 'awaiting';
    // The button is only for the supervisor of this technician, while the feedback is awaited (the page it opens and its
    // POST check the same relation in the backend).
    $canGiveFeedback = $activity->canGiveSupervisorFeedback(auth()->user());
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
        @if ($canGiveFeedback)
            <a href="{{ route('safety.lototo.follow-up', $activity) }}" data-give-supervisor-feedback
               title="Beri feedback supervisor untuk aktivitas ini (satu feedback untuk seluruh aktivitas)"
               class="mt-1 inline-flex min-h-9 items-center gap-1.5 rounded-md bg-neutral-900 px-2.5 text-[11px] font-bold text-white hover:bg-neutral-700">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12c0 4.4-4 8-9 8a9.9 9.9 0 0 1-3.6-.7L3 21l1.5-4.2A7.6 7.6 0 0 1 3 12c0-4.4 4-8 9-8s9 3.6 9 8Z"/></svg>
                Beri Feedback
            </a>
        @endif
    @endif
</div>
