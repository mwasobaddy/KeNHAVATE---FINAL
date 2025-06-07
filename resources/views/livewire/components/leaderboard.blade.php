<?php

use Livewire\Volt\Component;
use App\Services\GamificationService;
use App\Models\User;

new class extends Component
{
    public string $period = 'all';
    public array $leaderboard = [];
    public int $userRank = 0;
    public string $filter = 'overall';
    public bool $mini = false;
    public ?string $roleFilter = null;
    public bool $adminView = false;

    protected $gamificationService;

    public function boot(GamificationService $gamificationService)
    {
        $this->gamificationService = $gamificationService;
    }

    public function mount($mini = false, $roleFilter = null, $adminView = false)
    {
        $this->mini = $mini;
        $this->roleFilter = $roleFilter;
        $this->adminView = $adminView;
        $this->loadLeaderboard();
    }

    public function updatedPeriod()
    {
        $this->loadLeaderboard();
    }

    public function updatedFilter()
    {
        $this->loadLeaderboard();
    }

    public function loadLeaderboard()
    {
        $limit = $this->mini ? 5 : ($this->adminView ? 50 : 20);
        
        // Apply role filter if specified
        $filter = $this->roleFilter ? 'role' : $this->filter;
        
        if ($filter === 'department') {
            $this->leaderboard = $this->gamificationService->getDepartmentLeaderboard($this->period, $limit);
        } elseif ($filter === 'role') {
            $this->leaderboard = $this->gamificationService->getRoleBasedLeaderboard($this->period, $limit, $this->roleFilter);
        } else {
            $this->leaderboard = $this->gamificationService->getLeaderboard($this->period, $limit);
        }

        $this->userRank = auth()->user()->getRankingPosition($this->period);
    }

    public function getPeriodLabel(): string
    {
        return match($this->period) {
            'monthly' => 'This Month',
            'yearly' => 'This Year',
            'weekly' => 'This Week',
            default => 'All Time'
        };
    }

    public function getFilterLabel(): string
    {
        return match($this->filter) {
            'department' => 'Department Rankings',
            'role' => 'Role-based Rankings',
            default => 'Overall Rankings'
        };
    }

    public function getRankIcon(int $rank): string
    {
        return match($rank) {
            1 => '🥇',
            2 => '🥈', 
            3 => '🥉',
            default => "#{$rank}"
        };
    }

    public function getRankColor(int $rank): string
    {
        return match($rank) {
            1 => 'text-yellow-600',
            2 => 'text-gray-500',
            3 => 'text-amber-600',
            default => 'text-[#9B9EA4]'
        };
    }
}; ?>

<div class="bg-white rounded-xl shadow-lg border border-[#9B9EA4]/20 p-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h3 class="text-xl font-bold text-[#231F20] mb-1">Leaderboard</h3>
            <p class="text-sm text-[#9B9EA4]">{{ $this->getFilterLabel() }} - {{ $this->getPeriodLabel() }}</p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-2 mt-4 sm:mt-0">
            <!-- Period Filter -->
            <select wire:model.live="period" class="px-3 py-2 border border-[#9B9EA4]/30 rounded-lg text-sm text-[#231F20] bg-white focus:ring-2 focus:ring-[#FFF200] focus:border-transparent">
                <option value="all">All Time</option>
                <option value="monthly">This Month</option>
                <option value="yearly">This Year</option>
                <option value="weekly">This Week</option>
            </select>

            <!-- Category Filter -->
            <select wire:model.live="filter" class="px-3 py-2 border border-[#9B9EA4]/30 rounded-lg text-sm text-[#231F20] bg-white focus:ring-2 focus:ring-[#FFF200] focus:border-transparent">
                <option value="overall">Overall</option>
                <option value="department">By Department</option>
                <option value="role">By Role</option>
            </select>
        </div>
    </div>

    <!-- User's Current Rank -->
    <div class="bg-[#F8EBD5] rounded-lg p-4 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-[#FFF200] rounded-full flex items-center justify-center">
                    <span class="text-sm font-bold text-[#231F20]">{{ $userRank }}</span>
                </div>
                <div>
                    <span class="font-semibold text-[#231F20]">Your Current Rank</span>
                    <p class="text-sm text-[#9B9EA4]">{{ auth()->user()->name }}</p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold text-[#231F20]">
                    {{ number_format(
                        $period === 'monthly' ? auth()->user()->monthlyPoints() :
                        ($period === 'yearly' ? auth()->user()->yearlyPoints() :
                        ($period === 'weekly' ? auth()->user()->weeklyPoints() : auth()->user()->totalPoints()))
                    ) }}
                </div>
                <div class="text-xs text-[#9B9EA4]">points</div>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div wire:loading class="space-y-4">
        @for($i = 0; $i < 5; $i++)
            <div class="animate-pulse flex items-center justify-between p-4 border border-[#9B9EA4]/20 rounded-lg">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-[#9B9EA4]/20 rounded-full"></div>
                    <div class="space-y-2">
                        <div class="h-4 bg-[#9B9EA4]/20 rounded w-24"></div>
                        <div class="h-3 bg-[#9B9EA4]/20 rounded w-16"></div>
                    </div>
                </div>
                <div class="h-6 bg-[#9B9EA4]/20 rounded w-16"></div>
            </div>
        @endfor
    </div>

    <!-- Leaderboard List -->
    <div wire:loading.remove class="space-y-3">
        @forelse($leaderboard as $entry)
            <div class="flex items-center justify-between p-4 border border-[#9B9EA4]/20 rounded-lg hover:bg-[#F8EBD5]/30 transition-colors
                        {{ $entry['user']->id === auth()->id() ? 'bg-[#FFF200]/10 border-[#FFF200]/50' : '' }}">
                
                <!-- Rank and User Info -->
                <div class="flex items-center space-x-4">
                    <!-- Rank Badge -->
                    <div class="w-8 h-8 flex items-center justify-center">
                        <span class="text-lg font-bold {{ $this->getRankColor($entry['rank']) }}">
                            {{ $this->getRankIcon($entry['rank']) }}
                        </span>
                    </div>

                    <!-- User Details -->
                    <div class="flex items-center space-x-3">
                        <!-- Avatar -->
                        <div class="w-10 h-10 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] rounded-full flex items-center justify-center">
                            <span class="text-sm font-bold text-[#231F20]">
                                {{ strtoupper(substr($entry['user']->name, 0, 2)) }}
                            </span>
                        </div>

                        <!-- Name and Role -->
                        <div>
                            <div class="font-semibold text-[#231F20] flex items-center space-x-2">
                                <span>{{ $entry['user']->name }}</span>
                                @if($entry['user']->id === auth()->id())
                                    <span class="text-xs bg-[#FFF200] text-[#231F20] px-2 py-1 rounded-full">You</span>
                                @endif
                            </div>
                            <div class="text-sm text-[#9B9EA4] flex items-center space-x-2">
                                <span>{{ $entry['user']->getRoleNames()->first() ?? 'User' }}</span>
                                @if($entry['user']->staff && $filter === 'department')
                                    <span class="text-xs">• {{ $entry['user']->staff->department ?? 'N/A' }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Points -->
                <div class="text-right">
                    <div class="text-lg font-bold text-[#231F20]">
                        {{ number_format($entry['total_points']) }}
                    </div>
                    <div class="text-xs text-[#9B9EA4]">points</div>
                </div>
            </div>
        @empty
            <div class="text-center py-8">
                <div class="w-16 h-16 bg-[#9B9EA4]/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-[#9B9EA4]" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-[#231F20] mb-2">No Data Available</h3>
                <p class="text-[#9B9EA4]">No leaderboard data found for the selected period.</p>
            </div>
        @endforelse
    </div>

    <!-- Footer Message -->
    @if(count($leaderboard) > 0)
        <div class="mt-6 text-center">
            <p class="text-sm text-[#9B9EA4]">
                Showing top {{ count($leaderboard) }} contributors for {{ strtolower($this->getPeriodLabel()) }}
            </p>
        </div>
    @endif
</div>
