<x-app-layout>
    <x-slot name="header">Quiz — {{ $program->title }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-4">
        <a href="{{ route('employees.show', ['employee' => $participant->employee, 'tab' => 'training']) }}" class="text-sm text-neutral-600 hover:underline">&larr; Back to my profile</a>

        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg border border-neutral-200 border-t-2 border-t-brand-500 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Competency quiz</p>
            <h2 class="text-lg font-semibold text-neutral-900">{{ $program->title }}</h2>
            <p class="text-sm text-neutral-500">{{ $participant->employee->full_name }} &middot; {{ $participant->employee->employee_number }}
                &middot; passing score {{ $program->passing_score ?? '—' }}</p>
        </div>

        @if ($participant->quiz_submitted_at)
            @php $passed = $participant->certificate_id !== null; @endphp
            <div @class(['rounded-lg border p-6 text-center', 'border-success-200 bg-success-50' => $passed, 'border-danger-200 bg-danger-50' => ! $passed])>
                <p class="text-sm text-neutral-600">Your score</p>
                <p class="text-4xl font-bold {{ $passed ? 'text-success-700' : 'text-danger-700' }}">{{ $participant->quiz_score }} <span class="text-lg font-medium">/ 100</span></p>
                <p class="mt-1 text-sm font-semibold {{ $passed ? 'text-success-800' : 'text-danger-800' }}">{{ $passed ? 'PASSED' : 'NOT PASSED' }}</p>
                @if ($passed)
                    <p class="mt-2 text-sm text-neutral-600">Certificate No. <strong>{{ $participant->certificate->certificate_number }}</strong></p>
                    <a href="{{ route('certificates.show', $participant->certificate) }}" target="_blank" class="mt-3 inline-block rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">View Certificate</a>
                @else
                    <p class="mt-2 text-sm text-neutral-600">No certificate was issued. Please contact People Development about a re-training.</p>
                @endif
            </div>
        @elseif (! $eligible)
            <div class="rounded-md border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">{{ $message }}</div>
        @else
            <form method="POST" action="{{ route('training.quiz.submit', $participant) }}" onsubmit="return confirm('Answers cannot be changed after submitting. Continue?');" class="space-y-4">
                @csrf
                @foreach ($questions as $question)
                    <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md">
                        <p class="mb-3 text-sm font-medium text-neutral-900">{{ $loop->iteration }}. {!! nl2br(e($question->question_text)) !!}
                            @if ($question->allow_multiple_answers)
                                <span class="ml-1 rounded-full bg-accent-100 px-2 py-0.5 text-xs font-medium text-accent-800">choose more than one</span>
                            @endif
                        </p>
                        @foreach ($question->choices as $choice)
                            <label class="mb-1.5 flex cursor-pointer items-start gap-2 rounded-md px-2 py-1 text-sm text-neutral-700 hover:bg-brand-50/50">
                                <input type="{{ $question->allow_multiple_answers ? 'checkbox' : 'radio' }}"
                                       name="answers[{{ $question->id }}]{{ $question->allow_multiple_answers ? '[]' : '' }}"
                                       value="{{ $choice->id }}" class="mt-0.5 border-neutral-300 text-brand-600 focus:ring-brand-500 {{ $question->allow_multiple_answers ? 'rounded' : '' }}">
                                <span><strong>{{ $choice->option_label }}.</strong> {{ $choice->choice_text }}</span>
                            </label>
                        @endforeach
                    </div>
                @endforeach

                <button type="submit" class="w-full rounded-md bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700">Submit Answers</button>
            </form>
        @endif
    </div>
</x-app-layout>
