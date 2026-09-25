<x-app-layout>
    <x-slot name="header">User Management</x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-neutral-600">
                    Manage which people can access the app, what role they hold, and which menus they may edit beyond the default (read-only, except Job Descriptions).
                </p>
                <p class="mt-1 text-sm text-neutral-500">{{ $users->total() }} user(s) found.</p>
            </div>
            <a href="{{ route('admin.users.create') }}" class="shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Add User</a>
        </div>

        @php
            $activeFieldClass = 'border-brand-300 bg-brand-50 text-brand-800 font-medium ring-1 ring-brand-200 shadow-sm';
            $defaultFieldClass = 'border-neutral-300 hover:border-accent-400';
            $filterActiveCount = collect($filters)->filter(fn ($value) => filled($value))->count();
        @endphp

        <x-filter-panel :active-count="$filterActiveCount">
            <form method="GET" action="{{ route('admin.users.index') }}" class="grid grid-cols-1 gap-2.5 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email..."
                       class="col-span-1 rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 sm:col-span-2 {{ filled($filters['search'] ?? null) ? $activeFieldClass : $defaultFieldClass }}" />

                <select name="role" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['role'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(($filters['role'] ?? null) == $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>

                <select name="department_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['department_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(($filters['department_id'] ?? null) == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>

                <select name="position_id" class="rounded-md text-sm py-1.5 transition focus:border-brand-500 focus:ring-brand-500 {{ filled($filters['position_id'] ?? null) ? $activeFieldClass : $defaultFieldClass }}">
                    <option value="">All Positions</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->id }}" @selected(($filters['position_id'] ?? null) == $position->id)>{{ $position->title }}</option>
                    @endforeach
                </select>

                <div class="col-span-1 flex gap-2 sm:col-span-2 lg:col-span-4">
                    <button type="submit" class="rounded-md bg-brand-600 px-3.5 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Filter</button>
                    <a href="{{ route('admin.users.index') }}" class="rounded-md border border-neutral-300 px-3.5 py-1.5 text-sm font-medium text-neutral-600 transition shadow-sm hover:border-accent-400 hover:bg-accent-50 hover:text-accent-700">Reset</a>
                </div>
            </form>
        </x-filter-panel>

        <div class="max-h-[70vh] overflow-auto rounded-lg border border-neutral-200 bg-white shadow-md">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Name</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Email</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Position</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Role</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($users as $user)
                        <tr>
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ $user->name }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ $user->email }}</td>
                            <td class="px-3 py-2 text-neutral-600">
                                {{ $user->employee?->position?->title ?? '—' }}
                                @if ($user->employee?->department)
                                    <span class="text-xs text-neutral-400">({{ $user->employee->department->name }})</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @foreach ($user->roles as $role)
                                    <span class="inline-flex rounded-full bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-700">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('admin.users.edit', $user) }}" class="text-neutral-600 hover:underline">Manage</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </div>
</x-app-layout>
