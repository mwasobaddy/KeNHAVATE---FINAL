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

<div class="bg-white rounded-xl shadow-lg border border-[#9B9EA4]/20 p-6 transition-all duration-300 hover:shadow-xl">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-3">
            <div class="p-2 bg-[#FFF200]/10 rounded-lg">
                <svg class="w-6 h-6 text-[#FFF200]" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-[#231F20]">My Points</h3>
                <p class="text-sm text-[#9B9EA4]">Rank #{{ $currentRank }}</p>
            </div>
        </div>
        <button 
            wire:click="toggleDetails"
            class="p-2 text-[#9B9EA4] hover:text-[#231F20] transition-colors"
        >
            <svg class="w-5 h-5 transition-transform {{ $showDetails ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>

    <!-- Points Summary -->
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div class="text-center p-3 bg-[#F8EBD5] rounded-lg">
            <div class="text-2xl font-bold text-[#231F20]">{{ number_format($totalPoints) }}</div>
            <div class="text-xs text-[#9B9EA4]">Total Points</div>
        </div>
        <div class="text-center p-3 bg-[#F8EBD5] rounded-lg">
            <div class="text-2xl font-bold text-[#231F20]">{{ number_format($monthlyPoints) }}</div>
            <div class="text-xs text-[#9B9EA4]">This Month</div>
        </div>
        <div class="text-center p-3 bg-[#F8EBD5] rounded-lg">
            <div class="text-2xl font-bold text-[#231F20]">{{ number_format($todayPoints) }}</div>
            <div class="text-xs text-[#9B9EA4]">Today</div>
        </div>
    </div>

    <!-- Achievements Preview -->
    <div class="mb-4">
        <h4 class="text-sm font-semibold text-[#231F20] mb-2">Recent Achievements</h4>
        <div class="flex space-x-2 overflow-x-auto">
            @foreach(array_slice($achievements, 0, 3) as $achievement)
                <div class="flex-shrink-0 text-center">
                    <div class="w-8 h-8 {{ $this->getBadgeColor($achievement['badge']) }} rounded-full flex items-center justify-center mb-1">
                        @if($achievement['achieved'])
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        @else
                            <div class="w-2 h-2 bg-white rounded-full"></div>
                        @endif
                    </div>
                    <div class="text-xs text-[#9B9EA4] w-16">{{ $achievement['name'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Detailed View -->
    @if($showDetails)
        <div class="border-t border-[#9B9EA4]/20 pt-4 space-y-4" x-show="true" x-transition>
            <!-- Points Breakdown -->
            <div>
                <h4 class="text-sm font-semibold text-[#231F20] mb-3">Points Breakdown</h4>
                <div class="space-y-2">
                    @foreach($pointsBreakdown as $action => $data)
                        <div class="flex justify-between items-center py-1">
                            <span class="text-sm text-[#9B9EA4]">{{ $this->getActionDescription($action) }}</span>
                            <div class="text-right">
                                <span class="text-sm font-medium text-[#231F20]">{{ number_format($data['total']) }}</span>
                                <span class="text-xs text-[#9B9EA4] ml-1">({{ $data['count'] }}x)</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- All Achievements -->
            <div>
                <h4 class="text-sm font-semibold text-[#231F20] mb-3">Achievements Progress</h4>
                <div class="space-y-3">
                    @foreach($achievements as $achievement)
                        <div class="p-3 border border-[#9B9EA4]/20 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center space-x-2">
                                    <div class="w-6 h-6 {{ $this->getBadgeColor($achievement['badge']) }} rounded-full flex items-center justify-center">
                                        @if($achievement['achieved'])
                                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        @else
                                            <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
                                        @endif
                                    </div>
                                    <span class="text-sm font-medium text-[#231F20]">{{ $achievement['name'] }}</span>
                                </div>
                                <span class="text-xs text-[#9B9EA4]">{{ round($achievement['progress']) }}%</span>
                            </div>
                            <p class="text-xs text-[#9B9EA4] mb-2">{{ $achievement['description'] }}</p>
                            <div class="w-full bg-[#9B9EA4]/20 rounded-full h-1.5">
                                <div class="bg-[#FFF200] h-1.5 rounded-full transition-all duration-500" 
                                     style="width: {{ $achievement['progress'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
