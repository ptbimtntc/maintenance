{{-- Photo input of an employee form: shows the current photo (or initials) and previews a newly chosen file before saving. --}}
@props(['employee' => null, 'signed' => false])
<div x-data="{ preview: null }" class="mt-1">
    <div class="mb-2 flex items-center gap-3">
        <div x-show="!preview">
            @if ($employee)
                <x-employee-avatar :employee="$employee" :signed="$signed"
                    img-class="h-16 w-16 shrink-0 rounded-full object-cover"
                    fallback-class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-lg font-semibold text-neutral-500" />
            @else
                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-neutral-400" aria-hidden="true">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 21a8 8 0 0 1 16 0"/></svg>
                </span>
            @endif
        </div>
        <img x-show="preview" x-cloak :src="preview" alt="Preview" class="h-16 w-16 shrink-0 rounded-full object-cover">
        <span x-show="preview" x-cloak class="text-xs text-neutral-500">Preview foto baru (belum disimpan)</span>
    </div>
    <input id="photo" type="file" name="photo" accept="image/*"
           @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
           class="block w-full text-sm text-neutral-700 file:mr-3 file:rounded-md file:border-0 file:bg-brand-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white shadow-sm transition hover:file:bg-brand-700" />
</div>
