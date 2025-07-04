<?php

use Livewire\Volt\Component;
use App\Models\Idea;
use App\Models\Review;
use App\Models\Challenge;
use App\Services\AchievementService;
use Livewire\Attributes\{Layout, Title};

new #[Layout('components.layouts.app')] #[Title('Board Member Dashboard')] class extends Component
{
    public $pendingApprovals;
    public $recentDecisions;
    public $systemStats;
    public $monthlyMetrics;

    public function mount()
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        // Ideas requiring board approval
        $this->pendingApprovals = Idea::where('current_stage', 'board_review')
            ->with(['author', 'category', 'reviews'])
            ->orderBy('submitted_at')
            ->take(10)
            ->get();

        // Recent board decisions
        $this->recentDecisions = Review::where('reviewer_id', auth()->id())
            ->where('review_stage', 'board_review')
            ->with(['idea.author'])
            ->latest()
            ->take(8)
            ->get();

        // High-level system statistics
        $this->systemStats = [
            'total_ideas' => Idea::count(),
            'ideas_this_month' => Idea::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'pending_board_review' => Idea::where('current_stage', 'board_review')->count(),
            'approved_this_month' => Review::where('reviewer_id', auth()->id())
                ->where('review_stage', 'board_review')
                ->where('decision', 'approved')
                ->whereMonth('created_at', now()->month)
                ->count(),
            'total_challenges' => Challenge::count(),
            'active_challenges' => Challenge::where('status', 'active')
                ->where('deadline', '>', now())
                ->count(),
        ];

        // Monthly metrics for trend analysis
        $this->monthlyMetrics = $this->getMonthlyMetrics();
    }

    private function getMonthlyMetrics()
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = [
                'month' => $date->format('M'),
                'ideas' => Idea::whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->count(),
                'approvals' => Review::where('reviewer_id', auth()->id())
                    ->where('review_stage', 'board_review')
                    ->where('decision', 'approved')
                    ->whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->count(),
            ];
        }
        return $months;
    }

    public function quickApprove($ideaId)
    {
        $idea = Idea::findOrFail($ideaId);
        
        // Create board review
        Review::create([
            'idea_id' => $idea->id,
            'reviewer_id' => auth()->id(),
            'review_stage' => 'board_review',
            'decision' => 'approved',
            'rating' => 5,
            'comments' => 'Quick approval - meets strategic objectives',
        ]);

        // Update idea stage
        $idea->update(['current_stage' => 'implementation']);

        $this->loadDashboardData();
        $this->dispatch('idea-approved');
    }

    public function viewIdea($ideaId)
    {
        return redirect()->route('ideas.show', $ideaId);
    }
}; ?>

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 right-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 left-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 right-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Gamification Integration for Board Member Dashboard --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-2">
                <div class="group relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl p-6">
                    <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/5 via-transparent to-[#F8EBD5]/10"></div>
                    <div class="relative">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Gamification Dashboard</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            {{-- Leaderboard Card --}}
                            <a href="{{ route('gamification.leaderboard') }}" class="group/card relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 border border-blue-200 dark:border-blue-700/50 p-4 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-500 dark:bg-blue-600 flex items-center justify-center">
                                        <flux:icon.trophy class="w-5 h-5 text-white" />
                                    </div>
                                    <flux:icon.chevron-right class="w-4 h-4 text-blue-600 dark:text-blue-400 group-hover/card:translate-x-1 transition-transform" />
                                </div>
                                <h4 class="font-semibold text-blue-900 dark:text-blue-100 text-sm">Leaderboard</h4>
                                <p class="text-blue-700 dark:text-blue-300 text-xs mt-1">Organization Rankings</p>
                            </a>

                            {{-- Points Card --}}
                            <a href="{{ route('gamification.points') }}" class="group/card relative overflow-hidden rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-800/20 border border-emerald-200 dark:border-emerald-700/50 p-4 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="w-10 h-10 rounded-lg bg-emerald-500 dark:bg-emerald-600 flex items-center justify-center">
                                        <flux:icon.currency-dollar class="w-5 h-5 text-white" />
                                    </div>
                                    <flux:icon.chevron-right class="w-4 h-4 text-emerald-600 dark:text-emerald-400 group-hover/card:translate-x-1 transition-transform" />
                                </div>
                                <h4 class="font-semibold text-emerald-900 dark:text-emerald-100 text-sm">Points & History</h4>
                                <p class="text-emerald-700 dark:text-emerald-300 text-xs mt-1">{{ number_format($this->userPoints ?? 0) }} total points</p>
                            </a>

                            {{-- Achievements Card --}}
                            <a href="{{ route('gamification.achievements') }}" class="group/card relative overflow-hidden rounded-xl bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 border border-purple-200 dark:border-purple-700/50 p-4 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="w-10 h-10 rounded-lg bg-purple-500 dark:bg-purple-600 flex items-center justify-center">
                                        <flux:icon.star class="w-5 h-5 text-white" />
                                    </div>
                                    <flux:icon.chevron-right class="w-4 h-4 text-purple-600 dark:text-purple-400 group-hover/card:translate-x-1 transition-transform" />
                                </div>
                                <h4 class="font-semibold text-purple-900 dark:text-purple-100 text-sm">Achievements</h4>
                                <p class="text-purple-700 dark:text-purple-300 text-xs mt-1">{{ $this->userAchievements ?? 0 }} unlocked</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-1">
                <div class="group relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl p-6">
                    <div class="absolute inset-0 bg-gradient-to-r from-amber-500/5 via-transparent to-amber-400/10"></div>
                    <div class="relative">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Board Performance</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Overall Rank</span>
                                <span class="font-semibold text-gray-900 dark:text-white">#{{ $this->userRank ?? '--' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Board Rank</span>
                                <span class="font-semibold text-gray-900 dark:text-white">#{{ $this->boardRank ?? '--' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Final Reviews</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ $this->finalReviews ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Decisions Made</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ $this->decisionsMade ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Welcome Header with Personalized Greeting --}}
        <div class="group relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
            {{-- Gradient Overlay --}}
            <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/10 via-transparent to-[#F8EBD5]/20 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/10"></div>
            
            <div class="relative p-8 flex items-center justify-between">
                <div class="flex items-center space-x-6">
                <div class="ml-4">
                    <p class="text-sm font-medium text-[#9B9EA4]">Pending Approvals</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $systemStats['pending_board_review'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-[#9B9EA4]">Ideas This Month</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $systemStats['ideas_this_month'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-[#FFF200]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-[#9B9EA4]">Approved This Month</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $systemStats['approved_this_month'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-[#9B9EA4]">Active Challenges</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $systemStats['active_challenges'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Monthly Trends --}}
    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
        <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
            <h3 class="text-lg font-semibold text-[#231F20]">Innovation Trends (Last 6 Months)</h3>
            <p class="text-sm text-[#9B9EA4]">Track submission and approval patterns</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-6 gap-4">
                @foreach($monthlyMetrics as $month)
                    <div class="text-center">
                        <div class="mb-2">
                            <div class="h-24 flex items-end justify-center space-x-1">
                                <div class="bg-blue-200 rounded-t" style="height: {{ max($month['ideas'] * 4, 8) }}px; width: 12px;" title="Ideas: {{ $month['ideas'] }}"></div>
                                <div class="bg-green-200 rounded-t" style="height: {{ max($month['approvals'] * 8, 4) }}px; width: 12px;" title="Approvals: {{ $month['approvals'] }}"></div>
                            </div>
                        </div>
                        <p class="text-xs font-medium text-[#231F20]">{{ $month['month'] }}</p>
                        <p class="text-xs text-[#9B9EA4]">{{ $month['ideas'] }} / {{ $month['approvals'] }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 flex justify-center space-x-4 text-xs">
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-blue-200 rounded mr-1"></div>
                    <span class="text-[#9B9EA4]">Ideas Submitted</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-green-200 rounded mr-1"></div>
                    <span class="text-[#9B9EA4]">Board Approvals</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Pending Board Approvals --}}
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
            <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                <h3 class="text-lg font-semibold text-[#231F20]">Pending Strategic Decisions</h3>
                <p class="text-sm text-[#9B9EA4]">Ideas requiring board-level approval</p>
            </div>
            <div class="p-6 max-h-96 overflow-y-auto">
                @forelse($pendingApprovals as $idea)
                    <div class="flex items-start space-x-4 py-3 border-b border-[#9B9EA4]/10 last:border-b-0">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-[#FFF200]/20 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[#231F20] truncate">{{ $idea->title }}</p>
                            <p class="text-xs text-[#9B9EA4]">By {{ $idea->author->first_name }} {{ $idea->author->last_name }}</p>
                            <p class="text-xs text-[#9B9EA4]">Submitted {{ $idea->submitted_at?->diffForHumans() }}</p>
                            @if($idea->reviews->count() > 0)
                                <div class="flex items-center mt-1">
                                    <span class="text-xs text-[#9B9EA4]">Avg Rating:</span>
                                    <div class="flex ml-1">
                                        @php $avgRating = $idea->reviews->avg('rating') @endphp
                                        @for($i = 1; $i <= 5; $i++)
                                            <svg class="w-3 h-3 {{ $i <= $avgRating ? 'text-[#FFF200]' : 'text-[#9B9EA4]' }}" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                            </svg>
                                        @endfor
                                        <span class="text-xs text-[#9B9EA4] ml-1">({{ number_format($avgRating, 1) }})</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-col space-y-1">
                            <button wire:click="viewIdea({{ $idea->id }})" class="inline-flex items-center px-3 py-1 border border-[#9B9EA4] text-xs font-medium rounded-md text-[#231F20] bg-white hover:bg-[#F8EBD5] transition-colors">
                                Review
                            </button>
                            <button wire:click="quickApprove({{ $idea->id }})" class="inline-flex items-center px-3 py-1 border border-green-500 text-xs font-medium rounded-md text-green-700 bg-green-50 hover:bg-green-100 transition-colors">
                                Quick Approve
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-[#231F20]">All caught up!</h3>
                        <p class="mt-1 text-sm text-[#9B9EA4]">No ideas pending board approval at this time.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Recent Decisions --}}
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
            <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                <h3 class="text-lg font-semibold text-[#231F20]">Recent Board Decisions</h3>
                <p class="text-sm text-[#9B9EA4]">Your latest strategic approvals and decisions</p>
            </div>
            <div class="p-6 max-h-96 overflow-y-auto">
                @forelse($recentDecisions as $review)
                    <div class="flex items-start space-x-4 py-3 border-b border-[#9B9EA4]/10 last:border-b-0">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 {{ $review->decision === 'approved' ? 'bg-green-100' : 'bg-red-100' }} rounded-full flex items-center justify-center">
                                @if($review->decision === 'approved')
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                @endif
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[#231F20] truncate">{{ $review->idea->title }}</p>
                            <p class="text-xs text-[#9B9EA4]">By {{ $review->idea->author->first_name }} {{ $review->idea->author->last_name }}</p>
                            <div class="flex items-center mt-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $review->decision === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($review->decision) }}
                                </span>
                                <span class="text-xs text-[#9B9EA4] ml-2">{{ $review->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                        <div class="flex items-center">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-3 h-3 {{ $i <= $review->rating ? 'text-[#FFF200]' : 'text-[#9B9EA4]' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                            @endfor
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-[#231F20]">No decisions yet</h3>
                        <p class="mt-1 text-sm text-[#9B9EA4]">Your board decisions will appear here once you start reviewing ideas.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Strategic Insights --}}
    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
        <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
            <h3 class="text-lg font-semibold text-[#231F20]">Strategic Innovation Insights</h3>
            <p class="text-sm text-[#9B9EA4]">Key performance indicators for organizational innovation</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-100 rounded-lg mb-3">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $systemStats['total_ideas'] }}</p>
                    <p class="text-sm text-[#9B9EA4]">Total Ideas Submitted</p>
                    <p class="text-xs text-[#9B9EA4] mt-1">Organizational innovation pipeline</p>
                </div>
                
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-green-100 rounded-lg mb-3">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    @php 
                        $approvalRate = $systemStats['total_ideas'] > 0 
                            ? round((Review::where('review_stage', 'board_review')->where('decision', 'approved')->count() / $systemStats['total_ideas']) * 100, 1)
                            : 0;
                    @endphp
                    <p class="text-2xl font-bold text-[#231F20]">{{ $approvalRate }}%</p>
                    <p class="text-sm text-[#9B9EA4]">Board Approval Rate</p>
                    <p class="text-xs text-[#9B9EA4] mt-1">Strategic alignment success</p>
                </div>
                
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-[#FFF200]/20 rounded-lg mb-3">
                        <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $systemStats['total_challenges'] }}</p>
                    <p class="text-sm text-[#9B9EA4]">Innovation Challenges</p>
                    <p class="text-xs text-[#9B9EA4] mt-1">Targeted innovation drivers</p>
                </div>
            </div>
        </div>
        
        {{-- Gamification Achievement Notifications --}}
        <livewire:components.achievement-notifications />
    </div>
</div>
