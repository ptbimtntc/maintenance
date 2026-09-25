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
            <div @class(['rounded-lg border p-6 text-center', 'border-success-200 bg-success-50' => $passed, 'border-danger-200 bg-danger-50' => ! $passed])>
                <p class="text-sm text-neutral-600">Score</p>
                <p class="text-4xl font-bold {{ $passed ? 'text-success-700' : 'text-danger-700' }}">{{ $participant->quiz_score }} <span class="text-lg font-medium">/ 100</span></p>
                <p class="mt-1 text-sm font-semibold {{ $passed ? 'text-success-800' : 'text-danger-800' }}">{{ $passed ? 'PASSED' : 'NOT PASSED' }}</p>
                <p class="mt-2 text-xs text-neutral-500">Submitted {{ $participant->quiz_submitted_at->format('d M Y H:i') }}</p>
                @if ($passed)
                    <p class="mt-2 text-sm text-neutral-600">Certificate No. <strong>{{ $participant->certificate->certificate_number }}</strong></p>
                    <a href="{{ route('certificates.show', $participant->certificate) }}" target="_blank" class="mt-3 inline-block rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">View Certificate</a>
                @else
                    <p class="mt-2 text-sm text-neutral-600">No certificate was issued.</p>
                @endif

                @can(\App\Enums\PermissionName::ManageTraining->value)
                    <form method="POST" action="{{ route('training.sessions.participants.quiz-reset', [$trainingSession, $participant]) }}" class="mt-4"
                          onsubmit="return confirm('Reset this quiz? The recorded answers{{ $passed ? ' and the issued certificate' : '' }} will be deleted, and the participant can take the quiz again. This cannot be undone.');">
                        @csrf
                        <button type="submit" class="rounded-md border border-danger-300 px-4 py-2 text-sm font-medium text-danger-700 hover:bg-danger-50">Reset Quiz</button>
                    </form>
                @endcan
            </div>

            <div class="space-y-4">
                @foreach ($program->questions as $question)
                    <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md">
                        <p class="mb-3 text-sm font-medium text-neutral-900">{{ $loop->iteration }}. {!! nl2br(e($question->question_text)) !!}
                            @if ($question->allow_multiple_answers)
                                <span class="ml-1 rounded-full bg-accent-100 px-2 py-0.5 text-xs font-medium text-accent-800">choose more than one</span>
                            @endif
                        </p>
                        @foreach ($question->choices as $choice)
                            @php
                                $wasPicked = $selectedChoiceIds->contains($choice->id);
                                $style = match (true) {
                                    $choice->is_correct && $wasPicked => 'border-success-300 bg-success-50 text-success-800',
                                    $choice->is_correct && ! $wasPicked => 'border-success-200 bg-white text-success-700',
                                    ! $choice->is_correct && $wasPicked => 'border-danger-300 bg-danger-50 text-danger-800',
                                    default => 'border-neutral-200 bg-white text-neutral-600',
                                };
                            @endphp
                            <div class="mb-1.5 flex items-center gap-2 rounded-md border px-2 py-1.5 text-sm {{ $style }}">
                                <span class="w-4 shrink-0 text-center">
                                    @if ($wasPicked) ✓ @endif
                                </span>
                                <span><strong>{{ $choice->option_label }}.</strong> {{ $choice->choice_text }}</span>
                                @if ($choice->is_correct)
                                    <span class="ml-auto shrink-0 text-xs font-medium text-success-700">correct</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @else
            @php [$eligible, $reason] = $participant->quizEligibility(); @endphp
            <div class="rounded-md border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">
                This participant has not submitted the quiz yet.
                @if (! $eligible)
                    <br>{{ \App\Models\TrainingParticipant::quizMessage($reason, $trainingSession) }}
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
