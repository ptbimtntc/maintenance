<x-app-layout>
    <x-slot name="header">Shift Comm Parameters</x-slot>

    <div class="space-y-4">
        <div>
            <a href="{{ route('organization.landing') }}" class="text-sm text-neutral-600 hover:underline">&larr; Back to Master Data</a>
        </div>

        <div class="max-w-2xl rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('organization.shift-comm-settings.update') }}">
                @csrf
                @method('PUT')

                @php
                    $canEdit = auth()->user()->canEditMenu(\App\Enums\MenuKey::Organization);
                @endphp

                <div class="space-y-6">
                    <div>
                        <x-input-label for="shift_comm_repeat_window_days" value="Rolling Days Problem Berulang TS (Hari)" />
                        <x-text-input id="shift_comm_repeat_window_days"
                                      type="number"
                                      min="1"
                                      max="365"
                                      name="shift_comm_repeat_window_days"
                                      class="mt-1 block w-full"
                                      value="{{ old('shift_comm_repeat_window_days', $repeatWindowDays) }}"
                                      :disabled="!$canEdit"
                                      required />
                        <p class="mt-1 text-xs text-neutral-500">Rentang hari ke belakang (rolling window) untuk akumulasi pengecekan problem Torsion Shaft berulang pada mesin. Contoh: 7 berarti pengecekan dalam 7 hari terakhir.</p>
                        <x-input-error :messages="$errors->get('shift_comm_repeat_window_days')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="shift_comm_repeat_threshold" value="Threshold Kejadian Problem Berulang TS (Kali)" />
                        <x-text-input id="shift_comm_repeat_threshold"
                                      type="number"
                                      min="1"
                                      max="100"
                                      name="shift_comm_repeat_threshold"
                                      class="mt-1 block w-full"
                                      value="{{ old('shift_comm_repeat_threshold', $repeatThreshold) }}"
                                      :disabled="!$canEdit"
                                      required />
                        <p class="mt-1 text-xs text-neutral-500">Jumlah minimum kejadian problem Torsion Shaft pada mesin yang sama dalam rentang rolling days agar notifikasi peringatan muncul (Default: 4 kali).</p>
                        <x-input-error :messages="$errors->get('shift_comm_repeat_threshold')" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-neutral-200 pt-4">
                    <x-button variant="secondary" href="{{ route('organization.landing') }}">Batal</x-button>
                    @if ($canEdit)
                        <x-button type="submit">Simpan Parameter</x-button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

