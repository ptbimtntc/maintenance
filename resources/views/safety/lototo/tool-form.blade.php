<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Formulir Tools LOTOTO</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-100 font-sans antialiased">
    <div class="min-h-screen px-4 py-5 sm:px-6 sm:py-8">
    <div class="mx-auto max-w-4xl space-y-5">
        <header class="overflow-hidden rounded-2xl bg-neutral-900 text-white shadow-lg">
            <div class="border-l-4 border-yellow-400 px-5 py-6 sm:px-8 sm:py-8">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-yellow-300">Keselamatan Kerja · Tools</p>
                <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">{{ $tool->code }} · {{ $tool->name }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-neutral-300">
                    IN berarti Tools sedang digunakan atau berada di lokasi kerja. OUT berarti Tools dikembalikan ke board.
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
                <p class="font-bold">Status Tools belum dapat diubah.</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="grid gap-4 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:grid-cols-[8rem_1fr] sm:p-6">
            <div class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-xl border border-neutral-200 bg-neutral-50">
                @if ($tool->image_path)
                    <img src="{{ route('safety.lototo.tools.image', $tool) }}?v={{ $tool->updated_at?->timestamp }}" alt="Gambar {{ $tool->name }}" class="h-full w-full object-contain">
                @else
                    <svg class="h-12 w-12 text-neutral-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94L14.7 6.3z"/></svg>
                @endif
            </div>
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-xs font-bold uppercase tracking-wide text-neutral-500">LOTOTO Board</dt><dd class="mt-1 font-semibold text-neutral-900">{{ $board->title }}</dd><dd class="text-xs text-neutral-500">{{ $board->businessUnit?->name }} · {{ $board->division_name }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-neutral-500">Kode / Nama</dt><dd class="mt-1 font-semibold text-neutral-900">{{ $tool->code }} · {{ $tool->name }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-neutral-500">Lokasi Tools</dt><dd class="mt-1 text-neutral-900">{{ $tool->location ?: '—' }}</dd></div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-neutral-500">Status saat ini</dt>
                    <dd class="mt-1">
                        @if ($openActivity)
                            <span class="inline-block rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-900" data-tool-status="in">IN</span>
                            <span class="mt-1 block text-xs text-neutral-600">Di {{ $openActivity->location }} sejak {{ \App\Models\LototoActivity::localTimeFromUtc($openActivity->started_at)->format('d M Y H:i') }} oleh {{ $openActivity->startedBy?->name ?? 'pengguna' }}</span>
                        @else
                            <span class="inline-block rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-800" data-tool-status="out">OUT</span>
                            <span class="mt-1 block text-xs text-neutral-600">{{ $tool->is_active ? 'Tools berada di board.' : 'Tools nonaktif.' }}</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
            @if ($openActivity)
                @if ($canFinish)
                    <form method="POST" action="{{ route('safety.lototo.tools.form.store', $tool) }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="board_id" value="{{ $board->id }}">
                        <input type="hidden" name="action" value="out">
                        <input type="hidden" name="activity_id" value="{{ $openActivity->id }}">
                        <p class="text-sm text-neutral-700">Kembalikan Tools ke board setelah selesai digunakan di <span class="font-semibold">{{ $openActivity->location }}</span>.</p>
                        <button type="submit" class="min-h-11 rounded-lg bg-emerald-700 px-5 text-sm font-bold text-white hover:bg-emerald-600">OUT · Kembalikan ke board</button>
                    </form>
                @else
                    <p class="text-sm text-neutral-700">Tools sedang IN. OUT hanya dapat dilakukan oleh pengguna yang melakukan IN atau pengguna dengan izin Manage Safety.</p>
                @endif
            @elseif (! $board->is_active)
                <p class="text-sm text-amber-900">Board nonaktif sehingga tidak menerima IN Tools baru.</p>
            @elseif (! $tool->is_active)
                <p class="text-sm text-neutral-700">Tools nonaktif sehingga tidak dapat di-IN.</p>
            @else
                <form method="POST" action="{{ route('safety.lototo.tools.form.store', $tool) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="board_id" value="{{ $board->id }}">
                    <input type="hidden" name="action" value="in">
                    <label class="block text-sm font-bold text-neutral-800">Lokasi penggunaan
                        <input name="location" value="{{ old('location', $tool->location) }}" required maxlength="255" class="mt-1 block min-h-11 w-full rounded-lg border-neutral-300 text-sm" placeholder="Contoh: Line 3 · Mesin Extruder">
                    </label>
                    <button type="submit" class="min-h-11 rounded-lg bg-amber-500 px-5 text-sm font-bold text-neutral-900 hover:bg-amber-400">IN · Gunakan Tools</button>
                </form>
            @endif
        </section>

        <section class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
            <header class="border-b border-neutral-200 px-5 py-3"><h2 class="font-bold text-neutral-900">Riwayat penggunaan</h2></header>
            @if ($activities->isEmpty())
                <p class="p-5 text-sm text-neutral-500">Belum ada riwayat penggunaan Tools ini.</p>
            @else
                <ul class="divide-y divide-neutral-100 sm:hidden">
                    @foreach ($activities as $activity)
                        <li class="px-5 py-3 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <p class="min-w-0 break-words font-semibold text-neutral-900">{{ $activity->location }}</p>
                                <span @class(['shrink-0 rounded px-2 py-0.5 text-xs font-bold', 'bg-amber-100 text-amber-900' => $activity->status === 'in_progress', 'bg-emerald-100 text-emerald-800' => $activity->status !== 'in_progress'])>{{ $activity->status === 'in_progress' ? 'IN' : 'OUT' }}</span>
                            </div>
                            <dl class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                <div><dt class="font-semibold uppercase text-neutral-500">IN</dt><dd class="text-neutral-800">{{ \App\Models\LototoActivity::localTimeFromUtc($activity->started_at)->format('d M Y H:i') }}<span class="block text-neutral-500">{{ $activity->startedBy?->name }}</span></dd></div>
                                <div><dt class="font-semibold uppercase text-neutral-500">OUT</dt><dd class="text-neutral-800">{{ \App\Models\LototoActivity::localTimeFromUtc($activity->ended_at)?->format('d M Y H:i') ?? '—' }}<span class="block text-neutral-500">{{ $activity->endedBy?->name }}</span></dd></div>
                            </dl>
                        </li>
                    @endforeach
                </ul>
                <div class="hidden overflow-x-auto sm:block">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-600">
                            <tr><th class="px-4 py-2">Lokasi</th><th class="px-4 py-2">IN</th><th class="px-4 py-2">OUT</th><th class="px-4 py-2">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($activities as $activity)
                                <tr>
                                    <td class="px-4 py-2">{{ $activity->location }}</td>
                                    <td class="whitespace-nowrap px-4 py-2">{{ \App\Models\LototoActivity::localTimeFromUtc($activity->started_at)->format('d M Y H:i') }}<span class="block text-xs text-neutral-500">{{ $activity->startedBy?->name }}</span></td>
                                    <td class="whitespace-nowrap px-4 py-2">{{ \App\Models\LototoActivity::localTimeFromUtc($activity->ended_at)?->format('d M Y H:i') ?? '—' }}<span class="block text-xs text-neutral-500">{{ $activity->endedBy?->name }}</span></td>
                                    <td class="px-4 py-2">{{ $activity->status === 'in_progress' ? 'IN' : 'OUT' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3">{{ $activities->appends(['board' => $board->id])->links() }}</div>
            @endif
        </section>

        <a href="{{ route('safety.lototo.boards.show', $board) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-blue-700 hover:underline lg:inline-block lg:min-h-0">Kembali ke board</a>
    </div>
    </div>
</body>
</html>
