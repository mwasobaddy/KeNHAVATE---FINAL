<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen  bg-gradient-to-br from-[#F8EBD5]/20 via-white to-[#F8EBD5]/10 dark:from-zinc-900/50 dark:via-zinc-800 dark:to-zinc-900/30">
        <!-- Desktop Sidebar - Always Visible -->
        <flux:sidebar sticky class="hidden lg:flex border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <!-- Desktop sidebar has no toggle and is always visible -->

            @php
                $userRole = auth()->user()->roles->first()?->name ?? 'user';
                $logoRoute = match($userRole) {
                    'developer', 'administrator' => route('dashboard.admin'),
                    'board_member' => route('dashboard.board-member'),
                    'manager' => route('dashboard.manager'),
                    'sme' => route('dashboard.sme'),
                    'challenge_reviewer' => route('dashboard.challenge-reviewer'),
                    default => route('dashboard.user'),
                };
            @endphp

            <a href="{{ $logoRoute }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group heading="Platform" class="grid">
                    @php
                        $userRole = auth()->user()->roles->first()?->name ?? 'user';
                        $dashboardRoute = match($userRole) {
                            'developer', 'administrator' => route('dashboard.admin'),
                            'board_member' => route('dashboard.board-member'),
                            'manager' => route('dashboard.manager'),
                            'sme' => route('dashboard.sme'),
                            'challenge_reviewer' => route('dashboard.challenge-reviewer'),
                            default => route('dashboard.user'),
                        };
                    @endphp
                    <flux:navlist.item icon="home" :href="$dashboardRoute" :current="request()->routeIs('dashboard*')" wire:navigate>Dashboard</flux:navlist.item>
                    <flux:navlist.item icon="light-bulb" :href="route('ideas.index')" :current="request()->routeIs('ideas.*')" wire:navigate>Ideas</flux:navlist.item>
                    <flux:navlist.item icon="trophy" :href="route('challenges.index')" :current="request()->routeIs('challenges.*') || request()->routeIs('challenge-reviews.*')" wire:navigate>Challenges</flux:navlist.item>
                    @if(auth()->user()->activeCollaborations()->count() > 0 || auth()->user()->collaborations()->where('status', 'pending')->count() > 0)
                        <flux:navlist.item icon="users" href="/collaboration/dashboard" :current="request()->is('collaboration/*')" wire:navigate>Collaboration</flux:navlist.item>
                    @endif
                    @if(auth()->user()->hasAnyRole(['manager', 'sme', 'board_member', 'administrator', 'developer']))
                        <flux:navlist.item icon="clipboard-document-check" :href="route('reviews.index')" :current="request()->routeIs('reviews.*')" wire:navigate>Reviews</flux:navlist.item>
                    @endif
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <flux:navlist variant="outline">
                <flux:navlist.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                Repository
                </flux:navlist.item>

                <flux:navlist.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                Documentation
                </flux:navlist.item>
            </flux:navlist>

            <!-- Desktop User Menu -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>Settings</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Log Out
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile Collapsible Sidebar -->
        <flux:sidebar sticky stashable class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ $logoRoute }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('Platform')" class="grid">
                    @php
                        $userRole = auth()->user()->roles->first()?->name ?? 'user';
                        $dashboardRoute = match($userRole) {
                            'developer', 'administrator' => route('dashboard.admin'),
                            'board_member' => route('dashboard.board-member'),
                            'manager' => route('dashboard.manager'),
                            'sme' => route('dashboard.sme'),
                            'challenge_reviewer' => route('dashboard.challenge-reviewer'),
                            default => route('dashboard.user'),
                        };
                    @endphp
                    <flux:navlist.item icon="home" :href="$dashboardRoute" :current="request()->routeIs('dashboard*')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                    <flux:navlist.item icon="light-bulb" :href="route('ideas.index')" :current="request()->routeIs('ideas.*')" wire:navigate>{{ __('Ideas') }}</flux:navlist.item>
                    <flux:navlist.item icon="trophy" :href="route('challenges.index')" :current="request()->routeIs('challenges.*') || request()->routeIs('challenge-reviews.*')" wire:navigate>{{ __('Challenges') }}</flux:navlist.item>
                    @if(auth()->user()->activeCollaborations()->count() > 0 || auth()->user()->collaborations()->where('status', 'pending')->count() > 0)
                        <flux:navlist.item icon="users" href="/collaboration/dashboard" :current="request()->is('collaboration/*')" wire:navigate>{{ __('Collaboration') }}</flux:navlist.item>
                    @endif
                    @if(auth()->user()->hasAnyRole(['manager', 'sme', 'board_member', 'administrator', 'developer']))
                        <flux:navlist.item icon="clipboard-document-check" :href="route('reviews.index')" :current="request()->routeIs('reviews.*')" wire:navigate>{{ __('Reviews') }}</flux:navlist.item>
                    @endif
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <flux:navlist variant="outline">
                <flux:navlist.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                {{ __('Repository') }}
                </flux:navlist.item>

                <flux:navlist.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                {{ __('Documentation') }}
                </flux:navlist.item>
            </flux:navlist>

            <!-- Desktop User Menu -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden sticky top-0 z-50 bg-white border-b border-zinc-200 dark:bg-zinc-900 dark:border-zinc-700 px-4 py-2">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>
        
        {{ $slot }}

        @fluxScripts
    </body>
</html>
