<div class="flex h-16 shrink-0 items-center gap-x-4 border-b border-neutral-200 bg-white px-4 shadow-sm sm:px-6">
    <button type="button" class="text-neutral-500 lg:hidden" @click="sidebarOpen = true">
        <span class="sr-only">Open sidebar</span>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    </button>

    <div class="flex flex-1 items-center justify-between">
        <h1 class="text-lg font-semibold text-neutral-900">
            {{ $header ?? 'Maintenance People Development System' }}
        </h1>

        <div class="flex items-center gap-4">
            @php
                $unreadNotifications = Auth::user()->unreadNotifications()->latest()->take(10)->get();
                $unreadCount = Auth::user()->unreadNotifications()->count();
            @endphp

            <x-dropdown align="right" width="80">
                <x-slot name="trigger">
                    <button type="button" class="relative text-neutral-400 hover:text-neutral-600" title="{{ $unreadCount > 0 ? $unreadCount.' unread notification(s)' : 'Notifications (no unread items)' }}">
                        <span class="sr-only">Notifications</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        @if ($unreadCount > 0)
                            <span class="absolute -right-1 -top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">
                                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                            </span>
                        @endif
                    </button>
                </x-slot>

                <x-slot name="content">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-2">
                        <span class="text-sm font-semibold text-neutral-700">Notifications</span>
                        @if ($unreadCount > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}">
                                @csrf
                                <button type="submit" class="text-xs font-medium text-brand-600 hover:text-brand-800">
                                    Mark all as read
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="max-h-96 overflow-y-auto">
                        @forelse ($unreadNotifications as $notification)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-3 text-left text-sm hover:bg-neutral-100">
                                    <span class="block font-medium text-neutral-800">{{ $notification->data['title'] ?? 'Notification' }}</span>
                                    <span class="mt-0.5 block text-xs text-neutral-500">{{ $notification->data['message'] ?? '' }}</span>
                                    <span class="mt-1 block text-xs text-neutral-400">{{ $notification->created_at->diffForHumans() }}</span>
                                </button>
                            </form>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-neutral-500">No unread notifications.</p>
                        @endforelse
                    </div>
                </x-slot>
            </x-dropdown>

            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium text-neutral-700 hover:bg-neutral-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-neutral-700 text-xs font-semibold text-white">
                            {{ Illuminate\Support\Str::of(Auth::user()->name)->explode(' ')->map(fn ($part) => Illuminate\Support\Str::substr($part, 0, 1))->take(2)->implode('') }}
                        </span>
                        <span class="hidden sm:block">
                            <span class="block">{{ Auth::user()->name }}</span>
                            <span class="block text-xs font-normal text-neutral-500">{{ Auth::user()->getRoleNames()->first() ?? 'No role assigned' }}</span>
                        </span>
                        <svg class="h-4 w-4 fill-current text-neutral-400" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('profile.edit')">
                        {{ __('Profile') }}
                    </x-dropdown-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</div>
