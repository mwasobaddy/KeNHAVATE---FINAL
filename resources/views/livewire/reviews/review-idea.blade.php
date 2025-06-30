<?php

use Livewire\Volt\Component;
use App\Models\Idea;
use App\Models\Review;
use App\Services\IdeaWorkflowService;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\{Layout, Title};

new #[Layout('components.layouts.app')] #[Title('Review Idea')] class extends Component
{

    public Idea $idea;
    public string $stage = '';
    
    #[Validate('required|in:approved,rejected,needs_revision')]
    public string $decision = '';
    
    #[Validate('required|min:10')]
    public string $comments = '';
    
    #[Validate('nullable|string')]
    public string $feedback = '';
    
    #[Validate('nullable|numeric|min:0|max:10')]
    public ?float $overallScore = null;
    
    public array $criteriaScores = [];
    
    public bool $showReviewForm = false;
    public bool $isSubmitting = false;
    
    protected IdeaWorkflowService $workflowService;
    
    public function boot(IdeaWorkflowService $workflowService): void
    {
        $this->workflowService = $workflowService;
    }
    
    public function mount(): void
    {
        $user = Auth::user();
        $this->stage = $this->idea->current_stage;
        
        // Check if user can review this idea
        $pendingReviews = $this->workflowService->getPendingReviews($user);
        if (!$pendingReviews->contains('id', $this->idea->id)) {
            abort(403, 'You are not authorized to review this idea.');
        }
        
        // Initialize criteria scores based on review stage
        $this->initializeCriteriaScores();
    }
    
    protected function initializeCriteriaScores(): void
    {
        $criteria = match($this->stage) {
            'manager_review' => [
                'feasibility' => 0,
                'alignment' => 0,
                'impact' => 0,
                'resources' => 0
            ],
            'sme_review' => [
                'technical_feasibility' => 0,
                'innovation' => 0,
                'implementation' => 0,
                'scalability' => 0,
                'risk_assessment' => 0
            ],
            'board_review' => [
                'strategic_value' => 0,
                'roi_potential' => 0,
                'organizational_impact' => 0,
                'market_relevance' => 0
            ],
            default => []
        };
        
        $this->criteriaScores = $criteria;
    }
    
    public function toggleReviewForm(): void
    {
        $this->showReviewForm = !$this->showReviewForm;
        $this->reset(['decision', 'comments', 'feedback', 'overallScore']);
        $this->initializeCriteriaScores();
    }
    
    public function submitReview(): void
    {
        $this->validate();
        
        $this->isSubmitting = true;
        
        try {
            $this->workflowService->submitReview(
                $this->idea,
                Auth::user(),
                $this->decision,
                $this->comments,
                $this->overallScore,
                $this->criteriaScores,
                $this->feedback
            );
            
            session()->flash('success', 'Review submitted successfully!');
            
            // Redirect to dashboard or reviews list
            return $this->redirect(route('dashboard'));
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to submit review: ' . $e->getMessage());
        } finally {
            $this->isSubmitting = false;
        }
    }
    
    public function with(): array
    {
        $user = Auth::user();
        
        return [
            'canReview' => $this->workflowService->getPendingReviews($user)->contains('id', $this->idea->id),
            'existingReview' => Review::where('reviewable_type', Idea::class)
                ->where('reviewable_id', $this->idea->id)
                ->where('reviewer_id', $user->id)
                ->where('review_stage', $this->stage)
                ->first(),
            'stageName' => match($this->stage) {
                'manager_review' => 'Manager Review',
                'sme_review' => 'SME Review', 
                'board_review' => 'Board Review',
                default => 'Review'
            }
        ];
    }
    
}; ?>

{{-- Enhanced Review Idea Component with Glass Morphism & Modern UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/10 dark:bg-yellow-400/5 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/30 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 max-w-6xl mx-auto p-6 space-y-8">
        {{-- Enhanced Review Header with Glass Morphism --}}
        <section class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Gradient Background Effect --}}
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-purple-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-purple-500/20"></div>
                
                <div class="relative p-8">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                        <div class="flex items-start space-x-4">
                            {{-- Review Icon with Glow Effect --}}
                            <div class="relative">
                                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 dark:from-blue-400 dark:to-indigo-500 flex items-center justify-center shadow-lg">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                    </svg>
                                </div>
                                <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-60"></div>
                            </div>
                            
                            <div>
                                <h2 class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">{{ $stageName }}</h2>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">Review and provide comprehensive feedback for this innovation</p>
                            </div>
                        </div>
                        
                        {{-- Enhanced Status Badge --}}
                        <div class="flex items-center space-x-3">
                            @if($existingReview)
                                <div class="inline-flex items-center space-x-3 px-6 py-3 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700/50 rounded-2xl backdrop-blur-sm">
                                    <div class="w-3 h-3 bg-emerald-500 dark:bg-emerald-400 rounded-full animate-pulse"></div>
                                    <span class="text-emerald-800 dark:text-emerald-300 font-semibold">Review Completed</span>
                                </div>
                            @else
                                <div class="inline-flex items-center space-x-3 px-6 py-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700/50 rounded-2xl backdrop-blur-sm">
                                    <div class="w-3 h-3 bg-amber-500 dark:bg-amber-400 rounded-full animate-ping"></div>
                                    <span class="text-amber-800 dark:text-amber-300 font-semibold">Awaiting Your Review</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Idea Details Card --}}
        <section class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Header with Modern Typography --}}
                <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Innovation Details</h3>
                            <p class="text-[#9B9EA4] dark:text-zinc-400">Comprehensive overview of the submitted idea</p>
                        </div>
                    </div>
                    
                    {{-- Idea Title and Description --}}
                    <div class="space-y-4">
                        <div>
                            <h4 class="text-xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">{{ $idea->title }}</h4>
                            <p class="text-[#231F20] dark:text-zinc-300 leading-relaxed">{{ $idea->description }}</p>
                        </div>
                    </div>
                </div>
                
                {{-- Enhanced Metadata Grid --}}
                <div class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        {{-- Author Card --}}
                        <div class="group/card relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 rounded-xl flex items-center justify-center text-white font-semibold text-sm">
                                    {{ $idea->author->initials() ?? substr($idea->author->first_name ?? $idea->author->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wider">Author</p>
                                    <p class="font-semibold text-[#231F20] dark:text-zinc-100">{{ $idea->author->first_name ?? '' }} {{ $idea->author->last_name ?? $idea->author->name }}</p>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Category Card --}}
                        <div class="group/card relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wider">Category</p>
                                    <p class="font-semibold text-[#231F20] dark:text-zinc-100">{{ $idea->category->name }}</p>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Submission Date Card --}}
                        <div class="group/card relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wider">Submitted</p>
                                    <p class="font-semibold text-[#231F20] dark:text-zinc-100">{{ $idea->submitted_at?->format('M d, Y') ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Stage Card --}}
                        <div class="group/card relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wider">Current Stage</p>
                                    <p class="font-semibold text-[#231F20] dark:text-zinc-100 capitalize">{{ str_replace('_', ' ', $idea->current_stage) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Business Case & Impact Section --}}
        @if($idea->business_case || $idea->expected_impact)
        <section class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="p-8">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 dark:from-indigo-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Business Case & Impact Analysis</h4>
                            <p class="text-[#9B9EA4] dark:text-zinc-400">Strategic value and implementation details</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        @if($idea->business_case)
                        <div class="group/detail relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                            <h5 class="font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-3 uppercase tracking-wider text-sm">Business Case</h5>
                            <p class="text-[#231F20] dark:text-zinc-300 leading-relaxed">{{ $idea->business_case }}</p>
                        </div>
                        @endif
                        
                        @if($idea->expected_impact)
                        <div class="group/detail relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                            <h5 class="font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-3 uppercase tracking-wider text-sm">Expected Impact</h5>
                            <p class="text-[#231F20] dark:text-zinc-300 leading-relaxed">{{ $idea->expected_impact }}</p>
                        </div>
                        @endif
                        
                        @if($idea->implementation_timeline)
                        <div class="group/detail relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                            <h5 class="font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-3 uppercase tracking-wider text-sm">Implementation Timeline</h5>
                            <p class="text-[#231F20] dark:text-zinc-300 leading-relaxed">{{ $idea->implementation_timeline }}</p>
                        </div>
                        @endif
                        
                        @if($idea->resource_requirements)
                        <div class="group/detail relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                            <h5 class="font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-3 uppercase tracking-wider text-sm">Resource Requirements</h5>
                            <p class="text-[#231F20] dark:text-zinc-300 leading-relaxed">{{ $idea->resource_requirements }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
        @endif

        {{-- Enhanced Attachments Section --}}
        @if($idea->attachments->count() > 0)
        <section class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="p-8">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-12 h-12 bg-gradient-to-br from-rose-500 to-pink-600 dark:from-rose-400 dark:to-pink-500 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Supporting Documents</h4>
                            <p class="text-[#9B9EA4] dark:text-zinc-400">Files and attachments provided with this idea</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($idea->attachments as $attachment)
                        <a href="{{ Storage::url($attachment->file_path) }}" 
                           target="_blank" 
                           class="group/attachment relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transform hover:-translate-y-1 transition-all duration-300">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-[#231F20] dark:text-zinc-100 group-hover/attachment:text-blue-600 dark:group-hover/attachment:text-blue-400 transition-colors">{{ $attachment->original_filename }}</p>
                                    <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ number_format($attachment->file_size / 1024, 1) }} KB</p>
                                </div>
                                <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400 group-hover/attachment:text-blue-600 dark:group-hover/attachment:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
        @endif

        {{-- Enhanced Existing Review Display --}}
        @if($existingReview)
        <section class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="p-8">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-600 dark:from-emerald-400 dark:to-teal-500 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Your Review</h4>
                            <p class="text-[#9B9EA4] dark:text-zinc-400">Previously submitted evaluation and feedback</p>
                        </div>
                    </div>
                    
                    <div class="space-y-6">
                        {{-- Decision Badge --}}
                        <div class="flex items-center space-x-4">
                            <span class="text-lg font-semibold text-[#9B9EA4] dark:text-zinc-400">Decision:</span>
                            <span class="inline-flex items-center px-4 py-2 rounded-2xl text-sm font-semibold
                                @if($existingReview->decision === 'approved') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300
                                @elseif($existingReview->decision === 'rejected') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300
                                @else bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 @endif
                            ">
                                {{ ucfirst(str_replace('_', ' ', $existingReview->decision)) }}
                            </span>
                        </div>
                        
                        @if($existingReview->overall_score)
                        <div class="flex items-center space-x-4">
                            <span class="text-lg font-semibold text-[#9B9EA4] dark:text-zinc-400">Overall Score:</span>
                            <div class="flex items-center space-x-2">
                                <span class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">{{ $existingReview->overall_score }}</span>
                                <span class="text-lg text-[#9B9EA4] dark:text-zinc-400">/10</span>
                            </div>
                        </div>
                        @endif
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="group/review-detail relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                                <h5 class="font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-3 uppercase tracking-wider text-sm">Comments</h5>
                                <p class="text-[#231F20] dark:text-zinc-300 leading-relaxed">{{ $existingReview->comments }}</p>
                            </div>
                            
                            @if($existingReview->feedback)
                            <div class="group/review-detail relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                                <h5 class="font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-3 uppercase tracking-wider text-sm">Feedback</h5>
                                <p class="text-[#231F20] dark:text-zinc-300 leading-relaxed">{{ $existingReview->feedback }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
        @endif

        {{-- Enhanced Review Actions --}}
        @if($canReview && !$existingReview)
        <section class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="p-8 text-center">
                    <flux:button wire:click="toggleReviewForm" 
                               class="group relative overflow-hidden px-8 py-4 bg-gradient-to-r from-[#FFF200] to-yellow-300 dark:from-yellow-400 dark:to-yellow-500 text-[#231F20] font-semibold rounded-2xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                        <span class="relative z-10">
                            {{ $showReviewForm ? 'Cancel Review' : 'Start Comprehensive Review' }}
                        </span>
                        <div class="absolute inset-0 bg-gradient-to-r from-yellow-300 to-yellow-400 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    </flux:button>
                </div>
            </div>
        </section>
        @endif

        {{-- Enhanced Review Form --}}
        @if($showReviewForm && !$existingReview)
        <section class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border-2 border-[#FFF200]/50 dark:border-yellow-400/50 shadow-xl">
                {{-- Gradient Background Effect --}}
                <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/5 via-[#F8EBD5]/10 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent"></div>
                
                <div class="relative p-8">
                    <div class="flex items-center space-x-4 mb-8">
                        <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Submit Your Comprehensive Review</h4>
                            <p class="text-[#9B9EA4] dark:text-zinc-400">Provide detailed evaluation and constructive feedback</p>
                        </div>
                    </div>
                    
                    <form wire:submit="submitReview" class="space-y-8">
                        {{-- Enhanced Decision Section --}}
                        <div class="space-y-4">
                            <label class="block text-lg font-semibold text-[#231F20] dark:text-zinc-100">Review Decision *</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <label class="group/decision relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/60 to-white/40 dark:from-zinc-700/60 dark:to-zinc-800/40 border-2 border-white/40 dark:border-zinc-600/40 backdrop-blur-sm cursor-pointer hover:shadow-lg transform hover:-translate-y-1 transition-all duration-300 p-6">
                                    <input type="radio" wire:model="decision" value="approved" class="sr-only">
                                    <div class="flex flex-col items-center space-y-3">
                                        <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-xl flex items-center justify-center shadow-lg">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </div>
                                        <span class="font-semibold text-[#231F20] dark:text-zinc-100">Approve</span>
                                    </div>
                                </label>
                                
                                <label class="group/decision relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/60 to-white/40 dark:from-zinc-700/60 dark:to-zinc-800/40 border-2 border-white/40 dark:border-zinc-600/40 backdrop-blur-sm cursor-pointer hover:shadow-lg transform hover:-translate-y-1 transition-all duration-300 p-6">
                                    <input type="radio" wire:model="decision" value="needs_revision" class="sr-only">
                                    <div class="flex flex-col items-center space-y-3">
                                        <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 rounded-xl flex items-center justify-center shadow-lg">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </div>
                                        <span class="font-semibold text-[#231F20] dark:text-zinc-100">Needs Revision</span>
                                    </div>
                                </label>
                                
                                <label class="group/decision relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/60 to-white/40 dark:from-zinc-700/60 dark:to-zinc-800/40 border-2 border-white/40 dark:border-zinc-600/40 backdrop-blur-sm cursor-pointer hover:shadow-lg transform hover:-translate-y-1 transition-all duration-300 p-6">
                                    <input type="radio" wire:model="decision" value="rejected" class="sr-only">
                                    <div class="flex flex-col items-center space-y-3">
                                        <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 dark:from-red-400 dark:to-red-500 rounded-xl flex items-center justify-center shadow-lg">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </div>
                                        <span class="font-semibold text-[#231F20] dark:text-zinc-100">Reject</span>
                                    </div>
                                </label>
                            </div>
                            @error('decision') <span class="text-red-500 dark:text-red-400 text-sm font-medium">{{ $message }}</span> @enderror
                        </div>

                        {{-- Enhanced Criteria Scoring --}}
                        @if(!empty($criteriaScores))
                        <div class="space-y-6">
                            <label class="block text-lg font-semibold text-[#231F20] dark:text-zinc-100">Evaluation Criteria (0-10 scale)</label>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                @foreach($criteriaScores as $criterion => $score)
                                <div class="group/criteria relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                                    <label class="block text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-4 uppercase tracking-wider">{{ ucfirst(str_replace('_', ' ', $criterion)) }}</label>
                                    <div class="space-y-4">
                                        <input type="range" 
                                               wire:model="criteriaScores.{{ $criterion }}" 
                                               min="0" max="10" step="0.5"
                                               class="w-full h-3 bg-gray-200 dark:bg-zinc-700 rounded-lg appearance-none cursor-pointer slider">
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs text-[#9B9EA4] dark:text-zinc-400 font-medium">Poor (0)</span>
                                            <div class="text-center">
                                                <span class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">{{ $criteriaScores[$criterion] }}</span>
                                                <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">/10</span>
                                            </div>
                                            <span class="text-xs text-[#9B9EA4] dark:text-zinc-400 font-medium">Excellent (10)</span>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Enhanced Overall Score --}}
                        <div class="space-y-4">
                            <label class="block text-lg font-semibold text-[#231F20] dark:text-zinc-100">Overall Score (0-10)</label>
                            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                                <flux:input type="number" 
                                          wire:model="overallScore" 
                                          step="0.1" min="0" max="10"
                                          placeholder="Enter overall score (e.g., 7.5)"
                                          class="w-full text-lg font-semibold text-center"/>
                            </div>
                            @error('overallScore') <span class="text-red-500 dark:text-red-400 text-sm font-medium">{{ $message }}</span> @enderror
                        </div>

                        {{-- Enhanced Comments Section --}}
                        <div class="space-y-4">
                            <label class="block text-lg font-semibold text-[#231F20] dark:text-zinc-100">Detailed Comments *</label>
                            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                                <flux:textarea wire:model="comments" 
                                             rows="6" 
                                             placeholder="Provide comprehensive comments about your decision, highlighting strengths, weaknesses, and specific recommendations..."
                                             class="w-full resize-none"/>
                            </div>
                            @error('comments') <span class="text-red-500 dark:text-red-400 text-sm font-medium">{{ $message }}</span> @enderror
                        </div>

                        {{-- Enhanced Feedback Section --}}
                        <div class="space-y-4">
                            <label class="block text-lg font-semibold text-[#231F20] dark:text-zinc-100">Constructive Feedback for Author</label>
                            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                                <flux:textarea wire:model="feedback" 
                                             rows="4" 
                                             placeholder="Optional: Provide specific, actionable feedback to help the author improve their idea..."
                                             class="w-full resize-none"/>
                            </div>
                            @error('feedback') <span class="text-red-500 dark:text-red-400 text-sm font-medium">{{ $message }}</span> @enderror
                        </div>

                        {{-- Enhanced Action Buttons --}}
                        <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 pt-6">
                            <flux:button type="button" 
                                       wire:click="toggleReviewForm"
                                       variant="ghost"
                                       class="px-8 py-3 border-2 border-[#9B9EA4] dark:border-zinc-600 text-[#231F20] dark:text-zinc-100 rounded-2xl hover:bg-[#9B9EA4] hover:text-white dark:hover:bg-zinc-600 transition-all duration-300 font-semibold">
                                Cancel Review
                            </flux:button>
                            
                            <flux:button type="submit" 
                                       :disabled="isSubmitting"
                                       class="group relative overflow-hidden px-8 py-3 bg-gradient-to-r from-[#FFF200] to-yellow-300 dark:from-yellow-400 dark:to-yellow-500 text-[#231F20] font-semibold rounded-2xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span wire:loading.remove wire:target="submitReview" class="relative z-10">Submit Comprehensive Review</span>
                                <span wire:loading wire:target="submitReview" class="relative z-10 flex items-center space-x-2">
                                    <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    <span>Submitting Review...</span>
                                </span>
                                <div class="absolute inset-0 bg-gradient-to-r from-yellow-300 to-yellow-400 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </flux:button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        @endif
    </div>
</div>
