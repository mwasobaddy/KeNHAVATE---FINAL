<?php

use Livewire\Volt\Component;
use App\Models\Idea;
use App\Models\Review;

new #[Layout('components.layouts.app', title: 'Idea Reviewer Dashboard')] class extends Component
{
    public $pendingManagerReviews;
    public $pendingSmeReviews;
    public $completedReviews;
    public $reviewStats;

    public function mount()
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        $userId = auth()->id();

        // Ideas pending manager review (if user has manager role)
        $this->pendingManagerReviews = collect();
        if (auth()->user()->hasRole('manager')) {
            $this->pendingManagerReviews = Idea::where('current_stage', 'manager_review')
                ->whereDoesntHave('reviews', function($query) use ($userId) {
                    $query->where('reviewer_id', $userId)
                          ->where('review_stage', 'manager_review');
                })
                ->where('author_id', '!=', $userId) // Cannot review own ideas
                ->with(['author', 'category'])
                ->latest('submitted_at')
                ->take(5)
                ->get();
        }

        // Ideas pending SME review (if user has SME role)
        $this->pendingSmeReviews = collect();
        if (auth()->user()->hasRole('sme')) {
            $this->pendingSmeReviews = Idea::where('current_stage', 'sme_review')
                ->whereDoesntHave('reviews', function($query) use ($userId) {
                    $query->where('reviewer_id', $userId)
                          ->where('review_stage', 'sme_review');
                })
                ->where('author_id', '!=', $userId) // Cannot review own ideas
                ->with(['author', 'category'])
                ->latest('submitted_at')
                ->take(5)
                ->get();
        }

        // Recently completed reviews
        $this->completedReviews = Review::where('reviewer_id', $userId)
            ->where('review_type', 'idea')
            ->with(['idea.author', 'idea.category'])
            ->latest()
            ->take(8)
            ->get();

        // Review statistics
        $this->reviewStats = [
            'pending_manager_reviews' => $this->pendingManagerReviews->count(),
            'pending_sme_reviews' => $this->pendingSmeReviews->count(),
            'total_completed' => Review::where('reviewer_id', $userId)
                ->where('review_type', 'idea')
                ->count(),
            'reviews_this_month' => Review::where('reviewer_id', $userId)
                ->where('review_type', 'idea')
                ->whereMonth('created_at', now()->month)
                ->count(),
            'average_rating' => Review::where('reviewer_id', $userId)
                ->where('review_type', 'idea')
                ->avg('rating') ?? 0,
            'approval_rate' => $this->calculateApprovalRate($userId),
        ];
    }

    private function calculateApprovalRate($userId)
    {
        $totalReviews = Review::where('reviewer_id', $userId)
            ->where('review_type', 'idea')
            ->count();
            
        if ($totalReviews === 0) return 0;
        
        $approvedReviews = Review::where('reviewer_id', $userId)
            ->where('review_type', 'idea')
            ->where('decision', 'approved')
            ->count();
            
        return round(($approvedReviews / $totalReviews) * 100, 1);
    }

    public function quickReview($ideaId, $stage, $rating, $decision)
    {
        $idea = Idea::findOrFail($ideaId);
        
        // Validate review permissions
        if ($idea->author_id === auth()->id()) {
            $this->addError('review', 'You cannot review your own idea.');
            return;
        }

        if ($idea->current_stage !== $stage . '_review') {
            $this->addError('review', 'Idea is not in the correct stage for this review.');
            return;
        }

        // Create review
        Review::create([
            'idea_id' => $idea->id,
            'reviewer_id' => auth()->id(),
            'review_stage' => $stage . '_review',
            'review_type' => 'idea',
            'decision' => $decision,
            'rating' => $rating,
            'comments' => 'Quick review - ' . ucfirst($decision),
        ]);

        // Update idea stage based on decision
        if ($decision === 'approved') {
            $nextStage = $this->getNextStage($stage);
            $idea->update(['current_stage' => $nextStage]);
        } else {
            $idea->update(['current_stage' => 'rejected']);
        }

        $this->loadDashboardData();
        $this->dispatch('review-completed');
    }

    private function getNextStage($currentStage)
    {
        $stageMap = [
            'manager' => 'sme_review',
            'sme' => 'board_review',
        ];

        return $stageMap[$currentStage] ?? 'completed';
    }

    public function viewIdea($ideaId)
    {
        return redirect()->route('ideas.show', $ideaId);
    }
}; ?>

<div class="space-y-6">
    {{-- Welcome Section --}}
    <div class="bg-[#F8EBD5] rounded-lg p-6 border border-[#9B9EA4]/20">
        <h2 class="text-2xl font-bold text-[#231F20] mb-2">Welcome, {{ auth()->user()->first_name }}!</h2>
        <p class="text-[#231F20]/70">As an Idea Reviewer, you help shape KeNHA's innovation pipeline by evaluating and guiding promising ideas through the review process.</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-[#9B9EA4]">Pending Reviews</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $reviewStats['pending_manager_reviews'] + $reviewStats['pending_sme_reviews'] }}</p>
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
                    <p class="text-2xl font-bold text-[#231F20]">{{ $reviewStats['total_completed'] }}</p>
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

        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-[#9B9EA4]">Approval Rate</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $reviewStats['approval_rate'] }}%</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Manager Review Queue --}}
        @if(auth()->user()->hasRole('manager'))
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
            <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                <h3 class="text-lg font-semibold text-[#231F20]">Manager Review Queue</h3>
                <p class="text-sm text-[#9B9EA4]">Ideas requiring manager-level evaluation</p>
            </div>
            <div class="p-6 max-h-96 overflow-y-auto">
                @forelse($pendingManagerReviews as $idea)
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
                            @if($idea->category)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 mt-1">
                                    {{ $idea->category->name }}
                                </span>
                            @endif
                        </div>
                        <div class="flex flex-col space-y-1">
                            <button wire:click="viewIdea({{ $idea->id }})" class="inline-flex items-center px-3 py-1 border border-[#9B9EA4] text-xs font-medium rounded-md text-[#231F20] bg-white hover:bg-[#F8EBD5] transition-colors">
                                Review
                            </button>
                            <div class="flex space-x-1">
                                <button wire:click="quickReview({{ $idea->id }}, 'manager', 4, 'approved')" class="inline-flex items-center px-2 py-1 border border-green-500 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100 transition-colors" title="Quick Approve">
                                    ✓
                                </button>
                                <button wire:click="quickReview({{ $idea->id }}, 'manager', 2, 'rejected')" class="inline-flex items-center px-2 py-1 border border-red-500 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 transition-colors" title="Quick Reject">
                                    ✗
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-[#231F20]">No pending manager reviews</h3>
                        <p class="mt-1 text-sm text-[#9B9EA4]">All caught up! Check back later for new ideas.</p>
                    </div>
                @endforelse
            </div>
        </div>
        @endif

        {{-- SME Review Queue --}}
        @if(auth()->user()->hasRole('sme'))
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
            <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                <h3 class="text-lg font-semibold text-[#231F20]">SME Review Queue</h3>
                <p class="text-sm text-[#9B9EA4]">Ideas requiring subject matter expert evaluation</p>
            </div>
            <div class="p-6 max-h-96 overflow-y-auto">
                @forelse($pendingSmeReviews as $idea)
                    <div class="flex items-start space-x-4 py-3 border-b border-[#9B9EA4]/10 last:border-b-0">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[#231F20] truncate">{{ $idea->title }}</p>
                            <p class="text-xs text-[#9B9EA4]">By {{ $idea->author->first_name }} {{ $idea->author->last_name }}</p>
                            <p class="text-xs text-[#9B9EA4]">Submitted {{ $idea->submitted_at?->diffForHumans() }}</p>
                            @if($idea->category)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 mt-1">
                                    {{ $idea->category->name }}
                                </span>
                            @endif
                        </div>
                        <div class="flex flex-col space-y-1">
                            <button wire:click="viewIdea({{ $idea->id }})" class="inline-flex items-center px-3 py-1 border border-[#9B9EA4] text-xs font-medium rounded-md text-[#231F20] bg-white hover:bg-[#F8EBD5] transition-colors">
                                Review
                            </button>
                            <div class="flex space-x-1">
                                <button wire:click="quickReview({{ $idea->id }}, 'sme', 4, 'approved')" class="inline-flex items-center px-2 py-1 border border-green-500 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100 transition-colors" title="Quick Approve">
                                    ✓
                                </button>
                                <button wire:click="quickReview({{ $idea->id }}, 'sme', 2, 'rejected')" class="inline-flex items-center px-2 py-1 border border-red-500 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 transition-colors" title="Quick Reject">
                                    ✗
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-[#231F20]">No pending SME reviews</h3>
                        <p class="mt-1 text-sm text-[#9B9EA4]">All caught up! Check back later for new ideas.</p>
                    </div>
                @endforelse
            </div>
        </div>
        @endif

        {{-- If user has both manager and SME roles, show combined pending --}}
        @if(!auth()->user()->hasRole('manager') && !auth()->user()->hasRole('sme'))
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
            <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                <h3 class="text-lg font-semibold text-[#231F20]">Review Information</h3>
                <p class="text-sm text-[#9B9EA4]">Your review capabilities</p>
            </div>
            <div class="p-6">
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-[#231F20]">No review permissions</h3>
                    <p class="mt-1 text-sm text-[#9B9EA4]">You need manager or SME role to review ideas.</p>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Recent Review Activity --}}
    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
        <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
            <h3 class="text-lg font-semibold text-[#231F20]">Recent Review Activity</h3>
            <p class="text-sm text-[#9B9EA4]">Your latest idea reviews and decisions</p>
        </div>
        <div class="p-6">
            @forelse($completedReviews as $review)
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
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 ml-2">
                                {{ ucfirst(str_replace('_', ' ', $review->review_stage)) }}
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
                    <h3 class="mt-2 text-sm font-medium text-[#231F20]">No reviews yet</h3>
                    <p class="mt-1 text-sm text-[#9B9EA4]">Start reviewing ideas to see your activity here.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
