@php
$isEdit = $record !== null;
$old = fn ($field, $default = null) => old($field, $record?->$field ?? $default);
$parentField = $config['parent']['field'] ?? null;
@endphp

<x-app-layout>
    <x-slot name="header">{{ $isEdit ? 'Edit' : 'Add' }} {{ $config['singular'] }}</x-slot>

    <div class="max-w-2xl rounded-lg border border-gray-200 bg-white p-6">
        <form method="POST" action="{{ $isEdit ? route('organization.update', [$type, $record->id]) : route('organization.store', $type) }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="space-y-4">
                <div>
                    <x-input-label for="{{ $config['name_field'] }}" :value="ucfirst(str_replace('_', ' ', $config['name_field']))" />
                    <x-text-input id="{{ $config['name_field'] }}" name="{{ $config['name_field'] }}" class="mt-1 block w-full" value="{{ $old($config['name_field']) }}" required />
                    <x-input-error :messages="$errors->get($config['name_field'])" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="code" value="Code (optional)" />
                    <x-text-input id="code" name="code" class="mt-1 block w-full" value="{{ $old('code') }}" />
                    <x-input-error :messages="$errors->get('code')" class="mt-1" />
                </div>

                @if ($parentField)
                    <div>
                        <x-input-label for="{{ $parentField }}" :value="$config['parent']['label']" />
                        <select id="{{ $parentField }}" name="{{ $parentField }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach ($parentOptions as $option)
                                <option value="{{ $option->id }}" @selected($old($parentField) == $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get($parentField)" class="mt-1" />
                    </div>
                @endif

                @foreach ($config['extra_fields'] ?? [] as $field => $meta)
                    <div>
                        @if ($meta['type'] === 'boolean')
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="{{ $field }}" value="1" @checked($old($field, false)) class="rounded border-gray-300" />
                                <span class="text-sm text-gray-700">{{ $meta['label'] }}</span>
                            </label>
                        @elseif ($meta['type'] === 'select')
                            <x-input-label for="{{ $field }}" :value="$meta['label']" />
                            <select id="{{ $field }}" name="{{ $field }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                @foreach ($meta['options'] as $value => $label)
                                    <option value="{{ $value }}" @selected($old($field) == $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <x-input-label for="{{ $field }}" :value="$meta['label']" />
                            <x-text-input id="{{ $field }}" type="{{ $meta['type'] === 'number' ? 'number' : ($meta['type'] === 'time' ? 'time' : 'text') }}" name="{{ $field }}" class="mt-1 block w-full" value="{{ $old($field) }}" />
                        @endif
                        <x-input-error :messages="$errors->get($field)" class="mt-1" />
                    </div>
                @endforeach

                <div>
                    <x-input-label for="description" value="Description (optional)" />
                    <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $old('description') }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" @checked($old('is_active', true)) class="rounded border-gray-300" />
                    <span class="text-sm text-gray-700">Active</span>
                </label>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('organization.index', $type) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save</button>
            </div>
        </form>
    </div>
</x-app-layout>
