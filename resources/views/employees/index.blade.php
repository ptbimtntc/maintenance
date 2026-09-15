<x-app-layout>
    <x-slot name="header">Employees</x-slot>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <p class="text-sm text-gray-500">{{ $employees->total() }} employee(s) found.</p>
                <x-read-only-badge menu="employees" />
            </div>

            @can('create', \App\Models\Employee::class)
                <a href="{{ route('employees.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Add Employee
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('employees.index') }}" class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search NIK or name..."
                   class="col-span-1 rounded-md border-gray-300 text-sm sm:col-span-2 lg:col-span-2" />

            <select name="business_unit_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Business Units</option>
                @foreach ($businessUnits as $businessUnit)
                    <option value="{{ $businessUnit->id }}" @selected(($filters['business_unit_id'] ?? null) == $businessUnit->id)>{{ $businessUnit->name }}</option>
                @endforeach
            </select>

            <select name="employment_type_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Employment Types</option>
                @foreach ($employmentTypes as $type)
                    <option value="{{ $type->id }}" @selected(($filters['employment_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>

            <select name="employment_source_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Employment Sources</option>
                @foreach ($employmentSources as $source)
                    <option value="{{ $source->id }}" @selected(($filters['employment_source_id'] ?? null) == $source->id)>{{ $source->name }}</option>
                @endforeach
            </select>

            <select name="employment_status_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Statuses</option>
                @foreach ($employmentStatuses as $status)
                    <option value="{{ $status->id }}" @selected(($filters['employment_status_id'] ?? null) == $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>

            <select name="supervisor_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Supervisors</option>
                @foreach ($supervisors as $supervisor)
                    <option value="{{ $supervisor->id }}" @selected(($filters['supervisor_id'] ?? null) == $supervisor->id)>{{ $supervisor->full_name }} ({{ $supervisor->employee_number }})</option>
                @endforeach
            </select>

            <select name="shift_id" class="rounded-md border-gray-300 text-sm">
                <option value="">All Shifts</option>
                @foreach ($shifts as $shift)
                    <option value="{{ $shift->id }}" @selected(($filters['shift_id'] ?? null) == $shift->id)>{{ $shift->name }}</option>
                @endforeach
            </select>

            <div class="col-span-1 flex gap-2 sm:col-span-2 lg:col-span-6">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
                <a href="{{ route('employees.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                <a href="{{ route('employees.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
                <a href="{{ route('employees.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export XLSX</a>
            </div>
        </form>

        @can('create', \App\Models\Employee::class)
            <form id="bulk-delete-form" method="POST" action="{{ route('employees.bulk-destroy') }}"
                  onsubmit="return confirm('Delete the selected employee(s)? This cannot be undone.');"
                  x-data="{ checkedCount: 0 }">
                @csrf
        @endcan

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        @can('create', \App\Models\Employee::class)
                            <th class="w-10 px-4 py-3">
                                <input type="checkbox"
                                       class="rounded border-gray-300"
                                       @change="
                                           const boxes = $el.closest('table').querySelectorAll('tbody input[type=checkbox]');
                                           boxes.forEach(box => box.checked = $el.checked);
                                           checkedCount = $el.checked ? boxes.length : 0;
                                       ">
                            </th>
                        @endcan
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Photo</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">NIK</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Employee Name</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Supervisor</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Position</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Skill Position</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Shift</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($employees as $employee)
                        <tr>
                            @can('create', \App\Models\Employee::class)
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}"
                                           class="rounded border-gray-300"
                                           @change="checkedCount += $el.checked ? 1 : -1">
                                </td>
                            @endcan
                            <td class="px-4 py-3">
                                @if ($employee->photo_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->photo_path) }}"
                                         alt="{{ $employee->full_name }}" class="h-10 w-10 rounded-full object-cover">
                                @else
                                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-500">
                                        {{ \Illuminate\Support\Str::of($employee->full_name)->explode(' ')->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->take(2)->implode('') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $employee->employee_number }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('employees.show', $employee) }}" class="font-medium text-slate-900 hover:underline">{{ $employee->full_name }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $employee->supervisor?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $employee->position?->title ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $employee->skillPosition?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $employee->shift?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->can('create', \App\Models\Employee::class) ? 8 : 7 }}" class="px-4 py-10 text-center text-gray-500">
                                No employees match your filters yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @can('create', \App\Models\Employee::class)
                <div class="flex justify-end">
                    <button type="submit"
                            class="mt-3 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="checkedCount === 0">
                        Delete Selected (<span x-text="checkedCount"></span>)
                    </button>
                </div>
            </form>
        @endcan

        {{ $employees->links() }}
    </div>
</x-app-layout>
