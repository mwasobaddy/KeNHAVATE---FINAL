<?php

use App\Models\ChallengeSubmission;
use App\Models\ChallengeReview;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] #[Title('Review Submission')] class extends Component {
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
        'suggestions' => '',
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
        $this->existingReview = $submission
            ->reviews()
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
                'suggestions' => '',
            ];
        }

        // Initialize criteria scores if challenge has specific criteria
        if ($this->submission->challenge->judging_criteria) {
            $criteria = json_decode($this->submission->challenge->judging_criteria, true);
            foreach ($criteria as $criterion) {
                $this->criteriaScores[$criterion['name']] = $this->existingReview ? $this->existingReview->criteria_scores[$criterion['name']] ?? 0 : 0;
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
            $weightedSum += $score * $weight;
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
        $this->validate(
            [
                'score' => 'required|numeric|min:0|max:100',
                'feedback' => 'required|string|min:20|max:2000',
                'recommendation' => 'required|string|in:approve,reject,needs_revision',
                'strengthsWeaknesses.strengths' => 'required|string|min:10|max:500',
                'strengthsWeaknesses.weaknesses' => 'required|string|min:10|max:500',
                'strengthsWeaknesses.suggestions' => 'nullable|string|max:500',
            ],
            [
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
            ],
        );

        try {
            // Use ChallengeWorkflowService for integrated review processing
            $challengeWorkflowService = app(\App\Services\ChallengeWorkflowService::class);

            $review = $challengeWorkflowService->submitReview($this->submission, auth()->user(), [
                'score' => $this->score,
                'feedback' => $this->feedback,
                'recommendation' => $this->recommendation,
                'criteria_scores' => $this->criteriaScores,
                'strengths_weaknesses' => $this->strengthsWeaknesses,
            ]);

            // Send notification to submitter
            app(\App\Services\NotificationService::class)->sendNotification($this->submission->participant, 'submission_reviewed', [
                'title' => 'Your Submission Has Been Reviewed',
                'message' => "Your submission '{$this->submission->title}' for challenge '{$this->submission->challenge->title}' has been reviewed with a score of {$this->score}/100.",
                'related_id' => $this->submission->id,
                'related_type' => 'ChallengeSubmission',
            ]);

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
            'judgingCriteria' => $this->submission->challenge->judging_criteria ? json_decode($this->submission->challenge->judging_criteria, true) : [],
            'otherReviews' => $this->submission
                ->reviews()
                ->where('reviewer_id', '!=', auth()->id())
                ->with('reviewer')
                ->get(),
        ];
    }
}; ?>

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div
            class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse">
        </div>
        <div
            class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000">
        </div>
        <div
            class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500">
        </div>
    </div>

    <div class="relative z-10 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto">
        {{-- Enhanced Header with Glass Morphism --}}
        <section aria-labelledby="review-header" class="group mb-8">
            <div
                class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Header Content --}}
                <div class="p-8">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div>
                            <div class="flex items-center space-x-4 mb-4">
                                <div
                                    class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 flex items-center justify-center shadow-lg">
                                    <svg class="w-7 h-7 text-[#231F20]" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </svg>
                                </div>
                                <div>
                                    <h1 id="review-header" class="text-3xl font-bold text-[#231F20] dark:text-zinc-100">
                                        Review Submission</h1>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">{{ $submission->title }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <span
                                    class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium
                                    @if ($submission->status === 'submitted') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400
                                    @elseif($submission->status === 'under_review') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400
                                    @elseif($submission->status === 'reviewed') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
                                    @elseif($submission->status === 'approved') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400
                                    @elseif($submission->status === 'rejected') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400
                                    @else bg-gray-100 text-gray-800 dark:bg-gray-700/30 dark:text-gray-400 @endif">
                                    <div
                                        class="w-2 h-2 rounded-full mr-2 animate-pulse
                                        @if ($submission->status === 'submitted') bg-blue-500
                                        @elseif($submission->status === 'under_review') bg-yellow-500
                                        @elseif($submission->status === 'reviewed') bg-green-500
                                        @elseif($submission->status === 'approved') bg-emerald-500
                                        @elseif($submission->status === 'rejected') bg-red-500
                                        @else bg-gray-500 @endif">
                                    </div>
                                    {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                </span>
                                <span
                                    class="text-sm text-[#9B9EA4] dark:text-zinc-400 bg-[#F8EBD5]/50 dark:bg-zinc-700/50 px-3 py-1.5 rounded-full">
                                    Challenge: {{ $challenge->title }}
                                </span>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <flux:button href="{{ route('challenge-reviews.index') }}" variant="ghost"
                                class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Reviews
                            </flux:button>

                            @if ($existingReview)
                                <div
                                    class="inline-flex items-center space-x-2 text-sm font-medium text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30 px-4 py-2 rounded-2xl border border-green-200 dark:border-green-700/50">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <span>Previously Reviewed</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Navigation Tabs --}}
        <section class="group mb-8">
            <div
                class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="p-6">
                    <div class="flex flex-wrap gap-3">
                        @foreach ([['key' => 'overview', 'label' => 'Overview', 'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z'], ['key' => 'details', 'label' => 'Technical Details', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'], ['key' => 'attachments', 'label' => 'Attachments', 'icon' => 'M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13'], ['key' => 'review', 'label' => 'Submit Review', 'icon' => 'M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4']] as $tab)
                            <button wire:click="$set('activeTab', '{{ $tab['key'] }}')"
                                class="group/tab relative overflow-hidden rounded-2xl transition-all duration-300 transform hover:-translate-y-1 px-6 py-3 flex items-center space-x-3 {{ $activeTab === $tab['key'] ? 'bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] text-[#231F20] font-semibold shadow-lg' : 'bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-700/50 dark:to-zinc-800/50 text-[#231F20] dark:text-zinc-300 hover:shadow-md border border-white/40 dark:border-zinc-600/40' }}">

                                @if ($activeTab === $tab['key'])
                                    <div
                                        class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/20 to-[#F8EBD5]/30 opacity-50">
                                    </div>
                                @endif

                                <svg class="w-5 h-5 relative z-10" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="{{ $tab['icon'] }}" />
                                </svg>
                                <span class="relative z-10">{{ $tab['label'] }}</span>

                                @if ($tab['key'] === 'attachments' && count($attachments) > 0)
                                    <span
                                        class="relative z-10 ml-2 px-2 py-0.5 bg-[#231F20] text-white text-xs rounded-full">
                                        {{ count($attachments) }}
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- Main Content Grid --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            {{-- Main Content Area --}}
            <div class="xl:col-span-2 space-y-8">
                @if ($activeTab === 'overview')
                    {{-- Enhanced Submission Overview --}}
                    <div class="group">
                        <div
                            class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                            <div class="p-8">
                                <div class="flex items-center space-x-4 mb-6">
                                    <div
                                        class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] rounded-2xl flex items-center justify-center shadow-lg">
                                        <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Submission Overview
                                    </h2>
                                </div>

                                {{-- Enhanced Author Information --}}
                                <div
                                    class="group/author relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6 mb-6 hover:shadow-lg transition-all duration-300">
                                    <div class="flex items-start gap-4">
                                        <div
                                            class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 rounded-2xl flex items-center justify-center text-white font-bold text-lg shadow-lg">
                                            {{ substr($author->name, 0, 1) }}
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-bold text-[#231F20] dark:text-zinc-100 text-lg">
                                                {{ $author->name }}</h3>
                                            <p class="text-[#9B9EA4] dark:text-zinc-400">{{ $author->email }}</p>
                                            @if ($submission->is_team_submission && $teamMembers->count() > 0)
                                                <div class="mt-3">
                                                    <p
                                                        class="text-sm text-[#231F20] dark:text-zinc-300 font-medium mb-2">
                                                        Team Members:</p>
                                                    <div class="flex flex-wrap gap-2">
                                                        @foreach ($teamMembers as $member)
                                                            <span
                                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                                                <div
                                                                    class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full mr-2">
                                                                </div>
                                                                {{ $member->name }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 font-medium">Submitted
                                            </p>
                                            <p class="font-bold text-[#231F20] dark:text-zinc-100">
                                                {{ $submission->created_at->format('M j, Y') }}</p>
                                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                                                {{ $submission->created_at->format('g:i A') }}</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Enhanced Content Sections --}}
                                <div class="space-y-6">
                                    <div class="group/content">
                                        <h3 class="font-bold text-[#231F20] dark:text-zinc-100 mb-3 text-lg">Title</h3>
                                        <div
                                            class="p-4 bg-gradient-to-r from-[#F8EBD5]/30 to-[#F8EBD5]/20 dark:from-zinc-700/30 dark:to-zinc-800/20 rounded-2xl border border-[#F8EBD5]/50 dark:border-zinc-600/50">
                                            <p class="text-[#231F20] dark:text-zinc-100 text-lg font-medium">
                                                {{ $submission->title }}</p>
                                        </div>
                                    </div>

                                    <div class="group/content">
                                        <div class="flex items-center justify-between mb-3">
                                            <h3 class="font-bold text-[#231F20] dark:text-zinc-100 text-lg">Description
                                            </h3>
                                            @if (strlen($submission->description) > 300)
                                                <button wire:click="$toggle('showFullDescription')"
                                                    class="text-sm text-[#FFF200] hover:text-[#F8EBD5] transition-colors duration-200 font-medium">
                                                    {{ $showFullDescription ? 'Show Less' : 'Show More' }}
                                                </button>
                                            @endif
                                        </div>
                                        <div
                                            class="p-6 bg-gradient-to-r from-[#F8EBD5]/30 to-[#F8EBD5]/20 dark:from-zinc-700/30 dark:to-zinc-800/20 rounded-2xl border border-[#F8EBD5]/50 dark:border-zinc-600/50">
                                            <div
                                                class="prose prose-sm max-w-none text-[#231F20] dark:text-zinc-300 leading-relaxed">
                                                @if ($showFullDescription || strlen($submission->description) <= 300)
                                                    {!! nl2br(e($submission->description)) !!}
                                                @else
                                                    {!! nl2br(e(Str::limit($submission->description, 300))) !!}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif($activeTab === 'details')
                    {{-- Enhanced Technical Details --}}
                    <div class="group">
                        <div
                            class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                            <div class="p-8">
                                <div class="flex items-center space-x-4 mb-6">
                                    <div
                                        class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Technical Details
                                    </h2>
                                </div>

                                @if ($submission->technical_approach)
                                    <div class="space-y-6">
                                        @foreach ([['field' => 'technical_approach', 'label' => 'Technical Approach', 'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'], ['field' => 'implementation_plan', 'label' => 'Implementation Plan', 'icon' => 'M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'], ['field' => 'expected_impact', 'label' => 'Expected Impact', 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6']] as $section)
                                            @if ($submission->{$section['field']})
                                                <div class="group/section">
                                                    <div class="flex items-center space-x-3 mb-4">
                                                        <div
                                                            class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-indigo-600 dark:from-indigo-400 dark:to-indigo-500 rounded-xl flex items-center justify-center shadow-lg">
                                                            <svg class="w-5 h-5 text-white" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="1.5" d="{{ $section['icon'] }}" />
                                                            </svg>
                                                        </div>
                                                        <h3
                                                            class="font-bold text-[#231F20] dark:text-zinc-100 text-lg">
                                                            {{ $section['label'] }}</h3>
                                                    </div>
                                                    <div
                                                        class="p-6 bg-gradient-to-r from-[#F8EBD5]/30 to-[#F8EBD5]/20 dark:from-zinc-700/30 dark:to-zinc-800/20 rounded-2xl border border-[#F8EBD5]/50 dark:border-zinc-600/50">
                                                        <div
                                                            class="prose prose-sm max-w-none text-[#231F20] dark:text-zinc-300 leading-relaxed">
                                                            {!! nl2br(e($submission->{$section['field']})) !!}
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-12">
                                        <div
                                            class="w-16 h-16 bg-gradient-to-br from-[#9B9EA4]/20 to-[#9B9EA4]/10 dark:from-zinc-600/20 dark:to-zinc-700/10 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                            <svg class="w-8 h-8 text-[#9B9EA4] dark:text-zinc-500" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <h4 class="text-lg font-bold text-[#231F20] dark:text-zinc-100 mb-2">No
                                            Technical Details</h4>
                                        <p class="text-[#9B9EA4] dark:text-zinc-400">No technical details were provided
                                            for this submission.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @elseif($activeTab === 'attachments')
                    {{-- Enhanced Attachments Section --}}
                    <div class="group">
                        <div
                            class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                            <div class="p-8">
                                <div class="flex items-center space-x-4 mb-6">
                                    <div
                                        class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Attachments
                                        </h2>
                                        <p class="text-[#9B9EA4] dark:text-zinc-400">Supporting files and documents</p>
                                    </div>
                                </div>

                                @if (count($attachments) > 0)
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        @foreach ($attachments as $attachment)
                                            <div
                                                class="group/attachment relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                                                <div class="flex items-center gap-4">
                                                    <div
                                                        class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-xl flex items-center justify-center shadow-lg">
                                                        <svg class="w-6 h-6 text-[#231F20]" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p
                                                            class="font-bold text-[#231F20] dark:text-zinc-100 truncate">
                                                            {{ $attachment['original_name'] }}</p>
                                                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                                                            {{ $attachment['size'] ?? 'Unknown size' }}</p>
                                                    </div>
                                                    <button
                                                        wire:click="downloadAttachment('{{ $attachment['filename'] }}')"
                                                        class="p-3 text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-200 hover:bg-[#9B9EA4]/10 dark:hover:bg-zinc-600/20 rounded-xl transition-all duration-200 group-hover/attachment:scale-110">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-12">
                                        <div
                                            class="w-16 h-16 bg-gradient-to-br from-[#9B9EA4]/20 to-[#9B9EA4]/10 dark:from-zinc-600/20 dark:to-zinc-700/10 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                            <svg class="w-8 h-8 text-[#9B9EA4] dark:text-zinc-500" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                            </svg>
                                        </div>
                                        <h4 class="text-lg font-bold text-[#231F20] dark:text-zinc-100 mb-2">No
                                            Attachments</h4>
                                        <p class="text-[#9B9EA4] dark:text-zinc-400">No supporting files were provided
                                            with this submission.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @elseif($activeTab === 'review')
                    {{-- Enhanced Review Form --}}
                    <form wire:submit.prevent="submitReview" class="space-y-8">
                        {{-- Judging Criteria Section --}}
                        @if (count($judgingCriteria) > 0)
                            <div class="group">
                                <div
                                    class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                                    <div class="p-8">
                                        <div class="flex items-center space-x-4 mb-6">
                                            <div
                                                class="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 rounded-2xl flex items-center justify-center shadow-lg">
                                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">
                                                    Judging Criteria</h2>
                                                <p class="text-[#9B9EA4] dark:text-zinc-400">Evaluate based on specific
                                                    criteria</p>
                                            </div>
                                        </div>

                                        <div class="space-y-6">
                                            @foreach ($judgingCriteria as $criterion)
                                                <div
                                                    class="group/criterion relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                                                    <div class="flex justify-between items-start mb-4">
                                                        <div class="flex-1">
                                                            <h3
                                                                class="font-bold text-[#231F20] dark:text-zinc-100 text-lg">
                                                                {{ $criterion['name'] }}</h3>
                                                            <p
                                                                class="text-sm text-[#9B9EA4] dark:text-zinc-400 mt-1 leading-relaxed">
                                                                {{ $criterion['description'] }}</p>
                                                        </div>
                                                        <span
                                                            class="ml-4 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-[#F8EBD5] dark:bg-zinc-700 text-[#231F20] dark:text-zinc-300">
                                                            Weight: {{ $criterion['weight'] }}%
                                                        </span>
                                                    </div>

                                                    <div class="flex items-center gap-6">
                                                        <div class="flex items-center gap-3">
                                                            <label
                                                                class="text-sm font-medium text-[#231F20] dark:text-zinc-300">Score:</label>
                                                            <input type="number"
                                                                wire:model.live="criteriaScores.{{ $criterion['name'] }}"
                                                                min="0" max="100"
                                                                class="w-20 px-3 py-2 bg-white/70 dark:bg-zinc-700/70 border border-[#9B9EA4]/40 dark:border-zinc-600/40 rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent transition-all duration-200" />
                                                        </div>
                                                        <div
                                                            class="flex-1 bg-gray-200 dark:bg-zinc-600 rounded-full h-3 overflow-hidden">
                                                            <div class="bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] h-3 rounded-full transition-all duration-500 ease-out"
                                                                style="width: {{ $criteriaScores[$criterion['name']] ?? 0 }}%">
                                                            </div>
                                                        </div>
                                                        <span
                                                            class="text-lg font-bold text-[#231F20] dark:text-zinc-100 min-w-[60px] text-right">
                                                            {{ $criteriaScores[$criterion['name']] ?? 0 }}/100
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach

                                            {{-- Overall Score Display --}}
                                            <div
                                                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#FFF200]/20 to-[#F8EBD5]/30 dark:from-yellow-400/20 dark:to-amber-400/30 border-2 border-[#FFF200]/50 dark:border-yellow-400/50 p-6">
                                                <div class="flex justify-between items-center">
                                                    <div class="flex items-center space-x-3">
                                                        <div
                                                            class="w-10 h-10 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] rounded-xl flex items-center justify-center shadow-lg">
                                                            <svg class="w-5 h-5 text-[#231F20]" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                                            </svg>
                                                        </div>
                                                        <h3
                                                            class="font-bold text-[#231F20] dark:text-zinc-100 text-xl">
                                                            Overall Score</h3>
                                                    </div>
                                                    <span
                                                        class="text-3xl font-bold text-[#231F20] dark:text-zinc-100">{{ $score }}/100</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- Manual Score Input --}}
                            <div class="group">
                                <div
                                    class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                                    <div class="p-8">
                                        <div class="flex items-center space-x-4 mb-6">
                                            <div
                                                class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] rounded-2xl flex items-center justify-center shadow-lg">
                                                <svg class="w-6 h-6 text-[#231F20]" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                                </svg>
                                            </div>
                                            <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Overall
                                                Score</h2>
                                        </div>

                                        <div class="flex items-center gap-6">
                                            <div class="flex items-center gap-3">
                                                <label
                                                    class="text-sm font-medium text-[#231F20] dark:text-zinc-300">Score
                                                    (0-100):</label>
                                                <input type="number" wire:model="score" min="0"
                                                    max="100" required
                                                    class="w-24 px-3 py-2 bg-white/70 dark:bg-zinc-700/70 border border-[#9B9EA4]/40 dark:border-zinc-600/40 rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent transition-all duration-200" />
                                            </div>
                                            <div
                                                class="flex-1 bg-gray-200 dark:bg-zinc-600 rounded-full h-4 overflow-hidden">
                                                <div class="bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] h-4 rounded-full transition-all duration-500 ease-out"
                                                    style="width: {{ $score }}%"></div>
                                            </div>
                                            <span
                                                class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 min-w-[80px] text-right">{{ $score }}/100</span>
                                        </div>
                                        @error('score')
                                            <p class="text-red-600 dark:text-red-400 text-sm mt-3 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Enhanced Recommendation Section --}}
                        <div class="group">
                            <div
                                class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                                <div class="p-8">
                                    <div class="flex items-center space-x-4 mb-6">
                                        <div
                                            class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">
                                                Recommendation</h2>
                                            <p class="text-[#9B9EA4] dark:text-zinc-400">Select your final
                                                recommendation</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        @foreach ([['value' => 'approve', 'label' => 'Approve', 'icon' => '✅', 'desc' => 'Meets requirements', 'color' => 'green'], ['value' => 'needs_revision', 'label' => 'Needs Revision', 'icon' => '⚠️', 'desc' => 'Requires improvements', 'color' => 'yellow'], ['value' => 'reject', 'label' => 'Reject', 'icon' => '❌', 'desc' => 'Does not meet requirements', 'color' => 'red']] as $option)
                                            <label
                                                class="group/option relative overflow-hidden rounded-2xl cursor-pointer transition-all duration-300 transform hover:-translate-y-1 {{ $recommendation === $option['value'] ? 'scale-105' : '' }}">
                                                <input type="radio" wire:model="recommendation"
                                                    value="{{ $option['value'] }}" class="sr-only">
                                                <div
                                                    class="p-6 border-2 rounded-2xl transition-all duration-300 {{ $recommendation === $option['value'] ? 'border-' . $option['color'] . '-500 bg-' . $option['color'] . '-50 dark:bg-' . $option['color'] . '-900/30 shadow-lg' : 'border-white/40 dark:border-zinc-600/40 bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 hover:border-' . $option['color'] . '-300 dark:hover:border-' . $option['color'] . '-600' }}">
                                                    <div class="flex items-center space-x-4">
                                                        <div
                                                            class="w-12 h-12 rounded-2xl {{ $recommendation === $option['value'] ? 'bg-' . $option['color'] . '-500 text-white' : 'bg-gray-200 dark:bg-zinc-600 text-gray-500 dark:text-zinc-400' }} flex items-center justify-center text-2xl transition-all duration-300">
                                                            {{ $option['icon'] }}
                                                        </div>
                                                        <div class="flex-1">
                                                            <div
                                                                class="font-bold text-lg {{ $recommendation === $option['value'] ? 'text-' . $option['color'] . '-700 dark:text-' . $option['color'] . '-300' : 'text-[#231F20] dark:text-zinc-100' }}">
                                                                {{ $option['label'] }}</div>
                                                            <div
                                                                class="text-sm {{ $recommendation === $option['value'] ? 'text-' . $option['color'] . '-600 dark:text-' . $option['color'] . '-400' : 'text-[#9B9EA4] dark:text-zinc-400' }}">
                                                                {{ $option['desc'] }}</div>
                                                        </div>
                                                        <div
                                                            class="w-6 h-6 rounded-full border-2 flex items-center justify-center {{ $recommendation === $option['value'] ? 'border-' . $option['color'] . '-500 bg-' . $option['color'] . '-500' : 'border-gray-300 dark:border-zinc-500' }}">
                                                            @if ($recommendation === $option['value'])
                                                                <div class="w-3 h-3 bg-white rounded-full"></div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('recommendation')
                                        <p class="text-red-600 dark:text-red-400 text-sm mt-4 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Enhanced Detailed Assessment --}}
                        <div class="group">
                            <div
                                class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                                <div class="p-8">
                                    <div class="flex items-center space-x-4 mb-6">
                                        <div
                                            class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-indigo-600 dark:from-indigo-400 dark:to-indigo-500 rounded-2xl flex items-center justify-center shadow-lg">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Detailed
                                                Assessment</h2>
                                            <p class="text-[#9B9EA4] dark:text-zinc-400">Provide comprehensive
                                                evaluation</p>
                                        </div>
                                    </div>

                                    <div class="space-y-6">
                                        @foreach ([['field' => 'strengths', 'label' => 'Strengths', 'placeholder' => 'What are the key strengths of this submission?', 'required' => true, 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'], ['field' => 'weaknesses', 'label' => 'Areas for Improvement', 'placeholder' => 'What areas need improvement or could be strengthened?', 'required' => true, 'icon' => 'M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z'], ['field' => 'suggestions', 'label' => 'Suggestions for Enhancement', 'placeholder' => 'Any specific suggestions for how this could be improved or expanded?', 'required' => false, 'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z']] as $field)
                                            <div class="group/field">
                                                <div class="flex items-center space-x-3 mb-3">
                                                    <div
                                                        class="w-8 h-8 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-lg flex items-center justify-center shadow-lg">
                                                        <svg class="w-4 h-4 text-[#231F20]" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="1.5" d="{{ $field['icon'] }}" />
                                                        </svg>
                                                    </div>
                                                    <label
                                                        class="block text-lg font-bold text-[#231F20] dark:text-zinc-100">
                                                        {{ $field['label'] }}
                                                        @if ($field['required'])
                                                            <span class="text-red-500 ml-1">*</span>
                                                        @endif
                                                    </label>
                                                </div>
                                                <textarea wire:model="strengthsWeaknesses.{{ $field['field'] }}" rows="4"
                                                    placeholder="{{ $field['placeholder'] }}"
                                                    class="w-full px-4 py-3 bg-white/70 dark:bg-zinc-700/70 border border-[#9B9EA4]/40 dark:border-zinc-600/40 rounded-2xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent resize-none transition-all duration-200 text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] dark:placeholder-zinc-400"></textarea>
                                                @error('strengthsWeaknesses.' . $field['field'])
                                                    <p
                                                        class="text-red-600 dark:text-red-400 text-sm mt-2 flex items-center gap-2">
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                        {{ $message }}
                                                    </p>
                                                @enderror
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Enhanced General Feedback --}}
                        <div class="group">
                            <div
                                class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                                <div class="p-8">
                                    <div class="flex items-center space-x-4 mb-6">
                                        <div
                                            class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Overall
                                                Feedback</h2>
                                            <p class="text-[#9B9EA4] dark:text-zinc-400">Provide comprehensive feedback
                                                to the submitter</p>
                                        </div>
                                    </div>

                                    <div>
                                        <textarea wire:model="feedback" rows="6"
                                            placeholder="Please provide detailed feedback about this submission. What worked well? What could be improved? Be constructive and specific."
                                            class="w-full px-4 py-3 bg-white/70 dark:bg-zinc-700/70 border border-[#9B9EA4]/40 dark:border-zinc-600/40 rounded-2xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent resize-none transition-all duration-200 text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] dark:placeholder-zinc-400"></textarea>
                                        @error('feedback')
                                            <p class="text-red-600 dark:text-red-400 text-sm mt-2 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Submit Button --}}
                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary"
                                class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] text-[#231F20] font-semibold px-8 py-4 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                                <span class="relative z-10 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </svg>
                                    {{ $existingReview ? 'Update Review' : 'Submit Review' }}
                                </span>
                                <div
                                    class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/20 to-[#F8EBD5]/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                </div>
                            </flux:button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- Side Content Area --}}
            <div class="space-y-8">
                {{-- Other Reviews Summary Card --}}
                <div class="group">
                    <div
                        class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                        <div class="p-6">
                            <div class="flex items-center space-x-3 mb-4">
                                <div
                                    class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                                <h3 class="font-bold text-[#231F20] dark:text-zinc-100 text-lg">Other Reviews</h3>
                            </div>

                            @if (count($otherReviews) > 0)
                                <div class="space-y-4">
                                    @foreach ($otherReviews as $review)
                                        <div
                                            class="group/review relative overflow-hidden rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-md transition-all duration-300">
                                            <div class="flex justify-between items-center mb-3">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-500 dark:from-indigo-400 dark:to-purple-400 rounded-lg text-white flex items-center justify-center font-bold text-sm">
                                                        {{ substr($review->reviewer->name, 0, 1) }}
                                                    </div>
                                                    <span
                                                        class="font-medium text-[#231F20] dark:text-zinc-100">{{ $review->reviewer->name }}</span>
                                                </div>
                                                <div
                                                    class="rounded-full bg-gray-200 dark:bg-zinc-700 px-3 py-1 text-sm font-medium text-[#231F20] dark:text-zinc-300">
                                                    {{ $review->score }}/100</div>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span
                                                    class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $review->created_at->format('M j, Y') }}</span>
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
    @if ($review->recommendation === 'approve') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
    @elseif($review->recommendation === 'needs_revision') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400
    @elseif($review->recommendation === 'reject') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 @endif">
                                                    {{ ucfirst(str_replace('_', ' ', $review->recommendation)) }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-6">
                                    <div
                                        class="w-12 h-12 bg-gradient-to-br from-[#9B9EA4]/20 to-[#9B9EA4]/10 dark:from-zinc-600/20 dark:to-zinc-700/10 rounded-xl flex items-center justify-center mx-auto mb-3 shadow-lg">
                                        <svg class="w-6 h-6 text-[#9B9EA4] dark:text-zinc-500" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                    </div>
                                    <h4 class="text-lg font-bold text-[#231F20] dark:text-zinc-100 mb-1">No Other
                                        Reviews</h4>
                                    <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">You are the first one to
                                        review this submission.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Challenge Information Card --}}
                <div class="group">
                    <div
                        class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                        <div class="p-6">
                            <div class="flex items-center space-x-3 mb-4">
                                <div
                                    class="w-10 h-10 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-[#231F20]" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                    </svg>
                                </div>
                                <h3 class="font-bold text-[#231F20] dark:text-zinc-100 text-lg">Challenge Details</h3>
                            </div>

                            <div class="space-y-4">
                                <div
                                    class="bg-gradient-to-r from-[#F8EBD5]/30 to-[#F8EBD5]/20 dark:from-zinc-700/30 dark:to-zinc-800/20 rounded-xl border border-[#F8EBD5]/50 dark:border-zinc-600/50 p-4">
                                    <h4 class="text-[#231F20] dark:text-zinc-100 font-bold mb-1">
                                        {{ $challenge->title }}</h4>
                                    <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mb-3 line-clamp-2">
                                        {{ $challenge->description }}</p>
                                    <div class="flex flex-wrap gap-2">
                                        <div
                                            class="text-xs text-[#9B9EA4] dark:text-zinc-400 bg-white/50 dark:bg-zinc-800/50 px-2 py-1 rounded-md flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            Ends: {{ $challenge->submission_deadline->format('M j, Y') }}
                                        </div>
                                        <div
                                            class="text-xs text-[#9B9EA4] dark:text-zinc-400 bg-white/50 dark:bg-zinc-800/50 px-2 py-1 rounded-md flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            {{ $challenge->submissions_count ?? '0' }} Submissions
                                        </div>
                                    </div>
                                </div>

                                <flux:button href="{{ route('challenges.show', $challenge) }}" variant="ghost"
                                    class="w-full group relative overflow-hidden rounded-xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 shadow-md hover:shadow-lg transition-all duration-300">
                                    View Challenge Details
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
