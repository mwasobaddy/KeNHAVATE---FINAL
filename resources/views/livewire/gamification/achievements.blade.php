<?php

use Livewire\Volt\Component;
use App\Services\AchievementService;

new class extends Component
{
    public $achievements = [];
    public $filter = 'all';
    public $category = 'all';
    
    public $totalAchievements = 0;
    public $earnedAchievements = 0;
    public $completionRate = 0;

    public function mount()
    {
        $this->loadAchievements();
        $this->calculateStats();
    }

    public function updated($property)
    {
        if (in_array($property, ['filter', 'category'])) {
            $this->loadAchievements();
        }
    }

    public function loadAchievements()
    {
        $achievementService = app(AchievementService::class);
        $allAchievements = $achievementService->getUserAchievements(auth()->user());
        
        // Add category mapping to achievements
        $allAchievements = collect($allAchievements)->map(function($achievement) {
            $achievement['category'] = $this->getAchievementCategory($achievement['key']);
            return $achievement;
        })->toArray();
        
        // Apply filters
        $this->achievements = collect($allAchievements)->filter(function($achievement) {
            // Filter by completion status
            if ($this->filter === 'earned' && $achievement['progress_percentage'] < 100) {
                return false;
            }
            if ($this->filter === 'in_progress' && ($achievement['progress_percentage'] == 0 || $achievement['progress_percentage'] >= 100)) {
                return false;
            }
            if ($this->filter === 'not_started' && $achievement['progress_percentage'] > 0) {
                return false;
            }
            
            // Filter by category
            if ($this->category !== 'all' && ($achievement['category'] ?? 'other') !== $this->category) {
                return false;
            }

            return true;
        })->values()->toArray();
    }

    public function calculateStats()
    {
        $achievementService = app(AchievementService::class);
        $allAchievements = $achievementService->getUserAchievements(auth()->user());
        
        $this->totalAchievements = count($allAchievements);
        $this->earnedAchievements = collect($allAchievements)->where('progress_percentage', 100)->count();
        $this->completionRate = $this->totalAchievements > 0 ? round(($this->earnedAchievements / $this->totalAchievements) * 100) : 0;
    }

    public function getAchievementCategory($key)
    {
        return match($key) {
            'innovation_pioneer', 'idea_implementer', 'innovation_catalyst' => 'innovation',
            'collaboration_champion', 'community_builder' => 'collaboration',
            'quick_reviewer', 'review_expert' => 'contribution',
            'challenge_master' => 'leadership',
            'consistent_contributor', 'weekend_warrior' => 'consistency',
            default => 'participation'
        };
    }

    public function getCategoryIcon($category)
    {
        return match($category) {
            'participation' => 'user-plus',
            'contribution' => 'heart',
            'leadership' => 'star',
            'collaboration' => 'users',
            'innovation' => 'light-bulb',
            'consistency' => 'calendar-days',
            default => 'trophy'
        };
    }

    public function getCategoryColor($category)
    {
        return match($category) {
            'participation' => 'from-blue-500 to-blue-600',
            'contribution' => 'from-green-500 to-green-600',
            'leadership' => 'from-yellow-500 to-yellow-600',
            'collaboration' => 'from-purple-500 to-purple-600',
            'innovation' => 'from-orange-500 to-orange-600',
            'consistency' => 'from-indigo-500 to-indigo-600',
            default => 'from-gray-500 to-gray-600'
        };
    }
}; ?>

<div>
    {{-- Page Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-[#231F20]">Achievements</h1>
                <p class="mt-2 text-[#9B9EA4]">Track your accomplishments and progress towards goals</p>
            </div>
            <div class="flex items-center space-x-2">
                <flux:icon.trophy class="h-8 w-8 text-[#FFF200]" />
            </div>
        </div>
    </div>

    {{-- Achievement Stats --}}
    <div class="grid gap-4 md:grid-cols-3 mb-8">
        <div class="rounded-xl bg-gradient-to-r from-[#FFF200]/20 to-[#F8EBD5]/30 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-[#231F20]/70">Total Achievements</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ $totalAchievements }}</p>
                </div>
                <flux:icon.trophy class="size-8 text-[#FFF200]" />
            </div>
        </div>
        
        <div class="rounded-xl bg-gradient-to-r from-green-100 to-green-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-green-700">Earned</p>
                    <p class="text-3xl font-bold text-green-900">{{ $earnedAchievements }}</p>
                </div>
                <flux:icon.check-circle class="size-8 text-green-600" />
            </div>
        </div>
        
        <div class="rounded-xl bg-gradient-to-r from-blue-100 to-blue-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-blue-700">Completion Rate</p>
                    <p class="text-3xl font-bold text-blue-900">{{ $completionRate }}%</p>
                </div>
                <flux:icon.chart-pie class="size-8 text-blue-600" />
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-xl bg-white p-6 shadow-sm mb-8">
        <div class="flex flex-wrap gap-4">
            <div>
                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model.live="filter">
                        <option value="all">All Achievements</option>
                        <option value="earned">Earned</option>
                        <option value="in_progress">In Progress</option>
                        <option value="not_started">Not Started</option>
                    </flux:select>
                </flux:field>
            </div>
            
            <div>
                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:select wire:model.live="category">
                        <option value="all">All Categories</option>
                        <option value="participation">Participation</option>
                        <option value="contribution">Contribution</option>
                        <option value="leadership">Leadership</option>
                        <option value="collaboration">Collaboration</option>
                        <option value="innovation">Innovation</option>
                        <option value="consistency">Consistency</option>
                    </flux:select>
                </flux:field>
            </div>
        </div>
    </div>

    {{-- Achievement Grid --}}
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @forelse($achievements as $achievement)
            <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-start gap-4">
                    {{-- Achievement Icon/Badge --}}
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-r {{ $this->getCategoryColor($achievement['category']) }} text-white">
                            <span class="text-lg">{{ $achievement['badge'] }}</span>
                        </div>
                    </div>
                    
                    {{-- Achievement Details --}}
                    <div class="flex-1">
                        <h3 class="font-semibold text-[#231F20] text-lg">{{ $achievement['name'] }}</h3>
                        <p class="text-sm text-[#9B9EA4] mt-1">{{ $achievement['description'] }}</p>
                        
                        {{-- Progress Bar --}}
                        @if($achievement['progress_percentage'] < 100)
                            <div class="mt-4">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-xs text-[#9B9EA4]">Progress</span>
                                    <span class="text-xs font-medium text-[#231F20]">{{ $achievement['progress_percentage'] }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-gradient-to-r {{ $this->getCategoryColor($achievement['category']) }} h-2 rounded-full transition-all duration-300" 
                                         style="width: {{ $achievement['progress_percentage'] }}%"></div>
                                </div>
                                @if(isset($achievement['current_value']) && isset($achievement['criteria']))
                                    <div class="text-xs text-[#9B9EA4] mt-1">
                                        {{ $achievement['current_value'] }} / {{ $achievement['criteria'] }}
                                    </div>
                                @endif
                            </div>
                        @else
                            {{-- Completed Badge --}}
                            <div class="mt-4 flex items-center gap-2">
                                <flux:icon.check-circle class="h-4 w-4 text-green-600" />
                                <span class="text-sm font-medium text-green-600">Completed!</span>
                                @if(isset($achievement['achieved_at']))
                                    <span class="text-xs text-[#9B9EA4]">
                                        {{ \Carbon\Carbon::parse($achievement['achieved_at'])->format('M j, Y') }}
                                    </span>
                                @endif
                            </div>
                        @endif
                        
                        {{-- Category Badge --}}
                        <div class="mt-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 capitalize">
                                <flux:icon :name="$this->getCategoryIcon($achievement['category'])" class="h-3 w-3 mr-1" />
                                {{ $achievement['category'] }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <div class="text-center py-12">
                    <flux:icon.trophy class="mx-auto h-12 w-12 text-[#9B9EA4]" />
                    <h3 class="mt-2 text-sm font-semibold text-[#231F20]">No achievements found</h3>
                    <p class="mt-1 text-sm text-[#9B9EA4]">
                        @if($filter !== 'all' || $category !== 'all')
                            Try adjusting your filters to see more achievements.
                        @else
                            Start participating to unlock your first achievements!
                        @endif
                    </p>
                </div>
            </div>
        @endforelse
    </div>
</div>
