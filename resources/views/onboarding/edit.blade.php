<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Complete Your Profile - Maintenance People Development System</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased">
    <div class="mx-auto flex min-h-screen max-w-xl flex-col items-center px-4 py-10 sm:py-16">
        <div class="mb-6 flex h-14 w-14 items-center justify-center rounded bg-brand-500 text-xs font-semibold text-white">
            LOGO
        </div>
        <h1 class="text-center text-lg font-semibold text-neutral-900">Maintenance People Development System</h1>
        <p class="text-center text-sm text-neutral-500">PT Bekaert Indonesia</p>

        <div class="mt-8 w-full rounded-lg border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
            @if (! $employee)
                <div class="text-center">
                    <svg class="mx-auto h-10 w-10 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h2 class="mt-3 text-base font-semibold text-neutral-900">Link not found</h2>
                    <p class="mt-1 text-sm text-neutral-500">This onboarding link doesn't exist. Please check the QR code or link, or contact HR / People Development for a new one.</p>
                </div>
            @elseif ($justCompleted ?? false)
                <div class="text-center">
                    <svg class="mx-auto h-10 w-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h2 class="mt-3 text-base font-semibold text-neutral-900">Thank you, {{ $employee->full_name }}!</h2>
                    <p class="mt-1 text-sm text-neutral-500">Your profile has been updated. You can close this page now.</p>
                </div>
            @elseif (! $valid)
                <div class="text-center">
                    <svg class="mx-auto h-10 w-10 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <h2 class="mt-3 text-base font-semibold text-neutral-900">
                        {{ $employee->profile_completed_at ? 'Already completed' : 'Link expired' }}
                    </h2>
                    <p class="mt-1 text-sm text-neutral-500">
                        @if ($employee->profile_completed_at)
                            {{ $employee->full_name }}'s profile was already completed on {{ $employee->profile_completed_at->format('d M Y') }}.
                        @else
                            This onboarding link has expired. Please ask HR / People Development to generate a new one from your employee profile.
                        @endif
                    </p>
                </div>
            @else
                <div class="mb-6">
                    <h2 class="text-base font-semibold text-neutral-900">Welcome, {{ $employee->full_name }}!</h2>
                    <p class="mt-1 text-sm text-neutral-500">
                        HR has created your employee record ({{ $employee->employee_number }}). Please complete the details below.
                    </p>
                </div>

                @if ($errors->any())
                    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <ul class="list-disc space-y-1 pl-4">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('onboarding.update', $employee->profile_completion_token) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="photo" value="Photo (optional)" />
                        <input id="photo" type="file" name="photo" accept="image/*" class="mt-1 block w-full text-sm text-neutral-700 file:mr-3 file:rounded-md file:border-0 file:bg-brand-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-700" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="preferred_name" value="Preferred Name (optional)" />
                            <x-text-input id="preferred_name" name="preferred_name" class="mt-1 block w-full" value="{{ old('preferred_name', $employee->preferred_name) }}" />
                        </div>

                        <div>
                            <x-input-label for="gender" value="Gender" />
                            <select id="gender" name="gender" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                                <option value="">Not specified</option>
                                <option value="Male" @selected(old('gender', $employee->gender) === 'Male')>Male</option>
                                <option value="Female" @selected(old('gender', $employee->gender) === 'Female')>Female</option>
                            </select>
                        </div>

                        <div>
                            <x-input-label for="date_of_birth" value="Date of Birth" />
                            <x-text-input id="date_of_birth" type="date" name="date_of_birth" class="mt-1 block w-full" value="{{ old('date_of_birth', $employee->date_of_birth?->format('Y-m-d')) }}" />
                        </div>

                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-text-input id="phone" name="phone" class="mt-1 block w-full" value="{{ old('phone', $employee->phone) }}" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" type="email" name="email" class="mt-1 block w-full" value="{{ old('email', $employee->email) }}" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="education" value="Education" />
                            <x-text-input id="education" name="education" class="mt-1 block w-full" value="{{ old('education', $employee->education) }}" placeholder="e.g. D3 Mechanical Engineering" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="technical_background" value="Technical Background" />
                            <x-text-input id="technical_background" name="technical_background" class="mt-1 block w-full" value="{{ old('technical_background', $employee->technical_background) }}" />
                        </div>

                        <div>
                            <x-input-label for="years_of_experience" value="Years of Experience" />
                            <x-text-input id="years_of_experience" type="number" min="0" max="60" name="years_of_experience" class="mt-1 block w-full" value="{{ old('years_of_experience', $employee->years_of_experience) }}" />
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                            Save My Profile
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <p class="mt-6 text-center text-xs text-neutral-400">Officially from PTBI Maintenance Academy, developed by <a href="mailto:fajar.sodiq@bekaert.com" class="underline hover:text-neutral-600">fajar.sodiq@bekaert.com</a></p>
    </div>
</body>
</html>
