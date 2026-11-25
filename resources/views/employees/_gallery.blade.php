{{--
    Employees "Gallery" view: a 3D stack of photo cards that slides one card
    at a time as the page scrolls (resources/js/employee-gallery.js, styles in
    resources/css/employee-gallery.css).

    Without JS, or with prefers-reduced-motion, the same markup is a plain
    responsive photo grid. Photos are in data-src and only requested when a
    card comes near the front of the stack (or, in the grid, lazily).
--}}
{{-- Included with: $employees (collection), $total (all matches), $limit (cap). --}}

@if ($employees->isEmpty())
    <div class="rounded-2xl border border-neutral-200 bg-white px-4 py-16 text-center text-sm text-neutral-500 shadow-sm">
        No employees match your filters yet.
    </div>
@else
    <section id="employee-gallery" class="employee-gallery" style="--count: {{ $employees->count() }}" aria-label="Employee photo gallery">
        <div class="employee-gallery__viewport">
            <div aria-hidden="true" class="motion-blob employee-gallery__blob employee-gallery__blob--orange"></div>
            <div aria-hidden="true" class="motion-blob motion-blob--slow employee-gallery__blob employee-gallery__blob--blue"></div>
            <div aria-hidden="true" class="employee-gallery__glow"></div>

            <div class="employee-gallery__stage" data-gallery-stage>
                @foreach ($employees as $employee)
                    @php
                        $photoUrl = \App\Support\LototoEmployeePhoto::url($employee, 'employees.photo');
                        $initials = \Illuminate\Support\Str::of((string) $employee->full_name)->explode(' ')->filter()
                            ->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->take(2)->implode('');
                    @endphp
                    <a href="{{ route('employees.show', $employee) }}"
                       class="employee-card"
                       data-gallery-card
                       data-index="{{ $loop->index }}"
                       data-name="{{ $employee->full_name }}"
                       data-nik="{{ $employee->employee_number }}"
                       data-position="{{ $employee->position?->title }}"
                       aria-label="{{ $employee->full_name }} ({{ $employee->employee_number }})">
                        <span class="employee-card__inner">
                            <span class="employee-card__initials" aria-hidden="true">{{ $initials }}</span>
                            @if ($photoUrl)
                                <img data-src="{{ $photoUrl }}" alt="" decoding="async" class="employee-card__photo"
                                     onload="this.classList.add('is-loaded')" onerror="this.remove()">
                            @endif
                            <span class="employee-card__info">
                                <span class="employee-card__nik">{{ $employee->employee_number }}</span>
                                <span class="employee-card__name">{{ $employee->full_name }}</span>
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="employee-gallery__caption" aria-live="polite">
                <div class="min-w-0">
                    <p class="employee-gallery__caption-name" data-gallery-name>{{ $employees->first()->full_name }}</p>
                    <p class="employee-gallery__caption-meta">
                        <span data-gallery-nik>{{ $employees->first()->employee_number }}</span>
                        <span data-gallery-position>{{ $employees->first()->position?->title ? '· '.$employees->first()->position->title : '' }}</span>
                    </p>
                </div>
                <div class="employee-gallery__counter">
                    <span data-gallery-current>01</span><span class="opacity-40"> / {{ str_pad($employees->count(), 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="employee-gallery__bar"><span data-gallery-bar></span></span>
                </div>
            </div>

            <p class="employee-gallery__hint" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" /></svg>
                Scroll to browse
            </p>
        </div>
    </section>

    @if ($total > $employees->count())
        <p class="text-center text-xs text-neutral-500">
            Showing the first {{ $limit }} of {{ $total }} employees. Use the filters or the Table view to see the rest.
        </p>
    @endif
@endif
