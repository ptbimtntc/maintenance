@php
$parentRelation = isset($config['parent']) ? \Illuminate\Support\Str::camel(class_basename($config['parent']['model'])) : null;
@endphp

<x-app-layout>
    <x-slot name="header">{{ $config['label'] }}</x-slot>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <a href="{{ route('organization.landing') }}" class="text-sm text-neutral-600 hover:underline">&larr; Back to Organization</a>
            <div class="flex items-center gap-2">
                <a href="{{ route('organization.export', $type) }}"
                   class="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    Export XLSX
                </a>

                @if (auth()->user()->canEditMenu(\App\Enums\MenuKey::Organization))
                    <form method="POST" action="{{ route('organization.import', $type) }}" enctype="multipart/form-data">
                        @csrf
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-4.5L12 16.5m0 0 4.5-4.5M12 16.5V3" />
                            </svg>
                            Import XLSX
                            <input type="file" name="file" accept=".xlsx" class="hidden" onchange="this.form.requestSubmit()">
                        </label>
                    </form>

                    <div class="h-6 w-px bg-neutral-200"></div>
                @endif

                <a href="{{ route('organization.create', $type) }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    Add {{ $config['singular'] }}
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        @if (session('import_errors'))
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('import_errors') }}</div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Name</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Code</th>
                        @if ($parentRelation)
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">{{ $config['parent']['label'] }}</th>
                        @endif
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Status</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($records as $record)
                        <tr class="hover:bg-neutral-50">
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ $record->{$config['name_field']} }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ $record->code ?? '—' }}</td>
                            @if ($parentRelation)
                                <td class="px-3 py-2 text-neutral-600">{{ $record->$parentRelation?->name ?? '—' }}</td>
                            @endif
                            <td class="px-3 py-2">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                    'bg-green-100 text-green-800' => $record->is_active,
                                    'bg-neutral-100 text-neutral-600' => ! $record->is_active,
                                ])>
                                    {{ $record->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('organization.edit', [$type, $record->id]) }}" class="font-medium text-accent-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('organization.destroy', [$type, $record->id]) }}" class="inline"
                                      onsubmit="return confirm('Delete this {{ strtolower($config['singular']) }}? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ml-3 text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-neutral-500">No {{ strtolower($config['label']) }} yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $records->links() }}
    </div>
</x-app-layout>
