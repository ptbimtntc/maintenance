{{-- Supervisor Comment / Feedback, below the technician's information. A follow-up that already has a reply takes
     further replies; earlier ones stay in the history above. --}}
@if (in_array($activity->supervisor_follow_up_status, ['pending', 'completed'], true))
    <form method="POST" action="{{ $formAction }}" class="space-y-3 border-t border-neutral-200 pt-4" data-supervisor-feedback-form>
        @csrf
        @if ($activity->supervisor_follow_up_status === 'completed')
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800">STATUS: CLOSED · balasan tambahan tetap dapat dikirim.</p>
        @endif
        <label for="supervisor_follow_up" class="block text-base font-bold text-neutral-900">Supervisor Comment / Feedback <span class="text-red-600">*</span></label>
        <p class="text-sm text-neutral-600">Tuliskan komentar, arahan, atau tindakan atas kondisi yang dilaporkan teknisi.</p>
        <textarea
            id="supervisor_follow_up"
            name="supervisor_follow_up"
            rows="5"
            required
            maxlength="5000"
            class="block w-full rounded-xl border-neutral-300 text-base shadow-sm focus:border-yellow-500 focus:ring-yellow-500"
        >{{ old('supervisor_follow_up') }}</textarea>
        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-neutral-900 px-6 text-sm font-bold text-white transition hover:bg-neutral-700 sm:w-auto">
            Send Feedback
        </button>
    </form>
@else
    <p class="border-t border-neutral-200 pt-4 text-sm text-neutral-600">Aktivitas ini tidak memerlukan tindak lanjut supervisor.</p>
@endif
