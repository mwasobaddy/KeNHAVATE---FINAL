<?php

use Livewire\Volt\Component;
use App\Models\Idea;
use App\Models\Review;
use App\Services\IdeaWorkflowService;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    
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

<div class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow-lg border border-[#9B9EA4]">
    
    <!-- Review Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-[#231F20]">{{ $stageName }}</h2>
            <p class="text-[#9B9EA4] mt-1">Review and provide feedback for this idea</p>
        </div>
        
        <div class="flex items-center space-x-3">
            @if($existingReview)
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                    Review Completed
                </span>
            @else
                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                    Pending Review
                </span>
            @endif
        </div>
    </div>
    
    <!-- Idea Details -->
    <div class="bg-[#F8EBD5] p-6 rounded-lg mb-6">
        <h3 class="text-xl font-semibold text-[#231F20] mb-3">{{ $idea->title }}</h3>
        <p class="text-[#231F20] mb-4">{{ $idea->description }}</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-medium text-[#9B9EA4]">Author:</span>
                <span class="text-[#231F20]">{{ $idea->author->name }}</span>
            </div>
            <div>
                <span class="font-medium text-[#9B9EA4]">Category:</span>
                <span class="text-[#231F20]">{{ $idea->category->name }}</span>
            </div>
            <div>
                <span class="font-medium text-[#9B9EA4]">Submitted:</span>
                <span class="text-[#231F20]">{{ $idea->submitted_at?->format('M d, Y') ?? 'N/A' }}</span>
            </div>
            <div>
                <span class="font-medium text-[#9B9EA4]">Current Stage:</span>
                <span class="text-[#231F20] capitalize">{{ str_replace('_', ' ', $idea->current_stage) }}</span>
            </div>
        </div>
    </div>
    
    <!-- Business Case & Details -->
    @if($idea->business_case || $idea->expected_impact)
    <div class="mb-6">
        <h4 class="text-lg font-semibold text-[#231F20] mb-3">Business Case & Impact</h4>
        
        @if($idea->business_case)
        <div class="mb-4">
            <h5 class="font-medium text-[#9B9EA4] mb-2">Business Case:</h5>
            <p class="text-[#231F20]">{{ $idea->business_case }}</p>
        </div>
        @endif
        
        @if($idea->expected_impact)
        <div class="mb-4">
            <h5 class="font-medium text-[#9B9EA4] mb-2">Expected Impact:</h5>
            <p class="text-[#231F20]">{{ $idea->expected_impact }}</p>
        </div>
        @endif
        
        @if($idea->implementation_timeline)
        <div class="mb-4">
            <h5 class="font-medium text-[#9B9EA4] mb-2">Implementation Timeline:</h5>
            <p class="text-[#231F20]">{{ $idea->implementation_timeline }}</p>
        </div>
        @endif
        
        @if($idea->resource_requirements)
        <div>
            <h5 class="font-medium text-[#9B9EA4] mb-2">Resource Requirements:</h5>
            <p class="text-[#231F20]">{{ $idea->resource_requirements }}</p>
        </div>
        @endif
    </div>
    @endif
    
    <!-- Attachments -->
    @if($idea->attachments->count() > 0)
    <div class="mb-6">
        <h4 class="text-lg font-semibold text-[#231F20] mb-3">Attachments</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($idea->attachments as $attachment)
            <a href="{{ Storage::url($attachment->file_path) }}" 
               target="_blank" 
               class="flex items-center p-3 border border-[#9B9EA4] rounded-lg hover:bg-[#F8EBD5] transition-colors">
                <svg class="w-5 h-5 text-[#9B9EA4] mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                <div>
                    <p class="font-medium text-[#231F20]">{{ $attachment->original_filename }}</p>
                    <p class="text-sm text-[#9B9EA4]">{{ number_format($attachment->file_size / 1024, 1) }} KB</p>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif
    
    <!-- Existing Review (if completed) -->
    @if($existingReview)
    <div class="mb-6 p-4 border border-[#9B9EA4] rounded-lg">
        <h4 class="text-lg font-semibold text-[#231F20] mb-3">Your Review</h4>
        
        <div class="mb-3">
            <span class="font-medium text-[#9B9EA4]">Decision:</span>
            <span class="ml-2 px-2 py-1 rounded-full text-sm font-medium
                @if($existingReview->decision === 'approved') bg-green-100 text-green-800
                @elseif($existingReview->decision === 'rejected') bg-red-100 text-red-800
                @else bg-yellow-100 text-yellow-800 @endif
            ">
                {{ ucfirst(str_replace('_', ' ', $existingReview->decision)) }}
            </span>
        </div>
        
        @if($existingReview->overall_score)
        <div class="mb-3">
            <span class="font-medium text-[#9B9EA4]">Overall Score:</span>
            <span class="ml-2 text-[#231F20]">{{ $existingReview->overall_score }}/10</span>
        </div>
        @endif
        
        <div class="mb-3">
            <span class="font-medium text-[#9B9EA4]">Comments:</span>
            <p class="text-[#231F20] mt-1">{{ $existingReview->comments }}</p>
        </div>
        
        @if($existingReview->feedback)
        <div>
            <span class="font-medium text-[#9B9EA4]">Feedback:</span>
            <p class="text-[#231F20] mt-1">{{ $existingReview->feedback }}</p>
        </div>
        @endif
    </div>
    @endif
    
    <!-- Review Actions -->
    @if($canReview && !$existingReview)
    <div class="flex justify-end">
        <button wire:click="toggleReviewForm" 
                class="px-6 py-2 bg-[#FFF200] text-[#231F20] font-medium rounded-lg hover:bg-yellow-300 transition-colors">
            {{ $showReviewForm ? 'Cancel Review' : 'Start Review' }}
        </button>
    </div>
    @endif
    
    <!-- Review Form -->
    @if($showReviewForm && !$existingReview)
    <div class="mt-6 p-6 border-2 border-[#FFF200] rounded-lg bg-[#F8EBD5]">
        <h4 class="text-lg font-semibold text-[#231F20] mb-4">Submit Your Review</h4>
        
        <form wire:submit="submitReview" class="space-y-6">
            
            <!-- Decision -->
            <div>
                <label class="block text-sm font-medium text-[#231F20] mb-2">Decision *</label>
                <div class="grid grid-cols-3 gap-3">
                    <label class="flex items-center p-3 border border-[#9B9EA4] rounded-lg cursor-pointer hover:bg-white transition-colors">
                        <input type="radio" wire:model="decision" value="approved" class="mr-3">
                        <span class="text-[#231F20]">Approve</span>
                    </label>
                    <label class="flex items-center p-3 border border-[#9B9EA4] rounded-lg cursor-pointer hover:bg-white transition-colors">
                        <input type="radio" wire:model="decision" value="needs_revision" class="mr-3">
                        <span class="text-[#231F20]">Needs Revision</span>
                    </label>
                    <label class="flex items-center p-3 border border-[#9B9EA4] rounded-lg cursor-pointer hover:bg-white transition-colors">
                        <input type="radio" wire:model="decision" value="rejected" class="mr-3">
                        <span class="text-[#231F20]">Reject</span>
                    </label>
                </div>
                @error('decision') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            
            <!-- Criteria Scoring -->
            @if(!empty($criteriaScores))
            <div>
                <label class="block text-sm font-medium text-[#231F20] mb-3">Criteria Assessment (0-10 scale)</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($criteriaScores as $criterion => $score)
                    <div>
                        <label class="block text-sm text-[#9B9EA4] mb-1">{{ ucfirst(str_replace('_', ' ', $criterion)) }}</label>
                        <input type="range" 
                               wire:model="criteriaScores.{{ $criterion }}" 
                               min="0" max="10" step="0.5"
                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                        <div class="flex justify-between text-xs text-[#9B9EA4] mt-1">
                            <span>0</span>
                            <span class="font-medium">{{ $criteriaScores[$criterion] }}</span>
                            <span>10</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            
            <!-- Overall Score -->
            <div>
                <label class="block text-sm font-medium text-[#231F20] mb-2">Overall Score (0-10)</label>
                <input type="number" 
                       wire:model="overallScore" 
                       step="0.1" min="0" max="10"
                       class="w-full px-3 py-2 border border-[#9B9EA4] rounded-lg focus:ring-2 focus:ring-[#FFF200] focus:border-transparent">
                @error('overallScore') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            
            <!-- Comments -->
            <div>
                <label class="block text-sm font-medium text-[#231F20] mb-2">Comments *</label>
                <textarea wire:model="comments" 
                          rows="4" 
                          placeholder="Provide detailed comments about your decision..."
                          class="w-full px-3 py-2 border border-[#9B9EA4] rounded-lg focus:ring-2 focus:ring-[#FFF200] focus:border-transparent"></textarea>
                @error('comments') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            
            <!-- Feedback -->
            <div>
                <label class="block text-sm font-medium text-[#231F20] mb-2">Additional Feedback</label>
                <textarea wire:model="feedback" 
                          rows="3" 
                          placeholder="Optional feedback for the author..."
                          class="w-full px-3 py-2 border border-[#9B9EA4] rounded-lg focus:ring-2 focus:ring-[#FFF200] focus:border-transparent"></textarea>
                @error('feedback') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            
            <!-- Submit Button -->
            <div class="flex justify-end space-x-3">
                <button type="button" 
                        wire:click="toggleReviewForm"
                        class="px-6 py-2 border border-[#9B9EA4] text-[#231F20] rounded-lg hover:bg-[#9B9EA4] hover:text-white transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        :disabled="isSubmitting"
                        class="px-6 py-2 bg-[#FFF200] text-[#231F20] font-medium rounded-lg hover:bg-yellow-300 transition-colors disabled:opacity-50">
                    <span wire:loading.remove wire:target="submitReview">Submit Review</span>
                    <span wire:loading wire:target="submitReview">Submitting...</span>
                </button>
            </div>
            
        </form>
    </div>
    @endif
    
</div>
