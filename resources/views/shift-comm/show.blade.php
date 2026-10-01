@php
$fields = [
    'Problem' => $comm->problem,
    'Progress' => $comm->progress,
    'Remark' => $comm->remark,
];
@endphp

<x-app-layout>
    <x-slot name="header">Shift Comm {{ $comm->comm_number }}</x-slot>

    <div class="max-w-3xl space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif

        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('shift-comm.index') }}" class="text-sm text-neutral-600 hover:underline">&larr; All Shift Comm</a>
            @can('update', $comm)
                <div class="flex items-center gap-2 text-sm">
                    <a href="{{ route('shift-comm.edit', $comm) }}" class="rounded-md border border-neutral-300 px-3 py-1.5 text-neutral-700 hover:bg-neutral-50">Edit</a>
                    <form method="POST" action="{{ route('shift-comm.destroy', $comm) }}" onsubmit="return confirm('Delete this Shift Comm entry?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-md border border-danger-300 px-3 py-1.5 text-danger-700 hover:bg-danger-50">Delete</button>
                    </form>
                </div>
            @endcan
        </div>

        <div class="space-y-5 rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
            <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div><dt class="text-[11px] font-medium uppercase text-neutral-500">Date</dt><dd class="mt-0.5">{{ $comm->comm_date->format('d M Y') }}</dd></div>
                <div><dt class="text-[11px] font-medium uppercase text-neutral-500">Shift</dt><dd class="mt-0.5">{{ $comm->shiftName() }}</dd></div>
                <div><dt class="text-[11px] font-medium uppercase text-neutral-500">Team</dt><dd class="mt-0.5">{{ $comm->team }}</dd></div>
                <div><dt class="text-[11px] font-medium uppercase text-neutral-500">Machine No.</dt><dd class="mt-0.5">{{ $comm->machine_no }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-[11px] font-medium uppercase text-neutral-500">Construction</dt><dd class="mt-0.5">{{ $comm->construction ?: '-' }}</dd></div>
                <div><dt class="text-[11px] font-medium uppercase text-neutral-500">Length</dt><dd class="mt-0.5">{{ $comm->length_m !== null ? number_format($comm->length_m).' m' : '-' }}</dd></div>
                <div><dt class="text-[11px] font-medium uppercase text-neutral-500">Logged by</dt><dd class="mt-0.5">{{ $comm->creator?->name ?? '-' }}</dd></div>
            </dl>

            @foreach ($fields as $label => $text)
                <div>
                    <h3 class="text-[11px] font-medium uppercase text-neutral-500">{{ $label }}</h3>
                    <p class="mt-0.5 whitespace-pre-line text-sm text-neutral-800">{{ $text ?: '-' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
