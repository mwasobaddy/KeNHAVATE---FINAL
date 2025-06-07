<?php

use Livewire\Volt\Component;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Review;
use App\Services\AchievementService;


new #[Layout('components.layouts.app', title: 'Challenge Reviewer Dashboard')] class extends Component
{
    public $assignedChallenges;
    public $pendingReviews;
    public $completedReviews;
    public $reviewStats;

    public function mount()
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        $userId = auth()->id();

        // Challenges assigned to this reviewer
        $this->assignedChallenges = Challenge::whereHas('reviewers', function($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->with(['creator', 'submissions'])
            ->where('status', 'active')
            ->latest()
            ->take(5)
            ->get();

        // Challenge submissions pending review
        $this->pendingReviews = ChallengeSubmission::whereHas('challenge.reviewers', function($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->whereDoesntHave('reviews', function($query) use ($userId) {
                $query->where('reviewer_id', $userId);
            })
            ->where('participant_id', '!=', $userId) // Cannot review own submissions
            ->with(['challenge', 'participant'])
            ->latest('submitted_at')
            ->take(8)
            ->get();

        // Recently completed reviews
        $this->completedReviews = Review::where('reviewer_id', $userId)
            ->where('review_type', 'challenge')
            ->with(['challengeSubmission.challenge', 'challengeSubmission.participant'])
            ->latest()
            ->take(5)
            ->get();

        // Review statistics
        $this->reviewStats = [
            'assigned_challenges' => $this->assignedChallenges->count(),
            'pending_reviews' => $this->pendingReviews->count(),
            'completed_reviews' => Review::where('reviewer_id', $userId)
                ->where('review_type', 'challenge')
                ->count(),
            'reviews_this_month' => Review::where('reviewer_id', $userId)
                ->where('review_type', 'challenge')
                ->whereMonth('created_at', now()->month)
                ->count(),
            'average_rating' => Review::where('reviewer_id', $userId)
                ->where('review_type', 'challenge')
                ->avg('rating') ?? 0,
        ];
    }

    public function quickReview($submissionId, $rating, $decision)
    {
        $submission = ChallengeSubmission::findOrFail($submissionId);
        
        // Check if reviewer is assigned to this challenge
        if (!$submission->challenge->reviewers->contains(auth()->id())) {
            $this->addError('review', 'You are not assigned to review this challenge.');
            return;
        }

        // Create review
        Review::create([
            'challenge_submission_id' => $submission->id,
            'reviewer_id' => auth()->id(),
            'review_type' => 'challenge',
            'decision' => $decision,
            'rating' => $rating,
            'comments' => 'Quick review - ' . ucfirst($decision),
        ]);

        $this->loadDashboardData();
        $this->dispatch('review-completed');
    }

    public function viewSubmission($submissionId)
    {
        return redirect()->route('challenge-submissions.show', $submissionId);
    }

    public function viewChallenge($challengeId)
    {
        return redirect()->route('challenges.show', $challengeId);
    }
}; ?>

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-purple-500/20 dark:bg-purple-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-orange-500/10 dark:bg-orange-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Gamification Integration for Challenge Reviewer Dashboard --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-2">
                <livewire:components.points-widget />
            </div>
            <div class="lg:col-span-1">
                <livewire:components.leaderboard :mini="true" :role-filter="'challenge_reviewer'" />
            </div>
        </div>

        {{-- Welcome Section with Glass Morphism --}}
        <div class="group relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
            {{-- Gradient Overlay --}}
            <div class="absolute inset-0 bg-gradient-to-r from-purple-500/10 via-transparent to-[#F8EBD5]/20 dark:from-purple-400/10 dark:via-transparent dark:to-amber-400/10"></div>
            
            <div class="relative p-8 flex items-center">
                <div class="flex items-center space-x-6">
                    {{-- Avatar with Glow Effect --}}
                    <div class="relative hidden md:flex">
                        <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                            <span class="text-2xl font-bold text-white">{{ auth()->user()->initials() }}</span>
                        </div>
                        <div class="absolute -inset-2 bg-purple-500/20 dark:bg-purple-400/20 rounded-2xl blur-lg -z-10"></div>
                    </div>
                    
                    <div>
                        <h1 class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">
                            Welcome, {{ auth()->user()->first_name }}! 👋
                        </h1>
                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">
                            As a Challenge Reviewer, you evaluate innovative solutions and help identify the best ideas.
                            <span class="inline-flex items-center ml-2 text-sm font-medium text-purple-600 dark:text-purple-400 bg-[#231F20] dark:bg-zinc-700 px-3 py-1 rounded-full">
                                {{ now()->format('l, M j') }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Enhanced Statistics Cards with Glass Morphism --}}
        <section aria-labelledby="stats-heading" class="group">
            <h2 id="stats-heading" class="sr-only">Dashboard Statistics</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Assigned Challenges Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    {{-- Animated Gradient Background --}}
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-transparent to-purple-600/10 dark:from-purple-400/10 dark:via-transparent dark:to-purple-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        {{-- Icon with Glow Effect --}}
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-purple-500/20 dark:bg-purple-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Assigned Challenges</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-purple-600 dark:group-hover/card:text-purple-400 transition-colors duration-300">{{ number_format($reviewStats['assigned_challenges']) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-purple-500 dark:bg-purple-400 rounded-full"></div>
                                <span>Challenge reviewer</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pending Reviews Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-orange-500/5 via-transparent to-orange-600/10 dark:from-orange-400/10 dark:via-transparent dark:to-orange-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-orange-500 to-orange-600 dark:from-orange-400 dark:to-orange-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-orange-500/20 dark:bg-orange-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Pending Reviews</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-orange-600 dark:group-hover/card:text-orange-400 transition-colors duration-300">{{ number_format($reviewStats['pending_reviews']) }}</p>
                            
                            @if($reviewStats['pending_reviews'] > 0)
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/30 px-3 py-1.5 rounded-full">
                                    <div class="w-2 h-2 bg-orange-500 dark:bg-orange-400 rounded-full animate-ping"></div>
                                    <span>Awaiting review</span>
                                </div>
                            @else
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 px-3 py-1.5 rounded-full">
                                    <div class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full"></div>
                                    <span>All clear</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Completed Reviews Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-green-500/5 via-transparent to-green-600/10 dark:from-green-400/10 dark:via-transparent dark:to-green-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-green-500 to-green-600 dark:from-green-400 dark:to-green-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-green-500/20 dark:bg-green-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Completed Reviews</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-green-600 dark:group-hover/card:text-green-400 transition-colors duration-300">{{ number_format($reviewStats['completed_reviews']) }}</p>
                            
                            @if($reviewStats['completed_reviews'] > 0)
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30 px-3 py-1.5 rounded-full">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>Evaluation complete</span>
                                </div>
                            @else
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 px-3 py-1.5 rounded-full">
                                    <span>Start reviewing</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Average Rating Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-yellow-500/5 via-transparent to-yellow-600/10 dark:from-yellow-400/10 dark:via-transparent dark:to-yellow-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#FFF200] to-amber-500 dark:from-yellow-400 dark:to-amber-400 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-yellow-500/20 dark:bg-yellow-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Average Rating</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-amber-600 dark:group-hover/card:text-amber-400 transition-colors duration-300">{{ number_format($reviewStats['average_rating'], 1) }}</p>
                            
                            <div class="flex items-center space-x-1">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-4 h-4 {{ $i <= $reviewStats['average_rating'] ? 'text-[#FFF200] dark:text-yellow-400' : 'text-gray-300 dark:text-gray-600' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                @endfor
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Main Content with Adaptive Layout --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            {{-- Pending Reviews Section - Responsive 2 columns on xl screens --}}
            <div class="xl:col-span-2 group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl h-full">
                    {{-- Header with Modern Typography --}}
                    <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-600 dark:from-orange-400 dark:to-orange-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Pending Challenge Reviews</h3>
                                    <p class="text-[#9B9EA4] text-sm">Submissions awaiting your evaluation</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Pending Reviews List with Enhanced Cards --}}
                    <div class="p-8 space-y-6 max-h-[32rem] overflow-y-auto">
                        @forelse($pendingReviews as $submission)
                            <div class="group/review relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-1">
                                {{-- Status Indicator Strip --}}
                                <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-orange-400 to-orange-500"></div>
                                
                                <div class="p-6 pl-8">
                                    <div class="flex justify-between items-start mb-4">
                                        <h4 class="font-bold text-xl text-[#231F20] dark:text-zinc-100 group-hover/review:text-orange-600 dark:group-hover/review:text-orange-400 transition-colors duration-300 leading-tight">
                                            {{ $submission->title }}
                                        </h4>
                                        
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold shrink-0 ml-4 bg-orange-50 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 border border-orange-200 dark:border-orange-600">
                                            <div class="w-2 h-2 bg-current rounded-full mr-1 animate-pulse"></div>
                                            Pending Review
                                        </span>
                                    </div>
                                    
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 mb-4 leading-relaxed line-clamp-2">
                                        Challenge: {{ $submission->challenge->title }} <br>
                                        By {{ $submission->participant->first_name }} {{ $submission->participant->last_name }} • {{ $submission->submitted_at?->diffForHumans() }}
                                    </p>
                                    
                                    {{-- Enhanced Footer --}}
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center space-x-4 text-xs text-[#9B9EA4] dark:text-zinc-400"></div>
                                        
                                        <div class="flex items-center space-x-2">
                                            <button wire:click="quickReview({{ $submission->id }}, 4, 'approved')" class="group/btn inline-flex items-center px-3 py-1.5 text-sm bg-gradient-to-r from-green-500/80 to-green-600/80 hover:from-green-600 hover:to-green-700 text-white font-medium rounded-xl shadow-md hover:shadow-lg transition-all duration-300">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                <span>Approve</span>
                                            </button>
                                            <button wire:click="quickReview({{ $submission->id }}, 2, 'rejected')" class="group/btn inline-flex items-center px-3 py-1.5 text-sm bg-gradient-to-r from-red-500/80 to-red-600/80 hover:from-red-600 hover:to-red-700 text-white font-medium rounded-xl shadow-md hover:shadow-lg transition-all duration-300">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                                <span>Reject</span>
                                            </button>
                                            <button wire:click="viewSubmission({{ $submission->id }})" class="group/link inline-flex items-center space-x-2 text-[#231F20] dark:text-zinc-100 font-medium hover:text-orange-600 dark:hover:text-orange-400 px-3 py-1.5 bg-white/50 dark:bg-zinc-700/50 rounded-xl transition-colors duration-300">
                                                <span>Details</span>
                                                <svg class="w-4 h-4 transform group-hover/link:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            {{-- Enhanced Empty State --}}
                            <div class="text-center relative py-12">
                                {{-- Floating Elements --}}
                                <div class="absolute inset-0 flex items-center justify-center opacity-5 dark:opacity-10">
                                    <svg class="w-64 h-64 text-orange-500 dark:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                
                                <div class="relative z-10">
                                    <div class="w-20 h-20 bg-gradient-to-br from-orange-500 to-orange-600 dark:from-orange-400 dark:to-orange-500 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    
                                    <h4 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">All Caught Up!</h4>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 mb-6 max-w-md mx-auto leading-relaxed">
                                        There are currently no submissions waiting for your review. Check back later for new entries.
                                    </p>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Assigned Challenges - Enhanced Sidebar --}}
            <div class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl h-full">
                    {{-- Header --}}
                    <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                        <div class="flex items-center space-x-4 mb-2">
                            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Assigned Challenges</h3>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Challenges you're responsible for reviewing</p>
                            </div>
                        </div>
                    </div>

                    {{-- Challenges List --}}
                    <div class="p-8 space-y-6 max-h-[32rem] overflow-y-auto">
                        @forelse($assignedChallenges as $challenge)
                            <div class="group/challenge relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-purple-50/30 dark:from-zinc-800/50 dark:to-purple-900/20 border border-purple-200/40 dark:border-purple-600/30 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-1">
                                {{-- Challenge Priority Indicator --}}
                                <div class="absolute top-4 right-4 w-3 h-3 bg-purple-500 dark:bg-purple-400 rounded-full animate-pulse"></div>
                                
                                <div class="p-6">
                                    <h4 class="font-bold text-lg text-[#231F20] dark:text-zinc-100 mb-2 group-hover/challenge:text-purple-600 dark:group-hover/challenge:text-purple-400 transition-colors duration-300 leading-tight">
                                        {{ $challenge->title }}
                                    </h4>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 mb-4 text-sm leading-relaxed">
                                        Created by {{ $challenge->creator->first_name }} {{ $challenge->creator->last_name }}<br>
                                        Deadline: {{ $challenge->deadline->format('M j, Y') }}
                                    </p>
                                    
                                    {{-- Submission Info --}}
                                    <div class="flex items-center justify-between mb-4">
                                        <span class="inline-flex items-center text-xs text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-3 py-1.5 rounded-full font-medium">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            {{ $challenge->submissions->count() }} {{ Str::plural('submission', $challenge->submissions->count()) }}
                                        </span>
                                    </div>
                                    
                                    <button wire:click="viewChallenge({{ $challenge->id }})" class="w-full group bg-gradient-to-r from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 hover:from-[#231F20] hover:to-[#231F20] dark:hover:from-zinc-800 dark:hover:to-zinc-700 text-white dark:text-white font-semibold py-3 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-300">
                                        <span>View Challenge</span>
                                        <svg class="ml-2 w-4 h-4 transform group-hover:translate-x-1 transition-transform duration-300 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @empty
                            {{-- Enhanced Empty State for Challenges --}}
                            <div class="text-center py-12 relative">
                                <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                                
                                <h4 class="text-lg font-bold text-[#231F20] dark:text-zinc-100 mb-2">No Assigned Challenges</h4>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed">
                                    You haven't been assigned to review any challenges yet.<br>
                                    Check back later for new assignments.
                                </p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Enhanced Recent Review Activity with Modern Glass Design --}}
        <section aria-labelledby="recent-activity-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Animated Background Elements --}}
                <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-green-500/10 via-[#F8EBD5]/5 to-transparent dark:from-green-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-64 h-64 bg-gradient-to-tr from-purple-500/5 via-purple-500/5 to-transparent dark:from-purple-400/10 dark:via-purple-400/10 dark:to-transparent rounded-full -ml-32 -mb-32 blur-2xl"></div>
                
                <div class="relative z-10 p-8">
                    {{-- Enhanced Header --}}
                    <div class="flex items-center space-x-4 mb-8">
                        <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 dark:from-green-400 dark:to-green-500 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 id="recent-activity-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Recent Review Activity</h3>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Your latest challenge submission reviews</p>
                        </div>
                    </div>
                    
                    {{-- Recent Reviews Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @forelse($completedReviews as $review)
                            <div class="group/review relative overflow-hidden rounded-2xl bg-gradient-to-br {{ $review->decision === 'approved' ? 'from-white/50 to-green-50/30 dark:from-zinc-800/50 dark:to-green-900/20 border-green-200/40 dark:border-green-600/30' : 'from-white/50 to-red-50/30 dark:from-zinc-800/50 dark:to-red-900/20 border-red-200/40 dark:border-red-600/30' }} border backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-2">
                                <div class="p-6">
                                    {{-- Review Status Icon --}}
                                    <div class="relative mb-4">
                                        <div class="w-12 h-12 rounded-2xl {{ $review->decision === 'approved' ? 'bg-gradient-to-br from-green-500 to-green-600 dark:from-green-400 dark:to-green-500' : 'bg-gradient-to-br from-red-500 to-red-600 dark:from-red-400 dark:to-red-500' }} flex items-center justify-center shadow-lg">
                                            @if($review->decision === 'approved')
                                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            @else
                                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            @endif
                                        </div>
                                        <div class="absolute -inset-2 {{ $review->decision === 'approved' ? 'bg-green-500/20 dark:bg-green-400/30' : 'bg-red-500/20 dark:bg-red-400/30' }} rounded-2xl blur-xl opacity-0 group-hover/review:opacity-100 transition-opacity duration-500"></div>
                                    </div>
                                    
                                    <h4 class="font-bold text-lg text-[#231F20] dark:text-zinc-100 mb-2 group-hover/review:{{ $review->decision === 'approved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} transition-colors duration-300 line-clamp-1">
                                        {{ $review->challengeSubmission->title }}
                                    </h4>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 mb-3 text-sm leading-relaxed">
                                        {{ $review->challengeSubmission->challenge->title }}<br>
                                        By {{ $review->challengeSubmission->participant->first_name }} {{ $review->challengeSubmission->participant->last_name }}
                                    </p>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $review->decision === 'approved' ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' }}">
                                            {{ ucfirst($review->decision) }}
                                        </span>
                                        <div class="flex items-center">
                                            @for($i = 1; $i <= 5; $i++)
                                                <svg class="w-4 h-4 {{ $i <= $review->rating ? 'text-[#FFF200] dark:text-yellow-400' : 'text-gray-300 dark:text-gray-600' }}" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                                </svg>
                                            @endfor
                                        </div>
                                    </div>
                                    <div class="mt-3 text-xs text-[#9B9EA4] dark:text-zinc-400">
                                        Reviewed {{ $review->created_at->diffForHumans() }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-3 text-center py-12 relative">
                                <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 dark:from-green-400 dark:to-green-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                
                                <h4 class="text-lg font-bold text-[#231F20] dark:text-zinc-100 mb-2">No Reviews Yet</h4>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed">
                                    Start reviewing challenge submissions to see your activity here.
                                </p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    {{-- Enhanced Floating Action Button --}}
    <div class="fixed bottom-6 right-6 z-50 group/fab">
        {{-- Main FAB Button --}}
        <button wire:click="loadDashboardData"
                class="group relative w-16 h-16 bg-gradient-to-br from-purple-500 via-purple-600 to-purple-500 dark:from-purple-400 dark:via-purple-500 dark:to-purple-400 hover:from-[#231F20] hover:to-[#231F20] dark:hover:from-zinc-800 dark:hover:to-zinc-700 text-white dark:text-white hover:text-purple-400 dark:hover:text-purple-400 rounded-2xl shadow-2xl hover:shadow-3xl flex items-center justify-center transition-all duration-500 ease-out transform hover:scale-110 hover:-translate-y-2">
            
            {{-- Glow Effect --}}
            <div class="absolute -inset-1 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-2xl blur-lg opacity-60 group-hover:opacity-100 transition-opacity duration-500"></div>
            
            {{-- Icon --}}
            <div class="relative z-10">
                <svg class="w-7 h-7 transform group-hover:rotate-180 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </div>
            
            {{-- Ripple Effect --}}
            <div class="absolute inset-0 rounded-2xl overflow-hidden">
                <div class="absolute inset-0 bg-white/20 dark:bg-purple-400/20 scale-0 group-hover:scale-100 transition-transform duration-500 rounded-2xl"></div>
            </div>
        </button>
        
        {{-- Enhanced Tooltip --}}
        <div class="absolute right-20 top-1/2 transform -translate-y-1/2 opacity-0 group-hover/fab:opacity-100 transition-all duration-300 translate-x-2 group-hover/fab:translate-x-0 pointer-events-none">
            <div class="relative">
                <div class="bg-[#231F20] dark:bg-zinc-800 text-purple-500 dark:text-purple-400 px-4 py-2 rounded-xl shadow-xl backdrop-blur-sm text-sm font-semibold whitespace-nowrap">
                    Refresh Dashboard
                </div>
                <div class="absolute top-1/2 -right-1 transform -translate-y-1/2 w-2 h-2 bg-[#231F20] dark:bg-zinc-800 rotate-45"></div>
                <div class="absolute inset-0 bg-[#231F20] dark:bg-zinc-800 rounded-xl blur-md opacity-50 -z-10"></div>
            </div>
        </div>
        
        {{-- Gamification Achievement Notifications --}}
        <livewire:components.achievement-notifications />
    </div>
</div>
