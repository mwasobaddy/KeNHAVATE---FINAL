<?php

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\ChallengeReview;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] #[Title('Challenge Submissions')] class extends Component
{
    use WithPagination;

    public Challenge $challenge;
    public string $search = '';
    public string $statusFilter = '';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';
    public array $selectedSubmissions = [];
    public bool $selectAll = false;
    
    // Bulk action properties
    public string $bulkAction = '';
    public int $bulkScore = 0;
    public string $bulkFeedback = '';

    public function mount(Challenge $challenge)
    {
        // Authorization check
        $this->authorize('viewSubmissions', $challenge);
        
        $this->challenge = $challenge;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function updatedSelectAll()
    {
        if ($this->selectAll) {
            $this->selectedSubmissions = $this->getSubmissions()->pluck('id')->toArray();
        } else {
            $this->selectedSubmissions = [];
        }
    }

    public function getSubmissions()
    {
        return $this->challenge->submissions()
            ->with(['author', 'reviews.reviewer', 'teamMembers'])
            ->when($this->search, function (Builder $query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%')
                      ->orWhereHas('author', function ($authorQuery) {
                          $authorQuery->where('name', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->when($this->statusFilter, function (Builder $query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);
    }

    public function reviewSubmission($submissionId, $score, $feedback)
    {
        $submission = ChallengeSubmission::findOrFail($submissionId);
        
        // Authorization check
        $this->authorize('review', $submission);

        // Validate inputs
        $this->validate([
            'score' => 'required|integer|min:0|max:100',
            'feedback' => 'required|string|min:10|max:1000',
        ], [
            'score.required' => 'Score is required',
            'score.integer' => 'Score must be a number',
            'score.min' => 'Score cannot be negative',
            'score.max' => 'Score cannot exceed 100',
            'feedback.required' => 'Feedback is required',
            'feedback.min' => 'Feedback must be at least 10 characters',
            'feedback.max' => 'Feedback cannot exceed 1000 characters',
        ]);

        try {
            DB::transaction(function () use ($submission, $score, $feedback) {
                // Create or update review
                $review = ChallengeReview::updateOrCreate(
                    [
                        'challenge_submission_id' => $submission->id,
                        'reviewer_id' => auth()->id(),
                    ],
                    [
                        'score' => $score,
                        'feedback' => $feedback,
                        'reviewed_at' => now(),
                    ]
                );

                // Update submission status
                $submission->update(['status' => 'reviewed']);

                // Log audit trail
                app(AuditService::class)->log(
                    'submission_reviewed',
                    'ChallengeSubmission',
                    $submission->id,
                    ['status' => $submission->getOriginal('status')],
                    ['status' => 'reviewed', 'score' => $score]
                );

                // Send notification to submitter
                app(NotificationService::class)->sendNotification(
                    $submission->author,
                    'submission_reviewed',
                    [
                        'title' => 'Your Submission Has Been Reviewed',
                        'message' => "Your submission '{$submission->title}' for challenge '{$this->challenge->title}' has been reviewed with a score of {$score}/100.",
                        'related_id' => $submission->id,
                        'related_type' => 'ChallengeSubmission',
                    ]
                );
            });

            session()->flash('success', 'Submission reviewed successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to review submission: ' . $e->getMessage());
        }
    }

    public function updateSubmissionStatus($submissionId, $status)
    {
        $submission = ChallengeSubmission::findOrFail($submissionId);
        
        // Authorization check
        $this->authorize('updateStatus', $submission);

        $oldStatus = $submission->status;
        $submission->update(['status' => $status]);

        // Log audit trail
        app(AuditService::class)->log(
            'submission_status_updated',
            'ChallengeSubmission',
            $submission->id,
            ['status' => $oldStatus],
            ['status' => $status]
        );

        // Send notification
        app(NotificationService::class)->sendNotification(
            $submission->author,
            'status_change',
            [
                'title' => 'Submission Status Updated',
                'message' => "Your submission '{$submission->title}' status has been updated to: " . ucfirst($status),
                'related_id' => $submission->id,
                'related_type' => 'ChallengeSubmission',
            ]
        );

        session()->flash('success', 'Submission status updated successfully.');
    }

    public function bulkUpdateSubmissions()
    {
        if (empty($this->selectedSubmissions)) {
            session()->flash('error', 'Please select submissions to update.');
            return;
        }

        if (empty($this->bulkAction)) {
            session()->flash('error', 'Please select an action.');
            return;
        }

        try {
            $submissions = ChallengeSubmission::whereIn('id', $this->selectedSubmissions)->get();

            foreach ($submissions as $submission) {
                switch ($this->bulkAction) {
                    case 'approve':
                        $this->authorize('updateStatus', $submission);
                        $submission->update(['status' => 'approved']);
                        break;
                    case 'reject':
                        $this->authorize('updateStatus', $submission);
                        $submission->update(['status' => 'rejected']);
                        break;
                    case 'review':
                        if ($this->bulkScore && $this->bulkFeedback) {
                            $this->reviewSubmission($submission->id, $this->bulkScore, $this->bulkFeedback);
                        }
                        break;
                }
            }

            // Reset selections
            $this->selectedSubmissions = [];
            $this->selectAll = false;
            $this->bulkAction = '';
            $this->bulkScore = 0;
            $this->bulkFeedback = '';

            session()->flash('success', 'Bulk action completed successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Bulk action failed: ' . $e->getMessage());
        }
    }

    public function exportSubmissions($format = 'csv')
    {
        // Authorization check
        $this->authorize('exportSubmissions', $this->challenge);

        $submissions = $this->getSubmissions();
        
        // Implementation would depend on export package
        // For now, just flash a message
        session()->flash('info', 'Export functionality will be implemented with a dedicated export package.');
    }

    public function with(): array
    {
        return [
            'submissions' => $this->getSubmissions(),
            'statusOptions' => ChallengeSubmission::getStatusOptions(),
            'totalSubmissions' => $this->challenge->submissions()->count(),
            'reviewedSubmissions' => $this->challenge->submissions()->where('status', 'reviewed')->count(),
            'averageScore' => $this->challenge->submissions()
                ->whereHas('reviews')
                ->with('reviews')
                ->get()
                ->flatMap->reviews
                ->avg('score'),
        ];
    }
}; ?>

{{-- KeNHAVATE Challenge Submissions Management - Enhanced Glass Morphism UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Enhanced Header with Glass Morphism --}}
        <section aria-labelledby="page-header" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Header Gradient Background --}}
                <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl"></div>
                
                <div class="relative z-10 p-8">
                    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
                        <div class="flex items-start space-x-4">
                            {{-- Enhanced Icon with Glow Effect --}}
                            <div class="relative">
                                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 flex items-center justify-center shadow-xl">
                                    <svg class="w-8 h-8 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="absolute -inset-2 bg-[#FFF200]/30 dark:bg-yellow-400/40 rounded-2xl blur-xl opacity-75"></div>
                            </div>
                            
                            <div>
                                <h1 id="page-header" class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">
                                    Challenge Submissions
                                </h1>
                                <p class="text-lg text-[#9B9EA4] dark:text-zinc-400 mb-4">
                                    {{ $challenge->title }}
                                </p>
                                
                                {{-- Enhanced Statistics Cards --}}
                                <div class="flex flex-wrap gap-4">
                                    <div class="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-white/50 dark:bg-zinc-700/50 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm">
                                        <div class="w-3 h-3 bg-blue-500 dark:bg-blue-400 rounded-full"></div>
                                        <span class="text-sm font-medium text-[#231F20] dark:text-zinc-100">Total: {{ number_format($totalSubmissions) }}</span>
                                    </div>
                                    <div class="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-white/50 dark:bg-zinc-700/50 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm">
                                        <div class="w-3 h-3 bg-emerald-500 dark:bg-emerald-400 rounded-full"></div>
                                        <span class="text-sm font-medium text-[#231F20] dark:text-zinc-100">Reviewed: {{ number_format($reviewedSubmissions) }}</span>
                                    </div>
                                    @if($averageScore)
                                        <div class="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-white/50 dark:bg-zinc-700/50 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm">
                                            <svg class="w-4 h-4 text-amber-500 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                            <span class="text-sm font-medium text-[#231F20] dark:text-zinc-100">Avg: {{ number_format($averageScore, 1) }}/100</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        {{-- Enhanced Action Buttons --}}
                        <div class="flex flex-col sm:flex-row gap-3">
                            <flux:button 
                                wire:click="exportSubmissions('csv')"
                                class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-6 py-3">
                                <span class="absolute inset-0 bg-gradient-to-br from-emerald-500/10 to-emerald-600/20 dark:from-emerald-400/20 dark:to-emerald-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center space-x-2">
                                    <svg class="w-5 h-5 text-[#231F20] dark:text-zinc-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span class="font-semibold text-[#231F20] dark:text-zinc-100">Export</span>
                                </div>
                            </flux:button>
                            
                            <flux:button 
                                href="{{ route('challenges.show', $challenge) }}"
                                class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-6 py-3">
                                <span class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-blue-600/20 dark:from-blue-400/20 dark:to-blue-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center space-x-2">
                                    <svg class="w-5 h-5 text-[#231F20] dark:text-zinc-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                    </svg>
                                    <span class="font-semibold text-[#231F20] dark:text-zinc-100">Back to Challenge</span>
                                </div>
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Filters Section --}}
        <section aria-labelledby="filters-section" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="p-6">
                    <div class="flex flex-col xl:flex-row gap-6 items-start xl:items-center justify-between">
                        <div class="flex flex-col lg:flex-row gap-4 flex-1 w-full">
                            {{-- Enhanced Search Input --}}
                            <div class="flex-1">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                    <flux:input 
                                        wire:model.live.debounce.500ms="search"
                                        placeholder="Search submissions, authors, descriptions..."
                                        class="w-full pl-12 pr-4 py-3 rounded-xl border border-white/40 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-2 focus:ring-[#FFF200]/50 focus:border-[#FFF200]/50 transition-all duration-200"
                                    />
                                </div>
                            </div>
                            
                            {{-- Enhanced Status Filter --}}
                            <div class="w-full lg:w-64">
                                <flux:select 
                                    wire:model.live="statusFilter" 
                                    class="w-full py-3 rounded-xl border border-white/40 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-2 focus:ring-[#FFF200]/50 focus:border-[#FFF200]/50 transition-all duration-200">
                                    <option value="">All Status</option>
                                    @foreach($statusOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </div>

                        {{-- Enhanced Bulk Actions Panel --}}
                        @if(!empty($selectedSubmissions))
                            <div class="w-full xl:w-auto">
                                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-50/90 to-indigo-50/90 dark:from-blue-900/30 dark:to-indigo-900/30 border border-blue-200/50 dark:border-blue-700/50 backdrop-blur-sm p-4">
                                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-3 h-3 bg-blue-500 dark:bg-blue-400 rounded-full animate-pulse"></div>
                                            <span class="text-sm font-semibold text-blue-800 dark:text-blue-200">
                                                {{ count($selectedSubmissions) }} selected
                                            </span>
                                        </div>
                                        
                                        <div class="flex flex-wrap gap-2">
                                            <flux:select wire:model="bulkAction" class="text-sm min-w-32">
                                                <option value="">Select Action</option>
                                                <option value="approve">Approve</option>
                                                <option value="reject">Reject</option>
                                                <option value="review">Quick Review</option>
                                            </flux:select>
                                            
                                            @if($bulkAction === 'review')
                                                <flux:input 
                                                    wire:model="bulkScore" 
                                                    type="number" 
                                                    min="0" 
                                                    max="100" 
                                                    placeholder="Score"
                                                    class="w-20 text-sm"
                                                />
                                                <flux:input 
                                                    wire:model="bulkFeedback" 
                                                    placeholder="Quick feedback"
                                                    class="w-36 text-sm"
                                                />
                                            @endif
                                            
                                            <flux:button 
                                                wire:click="bulkUpdateSubmissions"
                                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white rounded-lg font-semibold text-sm transition-colors duration-200">
                                                Apply
                                            </flux:button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Submissions Table --}}
        <section aria-labelledby="submissions-table" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gradient-to-r from-[#231F20]/5 to-[#231F20]/10 dark:from-zinc-700/50 dark:to-zinc-800/50 border-b border-[#9B9EA4]/20 dark:border-zinc-600/30">
                            <tr>
                                <th class="px-6 py-4 text-left">
                                    <input 
                                        type="checkbox" 
                                        wire:model="selectAll"
                                        class="w-4 h-4 rounded border-[#9B9EA4]/40 dark:border-zinc-500/40 text-[#FFF200] focus:ring-[#FFF200]/50 dark:focus:ring-yellow-400/50 transition-all duration-200"
                                    />
                                </th>
                                <th class="px-6 py-4 text-left">
                                    <button 
                                        wire:click="sortBy('title')"
                                        class="group flex items-center gap-2 text-sm font-bold text-[#231F20] dark:text-zinc-100 hover:text-[#FFF200] dark:hover:text-yellow-400 transition-all duration-200">
                                        Submission
                                        <div class="flex flex-col">
                                            <svg class="w-3 h-3 {{ $sortBy === 'title' && $sortDirection === 'asc' ? 'text-[#FFF200] dark:text-yellow-400' : 'text-[#9B9EA4] dark:text-zinc-400' }}" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/>
                                            </svg>
                                        </div>
                                    </button>
                                </th>
                                <th class="px-6 py-4 text-left">
                                    <button 
                                        wire:click="sortBy('author_id')"
                                        class="group flex items-center gap-2 text-sm font-bold text-[#231F20] dark:text-zinc-100 hover:text-[#FFF200] dark:hover:text-yellow-400 transition-all duration-200">
                                        Submitter
                                        <div class="flex flex-col">
                                            <svg class="w-3 h-3 {{ $sortBy === 'author_id' && $sortDirection === 'asc' ? 'text-[#FFF200] dark:text-yellow-400' : 'text-[#9B9EA4] dark:text-zinc-400' }}" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/>
                                            </svg>
                                        </div>
                                    </button>
                                </th>
                                <th class="px-6 py-4 text-left">
                                    <button 
                                        wire:click="sortBy('status')"
                                        class="group flex items-center gap-2 text-sm font-bold text-[#231F20] dark:text-zinc-100 hover:text-[#FFF200] dark:hover:text-yellow-400 transition-all duration-200">
                                        Status
                                        <div class="flex flex-col">
                                            <svg class="w-3 h-3 {{ $sortBy === 'status' && $sortDirection === 'asc' ? 'text-[#FFF200] dark:text-yellow-400' : 'text-[#9B9EA4] dark:text-zinc-400' }}" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/>
                                            </svg>
                                        </div>
                                    </button>
                                </th>
                                <th class="px-6 py-4 text-left">
                                    <span class="text-sm font-bold text-[#231F20] dark:text-zinc-100">Score</span>
                                </th>
                                <th class="px-6 py-4 text-left">
                                    <button 
                                        wire:click="sortBy('created_at')"
                                        class="group flex items-center gap-2 text-sm font-bold text-[#231F20] dark:text-zinc-100 hover:text-[#FFF200] dark:hover:text-yellow-400 transition-all duration-200">
                                        Submitted
                                        <div class="flex flex-col">
                                            <svg class="w-3 h-3 {{ $sortBy === 'created_at' && $sortDirection === 'asc' ? 'text-[#FFF200] dark:text-yellow-400' : 'text-[#9B9EA4] dark:text-zinc-400' }}" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/>
                                            </svg>
                                        </div>
                                    </button>
                                </th>
                                <th class="px-6 py-4 text-right">
                                    <span class="text-sm font-bold text-[#231F20] dark:text-zinc-100">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#9B9EA4]/20 dark:divide-zinc-600/30">
                            @forelse($submissions as $submission)
                                <tr class="group/row hover:bg-gradient-to-r hover:from-[#F8EBD5]/20 hover:to-[#FFF200]/10 dark:hover:from-amber-400/5 dark:hover:to-yellow-400/5 transition-all duration-300">
                                    <td class="px-6 py-4">
                                        <input 
                                            type="checkbox" 
                                            wire:model="selectedSubmissions"
                                            value="{{ $submission->id }}"
                                            class="w-4 h-4 rounded border-[#9B9EA4]/40 dark:border-zinc-500/40 text-[#FFF200] focus:ring-[#FFF200]/50 dark:focus:ring-yellow-400/50 transition-all duration-200"
                                        />
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-2">
                                            <div class="font-bold text-[#231F20] dark:text-zinc-100 group-hover/row:text-[#FFF200] dark:group-hover/row:text-yellow-400 transition-colors duration-200">
                                                {{ $submission->title }}
                                            </div>
                                            <div class="text-sm text-[#9B9EA4] dark:text-zinc-400 leading-relaxed">
                                                {{ Str::limit($submission->description, 80) }}
                                            </div>
                                            @if($submission->is_team_submission && $submission->teamMembers->count() > 0)
                                                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 border border-blue-200/50 dark:border-blue-700/50">
                                                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                                                    </svg>
                                                    <span class="text-xs font-medium text-blue-800 dark:text-blue-200">
                                                        Team ({{ $submission->teamMembers->count() + 1 }} members)
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-xl flex items-center justify-center text-[#231F20] dark:text-zinc-900 font-bold text-sm shadow-lg">
                                                {{ strtoupper(substr($submission->author->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="font-semibold text-[#231F20] dark:text-zinc-100">{{ $submission->author->name }}</div>
                                                <div class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $submission->author->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold
                                            @if($submission->status === 'draft') bg-gray-50 text-gray-700 border border-gray-200 dark:bg-gray-800/50 dark:text-gray-300 dark:border-gray-600
                                            @elseif($submission->status === 'submitted') bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700
                                            @elseif($submission->status === 'under_review') bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-700
                                            @elseif($submission->status === 'reviewed') bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-700
                                            @elseif($submission->status === 'approved') bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700
                                            @elseif($submission->status === 'rejected') bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700
                                            @else bg-gray-50 text-gray-700 border border-gray-200 dark:bg-gray-800/50 dark:text-gray-300 dark:border-gray-600
                                            @endif">
                                            {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($submission->reviews->count() > 0)
                                            <div class="space-y-1">
                                                <div class="font-bold text-lg text-[#231F20] dark:text-zinc-100">
                                                    {{ number_format($submission->reviews->avg('score'), 1) }}<span class="text-sm font-normal text-[#9B9EA4] dark:text-zinc-400">/100</span>
                                                </div>
                                                <div class="text-xs text-[#9B9EA4] dark:text-zinc-400">
                                                    {{ $submission->reviews->count() }} review{{ $submission->reviews->count() !== 1 ? 's' : '' }}
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400 italic">Not reviewed</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-1">
                                            <div class="text-sm font-medium text-[#231F20] dark:text-zinc-100">
                                                {{ $submission->created_at->format('M j, Y') }}
                                            </div>
                                            <div class="text-xs text-[#9B9EA4] dark:text-zinc-400">
                                                {{ $submission->created_at->format('g:i A') }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            {{-- View Details --}}
                                            <a href="{{ route('submissions.show', $submission) }}" 
                                               class="group/action p-2 text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-100 hover:bg-[#F8EBD5]/20 dark:hover:bg-amber-400/10 rounded-lg transition-all duration-200 transform hover:scale-110"
                                               title="View Details">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>

                                            {{-- Quick Review --}}
                                            @can('review', $submission)
                                                <button 
                                                    onclick="openReviewModal({{ $submission->id }}, '{{ $submission->title }}')"
                                                    class="group/action p-2 text-[#9B9EA4] dark:text-zinc-400 hover:text-[#FFF200] dark:hover:text-yellow-400 hover:bg-[#FFF200]/10 dark:hover:bg-yellow-400/10 rounded-lg transition-all duration-200 transform hover:scale-110"
                                                    title="Quick Review">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                                    </svg>
                                                </button>
                                            @endcan

                                            {{-- Status Actions --}}
                                            @can('updateStatus', $submission)
                                                <div class="relative" x-data="{ open: false }">
                                                    <button 
                                                        @click="open = !open"
                                                        class="group/action p-2 text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-100 hover:bg-[#9B9EA4]/10 dark:hover:bg-zinc-600/30 rounded-lg transition-all duration-200 transform hover:scale-110"
                                                        title="Update Status">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                                        </svg>
                                                    </button>
                                                    
                                                    <div x-show="open" 
                                                         @click.away="open = false"
                                                         x-transition:enter="transition ease-out duration-200"
                                                         x-transition:enter-start="opacity-0 scale-95"
                                                         x-transition:enter-end="opacity-100 scale-100"
                                                         x-transition:leave="transition ease-in duration-150"
                                                         x-transition:leave-start="opacity-100 scale-100"
                                                         x-transition:leave-end="opacity-0 scale-95"
                                                         class="absolute right-0 mt-2 w-48 bg-white/90 dark:bg-zinc-800/90 backdrop-blur-xl rounded-xl shadow-xl border border-white/20 dark:border-zinc-700/50 z-50">
                                                        <div class="py-2">
                                                            @foreach(['approved', 'rejected', 'under_review'] as $status)
                                                                @if($submission->status !== $status)
                                                                    <button 
                                                                        wire:click="updateSubmissionStatus({{ $submission->id }}, '{{ $status }}')"
                                                                        @click="open = false"
                                                                        class="block w-full text-left px-4 py-2 text-sm text-[#231F20] dark:text-zinc-100 hover:bg-[#F8EBD5]/50 dark:hover:bg-amber-400/10 transition-colors duration-200">
                                                                        Mark as {{ ucfirst(str_replace('_', ' ', $status)) }}
                                                                    </button>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center justify-center space-y-4">
                                            <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200]/20 to-[#F8EBD5]/20 dark:from-yellow-400/20 dark:to-amber-400/20 rounded-2xl flex items-center justify-center shadow-lg">
                                                <svg class="w-8 h-8 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                            </div>
                                            <div class="space-y-2">
                                                <h3 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">No submissions found</h3>
                                                <p class="text-[#9B9EA4] dark:text-zinc-400 max-w-md leading-relaxed">
                                                    @if($search || $statusFilter)
                                                        Try adjusting your search criteria or filter settings to find what you're looking for.
                                                    @else
                                                        No submissions have been made for this challenge yet. Participants can start submitting their solutions.
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Enhanced Pagination --}}
                @if($submissions->hasPages())
                    <div class="px-6 py-4 border-t border-[#9B9EA4]/20 dark:border-zinc-600/30 bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 backdrop-blur-sm">
                        {{ $submissions->links() }}
                    </div>
                @endif
            </div>
        </section>
    </div>

    {{-- Enhanced Quick Review Modal --}}
    <div id="reviewModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="relative w-full max-w-2xl">
            {{-- Modal Background with Glass Morphism --}}
            <div class="relative overflow-hidden rounded-3xl bg-white/90 dark:bg-zinc-800/90 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-2xl">
                {{-- Animated Background --}}
                <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-[#FFF200]/20 to-[#F8EBD5]/10 dark:from-yellow-400/20 dark:to-amber-400/10 rounded-full -mr-32 -mt-32 blur-2xl"></div>
                
                <div class="relative z-10 p-8">
                    {{-- Enhanced Modal Header --}}
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-xl flex items-center justify-center shadow-lg">
                                <svg class="w-5 h-5 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Quick Review</h3>
                        </div>
                        <button 
                            onclick="closeReviewModal()" 
                            class="p-2 text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-100 hover:bg-[#9B9EA4]/10 dark:hover:bg-zinc-600/30 rounded-xl transition-all duration-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form id="reviewForm" onsubmit="submitQuickReview(event)" class="space-y-6">
                        <input type="hidden" id="submissionId" />
                        
                        {{-- Submission Info --}}
                        <div class="p-4 rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-700/50 dark:to-zinc-800/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm">
                            <label class="block text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-2">Submission</label>
                            <p id="submissionTitle" class="text-[#9B9EA4] dark:text-zinc-400 font-medium"></p>
                        </div>

                        {{-- Score Input --}}
                        <div>
                            <label for="reviewScore" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-2">
                                Score (0-100)
                            </label>
                            <input 
                                type="number" 
                                id="reviewScore" 
                                min="0" 
                                max="100" 
                                required
                                class="w-full px-4 py-3 border border-white/40 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm rounded-xl focus:ring-2 focus:ring-[#FFF200]/50 focus:border-[#FFF200]/50 transition-all duration-200 text-[#231F20] dark:text-zinc-100"
                                placeholder="Enter score between 0 and 100"
                            />
                        </div>

                        {{-- Feedback Input --}}
                        <div>
                            <label for="reviewFeedback" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-2">
                                Feedback
                            </label>
                            <textarea 
                                id="reviewFeedback" 
                                rows="4" 
                                required
                                placeholder="Provide detailed feedback on the submission's strengths, areas for improvement, and overall assessment..."
                                class="w-full px-4 py-3 border border-white/40 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm rounded-xl focus:ring-2 focus:ring-[#FFF200]/50 focus:border-[#FFF200]/50 transition-all duration-200 text-[#231F20] dark:text-zinc-100 resize-none"></textarea>
                        </div>

                        {{-- Enhanced Action Buttons --}}
                        <div class="flex flex-col sm:flex-row gap-3 justify-end pt-4">
                            <flux:button 
                                type="button" 
                                onclick="closeReviewModal()"
                                class="px-6 py-3 border border-[#9B9EA4]/40 dark:border-zinc-600/40 text-[#231F20] dark:text-zinc-100 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm rounded-xl hover:bg-[#9B9EA4]/10 dark:hover:bg-zinc-600/30 transition-all duration-200 font-semibold">
                                Cancel
                            </flux:button>
                            <flux:button 
                                type="submit"
                                class="px-6 py-3 bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 text-[#231F20] dark:text-zinc-900 rounded-xl hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200 font-bold">
                                Submit Review
                            </flux:button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openReviewModal(submissionId, title) {
    document.getElementById('submissionId').value = submissionId;
    document.getElementById('submissionTitle').textContent = title;
    document.getElementById('reviewModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Focus on score input for better UX
    setTimeout(() => {
        document.getElementById('reviewScore').focus();
    }, 100);
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.add('hidden');
    document.body.style.overflow = '';
    document.getElementById('reviewForm').reset();
}

function submitQuickReview(event) {
    event.preventDefault();
    
    const submissionId = document.getElementById('submissionId').value;
    const score = document.getElementById('reviewScore').value;
    const feedback = document.getElementById('reviewFeedback').value;
    
    // Basic client-side validation
    if (!score || score < 0 || score > 100) {
        alert('Please enter a valid score between 0 and 100.');
        return;
    }
    
    if (!feedback || feedback.trim().length < 10) {
        alert('Please provide detailed feedback (at least 10 characters).');
        return;
    }
    
    @this.call('reviewSubmission', submissionId, parseInt(score), feedback);
    closeReviewModal();
}

// Enhanced keyboard navigation
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeReviewModal();
    }
});

// Auto-resize textarea
document.addEventListener('input', function(event) {
    if (event.target.id === 'reviewFeedback') {
        event.target.style.height = 'auto';
        event.target.style.height = event.target.scrollHeight + 'px';
    }
});
</script>
@endpush
