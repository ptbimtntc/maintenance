<x-app-layout>
    <x-slot name="header">User Management</x-slot>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <p class="text-sm text-neutral-600">
                Manage which people can access the app, what role they hold, and which menus they may edit beyond the default (read-only, except Job Descriptions).
            </p>
            <a href="{{ route('admin.users.create') }}" class="shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Add User</a>
        </div>

        <div class="max-h-[70vh] overflow-auto rounded-lg border border-neutral-200 bg-white">
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
