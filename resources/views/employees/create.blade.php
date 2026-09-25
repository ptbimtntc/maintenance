<x-app-layout>
    <x-slot name="header">Add Employee</x-slot>

    <div class="max-w-4xl rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
        <form method="POST" action="{{ route('employees.store') }}" enctype="multipart/form-data">
            @csrf

            @include('employees._form')

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('employees.index') }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</a>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Save Employee</button>
            </div>
        </form>
    </div>
</x-app-layout>
