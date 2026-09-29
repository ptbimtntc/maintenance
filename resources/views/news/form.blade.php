@php
$isEdit = $news !== null;
$old = fn ($field, $default = null) => old($field, $news?->$field ?? $default);
@endphp

<x-app-layout>
    <x-slot name="header">{{ $isEdit ? 'Edit News' : 'Add News' }}</x-slot>

    <div class="max-w-2xl rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
        <form method="POST" action="{{ $isEdit ? route('news.update', $news) : route('news.store') }}" enctype="multipart/form-data">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="space-y-4">
                <div>
                    <x-input-label for="title" value="Title" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ $old('title') }}" required />
                    <x-input-error :messages="$errors->get('title')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="body" value="Content" />
                    <input id="body" type="hidden" name="body" value="{{ $old('body') }}">
                    <trix-editor input="body" class="mt-1 block w-full rounded-md border border-neutral-300 text-sm" style="min-height: 10rem;"></trix-editor>
                    <x-input-error :messages="$errors->get('body')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="template" value="Template" />
                    <select id="template" name="template" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                        <option value="standard" @selected($old('template', 'standard') === 'standard')>Standard (image on top, text below)</option>
                        <option value="highlight" @selected($old('template') === 'highlight')>Highlight (full-width image with text overlay)</option>
                    </select>
                    <x-input-error :messages="$errors->get('template')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="image" value="Image (optional)" />
                    @if ($news?->image_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($news->image_path) }}" alt="Current image" class="mt-1 mb-2 h-32 w-full max-w-sm rounded-md object-cover">
                    @endif
                    <input id="image" type="file" name="image" accept="image/*" class="mt-1 block w-full text-sm text-neutral-700 file:mr-3 file:rounded-md file:border-0 file:bg-brand-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white shadow-sm transition hover:file:bg-brand-700" />
                    <x-input-error :messages="$errors->get('image')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="expires_at" value="Show Until (optional)" />
                    <input id="expires_at" type="date" name="expires_at" class="mt-1 block w-full rounded-md border-neutral-300 text-sm" value="{{ $old('expires_at') ? \Illuminate\Support\Carbon::parse($old('expires_at'))->format('Y-m-d') : '' }}">
                    <p class="mt-1 text-xs text-neutral-400">After this date, the news is automatically hidden from the dashboard (it stays here and can still be edited). Leave blank to show indefinitely.</p>
                    <x-input-error :messages="$errors->get('expires_at')" class="mt-1" />
                </div>

                <div class="flex items-center gap-2">
                    <input id="is_published" type="checkbox" name="is_published" value="1" class="rounded border-neutral-300" @checked($old('is_published', true))>
                    <x-input-label for="is_published" value="Published (visible on dashboard)" />
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
                    {{ $isEdit ? 'Save Changes' : 'Publish News' }}
                </button>
                <a href="{{ route('news.index') }}" class="text-sm text-neutral-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
