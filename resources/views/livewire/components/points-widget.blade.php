<?php

use Livewire\Volt\Component;
use App\Services\GamificationService;
use App\Services\AchievementService;

new class extends Component
{
    public int $totalPoints = 0;
    public int $monthlyPoints = 0;
    public int $todayPoints = 0;
    public array $achievements = [];
    public array $pointsBreakdown = [];
    public int $currentRank = 0;
    public bool $showDetails = false;

    protected $gamificationService;
    protected $achievementService;

    public function boot(GamificationService $gamificationService, AchievementService $achievementService)
    {
        $this->gamificationService = $gamificationService;
        $this->achievementService = $achievementService;
    }

    public function mount()
    {
        $this->loadPointsData();
    }

    public function loadPointsData()
    {
        $user = auth()->user();
        
        $this->totalPoints = $user->totalPoints();
        $this->monthlyPoints = $user->monthlyPoints();
        $this->todayPoints = $user->todayPoints();
        $this->pointsBreakdown = $user->pointsBreakdown();
        $this->currentRank = $user->getRankingPosition();
        $this->achievements = $this->achievementService->getUserAchievements($user);
    }

    public function toggleDetails()
    {
        $this->showDetails = !$this->showDetails;
    }

    public function getActionDescription(string $action): string
    {
        return match($action) {
            'account_creation' => 'Account Creation',
            'daily_login' => 'Daily Login',
            'idea_submission' => 'Idea Submissions',
            'challenge_participation' => 'Challenge Participation',
            'collaboration_contribution' => 'Collaboration',
            'review_completion' => 'Review Completion',
            'idea_approved' => 'Idea Approved',
            'challenge_winner' => 'Challenge Winner',
            default => ucfirst(str_replace('_', ' ', $action))
        };
    }

    public function getBadgeColor(string $badge): string
    {
        return match($badge) {
            'bronze' => 'bg-amber-600',
            'silver' => 'bg-gray-400',
            'gold' => 'bg-yellow-500',
            'platinum' => 'bg-purple-600',
            'diamond' => 'bg-blue-600',
            default => 'bg-gray-500'
        };
    }
}; ?>

<div class="group/card relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transition-all duration-500">
    <!-- Animated Background Elements -->
    <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/5 via-transparent to-[#F8EBD5]/10 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/10 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
    
    <div class="relative z-10 p-6">
        <!-- Enhanced Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-[#231F20] dark:text-zinc-900" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    </div>
                    <div class="absolute -inset-2 bg-[#FFF200]/20 dark:bg-yellow-400/20 rounded-2xl blur-lg -z-10 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">My Points</h3>
                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">
                        <span class="inline-flex items-center text-xs font-medium bg-[#231F20] dark:bg-zinc-700 text-[#FFF200] dark:text-yellow-400 px-3 py-1 rounded-full">
                            Rank #{{ $currentRank }}
                        </span>
                    </p>
                </div>
            </div>
            <button 
                wire:click="toggleDetails"
                class="p-2 text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-100 hover:bg-[#F8EBD5]/20 dark:hover:bg-zinc-700/30 rounded-xl transition-all duration-300"
            >
                <svg class="w-5 h-5 transition-transform duration-300 {{ $showDetails ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>

        <!-- Enhanced Points Summary Cards -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <!-- Total Points Card -->
            <div class="group/stats relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#F8EBD5]/70 to-[#F8EBD5]/50 dark:from-amber-400/20 dark:to-amber-400/10 border border-white/40 dark:border-zinc-700/40 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-1">
                <div class="p-4 text-center">
                    <div class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-1 group-hover/stats:scale-110 transition-transform duration-300">{{ number_format($totalPoints) }}</div>
                    <div class="text-xs text-[#9B9EA4] dark:text-zinc-400 font-medium">Total Points</div>
                </div>
            </div>
            
            <!-- Monthly Points Card -->
            <div class="group/stats relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#F8EBD5]/70 to-[#F8EBD5]/50 dark:from-amber-400/20 dark:to-amber-400/10 border border-white/40 dark:border-zinc-700/40 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-1">
                <div class="p-4 text-center">
                    <div class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-1 group-hover/stats:scale-110 transition-transform duration-300">{{ number_format($monthlyPoints) }}</div>
                    <div class="text-xs text-[#9B9EA4] dark:text-zinc-400 font-medium">This Month</div>
                </div>
            </div>
            
            <!-- Today's Points Card -->
            <div class="group/stats relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#F8EBD5]/70 to-[#F8EBD5]/50 dark:from-amber-400/20 dark:to-amber-400/10 border border-white/40 dark:border-zinc-700/40 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-1">
                <div class="p-4 text-center">
                    <div class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-1 group-hover/stats:scale-110 transition-transform duration-300">{{ number_format($todayPoints) }}</div>
                    <div class="text-xs text-[#9B9EA4] dark:text-zinc-400 font-medium">Today</div>
                </div>
            </div>
        </div>

        <!-- Enhanced Achievements Preview -->
        <div class="mb-6">
            <h4 class="text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-3 uppercase tracking-wider">Recent Achievements</h4>
            <div class="flex space-x-4 overflow-x-auto pb-2">
                @foreach(array_slice($achievements, 0, 3) as $achievement)
                    <div class="group/badge flex-shrink-0 text-center transform hover:-translate-y-1 transition-all duration-300">
                        <div class="relative mb-2">
                            <div class="w-12 h-12 {{ $this->getBadgeColor($achievement['badge']) }} rounded-2xl flex items-center justify-center shadow-md">
                                @if($achievement['achieved'])
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                @else
                                    <div class="w-3 h-3 bg-white/70 rounded-full"></div>
                                @endif
                            </div>
                            <div class="absolute -inset-2 {{ $this->getBadgeColor($achievement['badge']) }} rounded-2xl blur-lg opacity-0 group-hover/badge:opacity-30 transition-opacity duration-500 -z-10"></div>
                        </div>
                        <div class="text-xs font-medium text-[#231F20] dark:text-zinc-100">{{ $achievement['name'] }}</div>
                        <div class="text-xs text-[#9B9EA4] dark:text-zinc-400">{{ round($achievement['progress_percentage'] ?? 0) }}%</div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Enhanced Detailed View with Animations -->
        @if($showDetails)
            <div class="border-t border-[#9B9EA4]/20 dark:border-zinc-700/30 pt-6 space-y-6 animate-fadeIn">
                <!-- Points Breakdown with Enhanced Design -->
                <div>
                    <h4 class="text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-4 uppercase tracking-wider">Points Breakdown</h4>
                    <div class="space-y-3">
                        @foreach($pointsBreakdown as $action => $data)
                            <div class="group/item flex justify-between items-center py-2 px-4 rounded-xl hover:bg-[#F8EBD5]/30 dark:hover:bg-zinc-700/30 transition-all duration-300">
                                <span class="text-sm font-medium text-[#231F20] dark:text-zinc-100">{{ $this->getActionDescription($action) }}</span>
                                <div class="text-right">
                                    <span class="text-sm font-bold text-[#231F20] dark:text-zinc-100 group-hover/item:text-[#FFF200] dark:group-hover/item:text-yellow-400 transition-colors duration-300">{{ number_format($data['total']) }}</span>
                                    <span class="text-xs text-[#9B9EA4] dark:text-zinc-400 ml-1">({{ $data['count'] }}x)</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- All Achievements with Enhanced Cards -->
                <div>
                    <h4 class="text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-4 uppercase tracking-wider">Achievements Progress</h4>
                    <div class="space-y-4">
                        @foreach($achievements as $achievement)
                            <div class="group/achievement relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-1 p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="relative">
                                            <div class="w-10 h-10 {{ $this->getBadgeColor($achievement['badge']) }} rounded-xl flex items-center justify-center shadow-md">
                                                @if($achievement['achieved'])
                                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                @else
                                                    <div class="w-2.5 h-2.5 bg-white/70 rounded-full"></div>
                                                @endif
                                            </div>
                                            <div class="absolute -inset-1 {{ $this->getBadgeColor($achievement['badge']) }} rounded-xl blur-md opacity-0 group-hover/achievement:opacity-30 transition-opacity duration-500 -z-10"></div>
                                        </div>
                                        <div>
                                            <span class="text-sm font-bold text-[#231F20] dark:text-zinc-100 group-hover/achievement:text-{{ str_replace('bg-', 'text-', $this->getBadgeColor($achievement['badge'])) }} transition-colors duration-300">{{ $achievement['name'] }}</span>
                                            <p class="text-xs text-[#9B9EA4] dark:text-zinc-400">{{ $achievement['description'] }}</p>
                                        </div>
                                    </div>
                                    <span class="text-xs font-medium bg-[#231F20] dark:bg-zinc-700 text-[#FFF200] dark:text-yellow-400 px-2 py-1 rounded-lg">
                                        {{ round($achievement['progress_percentage'] ?? 0) }}%
                                    </span>
                                </div>
                                
                                <div class="w-full bg-[#9B9EA4]/20 dark:bg-zinc-600/30 rounded-full h-2 overflow-hidden">
                                    <div class="bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 h-2 rounded-full transition-all duration-1000 ease-out" 
                                         style="width: {{ $achievement['progress_percentage'] ?? 0 }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
