<x-app-layout>
    <x-slot name="header">
        <span>
            {{ __('Profile') }}
        </span>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (auth()->user()->hasRole(\App\Enums\RoleName::Guest->value))
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg text-sm text-neutral-600">
                    This is the shared read-only Guest account - there's no profile to edit.
                </div>
            @else
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                {{-- Face Login is temporarily hidden from the profile page; the
                     partial, routes and login-page button are left intact. --}}

            @endif
        </div>
    </div>
</x-app-layout>
