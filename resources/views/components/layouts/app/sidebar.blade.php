<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gradient-to-br from-[#F8EBD5]/20 via-white to-[#F8EBD5]/10 dark:from-zinc-900/50 dark:via-zinc-800 dark:to-zinc-900/30">
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
                    <flux:navlist.item icon="users" :href="route('collaboration.dashboard')" :current="request()->routeIs('collaboration.*') || request()->routeIs('community.*')" wire:navigate>Collaboration</flux:navlist.item>
                    @if(auth()->user()->hasAnyRole(['manager', 'sme', 'board_member', 'administrator', 'developer']))
                        <flux:navlist.item icon="clipboard-document-check" :href="route('reviews.index')" :current="request()->routeIs('reviews.*')" wire:navigate>Reviews</flux:navlist.item>
                    @endif
                </flux:navlist.group>

                <flux:navlist.group heading="Gamification" class="grid">
                    <flux:navlist.item icon="trophy" :href="route('gamification.leaderboard')" :current="request()->routeIs('gamification.leaderboard')" wire:navigate>Leaderboard</flux:navlist.item>
                    <flux:navlist.item icon="star" :href="route('gamification.points')" :current="request()->routeIs('gamification.points')" wire:navigate>Points & History</flux:navlist.item>
                    <flux:navlist.item icon="shield-check" :href="route('gamification.achievements')" :current="request()->routeIs('gamification.achievements')" wire:navigate>Achievements</flux:navlist.item>
                </flux:navlist.group>

                @if(auth()->user()->hasAnyRole(['manager', 'board_member', 'administrator', 'developer']))
                    <flux:navlist.group heading="Analytics" class="grid">
                        <flux:navlist.item icon="chart-bar" :href="route('analytics.dashboard')" :current="request()->routeIs('analytics.*')" wire:navigate>Advanced Analytics</flux:navlist.item>
                    </flux:navlist.group>
                @endif

                @can('view_users')
                    <flux:navlist.group heading="Administration" class="grid">
                        <flux:navlist.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>User Management</flux:navlist.item>
                        @can('view_roles')
                        <flux:navlist.item icon="shield-check" :href="route('roles.index')" :current="request()->routeIs('roles.*')" wire:navigate>Role Management</flux:navlist.item>
                        @endcan
                    </flux:navlist.group>
                @endcan

                @can('view_audit_logs')
                    <flux:navlist.group heading="System" class="grid">
                        <flux:navlist.item icon="document-magnifying-glass" :href="route('audit.index')" :current="request()->routeIs('audit.*')" wire:navigate>Audit Logs</flux:navlist.item>
                    </flux:navlist.group>
                @endcan
            </flux:navlist>

            <flux:spacer />

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
                    <flux:navlist.item icon="users" :href="route('collaboration.dashboard')" :current="request()->routeIs('collaboration.*') || request()->routeIs('community.*')" wire:navigate>{{ __('Collaboration') }}</flux:navlist.item>
                    @if(auth()->user()->hasAnyRole(['manager', 'sme', 'board_member', 'administrator', 'developer']))
                        <flux:navlist.item icon="clipboard-document-check" :href="route('reviews.index')" :current="request()->routeIs('reviews.*')" wire:navigate>{{ __('Reviews') }}</flux:navlist.item>
                    @endif
                </flux:navlist.group>

                <flux:navlist.group :heading="__('Gamification')" class="grid">
                    <flux:navlist.item icon="trophy" :href="route('gamification.leaderboard')" :current="request()->routeIs('gamification.leaderboard')" wire:navigate>{{ __('Leaderboard') }}</flux:navlist.item>
                    <flux:navlist.item icon="star" :href="route('gamification.points')" :current="request()->routeIs('gamification.points')" wire:navigate>{{ __('Points & History') }}</flux:navlist.item>
                    <flux:navlist.item icon="shield-check" :href="route('gamification.achievements')" :current="request()->routeIs('gamification.achievements')" wire:navigate>{{ __('Achievements') }}</flux:navlist.item>
                </flux:navlist.group>

                @if(auth()->user()->hasAnyRole(['manager', 'board_member', 'administrator', 'developer']))
                    <flux:navlist.group :heading="__('Analytics')" class="grid">
                        <flux:navlist.item icon="chart-bar" :href="route('analytics.dashboard')" :current="request()->routeIs('analytics.*')" wire:navigate>{{ __('Advanced Analytics') }}</flux:navlist.item>
                    </flux:navlist.group>
                @endif

                @can('view_users')
                    <flux:navlist.group :heading="__('Administration')" class="grid">
                        <flux:navlist.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>{{ __('User Management') }}</flux:navlist.item>
                        @can('view_roles')
                        <flux:navlist.item icon="shield-check" :href="route('roles.index')" :current="request()->routeIs('roles.*')" wire:navigate>{{ __('Role Management') }}</flux:navlist.item>
                        @endcan
                    </flux:navlist.group>
                @endcan

                @can('view_audit_logs')
                    <flux:navlist.group :heading="__('System')" class="grid">
                        <flux:navlist.item icon="document-magnifying-glass" :href="route('audit.index')" :current="request()->routeIs('audit.*')" wire:navigate>{{ __('Audit Logs') }}</flux:navlist.item>
                    </flux:navlist.group>
                @endcan
            </flux:navlist>

            <flux:spacer />

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
