{{-- Lock shared by Technician, Supervisor and Spare slots. An IN lock is off the board, so only its tag (no shackle) is shown
     and the tag's band carries the IN status. --}}
@php
    $in = $in ?? false;
    $employee = $employee ?? null;
    if ($employee) {
        $name = \Illuminate\Support\Str::of($employee->preferred_name ?: $employee->full_name)->squish();
        $initials = $name->explode(' ')->map(fn ($part) => substr($part, 0, 1))->take(2)->implode('');
    }
@endphp
<span @class(['lototo-lock-content', 'lototo-lock-content--in' => $in])>
    @unless ($in)
        <span class="lototo-lock-shackle" aria-hidden="true"></span>
    @endunless
    <span class="lototo-lock-body">
        @if ($in)
            <span class="lototo-lock-danger lototo-lock-danger--in"><span>IN</span>@if ($employee && filled($code ?? null)) · {{ $code }}@endif</span>
        @else
            <span class="lototo-lock-danger">{{ $band ?? 'DANGER' }}</span>
        @endif
        <span class="lototo-lock-plate">
            @if ($employee)
                @if ($employee->photo_path)
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->photo_path) }}"
                        alt="{{ $employee->full_name }}"
                        class="lototo-photo"
                    >
                @else
                    <span class="lototo-photo lototo-photo--initials">{{ $initials }}</span>
                @endif
                <span class="lototo-number">{{ $employee->lototo_number }}</span>
                <span class="lototo-name" title="{{ $employee->full_name }}">{{ $name }}</span>
            @else
                <span class="lototo-spare-lock-code-number">{{ $code ?? '' }}</span>
            @endif
        </span>
    </span>
</span>
