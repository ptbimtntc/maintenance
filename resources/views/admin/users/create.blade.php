<x-app-layout>
    <x-slot name="header">Add User</x-slot>

    <div class="max-w-xl space-y-6">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6 rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
            @csrf

            <div>
                <x-input-label for="name" value="Name" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email') }}" required />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="password" value="Initial Password" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                <p class="mt-1 text-xs text-neutral-500">Share this with the user directly - they can change it from their own Profile page afterwards.</p>
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="password_confirmation" value="Confirm Password" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required />
            </div>

            <div>
                <x-input-label for="role" value="Role" />
                <select id="role" name="role" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="employee_id" value="Link to Employee Record (optional)" />
                <select id="employee_id" name="employee_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                    <option value="">— Not linked yet —</option>
                    @foreach ($unlinkedEmployees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>
                            {{ $employee->full_name }} ({{ $employee->employee_number }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-neutral-500">
                    Only employees not already linked to a login are listed. This gives the new account its position/department context - you can also do this later from the Employee form's "Linked User" field.
                </p>
                <x-input-error :messages="$errors->get('employee_id')" class="mt-1" />
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.users.index') }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">Cancel</a>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Create User</button>
            </div>
        </form>
    </div>
</x-app-layout>
