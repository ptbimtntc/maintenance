@php
$statusStyles = [
    'valid' => 'bg-success-100 text-success-800',
    'expiring_soon' => 'bg-warning-100 text-warning-800',
    'expired' => 'bg-danger-100 text-danger-800',
    'no_expiry' => 'bg-neutral-100 text-neutral-600',
    'pending_verification' => 'bg-accent-100 text-accent-800',
];
@endphp

<x-app-layout>
    <x-slot name="header">Certificates</x-slot>

    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <p class="text-sm text-neutral-500">{{ $certificates->total() }} certificate(s) found.</p>
                <x-read-only-badge menu="certificates" />
            </div>

            @can(\App\Enums\PermissionName::ManageCertificates->value)
                <div class="flex items-center gap-2">
                    <a href="{{ route('certificates.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}"
                       class="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        Export XLSX
                    </a>

                    <form method="POST" action="{{ route('certificates.import') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-4.5L12 16.5m0 0 4.5-4.5M12 16.5V3" />
                            </svg>
                            Import XLSX
                            <input type="file" name="file" accept=".xlsx" class="hidden" onchange="this.form.requestSubmit()">
                        </label>
                    </form>
                </div>
            @endcan
        </div>

        @if (session('import_errors'))
            <div class="rounded-md border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">
                {{ session('import_errors') }}
            </div>
        @endif

        @php
            $activeFieldClass = 'border-brand-300 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200 shadow-sm';
            $defaultFieldClass = 'border-neutral-300 hover:border-accent-400';
            $filterActiveCount = collect($filters)->filter(fn ($value) => filled($value))->count();
        @endphp

        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" class="grid grid-cols-1 gap-2.5 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm sm:grid-cols-4">
                <input type="text" name="employee_search" value="{{ $filters['employee_search'] ?? '' }}" placeholder="Search employee..." class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['employee_search'] ?? null) ? $activeFieldClass : $defaultFieldClass }}" />
                <select name="certificate_type_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['certificate_type_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Types</option>
                    @foreach ($certificateTypes as $type)
                        <option value="{{ $type->id }}" @selected(($filters['certificate_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['status'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Statuses</option>
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-brand-600 px-3.5 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Filter</button>
                    <a href="{{ route('certificates.index') }}" class="rounded-md border border-neutral-300 px-3.5 py-1.5 text-sm font-medium text-neutral-600 transition hover:border-accent-400 hover:bg-accent-50 hover:text-accent-700">Reset</a>
                </div>
            </form>
        </x-filter-panel>

        <div class="max-h-[70vh] overflow-auto rounded-lg border border-neutral-200 bg-white shadow-md">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Employee</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Certificate</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Type</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Expiry Date</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Status</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($certificates as $certificate)
                        <tr>
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ $certificate->employee->full_name }}</td>
                            <td class="px-3 py-2 text-neutral-700">{{ $certificate->name }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ $certificate->certificateType?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ $certificate->expiry_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusStyles[$certificate->status()] }}">{{ $statusLabels[$certificate->status()] }}</span></td>
                            <td class="px-3 py-2 text-right whitespace-nowrap"><a href="{{ route('certificates.show', $certificate) }}" target="_blank" class="font-medium text-accent-700 hover:underline">View Certificate</a> <a href="{{ route('employees.show', $certificate->employee) }}" class="ml-3 text-neutral-600 hover:underline">View Employee</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-neutral-500">No certificates recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $certificates->links() }}
    </div>
</x-app-layout>
