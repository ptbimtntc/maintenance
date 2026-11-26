<x-app-layout>
    <x-slot name="header">Employees</x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                {{-- Gallery (default) / Table switch; keeps the current filters. --}}
                @php
                    $viewQuery = fn ($target) => array_merge($filters, $target === 'table' ? ['view' => 'table'] : []);
                @endphp
                <div class="inline-flex rounded-lg border border-neutral-200 bg-white p-0.5 shadow-sm" role="group" aria-label="Choose view">
                    @foreach (['gallery' => ['Gallery', 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z'], 'table' => ['Table', 'M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M13.125 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5']] as $key => [$label, $icon])
                        <a href="{{ route('employees.index', $viewQuery($key)) }}"
                           @if ($view === $key) aria-current="page" @endif
                           @class([
                               'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition',
                               'bg-neutral-900 text-white shadow-sm' => $view === $key,
                               'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' => $view !== $key,
                           ])>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <p class="text-sm text-neutral-500">{{ $employees->total() }} employee(s) found.</p>
                <x-read-only-badge menu="employees" />
            </div>

            @can('create', \App\Models\Employee::class)
                <div class="flex items-center gap-2">
                    <x-button variant="secondary" href="{{ route('employees.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        Export XLSX
                    </x-button>

                    <x-button variant="secondary" type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'import-employees')">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-4.5L12 16.5m0 0 4.5-4.5M12 16.5V3" />
                        </svg>
                        Import XLSX
                    </x-button>

                    <div class="h-6 w-px bg-neutral-200"></div>

                    <x-button href="{{ route('employees.create') }}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Employee
                    </x-button>
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
            $filterActiveCount = collect($filters)->except('applied')->filter(fn ($value) => filled($value))->count();
        @endphp

        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" action="{{ route('employees.index') }}" class="grid grid-cols-1 gap-2.5 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
                <input type="hidden" name="applied" value="1">
                @if ($view === 'table')
                    <input type="hidden" name="view" value="table">
                @endif

                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search NIK or name..."
                       class="col-span-1 rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 sm:col-span-2 lg:col-span-2 {{ filled($filters['search'] ?? null) ? $activeFieldClass : $defaultFieldClass }}" />

                <x-multi-select-filter name="business_unit_id" label="All Business Units"
                    :options="$businessUnits->pluck('name', 'id')" :selected="$filters['business_unit_id'] ?? []" />

                <x-multi-select-filter name="employment_type_id" label="All Employment Types"
                    :options="$employmentTypes->pluck('name', 'id')" :selected="$filters['employment_type_id'] ?? []" />

                <x-multi-select-filter name="employment_source_id" label="All Employment Sources"
                    :options="$employmentSources->pluck('name', 'id')" :selected="$filters['employment_source_id'] ?? []" />

                <x-multi-select-filter name="employment_status_id" label="All Statuses"
                    :options="$employmentStatuses->pluck('name', 'id')" :selected="$filters['employment_status_id'] ?? []" />

                <x-multi-select-filter name="supervisor_id" label="All Supervisors"
                    :options="$supervisors->mapWithKeys(fn ($s) => [$s->id => $s->full_name.' ('.$s->employee_number.')'])" :selected="$filters['supervisor_id'] ?? []" />

                <x-multi-select-filter name="shift_id" label="All Shifts"
                    :options="$shifts->pluck('name', 'id')" :selected="$filters['shift_id'] ?? []" />

                <div class="col-span-1 flex gap-2 sm:col-span-2 lg:col-span-6">
                    <x-button size="sm" type="submit">Filter</x-button>
                    <x-button variant="secondary" size="sm" href="{{ route('employees.index', $view === 'table' ? ['view' => 'table'] : []) }}">Reset</x-button>
                </div>
            </form>
        </x-filter-panel>

        @if ($view === 'gallery')
            <x-people-gallery :employees="$galleryEmployees">
                @if ($employees->total() > $galleryEmployees->count())
                    <p class="text-center text-xs text-neutral-500">
                        Showing the first {{ $galleryLimit }} of {{ $employees->total() }} employees. Use the filters or the Table view to see the rest.
                    </p>
                @endif
            </x-people-gallery>
        @else
        @can('create', \App\Models\Employee::class)
            <form id="bulk-delete-form" method="POST" action="{{ route('employees.bulk-destroy') }}"
                  data-confirm="Delete the selected employee(s)? This cannot be undone."
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
                                <x-employee-avatar :employee="$employee" />
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
                    <x-button variant="danger" type="submit" class="mt-3" ::disabled="checkedCount === 0">
                        Delete Selected (<span x-text="checkedCount"></span>)
                    </x-button>
                </div>
            </form>
        @endcan

        {{ $employees->links() }}
        @endif
    </div>
    @can('create', \App\Models\Employee::class)
        <x-modal name="import-employees" maxWidth="lg">
            <form method="POST" action="{{ route('employees.import') }}" enctype="multipart/form-data"
                  x-data="{ selected: @js($importColumns), all: @js($importColumns) }" class="space-y-4 p-6">
                @csrf
                <div>
                    <h2 class="text-base font-semibold text-neutral-900">Import Employees</h2>
                    <p class="mt-1 text-sm text-neutral-500">
                        Pilih kolom yang ingin ditambahkan / diperbarui. Kolom lain di file akan diabaikan.
                        Employee Number selalu dipakai untuk mencocokkan data. Sel kosong tidak mengubah data.
                        Untuk menambah karyawan baru, kolom Full Name harus dipilih.
                    </p>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-neutral-700">Kolom yang diimport</span>
                        <span class="flex gap-3 text-xs">
                            <button type="button" class="text-brand-600 hover:underline" x-on:click="selected = [...all]">Pilih semua</button>
                            <button type="button" class="text-brand-600 hover:underline" x-on:click="selected = []">Kosongkan</button>
                        </span>
                    </div>
                    <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                        @foreach ($importColumns as $column)
                            <label class="flex items-center gap-2 text-sm text-neutral-700">
                                <input type="checkbox" name="columns[]" value="{{ $column }}" x-model="selected"
                                       class="rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                                {{ $column }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label for="import-file" class="mb-1 block text-sm font-medium text-neutral-700">File .xlsx</label>
                    <input id="import-file" type="file" name="file" accept=".xlsx" required
                           class="block w-full text-sm text-neutral-700 file:mr-3 file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-neutral-200">
                </div>

                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'import-employees')">Batal</x-button>
                    <x-button type="submit" x-bind:disabled="selected.length === 0">Import</x-button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
