<x-app-layout>
    <x-slot name="header">LOTOTO</x-slot>

    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <p class="text-sm text-neutral-500">Lock Out Tag Out records and tracking.</p>
            <x-read-only-badge menu="safety" />
        </div>

        <div class="rounded-lg border border-dashed border-neutral-300 bg-white p-10 text-center">
            <svg class="mx-auto h-10 w-10 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
            <h2 class="mt-4 text-sm font-semibold text-neutral-900">LOTOTO module under development</h2>
            <p class="mx-auto mt-1 max-w-md text-sm text-neutral-500">
                This page is a placeholder for the Lock Out Tag Out (LOTOTO) module, which will be built out in a future update.
            </p>
        </div>
    </div>
</x-app-layout>
