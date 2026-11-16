{{-- Supervisor Comment / Feedback, below the technician's information. A follow-up that already has a reply takes
     further replies; earlier ones stay in the history above. --}}
@if (in_array($activity->supervisor_follow_up_status, ['pending', 'completed'], true))
    <form method="POST" action="{{ $formAction }}" class="space-y-3 border-t border-neutral-200 pt-4" data-supervisor-feedback-form
          onsubmit="if (this.dataset.sending) { return false; } this.dataset.sending = '1'; this.querySelector('button[type=submit]').disabled = true;">
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

{{-- Outcome of the reply just sent (flashed once): email result and the WhatsApp hand-over. WhatsApp opens with the text
     filled in; the supervisor still presses send there. --}}
@if ($result = session('follow_up_result'))
    <div id="follow-up-result" role="dialog" aria-modal="true" aria-labelledby="follow-up-result-title"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md space-y-3 rounded-2xl bg-white p-6 shadow-xl">
            @if ($result['emailed'])
                <h2 id="follow-up-result-title" class="text-lg font-black text-emerald-800">Follow Up Berhasil Dikirim</h2>
                <p class="text-sm text-neutral-700">Email telah dikirim ke <strong>{{ $result['employee'] }}</strong> ({{ $result['email'] }}).</p>
            @elseif ($result['email_skipped'])
                <h2 id="follow-up-result-title" class="text-lg font-black text-neutral-900">Follow Up Sudah Tercatat</h2>
                <p class="text-sm text-neutral-700">Follow Up yang sama sudah tersimpan, jadi email tidak dikirim ulang.</p>
            @else
                <h2 id="follow-up-result-title" class="text-lg font-black text-red-800">Email Gagal Dikirim</h2>
                <p class="text-sm text-neutral-700">Follow Up tersimpan, tetapi email ke <strong>{{ $result['employee'] }}</strong> belum terkirim: {{ $result['error'] }}</p>
            @endif
            @if ($result['whatsapp_url'])
                <p class="text-sm text-neutral-700">Nomor WhatsApp {{ $result['employee'] }}: <strong>{{ $result['phone'] }}</strong></p>
                <p class="text-sm font-semibold text-neutral-900">Lanjutkan Follow Up melalui WhatsApp?</p>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <a href="{{ $result['whatsapp_url'] }}" target="_blank" rel="noopener" data-follow-up-whatsapp
                       class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700">Kirim via WhatsApp</a>
                    <button type="button" data-follow-up-close class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-neutral-300 px-4 text-sm font-bold text-neutral-700 hover:bg-neutral-50">Nanti saja</button>
                </div>
            @else
                <p class="text-sm text-neutral-600">Nomor WhatsApp karyawan tidak tersedia.</p>
                <button type="button" data-follow-up-close class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-neutral-900 px-4 text-sm font-bold text-white hover:bg-neutral-700">Tutup</button>
            @endif
        </div>
    </div>
    <script>
        (() => {
            const box = document.getElementById('follow-up-result');
            const close = () => box?.remove();
            box?.querySelectorAll('[data-follow-up-close]').forEach((button) => button.addEventListener('click', close));
            box?.querySelector('[data-follow-up-whatsapp]')?.addEventListener('click', () => setTimeout(close, 300));
        })();
    </script>
@endif
