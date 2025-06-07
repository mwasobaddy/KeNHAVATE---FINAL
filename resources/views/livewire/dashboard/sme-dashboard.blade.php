<?php

use Livewire\Volt\Component;
use App\Models\Idea;
use App\Models\Review;
use App\Models\Collaboration;
use App\Services\AchievementService;

new #[Layout('components.layouts.app', title: 'SME Dashboard')] class extends Component
{
    public $pendingReviews;
    public $completedReviews;
    public $collaborationRequests;
    public $myCollaborations;
    public $reviewStats;

    public function mount()
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        $userId = auth()->id();

        // Ideas assigned to this SME for review
        $this->pendingReviews = Idea::where('current_stage', 'sme_review')
            ->whereDoesntHave('reviews', function($query) use ($userId) {
                $query->where('reviewer_id', $userId);
            })
            ->where('author_id', '!=', $userId) // Cannot review own ideas
            ->with(['author', 'category'])
            ->latest('submitted_at')
            ->take(5)
            ->get();

        // Recently completed reviews
        $this->completedReviews = Review::where('reviewer_id', $userId)
            ->with(['idea.author', 'idea.category'])
            ->latest()
            ->take(5)
            ->get();

        // Pending collaboration requests
        $this->collaborationRequests = Collaboration::where('collaborator_id', $userId)
            ->where('status', 'pending')
            ->with(['idea.author'])
            ->latest()
            ->take(5)
            ->get();

        // Active collaborations
        $this->myCollaborations = Collaboration::where('collaborator_id', $userId)
            ->where('status', 'accepted')
            ->with(['idea.author'])
            ->latest()
            ->take(5)
            ->get();

        // Review statistics
        $this->reviewStats = [
            'total_reviews' => Review::where('reviewer_id', $userId)->count(),
            'reviews_this_month' => Review::where('reviewer_id', $userId)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'average_rating' => Review::where('reviewer_id', $userId)
                ->avg('rating') ?? 0,
            'pending_count' => $this->pendingReviews->count(),
            'collaboration_count' => $this->myCollaborations->count(),
        ];
    }

    public function acceptCollaboration($collaborationId)
    {
        $collaboration = Collaboration::findOrFail($collaborationId);
        
        if ($collaboration->collaborator_id !== auth()->id()) {
            $this->addError('collaboration', 'Unauthorized action.');
            return;
        }

        $collaboration->update([
            'status' => 'accepted',
            'responded_at' => now()
        ]);

        $this->loadDashboardData();
        $this->dispatch('collaboration-updated');
    }

    public function declineCollaboration($collaborationId)
    {
        $collaboration = Collaboration::findOrFail($collaborationId);
        
        if ($collaboration->collaborator_id !== auth()->id()) {
            $this->addError('collaboration', 'Unauthorized action.');
            return;
        }

        $collaboration->update([
            'status' => 'declined',
            'responded_at' => now()
        ]);

        $this->loadDashboardData();
        $this->dispatch('collaboration-updated');
    }
}; ?>

<div class="space-y-6">
    {{-- Gamification Integration for SME Dashboard --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                <h3 class="font-semibold text-[#231F20] mb-4">Gamification Hub</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    {{-- Leaderboard Card --}}
                    <a href="{{ route('gamification.leaderboard') }}" class="group relative overflow-hidden rounded-lg bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 p-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-1">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-500 flex items-center justify-center">
                                <flux:icon.trophy class="w-4 h-4 text-white" />
                            </div>
                            <flux:icon.chevron-right class="w-3 h-3 text-blue-600 group-hover:translate-x-1 transition-transform" />
                        </div>
                        <h4 class="font-medium text-blue-900 text-sm">Leaderboard</h4>
                        <p class="text-blue-700 text-xs mt-1">SME Rankings</p>
                    </a>

                    {{-- Points Card --}}
                    <a href="{{ route('gamification.points') }}" class="group relative overflow-hidden rounded-lg bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200 p-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-1">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-500 flex items-center justify-center">
                                <flux:icon.currency-dollar class="w-4 h-4 text-white" />
                            </div>
                            <flux:icon.chevron-right class="w-3 h-3 text-emerald-600 group-hover:translate-x-1 transition-transform" />
                        </div>
                        <h4 class="font-medium text-emerald-900 text-sm">Points</h4>
                        <p class="text-emerald-700 text-xs mt-1">{{ number_format($this->userPoints ?? 0) }} pts</p>
                    </a>

                    {{-- Achievements Card --}}
                    <a href="{{ route('gamification.achievements') }}" class="group relative overflow-hidden rounded-lg bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 p-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-1">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-8 h-8 rounded-lg bg-purple-500 flex items-center justify-center">
                                <flux:icon.star class="w-4 h-4 text-white" />
                            </div>
                            <flux:icon.chevron-right class="w-3 h-3 text-purple-600 group-hover:translate-x-1 transition-transform" />
                        </div>
                        <h4 class="font-medium text-purple-900 text-sm">Achievements</h4>
                        <p class="text-purple-700 text-xs mt-1">{{ $this->userAchievements ?? 0 }} unlocked</p>
                    </a>
                </div>
            </div>
        </div>
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                <h3 class="font-semibold text-[#231F20] mb-4">SME Performance</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-[#231F20]/70">Rank among SMEs</span>
                        <span class="font-semibold text-[#231F20]">#{{ $this->smeRank ?? '--' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-[#231F20]/70">Reviews Completed</span>
                        <span class="font-semibold text-[#231F20]">{{ $this->reviewsCompleted ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-[#231F20]/70">Collaborations</span>
                        <span class="font-semibold text-[#231F20]">{{ $this->collaborationsCount ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Welcome Section --}}
    <div class="bg-[#F8EBD5] rounded-lg p-6 border border-[#9B9EA4]/20">
        <h2 class="text-2xl font-bold text-[#231F20] mb-2">Welcome, {{ auth()->user()->first_name }}!</h2>
        <p class="text-[#231F20]/70">As a Subject Matter Expert, you play a crucial role in evaluating innovative ideas and fostering collaboration.</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-[#9B9EA4]">Pending Reviews</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $reviewStats['pending_count'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-[#9B9EA4]">Total Reviews</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $reviewStats['total_reviews'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-[#9B9EA4]">Active Collaborations</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $reviewStats['collaboration_count'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-[#FFF200]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-[#9B9EA4]">Average Rating</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ number_format($reviewStats['average_rating'], 1) }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Pending Reviews --}}
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
            <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                <h3 class="text-lg font-semibold text-[#231F20]">Pending Reviews</h3>
                <p class="text-sm text-[#9B9EA4]">Ideas awaiting your expert evaluation</p>
            </div>
            <div class="p-6">
                @forelse($pendingReviews as $idea)
                    <div class="flex items-start space-x-4 py-3 border-b border-[#9B9EA4]/10 last:border-b-0">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[#231F20] truncate">{{ $idea->title }}</p>
                            <p class="text-xs text-[#9B9EA4]">By {{ $idea->author->first_name }} {{ $idea->author->last_name }}</p>
                            <p class="text-xs text-[#9B9EA4]">Submitted {{ $idea->submitted_at?->diffForHumans() }}</p>
                        </div>
                        <a href="{{ route('ideas.show', $idea) }}" class="inline-flex items-center px-3 py-1 border border-[#9B9EA4] text-xs font-medium rounded-md text-[#231F20] bg-white hover:bg-[#F8EBD5] transition-colors">
                            Review
                        </a>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-[#231F20]">No pending reviews</h3>
                        <p class="mt-1 text-sm text-[#9B9EA4]">All caught up! Check back later for new ideas to review.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Collaboration Requests --}}
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
            <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                <h3 class="text-lg font-semibold text-[#231F20]">Collaboration Requests</h3>
                <p class="text-sm text-[#9B9EA4]">Invitations to collaborate on innovative ideas</p>
            </div>
            <div class="p-6">
                @forelse($collaborationRequests as $collaboration)
                    <div class="flex items-start space-x-4 py-3 border-b border-[#9B9EA4]/10 last:border-b-0">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[#231F20] truncate">{{ $collaboration->idea->title }}</p>
                            <p class="text-xs text-[#9B9EA4]">Invited by {{ $collaboration->idea->author->first_name }} {{ $collaboration->idea->author->last_name }}</p>
                            <p class="text-xs text-[#9B9EA4]">{{ $collaboration->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex space-x-2">
                            <button wire:click="acceptCollaboration({{ $collaboration->id }})" class="inline-flex items-center px-2 py-1 border border-green-500 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100 transition-colors">
                                Accept
                            </button>
                            <button wire:click="declineCollaboration({{ $collaboration->id }})" class="inline-flex items-center px-2 py-1 border border-red-500 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 transition-colors">
                                Decline
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-[#231F20]">No collaboration requests</h3>
                        <p class="mt-1 text-sm text-[#9B9EA4]">No pending collaboration invitations at the moment.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent Activity --}}
    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
        <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
            <h3 class="text-lg font-semibold text-[#231F20]">Recent Review Activity</h3>
            <p class="text-sm text-[#9B9EA4]">Your latest reviews and evaluations</p>
        </div>
        <div class="p-6">
            @forelse($completedReviews as $review)
                <div class="flex items-start space-x-4 py-3 border-b border-[#9B9EA4]/10 last:border-b-0">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-[#231F20] truncate">{{ $review->idea->title }}</p>
                        <p class="text-xs text-[#9B9EA4]">By {{ $review->idea->author->first_name }} {{ $review->idea->author->last_name }}</p>
                        <div class="flex items-center mt-1">
                            <span class="text-xs text-[#9B9EA4]">Rating:</span>
                            <div class="flex ml-1">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-3 h-3 {{ $i <= $review->rating ? 'text-[#FFF200]' : 'text-[#9B9EA4]' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                @endfor
                            </div>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="text-xs text-[#9B9EA4]">{{ $review->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            @empty
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-[#231F20]">No reviews yet</h3>
                    <p class="mt-1 text-sm text-[#9B9EA4]">Start reviewing ideas to see your activity here.</p>
                </div>
            @endforelse
        </div>
    </div>
    
    {{-- Gamification Achievement Notifications --}}
    <livewire:components.achievement-notifications />
</div>
