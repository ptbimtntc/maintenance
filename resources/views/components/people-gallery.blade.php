{{--
    Scroll-driven 3D stack of people photo cards (Employees gallery,
    Organization Chart team gallery). Engine: resources/js/employee-gallery.js;
    styles: resources/css/employee-gallery.css.

    Without JS, or with prefers-reduced-motion, the same markup is a plain
    responsive photo grid. Photos are in data-src and only requested when a
    card comes near the front of the stack (or, in the grid, lazily).

    Props:
      employees    collection of Employee (position / skillPosition loaded as needed)
      id           element id of the section
      label        aria-label of the section
      empty        message when there is nobody to show
      href         fn (Employee): ?string  - card link (null = not a link)
      subtitle     fn (Employee): ?string  - position line in the caption / hover
      teamSizes    id => direct-report count; shows a "Team n" badge on the card
      highlightIds ids to ring (search matches)
      stripe       show the workforce-category colour strip (BC / WCM / Intern)
--}}
@props([
    'employees',
    'id' => 'employee-gallery',
    'label' => 'Employee photo gallery',
    'empty' => 'No employees match your filters yet.',
    'href' => null,
    'subtitle' => null,
    'teamSizes' => collect(),
    'highlightIds' => collect(),
    'stripe' => false,
])
@php
    $href ??= fn ($employee) => route('employees.show', $employee);
    $subtitle ??= fn ($employee) => $employee->position?->title;
    $initialsOf = fn ($employee) => \Illuminate\Support\Str::of((string) $employee->full_name)->explode(' ')->filter()
        ->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->take(2)->implode('');
    $first = $employees->first();
@endphp

@if ($employees->isEmpty())
    <div class="rounded-2xl border border-neutral-200 bg-white px-4 py-16 text-center text-sm text-neutral-500 shadow-sm">
        {{ $empty }}
    </div>
@else
    <section id="{{ $id }}" data-people-gallery class="employee-gallery" style="--count: {{ $employees->count() }}" aria-label="{{ $label }}">
        <div class="employee-gallery__viewport">
            <div aria-hidden="true" class="motion-blob employee-gallery__blob employee-gallery__blob--orange"></div>
            <div aria-hidden="true" class="motion-blob motion-blob--slow employee-gallery__blob employee-gallery__blob--blue"></div>
            <div aria-hidden="true" class="employee-gallery__glow"></div>

            <div class="employee-gallery__stage" data-gallery-stage>
                @foreach ($employees as $employee)
                    @php
                        $photoUrl = \App\Support\LototoEmployeePhoto::url($employee, 'employees.photo');
                        $link = $href($employee);
                        $tag = $link ? 'a' : 'div';
                        $position = $subtitle($employee);
                        $teamSize = (int) $teamSizes->get($employee->id, 0);
                        $category = in_array($employee->workforce_category, ['BC', 'WCM', 'INTERN'], true) ? strtolower($employee->workforce_category) : null;
                    @endphp
                    <{{ $tag }} @if ($link) href="{{ $link }}" @else tabindex="0" @endif
                       @class(['employee-card', 'is-match' => $highlightIds->contains($employee->id)])
                       data-gallery-card
                       data-index="{{ $loop->index }}"
                       data-name="{{ $employee->full_name }}"
                       data-nik="{{ $employee->employee_number }}"
                       data-position="{{ $position }}"
                       aria-label="{{ $employee->full_name }} ({{ $employee->employee_number }}){{ $teamSize ? ', team of '.$teamSize : '' }}">
                        <span class="employee-card__inner">
                            <span class="employee-card__initials" aria-hidden="true">{{ $initialsOf($employee) }}</span>
                            @if ($photoUrl)
                                <img data-src="{{ $photoUrl }}" alt="" decoding="async" class="employee-card__photo"
                                     onload="this.classList.add('is-loaded')" onerror="this.remove()">
                            @endif
                            @if ($stripe && $category)
                                <span class="employee-card__stripe employee-card__stripe--{{ $category }}" aria-hidden="true"></span>
                            @endif
                            @if ($teamSize)
                                <span class="employee-card__badge" aria-hidden="true">
                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                    Team {{ $teamSize }}
                                </span>
                            @endif
                            <span class="employee-card__info">
                                <span class="employee-card__nik">{{ $employee->employee_number }}</span>
                                <span class="employee-card__name">{{ $employee->full_name }}</span>
                                @if ($position)
                                    <span class="employee-card__role">{{ $position }}</span>
                                @endif
                                @if ($link)
                                    <span class="employee-card__open" aria-hidden="true">Click again to open</span>
                                @endif
                            </span>
                        </span>
                    </{{ $tag }}>
                @endforeach
            </div>

            <div class="employee-gallery__caption" aria-live="polite">
                <div class="min-w-0">
                    <p class="employee-gallery__caption-name" data-gallery-name>{{ $first->full_name }}</p>
                    <p class="employee-gallery__caption-meta">
                        <span data-gallery-nik>{{ $first->employee_number }}</span>
                        <span data-gallery-position>{{ $subtitle($first) ? '· '.$subtitle($first) : '' }}</span>
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

    {{ $slot }}
@endif
