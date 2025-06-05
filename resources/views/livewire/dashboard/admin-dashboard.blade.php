<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Idea;
use App\Models\Challenge;
use App\Models\Review;

new #[Layout('components.layouts.app', title: 'Admin Dashboard')] class extends Component
{
    
    public function with(): array
    {
        return [
            'totalUsers' => User::count(),
            'totalIdeas' => Idea::count(),
            'totalChallenges' => Challenge::count(),
            'pendingReviews' => Review::whereNull('completed_at')->count(),
            'recentUsers' => User::latest()->take(5)->get(),
            'systemStats' => [
                'ideas_this_month' => Idea::whereMonth('created_at', now()->month)->count(),
                'challenges_this_month' => Challenge::whereMonth('created_at', now()->month)->count(),
                'active_collaborations' => 0, // TODO: Implement when collaboration features are ready
            ]
        ];
    }
    
}; ?>

<div class="space-y-6">
    <!-- System Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Users -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">Total Users</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ number_format($totalUsers) }}</p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Ideas -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">Total Ideas</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ number_format($totalIdeas) }}</p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Challenges -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">Total Challenges</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ number_format($totalChallenges) }}</p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Pending Reviews -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">Pending Reviews</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ number_format($pendingReviews) }}</p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- System Management Tools -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- User Management -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <h3 class="text-lg font-semibold text-[#231F20] mb-4">Recent Users</h3>
            <div class="space-y-3">
                @forelse($recentUsers as $user)
                    <div class="flex items-center justify-between p-3 bg-[#F8EBD5] rounded">
                        <div>
                            <p class="font-medium text-[#231F20]">{{ $user->first_name }} {{ $user->last_name }}</p>
                            <p class="text-sm text-[#9B9EA4]">{{ $user->email }}</p>
                        </div>
                        <div class="text-sm text-[#9B9EA4]">
                            {{ $user->created_at->diffForHumans() }}
                        </div>
                    </div>
                @empty
                    <p class="text-[#9B9EA4] text-center">No users registered yet</p>
                @endforelse
            </div>
        </div>

        <!-- System Health -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <h3 class="text-lg font-semibold text-[#231F20] mb-4">System Overview</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-[#9B9EA4]">Ideas This Month</span>
                    <span class="font-semibold text-[#231F20]">{{ $systemStats['ideas_this_month'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-[#9B9EA4]">Challenges This Month</span>
                    <span class="font-semibold text-[#231F20]">{{ $systemStats['challenges_this_month'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-[#9B9EA4]">Active Collaborations</span>
                    <span class="font-semibold text-[#231F20]">{{ $systemStats['active_collaborations'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-[#9B9EA4]">System Status</span>
                    <span class="text-green-600 font-semibold">Operational</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Actions -->
    <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
        <h3 class="text-lg font-semibold text-[#231F20] mb-4">Administrative Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <flux:button href="#" variant="ghost" class="w-full justify-center border border-[#9B9EA4]">
                Manage Users & Roles
            </flux:button>
            <flux:button href="#" variant="ghost" class="w-full justify-center border border-[#9B9EA4]">
                System Settings
            </flux:button>
            <flux:button href="#" variant="ghost" class="w-full justify-center border border-[#9B9EA4]">
                View Audit Logs
            </flux:button>
        </div>
    </div>
</div>
