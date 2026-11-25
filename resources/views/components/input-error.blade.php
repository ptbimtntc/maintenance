@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'form-error text-sm text-danger-600 space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
