<?php

use Livewire\Volt\Component;

new class extends Component {
    
    public function with(): array
    {
        $user = auth()->user();
        
        return [
            'user' => $user,
            'userRole' => $user->roles->first()?->name ?? 'user',
            'isStaff' => $user->staff()->exists(),
        ];
    }
    
}; ?>

<x-layouts.app title="Dashboard">
    <div class="flex flex-col gap-6">
        <!-- Welcome Section -->
        <div class="bg-[#F8EBD5] p-6 rounded-lg border border-[#9B9EA4]">
            <h1 class="text-2xl font-bold text-[#231F20] mb-2">
                Welcome back, {{ $user->first_name }}!
            </h1>
            <p class="text-[#9B9EA4]">
                @if($isStaff)
                    KeNHA Staff Member • {{ ucfirst($userRole) }}
                @else
                    Innovation Portal Member • {{ ucfirst($userRole) }}
                @endif
            </p>
        </div>

        <!-- Role-Specific Dashboard Content -->
        @if($userRole === 'developer' || $userRole === 'administrator')
            @livewire('dashboard.admin-dashboard')
        @elseif($userRole === 'board_member')
            @livewire('dashboard.board-member-dashboard')
        @elseif($userRole === 'manager')
            @livewire('dashboard.manager-dashboard')
        @elseif($userRole === 'sme')
            @livewire('dashboard.sme-dashboard')
        @elseif($userRole === 'challenge_reviewer')
            @livewire('dashboard.challenge-reviewer-dashboard')
        @elseif($userRole === 'idea_reviewer')
            @livewire('dashboard.idea-reviewer-dashboard')
        @else
            @livewire('dashboard.user-dashboard')
        @endif

        <!-- Quick Actions Section -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <h2 class="text-xl font-semibold text-[#231F20] mb-4">Quick Actions</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                
                @can('create', App\Models\Idea::class)
                    <flux:button 
                        href="#" 
                        variant="primary" 
                        class="w-full justify-center"
                    >
                        Submit New Idea
                    </flux:button>
                @endcan

                @can('viewAny', App\Models\Challenge::class)
                    <flux:button 
                        href="#" 
                        variant="ghost" 
                        class="w-full justify-center border border-[#9B9EA4]"
                    >
                        View Challenges
                    </flux:button>
                @endcan

                @can('create', App\Models\Challenge::class)
                    <flux:button 
                        href="#" 
                        variant="ghost" 
                        class="w-full justify-center border border-[#9B9EA4]"
                    >
                        Create Challenge
                    </flux:button>
                @endcan

                <flux:button 
                    href="#" 
                    variant="ghost" 
                    class="w-full justify-center border border-[#9B9EA4]"
                >
                    My Profile
                </flux:button>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <h2 class="text-xl font-semibold text-[#231F20] mb-4">Recent Activity</h2>
            <div class="space-y-3">
                <!-- Placeholder for recent activity items -->
                <div class="flex items-center gap-3 p-3 bg-[#F8EBD5] rounded">
                    <div class="w-2 h-2 bg-[#FFF200] rounded-full"></div>
                    <span class="text-[#231F20]">System initialized - Welcome to KeNHAVATE Innovation Portal!</span>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
