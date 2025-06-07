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
            1 => 'from-yellow-400 to-yellow-500 text-white',
            2 => 'from-gray-300 to-gray-400 text-white',
            3 => 'from-amber-500 to-amber-600 text-white',
            default => 'from-gray-100 to-gray-200 dark:from-zinc-700 dark:to-zinc-600 text-[#231F20] dark:text-zinc-300'
        };
    }

    public function getRankGradient(int $rank): string
    {
        return match($rank) {
            1 => 'bg-gradient-to-br from-yellow-400 to-yellow-600',
            2 => 'bg-gradient-to-br from-gray-300 to-gray-500',
            3 => 'bg-gradient-to-br from-amber-400 to-amber-600',
            default => 'bg-gradient-to-br from-gray-200 to-gray-300 dark:from-zinc-700 dark:to-zinc-600'
        };
    }
}; ?>

<div class="group relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
    {{-- Animated Background Elements --}}
    <div class="absolute top-0 right-0 w-72 h-72 bg-gradient-to-br from-[#FFF200]/5 to-[#F8EBD5]/10 dark:from-yellow-400/5 dark:to-amber-400/10 rounded-full -mr-36 -mt-36 blur-3xl"></div>
    <div class="absolute bottom-0 left-0 w-64 h-64 bg-gradient-to-tr from-blue-500/5 to-purple-500/5 dark:from-blue-400/10 dark:to-purple-400/10 rounded-full -ml-32 -mb-32 blur-3xl"></div>
    
    <div class="relative z-10 p-6">
        {{-- Header with Modern Typography --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 border-b border-gray-100/50 dark:border-zinc-700/50 pb-4">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.128a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Leaderboard</h3>
                    <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $this->getFilterLabel() }} - {{ $this->getPeriodLabel() }}</p>
                </div>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-3 mt-4 sm:mt-0">
                {{-- Period Filter --}}
                <select wire:model.live="period" class="px-3 py-2.5 rounded-xl border border-white/40 dark:border-zinc-600/40 text-sm text-[#231F20] dark:text-zinc-200 bg-white/70 dark:bg-zinc-800/70 backdrop-blur-sm focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:border-transparent transition-all duration-300">
                    <option value="all">All Time</option>
                    <option value="monthly">This Month</option>
                    <option value="yearly">This Year</option>
                    <option value="weekly">This Week</option>
                </select>

                {{-- Category Filter --}}
                <select wire:model.live="filter" class="px-3 py-2.5 rounded-xl border border-white/40 dark:border-zinc-600/40 text-sm text-[#231F20] dark:text-zinc-200 bg-white/70 dark:bg-zinc-800/70 backdrop-blur-sm focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:border-transparent transition-all duration-300">
                    <option value="overall">Overall</option>
                    <option value="department">By Department</option>
                    <option value="role">By Role</option>
                </select>
            </div>
        </div>

        {{-- User's Current Rank - Enhanced with Glass Morphism --}}
        <div class="group/card relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#F8EBD5]/60 to-[#F8EBD5]/30 dark:from-amber-400/20 dark:to-amber-400/10 backdrop-blur-sm border border-[#F8EBD5]/50 dark:border-amber-400/20 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 mb-6">
            <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/5 via-transparent to-[#F8EBD5]/10 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/5 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
            
            <div class="relative p-4 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    {{-- Rank Badge with Glow Effect --}}
                    <div class="relative">
                        <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-full flex items-center justify-center shadow-lg">
                            <span class="text-lg font-bold text-[#231F20] dark:text-zinc-900">{{ $userRank }}</span>
                        </div>
                        <div class="absolute -inset-1 bg-[#FFF200]/30 dark:bg-yellow-400/30 rounded-full blur-md opacity-0 group-hover/card:opacity-100 transition-opacity duration-300"></div>
                    </div>
                    
                    <div>
                        <span class="font-bold text-[#231F20] dark:text-zinc-100 text-lg">Your Current Rank</span>
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ auth()->user()->name }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 group-hover/card:text-amber-600 dark:group-hover/card:text-amber-400 transition-colors duration-300">
                        {{ number_format(
                            $period === 'monthly' ? auth()->user()->monthlyPoints() :
                            ($period === 'yearly' ? auth()->user()->yearlyPoints() :
                            ($period === 'weekly' ? auth()->user()->weeklyPoints() : auth()->user()->totalPoints()))
                        ) }}
                    </div>
                    <div class="text-xs text-[#9B9EA4] dark:text-zinc-400 font-medium uppercase tracking-wider">points</div>
                </div>
            </div>
        </div>

        {{-- Loading State with Enhanced Skeleton --}}
        <div wire:loading class="space-y-4">
            @for($i = 0; $i < 5; $i++)
                <div class="animate-pulse overflow-hidden rounded-2xl bg-white/50 dark:bg-zinc-800/50 border border-white/30 dark:border-zinc-700/30 backdrop-blur-sm p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-10 h-10 bg-gray-200/70 dark:bg-zinc-700/70 rounded-full"></div>
                            <div class="space-y-2">
                                <div class="h-4 bg-gray-200/70 dark:bg-zinc-700/70 rounded-full w-32"></div>
                                <div class="h-3 bg-gray-200/70 dark:bg-zinc-700/70 rounded-full w-24"></div>
                            </div>
                        </div>
                        <div class="h-8 bg-gray-200/70 dark:bg-zinc-700/70 rounded-full w-20"></div>
                    </div>
                </div>
            @endfor
        </div>

        {{-- Leaderboard List with Enhanced Glass Cards --}}
        <div wire:loading.remove class="space-y-4">
            @forelse($leaderboard as $entry)
                <div class="group/entry relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transform hover:-translate-y-1 transition-all duration-300
                          {{ $entry['user']->id === auth()->id() ? 'bg-gradient-to-r from-[#FFF200]/20 to-[#F8EBD5]/20 dark:from-yellow-400/20 dark:to-amber-400/10 border-[#FFF200]/30 dark:border-yellow-400/30' : '' }}">
                    
                    {{-- Hover Gradient Overlay --}}
                    <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/5 via-transparent to-[#F8EBD5]/10 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/5 opacity-0 group-hover/entry:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-4 flex items-center justify-between">
                        {{-- Rank and User Info --}}
                        <div class="flex items-center space-x-4">
                            {{-- Enhanced Rank Badge with Glow --}}
                            <div class="relative">
                                <div class="w-10 h-10 {{ $this->getRankGradient($entry['rank']) }} rounded-2xl flex items-center justify-center shadow-md">
                                    <span class="text-lg font-bold {{ in_array($entry['rank'], [1, 2, 3]) ? 'text-white' : 'text-[#231F20] dark:text-zinc-200' }}">
                                        {{ $this->getRankIcon($entry['rank']) }}
                                    </span>
                                </div>
                                @if(in_array($entry['rank'], [1, 2, 3]))
                                <div class="absolute -inset-1 {{ $this->getRankGradient($entry['rank']) }}/30 rounded-2xl blur-md opacity-0 group-hover/entry:opacity-100 transition-opacity duration-300"></div>
                                @endif
                            </div>

                            {{-- User Details --}}
                            <div class="flex items-center space-x-4">
                                {{-- Avatar with Gradient --}}
                                <div class="relative">
                                    <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-full flex items-center justify-center shadow-md">
                                        <span class="text-sm font-bold text-[#231F20] dark:text-zinc-900">
                                            {{ strtoupper(substr($entry['user']->name, 0, 2)) }}
                                        </span>
                                    </div>
                                    @if($entry['user']->id === auth()->id())
                                    <div class="absolute -inset-1 bg-[#FFF200]/30 dark:bg-yellow-400/30 rounded-full blur-md opacity-0 group-hover/entry:opacity-100 transition-opacity duration-300"></div>
                                    @endif
                                </div>

                                {{-- Name and Role with Enhanced Typography --}}
                                <div>
                                    <div class="font-bold text-lg text-[#231F20] dark:text-zinc-100 group-hover/entry:text-[#FFF200] dark:group-hover/entry:text-yellow-400 transition-colors duration-300 flex items-center space-x-2">
                                        <span>{{ $entry['user']->name }}</span>
                                        @if($entry['user']->id === auth()->id())
                                            <span class="text-xs bg-[#FFF200] dark:bg-yellow-400 text-[#231F20] dark:text-zinc-900 px-2.5 py-1 rounded-full shadow-sm">You</span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-[#9B9EA4] dark:text-zinc-400 flex items-center space-x-2">
                                        <span>{{ $entry['user']->getRoleNames()->first() ?? 'User' }}</span>
                                        @if($entry['user']->staff && $filter === 'department')
                                            <span class="text-xs">• {{ $entry['user']->staff->department ?? 'N/A' }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Points with Enhanced Display --}}
                        <div class="text-right">
                            <div class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 group-hover/entry:text-[#FFF200] dark:group-hover/entry:text-yellow-400 transition-colors duration-300">
                                {{ number_format($entry['total_points']) }}
                            </div>
                            <div class="text-xs text-[#9B9EA4] dark:text-zinc-400 font-medium uppercase tracking-wider">points</div>
                        </div>
                    </div>
                </div>
            @empty
                {{-- Enhanced Empty State with Consistent Styling --}}
                <div class="text-center py-12 relative">
                    {{-- Background Element --}}
                    <div class="absolute inset-0 flex items-center justify-center opacity-5 dark:opacity-10">
                        <svg class="w-64 h-64 text-[#FFF200] dark:text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    </div>
                    
                    <div class="relative z-10">
                        <div class="w-20 h-20 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                            <svg class="w-10 h-10 text-[#231F20] dark:text-zinc-900" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </div>
                        
                        <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">No Rankings Yet</h3>
                        <p class="text-[#9B9EA4] dark:text-zinc-400 max-w-md mx-auto leading-relaxed">
                            Be the first to contribute and earn points in the KeNHAVATE Innovation Portal.
                            Submit ideas or participate in challenges to appear on the leaderboard!
                        </p>
                    </div>
                </div>
            @endforelse
        </div>

        {{-- Enhanced Footer Message --}}
        @if(count($leaderboard) > 0)
            <div class="mt-6 text-center">
                <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 py-2 px-4 rounded-full bg-white/50 dark:bg-zinc-800/50 inline-block backdrop-blur-sm border border-white/30 dark:border-zinc-700/30">
                    Showing top {{ count($leaderboard) }} innovators for {{ strtolower($this->getPeriodLabel()) }}
                </p>
            </div>
        @endif
    </div>
</div>
