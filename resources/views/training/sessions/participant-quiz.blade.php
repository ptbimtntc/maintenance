<x-app-layout>
    <x-slot name="header">Quiz — {{ $participant->employee->full_name }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-4">
        <a href="{{ route('training.sessions.show', $trainingSession) }}" class="text-sm text-neutral-600 hover:underline">&larr; Back to session</a>

        <div class="rounded-lg border border-neutral-200 border-t-2 border-t-brand-500 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Competency quiz</p>
            <h2 class="text-lg font-semibold text-neutral-900">{{ $program->title }}</h2>
            <p class="text-sm text-neutral-500">{{ $participant->employee->full_name }} &middot; {{ $participant->employee->employee_number }}
                &middot; passing score {{ $program->passing_score ?? '—' }}</p>
        </div>

        @if ($participant->quiz_submitted_at)
            @php $passed = $participant->certificate_id !== null; @endphp
            <div @class(['rounded-lg border p-6 text-center', 'border-green-200 bg-green-50' => $passed, 'border-red-200 bg-red-50' => ! $passed])>
                <p class="text-sm text-neutral-600">Score</p>
                <p class="text-4xl font-bold {{ $passed ? 'text-green-700' : 'text-red-700' }}">{{ $participant->quiz_score }} <span class="text-lg font-medium">/ 100</span></p>
                <p class="mt-1 text-sm font-semibold {{ $passed ? 'text-green-800' : 'text-red-800' }}">{{ $passed ? 'PASSED' : 'NOT PASSED' }}</p>
                <p class="mt-2 text-xs text-neutral-500">Submitted {{ $participant->quiz_submitted_at->format('d M Y H:i') }}</p>
                @if ($passed)
                    <p class="mt-2 text-sm text-neutral-600">Certificate No. <strong>{{ $participant->certificate->certificate_number }}</strong></p>
                    <a href="{{ route('certificates.show', $participant->certificate) }}" target="_blank" class="mt-3 inline-block rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">View Certificate</a>
                @else
                    <p class="mt-2 text-sm text-neutral-600">No certificate was issued.</p>
                @endif
            </div>

            <div class="space-y-4">
                @foreach ($program->questions as $question)
                    <div class="rounded-lg border border-neutral-200 bg-white p-4">
                        <p class="mb-3 text-sm font-medium text-neutral-900">{{ $loop->iteration }}. {!! nl2br(e($question->question_text)) !!}
                            @if ($question->allow_multiple_answers)
                                <span class="ml-1 rounded-full bg-accent-100 px-2 py-0.5 text-xs font-medium text-accent-800">choose more than one</span>
                            @endif
                        </p>
                        @foreach ($question->choices as $choice)
                            @php
                                $wasPicked = $selectedChoiceIds->contains($choice->id);
                                $style = match (true) {
                                    $choice->is_correct && $wasPicked => 'border-green-300 bg-green-50 text-green-800',
                                    $choice->is_correct && ! $wasPicked => 'border-green-200 bg-white text-green-700',
                                    ! $choice->is_correct && $wasPicked => 'border-red-300 bg-red-50 text-red-800',
                                    default => 'border-neutral-200 bg-white text-neutral-600',
                                };
                            @endphp
                            <div class="mb-1.5 flex items-center gap-2 rounded-md border px-2 py-1.5 text-sm {{ $style }}">
                                <span class="w-4 shrink-0 text-center">
                                    @if ($wasPicked) ✓ @endif
                                </span>
                                <span><strong>{{ $choice->option_label }}.</strong> {{ $choice->choice_text }}</span>
                                @if ($choice->is_correct)
                                    <span class="ml-auto shrink-0 text-xs font-medium text-green-700">correct</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @else
            @php [$eligible, $reason] = $participant->quizEligibility(); @endphp
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                This participant has not submitted the quiz yet.
                @if (! $eligible)
                    <br>{{ \App\Models\TrainingParticipant::quizMessage($reason, $trainingSession) }}
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
