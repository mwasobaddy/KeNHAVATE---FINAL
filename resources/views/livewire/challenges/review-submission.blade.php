<?php

use App\Models\ChallengeSubmission;
use App\Models\ChallengeReview;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] #[Title('Review Submission')] class extends Component
{
    public ChallengeSubmission $submission;
    public ?ChallengeReview $existingReview = null;
    
    // Review form properties
    public int $score = 0;
    public string $feedback = '';
    public array $criteriaScores = [];
    public string $recommendation = '';
    public array $strengthsWeaknesses = [
        'strengths' => '',
        'weaknesses' => '',
        'suggestions' => ''
    ];
    
    // UI state
    public bool $showFullDescription = false;
    public string $activeTab = 'overview';

    public function mount(ChallengeSubmission $submission)
    {
        // Authorization check
        $this->authorize('review', $submission);
        
        $this->submission = $submission;
        
        // Check for existing review by current user
        $this->existingReview = $submission->reviews()
            ->where('reviewer_id', auth()->id())
            ->first();
            
        // Pre-populate form if review exists
        if ($this->existingReview) {
            $this->score = $this->existingReview->score;
            $this->feedback = $this->existingReview->feedback;
            $this->recommendation = $this->existingReview->recommendation ?? '';
            $this->strengthsWeaknesses = $this->existingReview->strengths_weaknesses ?? [
                'strengths' => '',
                'weaknesses' => '',
                'suggestions' => ''
            ];
        }
        
        // Initialize criteria scores if challenge has specific criteria
        if ($this->submission->challenge->judging_criteria) {
            $criteria = json_decode($this->submission->challenge->judging_criteria, true);
            foreach ($criteria as $criterion) {
                $this->criteriaScores[$criterion['name']] = $this->existingReview 
                    ? ($this->existingReview->criteria_scores[$criterion['name']] ?? 0)
                    : 0;
            }
        }
    }

    public function calculateTotalScore()
    {
        if (empty($this->criteriaScores)) {
            return $this->score;
        }
        
        // Calculate weighted average based on criteria
        $criteria = json_decode($this->submission->challenge->judging_criteria, true);
        $totalWeight = array_sum(array_column($criteria, 'weight'));
        $weightedSum = 0;
        
        foreach ($criteria as $criterion) {
            $score = $this->criteriaScores[$criterion['name']] ?? 0;
            $weight = $criterion['weight'];
            $weightedSum += ($score * $weight);
        }
        
        $this->score = $totalWeight > 0 ? round($weightedSum / $totalWeight, 1) : 0;
    }

    public function updatedCriteriaScores()
    {
        $this->calculateTotalScore();
    }

    public function submitReview()
    {
        // Validation
        $this->validate([
            'score' => 'required|numeric|min:0|max:100',
            'feedback' => 'required|string|min:20|max:2000',
            'recommendation' => 'required|string|in:approve,reject,needs_revision',
            'strengthsWeaknesses.strengths' => 'required|string|min:10|max:500',
            'strengthsWeaknesses.weaknesses' => 'required|string|min:10|max:500',
            'strengthsWeaknesses.suggestions' => 'nullable|string|max:500',
        ], [
            'score.required' => 'Score is required',
            'score.min' => 'Score cannot be negative',
            'score.max' => 'Score cannot exceed 100',
            'feedback.required' => 'Feedback is required',
            'feedback.min' => 'Feedback must be at least 20 characters',
            'feedback.max' => 'Feedback cannot exceed 2000 characters',
            'recommendation.required' => 'Recommendation is required',
            'strengthsWeaknesses.strengths.required' => 'Strengths section is required',
            'strengthsWeaknesses.strengths.min' => 'Strengths must be at least 10 characters',
            'strengthsWeaknesses.weaknesses.required' => 'Weaknesses section is required',
            'strengthsWeaknesses.weaknesses.min' => 'Weaknesses must be at least 10 characters',
        ]);

        try {
            // Use ChallengeWorkflowService for integrated review processing
            $challengeWorkflowService = app(\App\Services\ChallengeWorkflowService::class);
            
            $review = $challengeWorkflowService->submitReview(
                $this->submission,
                auth()->user(),
                [
                    'score' => $this->score,
                    'feedback' => $this->feedback,
                    'recommendation' => $this->recommendation,
                    'criteria_scores' => $this->criteriaScores,
                    'strengths_weaknesses' => $this->strengthsWeaknesses,
                ]
            );

            // Send notification to submitter
            app(\App\Services\NotificationService::class)->sendNotification(
                $this->submission->participant,
                'submission_reviewed',
                [
                    'title' => 'Your Submission Has Been Reviewed',
                    'message' => "Your submission '{$this->submission->title}' for challenge '{$this->submission->challenge->title}' has been reviewed with a score of {$this->score}/100.",
                    'related_id' => $this->submission->id,
                    'related_type' => 'ChallengeSubmission',
                ]
            );

            // Update existing review reference
            $this->existingReview = $review;

            session()->flash('success', 'Review submitted successfully.');
            
            // Redirect to reviews dashboard
            return redirect()->route('challenge-reviews.index');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to submit review: ' . $e->getMessage());
        }
    }

    public function downloadAttachment($filename)
    {
        $path = 'challenge-submissions/' . $this->submission->id . '/' . $filename;
        
        if (Storage::disk('private')->exists($path)) {
            return Storage::disk('private')->download($path);
        }
        
        session()->flash('error', 'File not found.');
    }

    public function with(): array
    {
        return [
            'challenge' => $this->submission->challenge,
            'author' => $this->submission->author,
            'teamMembers' => $this->submission->teamMembers,
            'attachments' => $this->submission->attachments ? json_decode($this->submission->attachments, true) : [],
            'judgingCriteria' => $this->submission->challenge->judging_criteria 
                ? json_decode($this->submission->challenge->judging_criteria, true) 
                : [],
            'otherReviews' => $this->submission->reviews()
                ->where('reviewer_id', '!=', auth()->id())
                ->with('reviewer')
                ->get(),
        ];
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-[#F8EBD5] via-[#F8EBD5] to-[#E8DBB5] p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-[#231F20] mb-2">
                        📝 Review Submission
                    </h1>
                    <p class="text-[#9B9EA4] text-lg">{{ $submission->title }}</p>
                    <div class="flex items-center gap-4 mt-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                            @if($submission->status === 'submitted') bg-blue-100 text-blue-800
                            @elseif($submission->status === 'under_review') bg-yellow-100 text-yellow-800
                            @elseif($submission->status === 'reviewed') bg-green-100 text-green-800
                            @elseif($submission->status === 'approved') bg-emerald-100 text-emerald-800
                            @elseif($submission->status === 'rejected') bg-red-100 text-red-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                        </span>
                        <span class="text-sm text-[#9B9EA4]">
                            Challenge: {{ $challenge->title }}
                        </span>
                    </div>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('challenge-reviews.index') }}" 
                       class="px-4 py-2 bg-[#9B9EA4]/20 hover:bg-[#9B9EA4]/30 text-[#231F20] rounded-xl transition-colors duration-200 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Reviews
                    </a>
                    @if($existingReview)
                        <span class="px-4 py-2 bg-green-100 text-green-800 rounded-xl flex items-center gap-2 text-sm font-medium">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Previously Reviewed
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Navigation Tabs -->
                <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                    <div class="flex flex-wrap gap-2">
                        <button 
                            wire:click="$set('activeTab', 'overview')"
                            class="px-4 py-2 rounded-xl transition-colors duration-200 {{ $activeTab === 'overview' ? 'bg-[#FFF200] text-[#231F20] font-semibold' : 'bg-[#9B9EA4]/20 text-[#231F20] hover:bg-[#9B9EA4]/30' }}">
                            Overview
                        </button>
                        <button 
                            wire:click="$set('activeTab', 'details')"
                            class="px-4 py-2 rounded-xl transition-colors duration-200 {{ $activeTab === 'details' ? 'bg-[#FFF200] text-[#231F20] font-semibold' : 'bg-[#9B9EA4]/20 text-[#231F20] hover:bg-[#9B9EA4]/30' }}">
                            Technical Details
                        </button>
                        <button 
                            wire:click="$set('activeTab', 'attachments')"
                            class="px-4 py-2 rounded-xl transition-colors duration-200 {{ $activeTab === 'attachments' ? 'bg-[#FFF200] text-[#231F20] font-semibold' : 'bg-[#9B9EA4]/20 text-[#231F20] hover:bg-[#9B9EA4]/30' }}">
                            Attachments
                            @if(count($attachments) > 0)
                                <span class="ml-1 px-2 py-0.5 bg-[#231F20] text-white text-xs rounded-full">
                                    {{ count($attachments) }}
                                </span>
                            @endif
                        </button>
                        <button 
                            wire:click="$set('activeTab', 'review')"
                            class="px-4 py-2 rounded-xl transition-colors duration-200 {{ $activeTab === 'review' ? 'bg-[#FFF200] text-[#231F20] font-semibold' : 'bg-[#9B9EA4]/20 text-[#231F20] hover:bg-[#9B9EA4]/30' }}">
                            Submit Review
                        </button>
                    </div>
                </div>

                <!-- Tab Content -->
                @if($activeTab === 'overview')
                    <!-- Submission Overview -->
                    <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                        <h2 class="text-xl font-bold text-[#231F20] mb-4">Submission Overview</h2>
                        
                        <!-- Author Information -->
                        <div class="flex items-start gap-4 mb-6 p-4 bg-[#F8EBD5]/50 rounded-2xl">
                            <div class="w-12 h-12 bg-[#FFF200] rounded-full flex items-center justify-center text-[#231F20] font-bold text-lg">
                                {{ substr($author->name, 0, 1) }}
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-[#231F20] text-lg">{{ $author->name }}</h3>
                                <p class="text-[#9B9EA4]">{{ $author->email }}</p>
                                @if($submission->is_team_submission && $teamMembers->count() > 0)
                                    <div class="mt-2">
                                        <p class="text-sm text-[#231F20] font-medium">Team Members:</p>
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach($teamMembers as $member)
                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                                    {{ $member->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-[#9B9EA4]">Submitted</p>
                                <p class="font-semibold text-[#231F20]">{{ $submission->created_at->format('M j, Y') }}</p>
                                <p class="text-sm text-[#9B9EA4]">{{ $submission->created_at->format('g:i A') }}</p>
                            </div>
                        </div>

                        <!-- Submission Title and Description -->
                        <div class="space-y-4">
                            <div>
                                <h3 class="font-semibold text-[#231F20] mb-2">Title</h3>
                                <p class="text-[#231F20] text-lg">{{ $submission->title }}</p>
                            </div>
                            
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="font-semibold text-[#231F20]">Description</h3>
                                    @if(strlen($submission->description) > 300)
                                        <button 
                                            wire:click="$toggle('showFullDescription')"
                                            class="text-sm text-[#FFF200] hover:underline">
                                            {{ $showFullDescription ? 'Show Less' : 'Show More' }}
                                        </button>
                                    @endif
                                </div>
                                <div class="prose prose-sm max-w-none text-[#231F20]">
                                    @if($showFullDescription || strlen($submission->description) <= 300)
                                        {!! nl2br(e($submission->description)) !!}
                                    @else
                                        {!! nl2br(e(Str::limit($submission->description, 300))) !!}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                @elseif($activeTab === 'details')
                    <!-- Technical Details -->
                    <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                        <h2 class="text-xl font-bold text-[#231F20] mb-4">Technical Details</h2>
                        
                        @if($submission->technical_approach)
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-[#231F20] mb-2">Technical Approach</h3>
                                    <div class="prose prose-sm max-w-none text-[#231F20] p-4 bg-[#F8EBD5]/30 rounded-xl">
                                        {!! nl2br(e($submission->technical_approach)) !!}
                                    </div>
                                </div>
                                
                                @if($submission->implementation_plan)
                                    <div>
                                        <h3 class="font-semibold text-[#231F20] mb-2">Implementation Plan</h3>
                                        <div class="prose prose-sm max-w-none text-[#231F20] p-4 bg-[#F8EBD5]/30 rounded-xl">
                                            {!! nl2br(e($submission->implementation_plan)) !!}
                                        </div>
                                    </div>
                                @endif
                                
                                @if($submission->expected_impact)
                                    <div>
                                        <h3 class="font-semibold text-[#231F20] mb-2">Expected Impact</h3>
                                        <div class="prose prose-sm max-w-none text-[#231F20] p-4 bg-[#F8EBD5]/30 rounded-xl">
                                            {!! nl2br(e($submission->expected_impact)) !!}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 text-[#9B9EA4] mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="text-[#9B9EA4]">No technical details provided</p>
                            </div>
                        @endif
                    </div>

                @elseif($activeTab === 'attachments')
                    <!-- Attachments -->
                    <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                        <h2 class="text-xl font-bold text-[#231F20] mb-4">Attachments</h2>
                        
                        @if(count($attachments) > 0)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach($attachments as $attachment)
                                    <div class="border border-[#9B9EA4]/20 rounded-xl p-4 hover:bg-[#F8EBD5]/30 transition-colors duration-200">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-[#FFF200] rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-medium text-[#231F20] truncate">{{ $attachment['original_name'] }}</p>
                                                <p class="text-sm text-[#9B9EA4]">{{ $attachment['size'] ?? 'Unknown size' }}</p>
                                            </div>
                                            <button 
                                                wire:click="downloadAttachment('{{ $attachment['filename'] }}')"
                                                class="p-2 text-[#9B9EA4] hover:text-[#231F20] hover:bg-[#9B9EA4]/10 rounded-lg transition-colors duration-200">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 text-[#9B9EA4] mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                </svg>
                                <p class="text-[#9B9EA4]">No attachments provided</p>
                            </div>
                        @endif
                    </div>

                @elseif($activeTab === 'review')
                    <!-- Review Form -->
                    <form wire:submit.prevent="submitReview" class="space-y-8">
                        <!-- Judging Criteria (if available) -->
                        @if(count($judgingCriteria) > 0)
                            <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                                <h2 class="text-xl font-bold text-[#231F20] mb-4">Judging Criteria</h2>
                                <div class="space-y-4">
                                    @foreach($judgingCriteria as $criterion)
                                        <div class="p-4 border border-[#9B9EA4]/20 rounded-xl">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <h3 class="font-semibold text-[#231F20]">{{ $criterion['name'] }}</h3>
                                                    <p class="text-sm text-[#9B9EA4] mt-1">{{ $criterion['description'] }}</p>
                                                </div>
                                                <span class="text-sm text-[#9B9EA4] bg-[#F8EBD5] px-2 py-1 rounded">
                                                    Weight: {{ $criterion['weight'] }}%
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-4 mt-3">
                                                <label class="text-sm font-medium text-[#231F20]">Score (0-100):</label>
                                                <input 
                                                    type="number" 
                                                    wire:model.live="criteriaScores.{{ $criterion['name'] }}"
                                                    min="0" 
                                                    max="100" 
                                                    class="w-24 px-3 py-2 border border-[#9B9EA4]/40 rounded-lg focus:ring-2 focus:ring-[#FFF200] focus:border-transparent"
                                                />
                                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                                    <div class="bg-[#FFF200] h-2 rounded-full transition-all duration-300" 
                                                         style="width: {{ ($criteriaScores[$criterion['name']] ?? 0) }}%"></div>
                                                </div>
                                                <span class="text-sm text-[#231F20] font-medium">
                                                    {{ $criteriaScores[$criterion['name']] ?? 0 }}/100
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                    
                                    <!-- Overall Score (calculated) -->
                                    <div class="p-4 bg-[#FFF200]/20 border-2 border-[#FFF200]/40 rounded-xl">
                                        <div class="flex justify-between items-center">
                                            <h3 class="font-bold text-[#231F20] text-lg">Overall Score</h3>
                                            <span class="text-2xl font-bold text-[#231F20]">{{ $score }}/100</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <!-- Manual Score Input -->
                            <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                                <h2 class="text-xl font-bold text-[#231F20] mb-4">Overall Score</h2>
                                <div class="flex items-center gap-4">
                                    <label class="text-sm font-medium text-[#231F20]">Score (0-100):</label>
                                    <input 
                                        type="number" 
                                        wire:model="score"
                                        min="0" 
                                        max="100" 
                                        required
                                        class="w-32 px-3 py-2 border border-[#9B9EA4]/40 rounded-lg focus:ring-2 focus:ring-[#FFF200] focus:border-transparent"
                                    />
                                    <div class="flex-1 bg-gray-200 rounded-full h-3">
                                        <div class="bg-[#FFF200] h-3 rounded-full transition-all duration-300" 
                                             style="width: {{ $score }}%"></div>
                                    </div>
                                    <span class="text-xl font-bold text-[#231F20]">{{ $score }}/100</span>
                                </div>
                                @error('score') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <!-- Recommendation -->
                        <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                            <h2 class="text-xl font-bold text-[#231F20] mb-4">Recommendation</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <label class="flex items-center p-4 border-2 rounded-xl cursor-pointer transition-all duration-200 {{ $recommendation === 'approve' ? 'border-green-500 bg-green-50' : 'border-[#9B9EA4]/20 hover:border-green-300' }}">
                                    <input type="radio" wire:model="recommendation" value="approve" class="sr-only">
                                    <div class="w-5 h-5 rounded-full border-2 mr-3 flex items-center justify-center {{ $recommendation === 'approve' ? 'border-green-500 bg-green-500' : 'border-gray-300' }}">
                                        @if($recommendation === 'approve')
                                            <div class="w-2 h-2 bg-white rounded-full"></div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-semibold text-green-700">✅ Approve</div>
                                        <div class="text-sm text-green-600">Meets requirements</div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-4 border-2 rounded-xl cursor-pointer transition-all duration-200 {{ $recommendation === 'needs_revision' ? 'border-yellow-500 bg-yellow-50' : 'border-[#9B9EA4]/20 hover:border-yellow-300' }}">
                                    <input type="radio" wire:model="recommendation" value="needs_revision" class="sr-only">
                                    <div class="w-5 h-5 rounded-full border-2 mr-3 flex items-center justify-center {{ $recommendation === 'needs_revision' ? 'border-yellow-500 bg-yellow-500' : 'border-gray-300' }}">
                                        @if($recommendation === 'needs_revision')
                                            <div class="w-2 h-2 bg-white rounded-full"></div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-semibold text-yellow-700">⚠️ Needs Revision</div>
                                        <div class="text-sm text-yellow-600">Requires improvements</div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-4 border-2 rounded-xl cursor-pointer transition-all duration-200 {{ $recommendation === 'reject' ? 'border-red-500 bg-red-50' : 'border-[#9B9EA4]/20 hover:border-red-300' }}">
                                    <input type="radio" wire:model="recommendation" value="reject" class="sr-only">
                                    <div class="w-5 h-5 rounded-full border-2 mr-3 flex items-center justify-center {{ $recommendation === 'reject' ? 'border-red-500 bg-red-500' : 'border-gray-300' }}">
                                        @if($recommendation === 'reject')
                                            <div class="w-2 h-2 bg-white rounded-full"></div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-semibold text-red-700">❌ Reject</div>
                                        <div class="text-sm text-red-600">Does not meet requirements</div>
                                    </div>
                                </label>
                            </div>
                            @error('recommendation') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
                        </div>

                        <!-- Strengths, Weaknesses, Suggestions -->
                        <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                            <h2 class="text-xl font-bold text-[#231F20] mb-4">Detailed Assessment</h2>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-[#231F20] mb-2">
                                        Strengths <span class="text-red-500">*</span>
                                    </label>
                                    <textarea 
                                        wire:model="strengthsWeaknesses.strengths"
                                        rows="3"
                                        placeholder="What are the key strengths of this submission?"
                                        class="w-full px-3 py-2 border border-[#9B9EA4]/40 rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent resize-none"></textarea>
                                    @error('strengthsWeaknesses.strengths') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-[#231F20] mb-2">
                                        Areas for Improvement <span class="text-red-500">*</span>
                                    </label>
                                    <textarea 
                                        wire:model="strengthsWeaknesses.weaknesses"
                                        rows="3"
                                        placeholder="What areas need improvement or could be strengthened?"
                                        class="w-full px-3 py-2 border border-[#9B9EA4]/40 rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent resize-none"></textarea>
                                    @error('strengthsWeaknesses.weaknesses') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-[#231F20] mb-2">
                                        Suggestions for Enhancement
                                    </label>
                                    <textarea 
                                        wire:model="strengthsWeaknesses.suggestions"
                                        rows="3"
                                        placeholder="Any specific suggestions for how this could be improved or expanded?"
                                        class="w-full px-3 py-2 border border-[#9B9EA4]/40 rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent resize-none"></textarea>
                                    @error('strengthsWeaknesses.suggestions') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- General Feedback -->
                        <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                            <h2 class="text-xl font-bold text-[#231F20] mb-4">General Feedback</h2>
                            <textarea 
                                wire:model="feedback"
                                rows="6"
                                placeholder="Provide comprehensive feedback on the submission. This will be shared with the submitter."
                                class="w-full px-3 py-2 border border-[#9B9EA4]/40 rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent resize-none"></textarea>
                            @error('feedback') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
                            <p class="text-sm text-[#9B9EA4] mt-2">Minimum 20 characters required</p>
                        </div>

                        <!-- Submit Button -->
                        <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                            <div class="flex gap-4">
                                <button 
                                    type="submit"
                                    class="flex-1 bg-[#FFF200] hover:bg-[#FFF200]/80 text-[#231F20] font-bold py-3 px-6 rounded-xl transition-colors duration-200 flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    {{ $existingReview ? 'Update Review' : 'Submit Review' }}
                                </button>
                                <a href="{{ route('challenge-reviews.index') }}" 
                                   class="px-6 py-3 border border-[#9B9EA4]/40 text-[#231F20] rounded-xl hover:bg-[#9B9EA4]/10 transition-colors duration-200 flex items-center gap-2">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Challenge Info -->
                <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                    <h3 class="font-bold text-[#231F20] mb-4">Challenge Details</h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-[#9B9EA4]">Challenge</p>
                            <p class="font-semibold text-[#231F20]">{{ $challenge->title }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-[#9B9EA4]">Status</p>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($challenge->status === 'active') bg-green-100 text-green-800
                                @elseif($challenge->status === 'judging') bg-yellow-100 text-yellow-800
                                @elseif($challenge->status === 'completed') bg-blue-100 text-blue-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ ucfirst($challenge->status) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-sm text-[#9B9EA4]">Deadline</p>
                            <p class="font-semibold text-[#231F20]">{{ $challenge->submission_deadline->format('M j, Y') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Other Reviews -->
                @if($otherReviews->count() > 0)
                    <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                        <h3 class="font-bold text-[#231F20] mb-4">Other Reviews</h3>
                        <div class="space-y-3">
                            @foreach($otherReviews as $review)
                                <div class="p-3 border border-[#9B9EA4]/20 rounded-xl">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="font-semibold text-[#231F20] text-sm">{{ $review->reviewer->name }}</p>
                                        <span class="text-sm font-bold text-[#231F20]">{{ $review->score }}/100</span>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                        @if($review->recommendation === 'approve') bg-green-100 text-green-800
                                        @elseif($review->recommendation === 'needs_revision') bg-yellow-100 text-yellow-800
                                        @elseif($review->recommendation === 'reject') bg-red-100 text-red-800
                                        @endif">
                                        {{ ucfirst(str_replace('_', ' ', $review->recommendation)) }}
                                    </span>
                                    <p class="text-xs text-[#9B9EA4] mt-2">{{ $review->reviewed_at->format('M j, Y') }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Quick Actions -->
                <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                    <h3 class="font-bold text-[#231F20] mb-4">Quick Actions</h3>
                    <div class="space-y-2">
                        <a href="{{ route('challenges.show', $challenge) }}" 
                           class="w-full px-4 py-2 text-left text-[#231F20] hover:bg-[#F8EBD5]/50 rounded-xl transition-colors duration-200 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            View Challenge
                        </a>
                        <a href="{{ route('challenges.submissions', $challenge) }}" 
                           class="w-full px-4 py-2 text-left text-[#231F20] hover:bg-[#F8EBD5]/50 rounded-xl transition-colors duration-200 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            All Submissions
                        </a>
                        <a href="{{ route('challenges.leaderboard', $challenge) }}" 
                           class="w-full px-4 py-2 text-left text-[#231F20] hover:bg-[#F8EBD5]/50 rounded-xl transition-colors duration-200 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Leaderboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
