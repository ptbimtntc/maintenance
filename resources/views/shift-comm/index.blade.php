<x-app-layout>
    <x-slot name="header">Shift Comm</x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif

        @foreach ($repeatWarnings as $warning)
            <div class="rounded-md border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-800">
                &#9888; WARNING: Mesin {{ $warning['machine'] }} telah mengalami Problem Torsion Shaft sebanyak {{ $warning['count'] }} kali dalam 30 hari terakhir ({{ $warning['first']->format('d M Y') }} s/d {{ $warning['last']->format('d M Y') }}). Mesin ini terindikasi mengalami problem berulang dan perlu dilakukan pengecekan/tindakan lanjutan.
            </div>
        @endforeach

        <div class="flex flex-wrap items-end justify-between gap-3">
            <form method="GET" action="{{ route('shift-comm.index') }}" class="flex flex-wrap items-end gap-2.5">
                <div>
                    <label class="block text-[11px] font-medium text-neutral-500">Date</label>
                    <input type="date" name="date" value="{{ $filters['date'] }}" onchange="this.form.submit()" class="mt-0.5 block rounded-md border-neutral-300 text-sm">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-neutral-500">Shift</label>
                    <select name="shift" onchange="this.form.submit()" class="mt-0.5 block rounded-md border-neutral-300 text-sm">
                        <option value="">All Shifts</option>
                        @foreach (\App\Models\ShiftComm::SHIFTS as $value => $label)
                            <option value="{{ $value }}" @selected($filters['shift'] == $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-neutral-500">Search</label>
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="ID, machine, construction, problem" class="mt-0.5 block w-64 rounded-md border-neutral-300 text-sm">
                </div>
                <div class="self-start pt-[1.125rem]">
                    <x-filter-panel label="TS filter" :active-count="(int) ($filters['torsion'] !== '') + (int) ($filters['relay'] !== '')">
                        <div class="flex flex-wrap items-end gap-2.5">
                        <div>
                            <label class="block text-[11px] font-medium text-neutral-500">Torsion Shaft</label>
                            <select name="torsion" onchange="this.form.submit()" class="mt-0.5 block rounded-md border-neutral-300 text-sm">
                                <option value="">Semua</option>
                                <option value="1" @selected($filters['torsion'] === '1')>YA</option>
                                <option value="0" @selected($filters['torsion'] === '0')>TIDAK</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-neutral-500">Estafet Torsion Shaft</label>
                            <select name="relay" onchange="this.form.submit()" class="mt-0.5 block rounded-md border-neutral-300 text-sm">
                                <option value="">Semua</option>
                                @foreach (\App\Models\ShiftComm::TORSION_RELAY_STATUSES as $key => $label)
                                    <option value="{{ $key }}" @selected($filters['relay'] === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        </div>
                    </x-filter-panel>
                </div>
                <x-secondary-button type="submit">Filter</x-secondary-button>
                @if (array_filter($filters))
                    <a href="{{ route('shift-comm.index') }}" class="py-2 text-sm text-neutral-500 hover:underline">Reset</a>
                @endif
            </form>
            <a href="{{ route('shift-comm.create') }}"><x-primary-button type="button">New Shift Comm</x-primary-button></a>
        </div>

        <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white shadow-md">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-2">ID</th>
                        <th class="px-4 py-2">Date</th>
                        <th class="px-4 py-2">Shift</th>
                        <th class="px-4 py-2">Team</th>
                        <th class="px-4 py-2">Machine</th>
                        <th class="px-4 py-2">Problem</th>
                        <th class="px-4 py-2">Construction</th>
                        <th class="px-4 py-2">Torsion Shaft</th>
                        <th class="px-4 py-2 text-right">Length (m)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($comms as $comm)
                        <tr class="hover:bg-neutral-50">
                            <td class="whitespace-nowrap px-4 py-2 font-medium"><a href="{{ route('shift-comm.show', $comm) }}" class="text-brand-700 hover:underline">{{ $comm->comm_number }}</a></td>
                            <td class="whitespace-nowrap px-4 py-2">{{ $comm->comm_date->format('d M Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-2">{{ $comm->shiftName() }}</td>
                            <td class="px-4 py-2">{{ $comm->team }}</td>
                            <td class="px-4 py-2">{{ $comm->machine_no }}</td>
                            <td class="max-w-xs truncate px-4 py-2 text-neutral-600">{{ $comm->problem }}</td>
                            <td class="px-4 py-2">{{ $comm->construction }}</td>
                            <td class="whitespace-nowrap px-4 py-2">{{ $comm->is_torsion_shaft ? 'YA - '.$comm->torsionRelayLabel() : ($comm->is_torsion_shaft === null ? '' : 'TIDAK') }}</td>
                            <td class="px-4 py-2 text-right">{{ $comm->length_m !== null ? number_format($comm->length_m) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-6 text-center text-neutral-500">No Shift Comm entries found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $comms->links() }}
    </div>
</x-app-layout>
