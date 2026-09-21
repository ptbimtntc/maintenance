<x-app-layout>
    <x-slot name="header">Quiz Questions — {{ $program->title }}</x-slot>

    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('training.programs.index') }}" class="text-sm text-neutral-600 hover:underline">&larr; Back to Training Programs</a>
            <p class="text-sm text-neutral-500">
                {{ $questions->count() }} question(s) &middot; passing score
                <strong class="text-neutral-800">{{ $program->passing_score ?? 'not set' }}</strong> &middot; validity
                <strong class="text-neutral-800">{{ $program->validity_months ? $program->validity_months.' months' : 'no expiry' }}</strong>
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="space-y-3">
            @forelse ($questions as $question)
                <div class="rounded-lg border border-neutral-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-medium text-neutral-900">{{ $loop->iteration }}. {{ $question->question_text }}
                            @if ($question->allow_multiple_answers)
                                <span class="ml-1 rounded-full bg-accent-100 px-2 py-0.5 text-xs font-medium text-accent-800">multiple answers</span>
                            @endif
                        </p>
                        @can(\App\Enums\PermissionName::ManageTraining->value)
                            <form method="POST" action="{{ route('training.programs.questions.destroy', [$program, $question]) }}" onsubmit="return confirm('Remove this question?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 hover:underline">Delete</button>
                            </form>
                        @endcan
                    </div>
                    <ul class="mt-2 grid grid-cols-1 gap-1 text-sm sm:grid-cols-2">
                        @foreach ($question->choices as $choice)
                            <li @class(['rounded px-2 py-1', 'bg-green-50 font-medium text-green-800' => $choice->is_correct, 'text-neutral-600' => ! $choice->is_correct])>
                                <strong>{{ $choice->option_label }}.</strong> {{ $choice->choice_text }}
                                @if ($choice->is_correct) <span class="text-xs">(correct, {{ $choice->points }} pt)</span> @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-neutral-300 bg-white p-8 text-center text-sm text-neutral-500">No questions yet. Add the first one below.</div>
            @endforelse
        </div>

        @can(\App\Enums\PermissionName::ManageTraining->value)
            <form method="POST" action="{{ route('training.programs.questions.store', $program) }}" class="space-y-4 rounded-lg border border-neutral-200 border-t-2 border-t-brand-500 bg-white p-4">
                @csrf
                <h2 class="text-sm font-semibold text-neutral-800">Add a question</h2>

                <div>
                    <x-input-label for="question_text" value="Question" />
                    <textarea id="question_text" name="question_text" rows="2" required class="mt-1 block w-full rounded-md border-neutral-300 text-sm">{{ old('question_text') }}</textarea>
                    <x-input-error :messages="$errors->get('question_text')" class="mt-1" />
                </div>

                <div class="space-y-2">
                    @foreach (['A', 'B', 'C', 'D'] as $label)
                        <div class="flex items-center gap-2">
                            <span class="w-5 text-sm font-semibold text-neutral-600">{{ $label }}</span>
                            <input type="text" name="choices[{{ $label }}][text]" value="{{ old("choices.$label.text") }}" required placeholder="Option {{ $label }}" class="block w-full rounded-md border-neutral-300 text-sm">
                            <input type="number" min="0" max="100" name="choices[{{ $label }}][points]" value="{{ old("choices.$label.points", 1) }}" title="Points if correct" class="w-20 rounded-md border-neutral-300 text-sm">
                            <label class="inline-flex shrink-0 items-center gap-1 text-sm text-neutral-700">
                                <input type="checkbox" name="correct[]" value="{{ $label }}" @checked(in_array($label, old('correct', []))) class="rounded border-neutral-300 text-brand-600 focus:ring-brand-500"> correct
                            </label>
                        </div>
                    @endforeach
                    <x-input-error :messages="$errors->get('correct')" class="mt-1" />
                    <x-input-error :messages="$errors->get('choices.A.text')" class="mt-1" />
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-neutral-700">
                    <input type="checkbox" name="allow_multiple_answers" value="1" @checked(old('allow_multiple_answers')) class="rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                    Allow more than one correct answer
                </label>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Add Question</button>
                </div>
            </form>
        @endcan
    </div>
</x-app-layout>
