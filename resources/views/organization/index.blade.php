@php
$parentRelation = isset($config['parent']) ? \Illuminate\Support\Str::camel(class_basename($config['parent']['model'])) : null;
@endphp

<x-app-layout>
    <x-slot name="header">{{ $config['label'] }}</x-slot>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <a href="{{ route('organization.landing') }}" class="text-sm text-neutral-600 hover:underline">&larr; Back to Organization</a>
            <a href="{{ route('organization.create', $type) }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                Add {{ $config['singular'] }}
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Name</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Code</th>
                        @if ($parentRelation)
                            <th class="px-4 py-3 text-left font-medium text-neutral-500">{{ $config['parent']['label'] }}</th>
                        @endif
                        <th class="px-4 py-3 text-left font-medium text-neutral-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($records as $record)
                        <tr>
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $record->{$config['name_field']} }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $record->code ?? '—' }}</td>
                            @if ($parentRelation)
                                <td class="px-4 py-3 text-neutral-600">{{ $record->$parentRelation?->name ?? '—' }}</td>
                            @endif
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                    'bg-green-100 text-green-800' => $record->is_active,
                                    'bg-neutral-100 text-neutral-600' => ! $record->is_active,
                                ])>
                                    {{ $record->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('organization.edit', [$type, $record->id]) }}" class="text-neutral-600 hover:underline">Edit</a>
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
                            <td colspan="5" class="px-4 py-10 text-center text-neutral-500">No {{ strtolower($config['label']) }} yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $records->links() }}
    </div>
</x-app-layout>
