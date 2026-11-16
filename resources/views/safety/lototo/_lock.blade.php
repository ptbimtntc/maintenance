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
            {{-- The place of work comes from the open activity (never an older one). --}}
            @if (filled($location ?? null))
                <span class="lototo-lock-location" title="{{ $location }}">
                    <svg class="lototo-location-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" fill="#dc2626" stroke="#facc15" stroke-linejoin="round" stroke-width="2" />
                        <circle cx="12" cy="10" r="3" fill="#facc15" />
                    </svg>
                    <span class="lototo-lock-location__text">{{ $location }}</span>
                </span>
            @endif
        @else
            {{-- The Technician band keeps its DANGER tag; other bands name themselves (supervisor, spare). --}}
            <span class="lototo-lock-danger">{{ ($band ?? 'technician') === 'technician' ? 'DANGER' : $band }}</span>
        @endif
        <span class="lototo-lock-plate">
            @if ($employee)
                @if ($photoUrl = \App\Support\LototoEmployeePhoto::url($employee))
                    <img
                        src="{{ $photoUrl }}"
                        alt="{{ $employee->full_name }}"
                        data-initials="{{ $initials }}"
                        loading="lazy"
                        decoding="async"
                        class="lototo-photo lototo-photo-img"
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
