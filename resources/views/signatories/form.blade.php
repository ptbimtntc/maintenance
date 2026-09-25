@php
$isEdit = $signatory !== null;
$old = fn ($field, $default = null) => old($field, $signatory?->$field ?? $default);
@endphp

<x-app-layout>
    <x-slot name="header">{{ $isEdit ? 'Edit Signatory' : 'Add Signatory' }}</x-slot>

    <div class="max-w-xl rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
        <form method="POST" action="{{ $isEdit ? route('signatories.update', $signatory) : route('signatories.store') }}" enctype="multipart/form-data">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="space-y-4">
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ $old('name') }}" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="title" value="Title" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ $old('title') }}" placeholder="Maintenance Manager" />
                    <x-input-error :messages="$errors->get('title')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="signature" value="Signature Image" />
                    @if ($signatory?->signature_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($signatory->signature_path) }}" alt="Signature" class="mt-1 h-14 border border-neutral-200 bg-white p-1">
                    @endif
                    <input id="signature" type="file" name="signature" accept="image/png,image/jpeg" class="mt-1 block w-full text-sm text-neutral-600 file:mr-3 file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm" />
                    <p class="mt-1 text-xs text-neutral-400">PNG with a transparent background works best.</p>
                    <x-input-error :messages="$errors->get('signature')" class="mt-1" />
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" @checked($old('is_active', true)) class="rounded border-neutral-300" />
                    <span class="text-sm text-neutral-700">Active (selectable on a Training Program)</span>
                </label>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('signatories.index') }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</a>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Save</button>
            </div>
        </form>
    </div>
</x-app-layout>
