<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Formulir LOTOTO</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-100 font-sans antialiased">
    <div class="min-h-screen px-4 py-5 sm:px-6 sm:py-8">
    <div class="mx-auto max-w-4xl space-y-5">
        <header class="overflow-hidden rounded-2xl bg-neutral-900 text-white shadow-lg">
            <div class="border-l-4 border-yellow-400 px-5 py-6 sm:px-8 sm:py-8">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-yellow-300">Keselamatan Kerja</p>
                <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">Pemeriksaan LOTOTO</h1>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-neutral-300">
                    Lengkapi pemeriksaan sebelum memulai pekerjaan. Pastikan semua risiko dan langkah pengendalian sudah dipahami.
                </p>
            </div>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-bold">Form belum dapat disimpan.</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($board->is_active)
        <div role="status" class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3l-7.5-13.1a2 2 0 0 0-3.4 0Z" />
            </svg>
            <p><span class="font-bold">Catatan alur:</span> Saat OUT Outstanding, pilih gembok spare yang akan langsung dipakai. QR gembok spare juga dapat dipakai untuk memulai IN mandiri tanpa handover; scan kembali untuk OUT/Finish.</p>
        </div>
        @else
        <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Board nonaktif. Aktivitas yang sedang berjalan hanya dapat diselesaikan dengan OUT Finish; IN baru dan Outstanding / Handover tidak tersedia.
        </div>
        @endif

        @if ($activeActivity)
            <div role="status" class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                Ada aktivitas terbuka untuk LOTOTO {{ $lototoNumber }}. Pilih hasil OUT; jawaban pemeriksaan IN sudah tersimpan dan tidak perlu diisi ulang.
            </div>
        @elseif ($spareLock)
            <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Gembok spare {{ $spareLock->code }} tersedia. Isi data pekerjaan untuk memulai IN mandiri, atau gembok ini akan langsung IN saat dipilih pada OUT Outstanding.
            </div>
        @endif

        <form method="POST" action="{{ $formAction }}" class="space-y-5" aria-label="Formulir pemeriksaan LOTOTO">
            @csrf
            <input type="hidden" name="board_id" value="{{ $board->id }}">
            @if ($requestedEmployee)
                <input type="hidden" name="employee_id" value="{{ $requestedEmployee->id }}">
            @endif
            <input type="hidden" name="lock_mode" value="{{ $spareLock ? 'spare' : 'personal' }}">
            <section class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-yellow-100 text-sm font-black text-yellow-900">01</span>
                    <div>
                        <h2 class="font-bold text-neutral-900">Informasi pekerjaan</h2>
                        <p class="text-xs text-neutral-500">Isi identitas gembok dan area kerja.</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="lototo_number" class="text-sm font-semibold text-neutral-700">No. LOTOTO <span class="text-red-600">*</span></label>
                        <input
                            id="lototo_number"
                            name="lototo_number"
                            type="text"
                            value="{{ $lototoNumber }}"
                            placeholder="Contoh: 01"
                            autocomplete="off"
                            @readonly(filled($lototoNumber))
                            required
                            class="mt-1.5 block min-h-11 w-full rounded-xl border-neutral-300 text-base shadow-sm focus:border-yellow-500 focus:ring-yellow-500"
                        >
                    </div>

                    <input type="hidden" name="lototo_board" value="{{ $lototoBoard }}">
                    <div>
                        <span class="text-sm font-semibold text-neutral-700">LOTOTO Board</span>
                        <p class="mt-1.5 flex min-h-11 items-center rounded-xl bg-neutral-100 px-3 text-sm font-bold text-neutral-800">
                            {{ $spareLock ? 'GEMBOK SPARE '.$spareLock->code : $board->title }}
                        </p>
                    </div>

                    <fieldset class="sm:col-span-2">
                        <legend class="text-sm font-semibold text-neutral-700">Status gembok <span class="text-red-600">*</span></legend>
                        @if ($activeActivity)
                            @if ($spareLock)
                                <input type="hidden" name="spare_lock_id" value="{{ $spareLock->id }}">
                            @endif
                            <input type="hidden" name="lock_status" value="out">
                            <p class="mt-2 flex min-h-12 items-center rounded-xl border border-amber-200 bg-amber-50 px-4 text-sm font-bold text-amber-900">
                                OUT — aktivitas mulai
                                {{ $spareLock && data_get($activeActivity->out_details, 'spare_started_at')
                                    ? \App\Models\LototoActivity::localTime(\Illuminate\Support\Carbon::parse(data_get($activeActivity->out_details, 'spare_started_at')))->format('d M Y H:i')
                                    : \App\Models\LototoActivity::localTimeFromUtc($activeActivity->started_at)?->format('d M Y H:i') }}
                            </p>
                        @else
                            @if ($spareLock)
                                <input type="hidden" name="spare_lock_id" value="{{ $spareLock->id }}">
                            @endif
                            <input type="hidden" name="lock_status" value="in">
                            <p class="mt-2 flex min-h-12 items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-bold text-emerald-900">
                                {{ $spareLock ? 'IN — mulai pemakaian gembok spare' : 'IN — mulai pemeriksaan pekerjaan' }}
                            </p>
                        @endif
                    </fieldset>

                    <fieldset id="outcome-section" @class(['hidden' => ! $activeActivity, 'sm:col-span-2'])>
                        <legend class="text-sm font-semibold text-neutral-700">Status pekerjaan saat OUT <span class="text-red-600">*</span></legend>
                        <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach (($spareLock || ! $allowOutstanding) ? ['finish' => 'Close / Finish (OUT)'] : ['finish' => 'Close / Finish', 'outstanding' => 'Outstanding / Handover'] as $value => $label)
                                <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-neutral-200 px-4 transition hover:border-yellow-400 has-[:checked]:border-yellow-500 has-[:checked]:bg-yellow-50">
                                    <input type="radio" name="outcome" value="{{ $value }}" @checked(old('outcome') === $value) class="border-neutral-300 text-yellow-600 focus:ring-yellow-500">
                                    <span class="text-sm font-bold text-neutral-800">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    @if ($activeActivity)
                        <div class="sm:col-span-2 rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Data pemeriksaan IN tersimpan</p>
                            <p class="mt-1 text-sm font-bold text-neutral-900">{{ $activeActivity->location }}</p>
                            <p class="mt-1 text-sm text-neutral-700">{{ $activeActivity->activity }}</p>
                            <p class="mt-2 text-xs text-neutral-500">Data pemeriksaan tidak perlu diisi ulang saat OUT.</p>
                        </div>
                    @else
                        <div class="sm:col-span-2">
                            <label for="location" class="text-sm font-semibold text-neutral-700">Lokasi / mesin <span class="text-red-600">*</span></label>
                            <input id="location" name="location" type="text" placeholder="Nama area atau mesin" required class="mt-1.5 block min-h-11 w-full rounded-xl border-neutral-300 text-base shadow-sm focus:border-yellow-500 focus:ring-yellow-500">
                        </div>

                        <div class="sm:col-span-2">
                            <label for="activity" class="text-sm font-semibold text-neutral-700">Aktivitas pekerjaan <span class="text-red-600">*</span></label>
                            <textarea id="activity" name="activity" rows="3" placeholder="Jelaskan pekerjaan yang akan dilakukan" required class="mt-1.5 block w-full rounded-xl border-neutral-300 text-base shadow-sm focus:border-yellow-500 focus:ring-yellow-500"></textarea>
                        </div>
                    @endif

                    @unless ($spareLock)
                        <div id="spare-lock-section" @class(['hidden' => ! $activeActivity, 'sm:col-span-2'])>
                            <label for="spare_lock_id" class="text-sm font-semibold text-neutral-700">Pilih gembok spare untuk handover <span class="text-red-600">*</span></label>
                            <select id="spare_lock_id" name="spare_lock_id" class="mt-1.5 block min-h-11 w-full rounded-xl border-neutral-300 text-base shadow-sm focus:border-yellow-500 focus:ring-yellow-500">
                                <option value="">Pilih kode gembok spare</option>
                                @foreach ($availableSpareLocks as $availableSpareLock)
                                    <option value="{{ $availableSpareLock->id }}" @selected(old('spare_lock_id') == $availableSpareLock->id)>{{ $availableSpareLock->code }}</option>
                                @endforeach
                            </select>
                            @if ($availableSpareLocks->isEmpty())
                                <p class="mt-2 text-sm text-amber-700">Belum ada gembok spare aktif yang tersedia. Hubungi supervisor sebelum memilih Outstanding.</p>
                            @else
                                <p class="mt-2 text-xs text-neutral-500">Gembok yang dipilih akan langsung ditandai IN saat OUT Outstanding disimpan.</p>
                            @endif
                        </div>
                    @endunless
                </div>
            </section>

            @unless ($activeActivity)
            <section class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-orange-100 text-sm font-black text-orange-800">02</span>
                    <div>
                        <h2 class="font-bold text-neutral-900">Kenali dan kendalikan risiko</h2>
                        <p class="text-xs text-neutral-500">Pilih semua risiko yang mungkin terjadi.</p>
                    </div>
                </div>

                <fieldset>
                    <legend class="text-sm font-semibold text-neutral-700">Risiko dalam pekerjaan <span class="text-red-600">*</span></legend>
                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach (['Kesetrum', 'Tertabrak', 'Terjatuh', 'Terjepit', 'Terpukul', 'Tertimpa'] as $risk)
                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-neutral-200 px-3 transition hover:border-orange-300 has-[:checked]:border-orange-400 has-[:checked]:bg-orange-50">
                                <input type="checkbox" name="risks[]" value="{{ \Illuminate\Support\Str::slug($risk) }}" class="rounded border-neutral-300 text-orange-600 focus:ring-orange-500">
                                <span class="text-sm text-neutral-700">{{ $risk }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-3">
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-neutral-200 px-3">
                            <input type="checkbox" name="risks[]" value="lainnya" class="rounded border-neutral-300 text-orange-600 focus:ring-orange-500">
                            <span class="text-sm text-neutral-700">Lainnya</span>
                        </label>
                        <input type="text" name="other_risk" aria-label="Risiko lainnya" placeholder="Tuliskan risiko lainnya" class="mt-2 block min-h-11 w-full rounded-xl border-neutral-300 text-base shadow-sm focus:border-orange-500 focus:ring-orange-500">
                    </div>
                </fieldset>

                <div class="mt-5">
                    <label for="risk_controls" class="text-sm font-semibold text-neutral-700">Bagaimana cara menghindari risiko tersebut? <span class="text-red-600">*</span></label>
                    <textarea id="risk_controls" name="risk_controls" rows="4" placeholder="Tuliskan langkah pengendalian yang akan dilakukan" required class="mt-1.5 block w-full rounded-xl border-neutral-300 text-base shadow-sm focus:border-yellow-500 focus:ring-yellow-500"></textarea>
                </div>
            </section>

            <section class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-sm font-black text-emerald-800">03</span>
                    <div>
                        <h2 class="font-bold text-neutral-900">Pemeriksaan kesiapan kerja</h2>
                        <p class="text-xs text-neutral-500">Jawab setiap pertanyaan dengan jujur sebelum bekerja.</p>
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach ([
                        'knows_procedure' => 'Saya tahu bagaimana melakukan pekerjaan ini',
                        'follows_lototo' => 'Saya melakukan standar LOTOTO',
                        'uses_ppe' => 'Saya menggunakan APD dengan baik dan benar',
                        'safe_tools' => 'Saya memiliki tools dengan kondisi aman untuk digunakan',
                        'safe_hands' => 'Jari / tangan saya AMAN (tidak dalam LoF)',
                    ] as $name => $question)
                        <fieldset class="grid gap-2 rounded-xl border border-neutral-200 p-3 sm:grid-cols-[1fr_auto] sm:items-center sm:gap-4 sm:p-4">
                            <legend class="sr-only">{{ $question }}</legend>
                            <p class="text-sm font-medium leading-relaxed text-neutral-800">{{ $question }} <span class="text-red-600">*</span></p>
                            <div class="grid grid-cols-2 gap-2 sm:w-44">
                                @foreach (['ya' => 'Ya', 'tidak' => 'Tidak'] as $value => $label)
                                    <label class="flex min-h-10 cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-200 px-3 has-[:checked]:border-yellow-500 has-[:checked]:bg-yellow-50">
                                        <input type="radio" name="{{ $name }}" value="{{ $value }}" required class="border-neutral-300 text-yellow-600 focus:ring-yellow-500">
                                        <span class="text-sm font-semibold text-neutral-700">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="mb-4">
                    <h2 class="font-bold text-neutral-900">Feedback untuk atasan</h2>
                    <p class="mt-1 text-xs text-neutral-500">Opsional. Feedback yang dikirim nantinya akan diteruskan kepada supervisor.</p>
                </div>
                <label for="supervisor_feedback" class="sr-only">Feedback untuk atasan</label>
                <textarea id="supervisor_feedback" name="supervisor_feedback" rows="4" placeholder="Tulis masukan atau kendala yang perlu diketahui atasan..." class="block w-full rounded-xl border-neutral-300 text-base shadow-sm focus:border-yellow-500 focus:ring-yellow-500"></textarea>
            </section>
            @endunless

            <footer class="flex flex-col-reverse gap-3 pb-6 sm:flex-row sm:items-center sm:justify-between">
                <span class="text-center text-xs text-neutral-500">
                    {{ $activeActivity ? ($spareLock ? 'Pilih Finish untuk mengakhiri pemakaian gembok spare.' : 'Pilih Finish untuk menutup gembok pribadi atau pilih spare jika Outstanding.') : ($spareLock ? 'Isi data aktivitas spare sebelum memulai IN.' : 'Pastikan semua jawaban sudah benar sebelum mengirim.') }}
                </span>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-neutral-900 px-6 text-sm font-bold text-white transition hover:bg-neutral-700">
                    {{ $activeActivity ? 'Simpan status OUT' : ($spareLock ? 'Mulai IN gembok spare' : 'Kirim pemeriksaan IN') }}
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
                    </svg>
                </button>
            </footer>
        </form>
    </div>
    </div>

</body>
</html>
