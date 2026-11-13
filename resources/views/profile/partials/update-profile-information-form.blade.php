<section>
    <header>
        <h2 class="text-lg font-medium text-neutral-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-neutral-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6" enctype="multipart/form-data">
        @csrf
        @method('patch')

        <div x-data="{ preview: null }">
            <x-input-label for="photo" :value="__('Photo')" />
            <div class="mt-2 flex items-center gap-4">
                <template x-if="preview">
                    <img :src="preview" alt="" class="h-16 w-16 rounded-full object-cover">
                </template>
                <template x-if="! preview">
                    <div>
                        @if ($user->photoUrl())
                            <img src="{{ $user->photoUrl() }}" alt="{{ __('Current photo') }}" class="h-16 w-16 rounded-full object-cover">
                        @else
                            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-neutral-700 text-lg font-semibold text-white">
                                {{ \Illuminate\Support\Str::of($user->name)->explode(' ')->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->take(2)->implode('') }}
                            </span>
                        @endif
                    </div>
                </template>
                <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                       x-on:change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                       class="block w-full text-sm text-neutral-700 file:mr-3 file:rounded-md file:border-0 file:bg-brand-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white shadow-sm transition hover:file:bg-brand-700" />
            </div>
            <p class="mt-1 text-xs text-neutral-500">{{ __('JPG, PNG, or WebP, max 2 MB.') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('photo')" />

            @if ($user->photoUrl())
                <label class="mt-2 inline-flex items-center gap-2 text-sm text-neutral-600">
                    <input type="checkbox" name="remove_photo" value="1" class="rounded border-neutral-300">
                    {{ __('Remove current photo') }}
                </label>
            @endif
        </div>

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-neutral-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-neutral-600 hover:text-neutral-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-success-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-neutral-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
