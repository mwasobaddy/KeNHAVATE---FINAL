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

{{-- KeNHAVATE Achievements - Enhanced UI with Glass Morphism & Modern Design --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">

        {{-- Enhanced Page Header with Glass Morphism --}}
        <section aria-labelledby="achievements-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl p-8">
                {{-- Header Background Pattern --}}
                <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl"></div>
                
                <div class="relative z-10 flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-8 h-8 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 id="achievements-heading" class="text-4xl font-bold text-[#231F20] dark:text-zinc-100">Achievements</h1>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">Track your accomplishments and progress towards goals</p>
                        </div>
                    </div>
                    <div class="hidden md:flex items-center space-x-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] rounded-2xl flex items-center justify-center shadow-lg animate-pulse">
                            <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Achievement Stats with Glass Morphism --}}
        <section aria-labelledby="stats-heading" class="group">
            <h2 id="stats-heading" class="sr-only">Achievement Statistics</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Total Achievements Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/5 via-transparent to-[#F8EBD5]/10 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-[#FFF200]/20 dark:bg-yellow-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Total Achievements</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-amber-600 dark:group-hover/card:text-amber-400 transition-colors duration-300">{{ number_format($totalAchievements) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-amber-500 dark:bg-amber-400 rounded-full"></div>
                                <span>Available goals</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Earned Achievements Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-emerald-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-emerald-500/20 dark:bg-emerald-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Earned</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-emerald-600 dark:group-hover/card:text-emerald-400 transition-colors duration-300">{{ number_format($earnedAchievements) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                                <span>Unlocked badges</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Completion Rate Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Completion Rate</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-blue-600 dark:group-hover/card:text-blue-400 transition-colors duration-300">{{ $completionRate }}%</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full animate-pulse"></div>
                                <span>Overall progress</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Filters Section --}}
        <section aria-labelledby="filters-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="p-8">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-10 h-10 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 id="filters-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Filter Achievements</h3>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Narrow down by status and category</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:field>
                                <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Status</flux:label>
                                <flux:select wire:model.live="filter" class="mt-2">
                                    <option value="all">All Achievements</option>
                                    <option value="earned">Earned</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="not_started">Not Started</option>
                                </flux:select>
                            </flux:field>
                        </div>
                        
                        <div>
                            <flux:field>
                                <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Category</flux:label>
                                <flux:select wire:model.live="category" class="mt-2">
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
            </div>
        </section>

        {{-- Enhanced Achievement Grid --}}
        <section aria-labelledby="achievement-grid-heading" class="group">
            <h2 id="achievement-grid-heading" class="sr-only">Achievement Grid</h2>
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @forelse($achievements as $achievement)
                    <div class="group/achievement relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                        {{-- Dynamic Background Based on Progress --}}
                        @if($achievement['progress_percentage'] >= 100)
                            <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/10 via-transparent to-emerald-600/20 dark:from-emerald-400/20 dark:via-transparent dark:to-emerald-500/30 opacity-0 group-hover/achievement:opacity-100 transition-opacity duration-500"></div>
                        @else
                            <div class="absolute inset-0 bg-gradient-to-br from-{{ $this->getCategoryColor($achievement['category']) }}/5 via-transparent to-{{ $this->getCategoryColor($achievement['category']) }}/10 opacity-0 group-hover/achievement:opacity-100 transition-opacity duration-500"></div>
                        @endif
                        
                        <div class="relative p-6">
                            <div class="flex items-start gap-4">
                                {{-- Enhanced Achievement Icon/Badge --}}
                                <div class="flex-shrink-0">
                                    <div class="relative">
                                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br {{ $this->getCategoryColor($achievement['category']) }} text-white shadow-lg group-hover/achievement:scale-110 transition-transform duration-300">
                                            <span class="text-2xl font-bold">{{ $achievement['badge'] }}</span>
                                        </div>
                                        @if($achievement['progress_percentage'] >= 100)
                                            <div class="absolute -top-2 -right-2 w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center shadow-lg">
                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </div>
                                        @endif
                                        <div class="absolute -inset-2 bg-gradient-to-br {{ $this->getCategoryColor($achievement['category']) }}/20 rounded-2xl blur-xl opacity-0 group-hover/achievement:opacity-100 transition-opacity duration-500"></div>
                                    </div>
                                </div>
                                
                                {{-- Enhanced Achievement Details --}}
                                <div class="flex-1">
                                    <h3 class="font-bold text-[#231F20] dark:text-zinc-100 text-xl mb-2 group-hover/achievement:text-amber-600 dark:group-hover/achievement:text-amber-400 transition-colors duration-300">{{ $achievement['name'] }}</h3>
                                    <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 leading-relaxed mb-4">{{ $achievement['description'] }}</p>
                                    
                                    {{-- Enhanced Progress Section --}}
                                    @if($achievement['progress_percentage'] < 100)
                                        <div class="space-y-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-xs font-semibold text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wider">Progress</span>
                                                <span class="text-sm font-bold text-[#231F20] dark:text-zinc-100">{{ $achievement['progress_percentage'] }}%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 dark:bg-zinc-700 rounded-full h-3 overflow-hidden">
                                                <div class="bg-gradient-to-r {{ $this->getCategoryColor($achievement['category']) }} h-3 rounded-full transition-all duration-700 ease-out shadow-sm" 
                                                     style="width: {{ $achievement['progress_percentage'] }}%"></div>
                                            </div>
                                            @if(isset($achievement['current_value']) && isset($achievement['criteria']))
                                                <div class="flex justify-between items-center text-xs">
                                                    <span class="text-[#9B9EA4] dark:text-zinc-400">Current: {{ $achievement['current_value'] }}</span>
                                                    <span class="text-[#9B9EA4] dark:text-zinc-400">Goal: {{ $achievement['criteria'] }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        {{-- Enhanced Completion Badge --}}
                                        <div class="flex items-center gap-3 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700/50">
                                            <div class="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <span class="font-bold text-emerald-700 dark:text-emerald-300">Achievement Unlocked!</span>
                                                @if(isset($achievement['achieved_at']))
                                                    <div class="text-xs text-emerald-600 dark:text-emerald-400">
                                                        {{ \Carbon\Carbon::parse($achievement['achieved_at'])->format('M j, Y') }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                    
                                    {{-- Enhanced Category Badge --}}
                                    <div class="mt-4">
                                        <div class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold bg-gradient-to-r {{ $this->getCategoryColor($achievement['category']) }}/10 border border-{{ $this->getCategoryColor($achievement['category']) }}/20 text-{{ $this->getCategoryColor($achievement['category']) }}">
                                            <flux:icon :name="$this->getCategoryIcon($achievement['category'])" class="h-3 w-3 mr-2" />
                                            <span class="capitalize">{{ $achievement['category'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full">
                        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                            <div class="text-center py-16 relative">
                                <div class="w-20 h-20 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                                    <svg class="w-10 h-10 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                    </svg>
                                </div>
                                
                                <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">No Achievements Found</h3>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg leading-relaxed max-w-md mx-auto">
                                    @if($filter !== 'all' || $category !== 'all')
                                        Try adjusting your filters to see more achievements.
                                    @else
                                        Start participating to unlock your first achievements and earn recognition for your contributions!
                                    @endif
                                </p>
                                
                                @if($filter === 'all' && $category === 'all')
                                    <div class="mt-8">
                                        <flux:button href="{{ route('ideas.create') }}" variant="primary" class="bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] text-[#231F20] font-semibold px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                                            Submit Your First Idea
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
