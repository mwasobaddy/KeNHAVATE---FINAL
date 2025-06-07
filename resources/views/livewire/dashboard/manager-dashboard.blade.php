<?php

use Livewire\Volt\Component;
use App\Models\Idea;
use App\Models\Challenge;
use App\Models\Review;
use App\Services\AchievementService;

new #[Layout('components.layouts.app', title: 'Manager Dashboard')] class extends Component
{
    
    public function with(): array
    {
        $user = auth()->user();
        $achievementService = app(AchievementService::class);
        
        return [
            'pendingReviews' => Idea::where('current_stage', 'manager_review')->latest()->take(5)->get(),
            'myChallenges' => Challenge::where('created_by', $user->id)->latest()->take(5)->get(),
            'reviewStats' => [
                'pending_manager_reviews' => Idea::where('current_stage', 'manager_review')->count(),
                'completed_reviews' => Review::where('reviewer_id', $user->id)
                    ->whereNotNull('completed_at')
                    ->count(),
                'challenges_created' => Challenge::where('created_by', $user->id)->count(),
                'avg_review_time' => '2.5 days', // TODO: Calculate actual average
            ],
            'recentActivity' => [
                'ideas_submitted_today' => Idea::whereDate('created_at', today())->count(),
                'reviews_completed_today' => Review::where('reviewer_id', $user->id)
                    ->whereDate('completed_at', today())
                    ->count(),
            ],
            'gamification' => [
                'total_points' => $user->totalPoints(),
                'monthly_points' => $user->monthlyPoints(),
                'ranking_position' => $user->getRankingPosition(),
                'achievements_count' => $achievementService->getUserAchievements($user)->count(),
                'review_streak' => $user->userPoints()->reviewStreak()->count(),
                'challenges_created_count' => $user->userPoints()->challengeCreation()->count(),
            ]
        ];
    }
    
}; ?>


<div class="space-y-6">
    {{-- Gamification Integration for Manager Dashboard --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Gamification Quick Access Cards --}}
        <div class="lg:col-span-2">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {{-- Leaderboard Card --}}
                <a href="{{ route('gamification.leaderboard') }}" class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 border border-blue-200 dark:border-blue-700/50 p-4 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-500 dark:bg-blue-600 flex items-center justify-center">
                            <flux:icon.trophy class="w-5 h-5 text-white" />
                        </div>
                        <flux:icon.chevron-right class="w-4 h-4 text-blue-600 dark:text-blue-400 group-hover:translate-x-1 transition-transform" />
                    </div>
                    <h3 class="font-semibold text-blue-900 dark:text-blue-100 text-sm">Leaderboard</h3>
                    <p class="text-blue-700 dark:text-blue-300 text-xs mt-1">Department Rankings</p>
                </a>

                {{-- Points Card --}}
                <a href="{{ route('gamification.points') }}" class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-800/20 border border-emerald-200 dark:border-emerald-700/50 p-4 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-lg bg-emerald-500 dark:bg-emerald-600 flex items-center justify-center">
                            <flux:icon.currency-dollar class="w-5 h-5 text-white" />
                        </div>
                        <flux:icon.chevron-right class="w-4 h-4 text-emerald-600 dark:text-emerald-400 group-hover:translate-x-1 transition-transform" />
                    </div>
                    <h3 class="font-semibold text-emerald-900 dark:text-emerald-100 text-sm">Points & History</h3>
                    <p class="text-emerald-700 dark:text-emerald-300 text-xs mt-1">{{ number_format($this->userPoints ?? 0) }} total points</p>
                </a>

                {{-- Achievements Card --}}
                <a href="{{ route('gamification.achievements') }}" class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 border border-purple-200 dark:border-purple-700/50 p-4 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-500 dark:bg-purple-600 flex items-center justify-center">
                            <flux:icon.star class="w-5 h-5 text-white" />
                        </div>
                        <flux:icon.chevron-right class="w-4 h-4 text-purple-600 dark:text-purple-400 group-hover:translate-x-1 transition-transform" />
                    </div>
                    <h3 class="font-semibold text-purple-900 dark:text-purple-100 text-sm">Achievements</h3>
                    <p class="text-purple-700 dark:text-purple-300 text-xs mt-1">{{ $this->userAchievements ?? 0 }} unlocked</p>
                </a>
            </div>
        </div>
        
        {{-- Quick Stats Summary --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Your Performance</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Current Rank</span>
                        <span class="font-semibold text-gray-900 dark:text-white">#{{ $this->userRank ?? '--' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Department Rank</span>
                        <span class="font-semibold text-gray-900 dark:text-white">#{{ $this->departmentRank ?? '--' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Ideas Reviewed</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $this->ideasReviewed ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manager Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Pending Reviews -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">Pending Reviews</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ $reviewStats['pending_manager_reviews'] }}</p>
                </div>
                <div class="w-12 h-12 bg-[#FFF200] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Completed Reviews -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">Completed Reviews</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ $reviewStats['completed_reviews'] }}</p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Challenges Created -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">My Challenges</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ $reviewStats['challenges_created'] }}</p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Average Review Time -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">Avg Review Time</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $reviewStats['avg_review_time'] }}</p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Manager Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Pending Reviews Queue -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-[#231F20]">Ideas Awaiting Your Review</h3>
                <flux:button href="{{ route('reviews.index') }}" variant="ghost" size="sm">
                    View All
                </flux:button>
            </div>
            <div class="space-y-3">
                @forelse($pendingReviews as $idea)
                    <div class="border border-[#9B9EA4] rounded-lg p-4">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-medium text-[#231F20]">{{ $idea->title }}</h4>
                            <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full">
                                {{ $idea->created_at->diffForHumans() }}
                            </span>
                        </div>
                        <p class="text-sm text-[#9B9EA4] mb-3">{{ Str::limit($idea->description, 120) }}</p>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-[#9B9EA4]">
                                By: {{ $idea->author->first_name }} {{ $idea->author->last_name }}
                            </span>
                            <flux:button href="{{ route('reviews.idea', $idea) }}" variant="primary" size="sm">
                                Review Now
                            </flux:button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <p class="mt-2 text-[#9B9EA4]">No ideas pending review</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- My Challenges -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-[#231F20]">My Challenges</h3>
                <flux:button href="#" variant="primary" size="sm">
                    Create New
                </flux:button>
            </div>
            <div class="space-y-3">
                @forelse($myChallenges as $challenge)
                    <div class="border border-[#9B9EA4] rounded-lg p-4">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-medium text-[#231F20]">{{ $challenge->title }}</h4>
                            <span class="text-xs 
                                @if($challenge->status === 'active') bg-green-100 text-green-800
                                @elseif($challenge->status === 'draft') bg-gray-100 text-gray-800
                                @else bg-red-100 text-red-800
                                @endif px-2 py-1 rounded-full">
                                {{ ucfirst($challenge->status) }}
                            </span>
                        </div>
                        <p class="text-sm text-[#9B9EA4] mb-3">{{ Str::limit($challenge->description, 120) }}</p>
                        <div class="flex justify-between items-center text-xs text-[#9B9EA4]">
                            <span>Deadline: {{ $challenge->deadline?->format('M j, Y') ?? 'No deadline' }}</span>
                            <span>0 submissions</span> {{-- TODO: Count actual submissions --}}
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        <p class="mt-2 text-[#9B9EA4]">No challenges created yet</p>
                        <flux:button href="#" variant="primary" size="sm" class="mt-3">
                            Create Your First Challenge
                        </flux:button>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Today's Activity -->
    <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
        <h3 class="text-lg font-semibold text-[#231F20] mb-4">Today's Activity</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="text-center">
                <p class="text-3xl font-bold text-[#231F20]">{{ $recentActivity['ideas_submitted_today'] }}</p>
                <p class="text-sm text-[#9B9EA4]">New Ideas Submitted</p>
            </div>
            <div class="text-center">
                <p class="text-3xl font-bold text-[#231F20]">{{ $recentActivity['reviews_completed_today'] }}</p>
                <p class="text-sm text-[#9B9EA4]">Reviews Completed by You</p>
            </div>
            <div class="text-center">
                <p class="text-3xl font-bold text-[#231F20]">{{ $reviewStats['pending_manager_reviews'] }}</p>
                <p class="text-sm text-[#9B9EA4]">Reviews Pending</p>
            </div>
        </div>
    </div>

    <!-- Manager Tips -->
    <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
        <h3 class="text-lg font-semibold text-[#231F20] mb-4">Review Guidelines</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-[#F8EBD5] rounded">
                <h4 class="font-medium text-[#231F20] mb-2">Initial Assessment</h4>
                <p class="text-sm text-[#9B9EA4]">Evaluate feasibility, alignment with KeNHA objectives, and initial resource requirements.</p>
            </div>
            <div class="p-4 bg-[#F8EBD5] rounded">
                <h4 class="font-medium text-[#231F20] mb-2">Constructive Feedback</h4>
                <p class="text-sm text-[#9B9EA4]">Provide clear, actionable feedback to help ideas improve and progress through the system.</p>
            </div>
            <div class="p-4 bg-[#F8EBD5] rounded">
                <h4 class="font-medium text-[#231F20] mb-2">SME Referral</h4>
                <p class="text-sm text-[#9B9EA4]">When in doubt, refer promising ideas to Subject Matter Experts for technical evaluation.</p>
            </div>
        </div>
    </div>
    
    {{-- Gamification Achievement Notifications --}}
    <livewire:components.achievement-notifications />
</div>
