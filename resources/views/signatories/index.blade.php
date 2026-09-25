<x-app-layout>
    <x-slot name="header">Signatories</x-slot>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <p class="text-sm text-neutral-500">Reusable trainer / authorizer names and signature images, used when setting up a Training Program's certificate.</p>
            @can(\App\Enums\PermissionName::ManageCertificates->value)
                <a href="{{ route('signatories.create') }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Add Signatory</a>
            @endcan
        </div>

        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif

        <div class="max-h-[70vh] overflow-auto rounded-lg border border-neutral-200 bg-white shadow-md">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="border-b-2 border-brand-500 bg-brand-50">
                    <tr>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Signature</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Name</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Title</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Status</th>
                        <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($signatories as $signatory)
                        <tr class="hover:bg-neutral-50">
                            <td class="px-3 py-2">
                                @if ($signatory->signature_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($signatory->signature_path) }}" alt="{{ $signatory->name }}" class="h-10 max-w-[120px] object-contain">
                                @else
                                    <span class="text-neutral-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ $signatory->name }}</td>
                            <td class="px-3 py-2 text-neutral-600">{{ $signatory->title ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                    'bg-success-100 text-success-800' => $signatory->is_active,
                                    'bg-neutral-100 text-neutral-600' => ! $signatory->is_active,
                                ])>
                                    {{ $signatory->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                @can(\App\Enums\PermissionName::ManageCertificates->value)
                                    <a href="{{ route('signatories.edit', $signatory) }}" class="font-medium text-accent-600 hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('signatories.destroy', $signatory) }}" class="inline"
                                          onsubmit="return confirm('Delete this signatory? Training programs using it keep their existing signature image.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ml-3 text-danger-600 hover:underline">Delete</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-neutral-500">No signatories yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $signatories->links() }}
    </div>
</x-app-layout>
