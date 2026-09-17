<x-app-layout>
    <x-slot name="header">Edit Employee</x-slot>

    <div class="max-w-4xl rounded-lg border border-gray-200 bg-white p-6">
        <form method="POST" action="{{ route('employees.update', $employee) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @include('employees._form')

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('employees.show', $employee) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save Changes</button>
            </div>
        </form>
    </div>
</x-app-layout>
