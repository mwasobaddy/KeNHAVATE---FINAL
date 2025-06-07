@php
    $userRole = auth()->user()->roles->first()?->name ?? 'user';
    $redirectRoute = match($userRole) {
        'developer', 'administrator' => route('dashboard.admin'),
        'board_member'   => route('dashboard.board-member'),
        'manager'        => route('dashboard.manager'),
        'sme'            => route('dashboard.sme'),
        'challenge_reviewer' => route('dashboard.challenge-reviewer'),
        default          => route('dashboard.user'),
    };
@endphp

<x-layouts.app title="Dashboard">
    {{-- Immediately redirect via meta-refresh --}}
    <meta http-equiv="refresh" content="0;url={{ $redirectRoute }}" />
    <div class="absolute w-full left-0 flex items-center justify-center min-h-screen">
        <div class="text-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#231F20] mx-auto mb-4"></div>
            <p class="text-[#9B9EA4]">Redirecting to your dashboard...</p>
        </div>
    </div>
</x-layouts.app>
