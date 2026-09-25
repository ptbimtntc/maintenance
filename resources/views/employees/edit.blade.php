<x-app-layout>
    <x-slot name="header">Edit Employee</x-slot>

    <div class="max-w-4xl rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
        <form method="POST" action="{{ route('employees.update', $employee) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @include('employees._form')

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('employees.show', $employee) }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">Cancel</a>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Save Changes</button>
            </div>
        </form>
    </div>
</x-app-layout>
