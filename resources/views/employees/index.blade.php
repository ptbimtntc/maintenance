<x-app-layout>
    <x-slot name="header">Employees</x-slot>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">{{ $employees->total() }} employee(s) found.</p>

            @can('create', \App\Models\Employee::class)
                <a href="{{ route('employees.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Add Employee
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('employees.index') }}" class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, number, email..."
                   class="col-span-1 rounded-md border-gray-300 text-sm sm:col-span-2 lg:col-span-2" />

            <select name="department_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Departments</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(($filters['department_id'] ?? null) == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>

            <select name="maintenance_area_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Areas</option>
                @foreach ($maintenanceAreas as $area)
                    <option value="{{ $area->id }}" @selected(($filters['maintenance_area_id'] ?? null) == $area->id)>{{ $area->name }}</option>
                @endforeach
            </select>

            <select name="maintenance_team_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Teams</option>
                @foreach ($maintenanceTeams as $team)
                    <option value="{{ $team->id }}" @selected(($filters['maintenance_team_id'] ?? null) == $team->id)>{{ $team->name }}</option>
                @endforeach
            </select>

            <select name="employment_status_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Statuses</option>
                @foreach ($employmentStatuses as $status)
                    <option value="{{ $status->id }}" @selected(($filters['employment_status_id'] ?? null) == $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>

            <div class="col-span-1 flex gap-2 sm:col-span-2 lg:col-span-6">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
                <a href="{{ route('employees.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                <a href="{{ route('employees.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Employee</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Position</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Area / Team</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($employees as $employee)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('employees.show', $employee) }}" class="font-medium text-slate-900 hover:underline">{{ $employee->full_name }}</a>
                                <p class="text-xs text-gray-500">{{ $employee->employee_number }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $employee->position?->title ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $employee->maintenanceArea?->name ?? '—' }}
                                @if ($employee->maintenanceTeam)
                                    <span class="text-gray-400">/ {{ $employee->maintenanceTeam->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($employee->employmentStatus)
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                        'bg-green-100 text-green-800' => $employee->employmentStatus->counts_as_active,
                                        'bg-gray-100 text-gray-600' => ! $employee->employmentStatus->counts_as_active,
                                    ])>
                                        {{ $employee->employmentStatus->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('employees.show', $employee) }}" class="text-slate-600 hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                                No employees match your filters yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $employees->links() }}
    </div>
</x-app-layout>
