@php
$statusStyles = [
    'valid' => 'bg-green-100 text-green-800',
    'expiring_soon' => 'bg-amber-100 text-amber-800',
    'expired' => 'bg-red-100 text-red-800',
    'no_expiry' => 'bg-gray-100 text-gray-600',
    'pending_verification' => 'bg-blue-100 text-blue-800',
];
@endphp

<x-app-layout>
    <x-slot name="header">Certificates</x-slot>

    <div class="space-y-4">
        <x-read-only-badge menu="certificates" />

        <form method="GET" class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-4">
            <input type="text" name="employee_search" value="{{ $filters['employee_search'] ?? '' }}" placeholder="Search employee..." class="rounded-md border-gray-300 text-sm" />
            <select name="certificate_type_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Types</option>
                @foreach ($certificateTypes as $type)
                    <option value="{{ $type->id }}" @selected(($filters['certificate_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-md border-gray-300 text-sm">
                <option value="">All Statuses</option>
                @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
                <a href="{{ route('certificates.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                <a href="{{ route('certificates.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export XLSX</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Employee</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Certificate</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Type</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Expiry Date</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($certificates as $certificate)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $certificate->employee->full_name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $certificate->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $certificate->certificateType?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $certificate->expiry_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusStyles[$certificate->status()] }}">{{ $statusLabels[$certificate->status()] }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('employees.show', $certificate->employee) }}" class="text-slate-600 hover:underline">View Employee</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No certificates recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $certificates->links() }}
    </div>
</x-app-layout>
