<x-app-layout>
    <x-slot name="header">Settings</x-slot>

    <div class="max-w-xl rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
        @if (session('status'))
            <div class="mb-4 rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('settings.update') }}">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <div>
                    <x-input-label for="certificate_expiring_soon_days" value="Certificate 'Expiring Soon' Window (days)" />
                    <x-text-input id="certificate_expiring_soon_days" type="number" min="1" max="365" name="certificate_expiring_soon_days" class="mt-1 block w-full" value="{{ old('certificate_expiring_soon_days', $certificateExpiringSoonDays) }}" />
                    <p class="mt-1 text-xs text-neutral-500">Certificates with an expiry date within this many days are flagged "Expiring Soon" on the Dashboard, Certificates list, and employee profiles.</p>
                    <x-input-error :messages="$errors->get('certificate_expiring_soon_days')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="training_reminder_days_before" value="Training Session Reminder Lead Time (days)" />
                    <x-text-input id="training_reminder_days_before" type="number" min="1" max="60" name="training_reminder_days_before" class="mt-1 block w-full" value="{{ old('training_reminder_days_before', $trainingReminderDaysBefore) }}" />
                    <p class="mt-1 text-xs text-neutral-500">Participants get an in-app notification this many days before a session starts.</p>
                    <x-input-error :messages="$errors->get('training_reminder_days_before')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Save Settings</button>
            </div>
        </form>
    </div>
</x-app-layout>
