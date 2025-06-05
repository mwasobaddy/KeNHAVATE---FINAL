<?php

use Livewire\Volt\Component;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Review;

new class extends Component
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

<x-layouts.app title="Challenge Reviewer Dashboard">
<div class="space-y-6">
    {{-- Welcome Section --}}
    <div class="bg-[#F8EBD5] rounded-lg p-6 border border-[#9B9EA4]/20">
        <h2 class="text-2xl font-bold text-[#231F20] mb-2">Welcome, {{ auth()->user()->first_name }}!</h2>
        <p class="text-[#231F20]/70">As a Challenge Reviewer, you evaluate innovative solutions and help identify the best ideas for implementation.</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-[#9B9EA4]">Assigned Challenges</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $reviewStats['assigned_challenges'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-[#9B9EA4]">Pending Reviews</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $reviewStats['pending_reviews'] }}</p>
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
                    <p class="text-sm font-medium text-[#9B9EA4]">Completed Reviews</p>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $reviewStats['completed_reviews'] }}</p>
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
        {{-- Pending Challenge Reviews --}}
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
            <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                <h3 class="text-lg font-semibold text-[#231F20]">Pending Challenge Reviews</h3>
                <p class="text-sm text-[#9B9EA4]">Submissions awaiting your evaluation</p>
            </div>
            <div class="p-6 max-h-96 overflow-y-auto">
                @forelse($pendingReviews as $submission)
                    <div class="flex items-start space-x-4 py-3 border-b border-[#9B9EA4]/10 last:border-b-0">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[#231F20] truncate">{{ $submission->title }}</p>
                            <p class="text-xs text-[#9B9EA4]">Challenge: {{ $submission->challenge->title }}</p>
                            <p class="text-xs text-[#9B9EA4]">By {{ $submission->participant->first_name }} {{ $submission->participant->last_name }}</p>
                            <p class="text-xs text-[#9B9EA4]">Submitted {{ $submission->submitted_at?->diffForHumans() }}</p>
                        </div>
                        <div class="flex flex-col space-y-1">
                            <button wire:click="viewSubmission({{ $submission->id }})" class="inline-flex items-center px-3 py-1 border border-[#9B9EA4] text-xs font-medium rounded-md text-[#231F20] bg-white hover:bg-[#F8EBD5] transition-colors">
                                Review
                            </button>
                            <div class="flex space-x-1">
                                <button wire:click="quickReview({{ $submission->id }}, 4, 'approved')" class="inline-flex items-center px-2 py-1 border border-green-500 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100 transition-colors" title="Quick Approve">
                                    ✓
                                </button>
                                <button wire:click="quickReview({{ $submission->id }}, 2, 'rejected')" class="inline-flex items-center px-2 py-1 border border-red-500 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 transition-colors" title="Quick Reject">
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
                        <h3 class="mt-2 text-sm font-medium text-[#231F20]">No pending reviews</h3>
                        <p class="mt-1 text-sm text-[#9B9EA4]">All caught up! Check back later for new submissions.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Assigned Challenges --}}
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
            <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                <h3 class="text-lg font-semibold text-[#231F20]">Assigned Challenges</h3>
                <p class="text-sm text-[#9B9EA4]">Challenges you're responsible for reviewing</p>
            </div>
            <div class="p-6">
                @forelse($assignedChallenges as $challenge)
                    <div class="flex items-start space-x-4 py-3 border-b border-[#9B9EA4]/10 last:border-b-0">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[#231F20] truncate">{{ $challenge->title }}</p>
                            <p class="text-xs text-[#9B9EA4]">Created by {{ $challenge->creator->first_name }} {{ $challenge->creator->last_name }}</p>
                            <p class="text-xs text-[#9B9EA4]">Deadline: {{ $challenge->deadline->format('M j, Y') }}</p>
                            <div class="flex items-center mt-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $challenge->submissions->count() }} {{ Str::plural('submission', $challenge->submissions->count()) }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <button wire:click="viewChallenge({{ $challenge->id }})" class="inline-flex items-center px-3 py-1 border border-[#9B9EA4] text-xs font-medium rounded-md text-[#231F20] bg-white hover:bg-[#F8EBD5] transition-colors">
                                View
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-[#231F20]">No assigned challenges</h3>
                        <p class="mt-1 text-sm text-[#9B9EA4]">You haven't been assigned to any challenges yet.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent Review Activity --}}
    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20">
        <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
            <h3 class="text-lg font-semibold text-[#231F20]">Recent Review Activity</h3>
            <p class="text-sm text-[#9B9EA4]">Your latest challenge submission reviews</p>
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
                        <p class="text-sm font-medium text-[#231F20] truncate">{{ $review->challengeSubmission->title }}</p>
                        <p class="text-xs text-[#9B9EA4]">Challenge: {{ $review->challengeSubmission->challenge->title }}</p>
                        <p class="text-xs text-[#9B9EA4]">By {{ $review->challengeSubmission->participant->first_name }} {{ $review->challengeSubmission->participant->last_name }}</p>
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
                    <h3 class="mt-2 text-sm font-medium text-[#231F20]">No reviews yet</h3>
                    <p class="mt-1 text-sm text-[#9B9EA4]">Start reviewing challenge submissions to see your activity here.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
</x-layouts.app>
