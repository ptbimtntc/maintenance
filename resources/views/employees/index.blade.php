<x-app-layout>
    <x-slot name="header">Employees</x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <p class="text-sm text-neutral-500">{{ $employees->total() }} employee(s) found.</p>
                <x-read-only-badge menu="employees" />
            </div>

            @can('create', \App\Models\Employee::class)
                <div class="flex items-center gap-2">
                    <a href="{{ route('employees.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}"
                       class="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        Export XLSX
                    </a>

                    <form method="POST" action="{{ route('employees.import') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-4.5L12 16.5m0 0 4.5-4.5M12 16.5V3" />
                            </svg>
                            Import XLSX
                            <input type="file" name="file" accept=".xlsx" class="hidden" onchange="this.form.requestSubmit()">
                        </label>
                    </form>

                    <div class="h-6 w-px bg-neutral-200"></div>

                    <a href="{{ route('employees.create') }}"
                       class="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-3.5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Employee
                    </a>
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
            <form method="GET" action="{{ route('employees.index') }}" class="grid grid-cols-1 gap-2.5 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search NIK or name..."
                       class="col-span-1 rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 sm:col-span-2 lg:col-span-2 {{ filled($filters['search'] ?? null) ? $activeFieldClass : $defaultFieldClass }}" />

                <select name="business_unit_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['business_unit_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Business Units</option>
                    @foreach ($businessUnits as $businessUnit)
                        <option value="{{ $businessUnit->id }}" @selected(($filters['business_unit_id'] ?? null) == $businessUnit->id)>{{ $businessUnit->name }}</option>
                    @endforeach
                </select>

                <select name="employment_type_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['employment_type_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Employment Types</option>
                    @foreach ($employmentTypes as $type)
                        <option value="{{ $type->id }}" @selected(($filters['employment_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>

                <select name="employment_source_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['employment_source_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Employment Sources</option>
                    @foreach ($employmentSources as $source)
                        <option value="{{ $source->id }}" @selected(($filters['employment_source_id'] ?? null) == $source->id)>{{ $source->name }}</option>
                    @endforeach
                </select>

                <select name="employment_status_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['employment_status_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Statuses</option>
                    @foreach ($employmentStatuses as $status)
                        <option value="{{ $status->id }}" @selected(($filters['employment_status_id'] ?? null) == $status->id)>{{ $status->name }}</option>
                    @endforeach
                </select>

                <select name="supervisor_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['supervisor_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Supervisors</option>
                    @foreach ($supervisors as $supervisor)
                        <option value="{{ $supervisor->id }}" @selected(($filters['supervisor_id'] ?? null) == $supervisor->id)>{{ $supervisor->full_name }} ({{ $supervisor->employee_number }})</option>
                    @endforeach
                </select>

                <select name="shift_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['shift_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Shifts</option>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}" @selected(($filters['shift_id'] ?? null) == $shift->id)>{{ $shift->name }}</option>
                    @endforeach
                </select>

                <div class="col-span-1 flex gap-2 sm:col-span-2 lg:col-span-6">
                    <button type="submit" class="rounded-md bg-brand-600 px-3.5 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Filter</button>
                    <a href="{{ route('employees.index') }}" class="rounded-md border border-neutral-300 px-3.5 py-1.5 text-sm font-medium text-neutral-600 transition shadow-sm hover:border-accent-400 hover:bg-accent-50 hover:text-accent-700">Reset</a>
                </div>
            </form>
        </x-filter-panel>

        @can('create', \App\Models\Employee::class)
            <form id="bulk-delete-form" method="POST" action="{{ route('employees.bulk-destroy') }}"
                  onsubmit="return confirm('Delete the selected employee(s)? This cannot be undone.');"
                  x-data="{ checkedCount: 0 }">
                @csrf
        @endcan

        <div class="max-h-[70vh] overflow-auto rounded-lg border border-neutral-200 bg-white shadow-md">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        @can('create', \App\Models\Employee::class)
                            <th class="sticky top-0 z-10 bg-brand-50 w-10 px-3 py-2">
                                <input type="checkbox"
                                       class="rounded border-neutral-300"
                                       @change="
                                           const boxes = $el.closest('table').querySelectorAll('tbody input[type=checkbox]');
                                           boxes.forEach(box => box.checked = $el.checked);
                                           checkedCount = $el.checked ? boxes.length : 0;
                                       ">
                            </th>
                        @endcan
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Photo</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">NIK</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Employee Name</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Supervisor</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Position</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Skill Position</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Shift</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($employees as $employee)
                        <tr>
                            @can('create', \App\Models\Employee::class)
                                <td class="px-3 py-2">
                                    <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}"
                                           class="rounded border-neutral-300"
                                           @change="checkedCount += $el.checked ? 1 : -1">
                                </td>
                            @endcan
                            <td class="px-3 py-2">
                                @if ($employee->photo_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->photo_path) }}"
                                         alt="{{ $employee->full_name }}" class="h-10 w-10 rounded-full object-cover">
                                @else
                                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-100 text-xs font-semibold text-neutral-500">
                                        {{ \Illuminate\Support\Str::of($employee->full_name)->explode(' ')->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->take(2)->implode('') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <a href="{{ route('employees.show', $employee) }}" class="font-medium text-neutral-900 hover:underline">{{ $employee->employee_number }}</a>
                            </td>
                            <td class="px-3 py-2 text-neutral-700">{{ $employee->full_name }}</td>
                            <td class="px-3 py-2 text-neutral-700">{{ $employee->supervisor?->full_name ?? '—' }}</td>
                            <td class="px-3 py-2 text-neutral-700">{{ $employee->position?->title ?? '—' }}</td>
                            <td class="px-3 py-2 text-neutral-700">{{ $employee->skillPosition?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-neutral-700">{{ $employee->shift?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->can('create', \App\Models\Employee::class) ? 8 : 7 }}" class="px-4 py-10 text-center text-neutral-500">
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
                            class="mt-3 rounded-md bg-danger-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-danger-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="checkedCount === 0">
                        Delete Selected (<span x-text="checkedCount"></span>)
                    </button>
                </div>
            </form>
        @endcan

        {{ $employees->links() }}
    </div>
</x-app-layout>
