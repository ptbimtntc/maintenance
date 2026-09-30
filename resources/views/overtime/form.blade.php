@php
$isEdit = $entry !== null;
$old = fn ($field, $default = null) => old($field, $isEdit ? ($entry->{$field} instanceof \Illuminate\Support\Carbon ? $entry->{$field}->format('Y-m-d\TH:i') : $entry->{$field}) : $default);
@endphp

<x-app-layout>
    <x-slot name="header">{{ $isEdit ? 'Edit Overtime Entry' : 'Log Overtime' }}</x-slot>

    <div class="max-w-2xl rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
        <form method="POST" action="{{ $isEdit ? route('overtime.update', $entry) : route('overtime.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="space-y-4">
                <div>
                    <x-input-label for="employee_id" value="Team Member" />
                    <select id="employee_id" name="employee_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                        @foreach ($directReports as $report)
                            <option value="{{ $report->id }}" @selected($old('employee_id') == $report->id)>{{ $report->full_name }} ({{ $report->employee_number }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('employee_id')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="start_at" value="From" />
                        <input id="start_at" type="datetime-local" name="start_at" class="mt-1 block w-full rounded-md border-neutral-300 text-sm" value="{{ $old('start_at') }}" required>
                        <x-input-error :messages="$errors->get('start_at')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="end_at" value="To" />
                        <input id="end_at" type="datetime-local" name="end_at" class="mt-1 block w-full rounded-md border-neutral-300 text-sm" value="{{ $old('end_at') }}" required>
                        <x-input-error :messages="$errors->get('end_at')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="compensation_type" value="OT Compensation" />
                    <select id="compensation_type" name="compensation_type" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                        <option value="OT_PAID" @selected($old('compensation_type', 'OT_PAID') === 'OT_PAID')>OT_PAID - Paid Overtime</option>
                        <option value="OT_LEAVE" @selected($old('compensation_type') === 'OT_LEAVE')>OT_LEAVE - Replacement Leave</option>
                    </select>
                    <x-input-error :messages="$errors->get('compensation_type')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="remarks" value="Remarks (work performed)" />
                    <textarea id="remarks" name="remarks" rows="4" class="mt-1 block w-full rounded-md border-neutral-300 text-sm" required>{{ $old('remarks') }}</textarea>
                    <x-input-error :messages="$errors->get('remarks')" class="mt-1" />
                </div>

                @if ($isEdit)
                    <p class="rounded-md bg-warning-50 px-3 py-2 text-xs text-warning-800">
                        Saving will re-lock this entry - editing it again will require a new approval.
                    </p>
                @else
                    <p class="rounded-md bg-neutral-50 px-3 py-2 text-xs text-neutral-500">
                        Once saved, this entry is locked and cannot be edited unless an edit request is approved.
                    </p>
                @endif
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
                    {{ $isEdit ? 'Save Changes' : 'Log Overtime' }}
                </button>
                <a href="{{ route('overtime.index') }}" class="text-sm text-neutral-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
