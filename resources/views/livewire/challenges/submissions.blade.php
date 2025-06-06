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

<div class="min-h-screen bg-gradient-to-br from-[#F8EBD5] via-[#F8EBD5] to-[#E8DBB5] p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto">
        <!-- Page Header with Glass Morphism -->
        <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6 mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-[#231F20] mb-2">
                        Challenge Submissions
                    </h1>
                    <p class="text-[#9B9EA4] text-lg">
                        {{ $challenge->title }}
                    </p>
                    <div class="flex flex-wrap gap-4 mt-3 text-sm text-[#231F20]">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Total: {{ $totalSubmissions }}
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                            </svg>
                            Reviewed: {{ $reviewedSubmissions }}
                        </span>
                        @if($averageScore)
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                Avg Score: {{ number_format($averageScore, 1) }}/100
                            </span>
                        @endif
                    </div>
                </div>
                <div class="flex gap-3">
                    <button 
                        wire:click="exportSubmissions('csv')"
                        class="px-4 py-2 bg-[#9B9EA4]/20 hover:bg-[#9B9EA4]/30 text-[#231F20] rounded-xl transition-colors duration-200 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export
                    </button>
                    <a href="{{ route('challenges.show', $challenge) }}" 
                       class="px-4 py-2 bg-[#9B9EA4]/20 hover:bg-[#9B9EA4]/30 text-[#231F20] rounded-xl transition-colors duration-200 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Challenge
                    </a>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6 mb-8">
            <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
                <div class="flex flex-col sm:flex-row gap-4 flex-1">
                    <!-- Search -->
                    <div class="flex-1">
                        <x-flux:input 
                            wire:model.live.debounce.500ms="search"
                            placeholder="Search submissions..."
                            class="w-full"
                        />
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="w-full sm:w-48">
                        <x-flux:select wire:model.live="statusFilter" class="w-full">
                            <option value="">All Status</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-flux:select>
                    </div>
                </div>

                <!-- Bulk Actions -->
                @if(!empty($selectedSubmissions))
                    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center bg-blue-50 p-4 rounded-xl border border-blue-200">
                        <span class="text-sm font-medium text-blue-800">
                            {{ count($selectedSubmissions) }} selected
                        </span>
                        <div class="flex gap-2">
                            <x-flux:select wire:model="bulkAction" class="text-sm">
                                <option value="">Select Action</option>
                                <option value="approve">Approve</option>
                                <option value="reject">Reject</option>
                                <option value="review">Quick Review</option>
                            </x-flux:select>
                            
                            @if($bulkAction === 'review')
                                <x-flux:input 
                                    wire:model="bulkScore" 
                                    type="number" 
                                    min="0" 
                                    max="100" 
                                    placeholder="Score"
                                    class="w-20 text-sm"
                                />
                                <x-flux:input 
                                    wire:model="bulkFeedback" 
                                    placeholder="Quick feedback"
                                    class="w-32 text-sm"
                                />
                            @endif
                            
                            <x-flux:button 
                                wire:click="bulkUpdateSubmissions"
                                variant="primary"
                                size="sm">
                                Apply
                            </x-flux:button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Submissions Table -->
        <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-[#231F20]/5 border-b border-[#9B9EA4]/20">
                        <tr>
                            <th class="px-6 py-4 text-left">
                                <input 
                                    type="checkbox" 
                                    wire:model="selectAll"
                                    class="rounded border-[#9B9EA4]/40 text-[#FFF200] focus:ring-[#FFF200]/50"
                                />
                            </th>
                            <th class="px-6 py-4 text-left">
                                <button 
                                    wire:click="sortBy('title')"
                                    class="flex items-center gap-2 text-sm font-semibold text-[#231F20] hover:text-[#FFF200] transition-colors duration-200">
                                    Submission
                                    @if($sortBy === 'title')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-4 text-left">
                                <button 
                                    wire:click="sortBy('author_id')"
                                    class="flex items-center gap-2 text-sm font-semibold text-[#231F20] hover:text-[#FFF200] transition-colors duration-200">
                                    Submitter
                                    @if($sortBy === 'author_id')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-4 text-left">
                                <button 
                                    wire:click="sortBy('status')"
                                    class="flex items-center gap-2 text-sm font-semibold text-[#231F20] hover:text-[#FFF200] transition-colors duration-200">
                                    Status
                                    @if($sortBy === 'status')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-4 text-left">Score</th>
                            <th class="px-6 py-4 text-left">
                                <button 
                                    wire:click="sortBy('created_at')"
                                    class="flex items-center gap-2 text-sm font-semibold text-[#231F20] hover:text-[#FFF200] transition-colors duration-200">
                                    Submitted
                                    @if($sortBy === 'created_at')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#9B9EA4]/20">
                        @forelse($submissions as $submission)
                            <tr class="hover:bg-[#231F20]/5 transition-colors duration-200">
                                <td class="px-6 py-4">
                                    <input 
                                        type="checkbox" 
                                        wire:model="selectedSubmissions"
                                        value="{{ $submission->id }}"
                                        class="rounded border-[#9B9EA4]/40 text-[#FFF200] focus:ring-[#FFF200]/50"
                                    />
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="font-semibold text-[#231F20]">{{ $submission->title }}</div>
                                        <div class="text-sm text-[#9B9EA4] mt-1">
                                            {{ Str::limit($submission->description, 60) }}
                                        </div>
                                        @if($submission->is_team_submission && $submission->teamMembers->count() > 0)
                                            <div class="flex items-center gap-1 mt-2">
                                                <svg class="w-4 h-4 text-[#9B9EA4]" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                                                </svg>
                                                <span class="text-xs text-[#9B9EA4]">
                                                    Team ({{ $submission->teamMembers->count() + 1 }} members)
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-[#FFF200] rounded-full flex items-center justify-center text-[#231F20] font-semibold text-sm">
                                            {{ substr($submission->author->name, 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-[#231F20]">{{ $submission->author->name }}</div>
                                            <div class="text-sm text-[#9B9EA4]">{{ $submission->author->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($submission->status === 'draft') bg-gray-100 text-gray-800
                                        @elseif($submission->status === 'submitted') bg-blue-100 text-blue-800
                                        @elseif($submission->status === 'under_review') bg-yellow-100 text-yellow-800
                                        @elseif($submission->status === 'reviewed') bg-green-100 text-green-800
                                        @elseif($submission->status === 'approved') bg-emerald-100 text-emerald-800
                                        @elseif($submission->status === 'rejected') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($submission->reviews->count() > 0)
                                        <div class="text-sm">
                                            <div class="font-semibold text-[#231F20]">
                                                {{ number_format($submission->reviews->avg('score'), 1) }}/100
                                            </div>
                                            <div class="text-[#9B9EA4]">
                                                {{ $submission->reviews->count() }} review(s)
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-sm text-[#9B9EA4]">Not reviewed</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-[#9B9EA4]">
                                        {{ $submission->created_at->format('M j, Y') }}
                                    </div>
                                    <div class="text-xs text-[#9B9EA4]">
                                        {{ $submission->created_at->format('g:i A') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- View Details -->
                                        <a href="{{ route('submissions.show', $submission) }}" 
                                           class="p-2 text-[#9B9EA4] hover:text-[#231F20] hover:bg-[#9B9EA4]/10 rounded-lg transition-colors duration-200"
                                           title="View Details">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>

                                        <!-- Quick Review -->
                                        @can('review', $submission)
                                            <button 
                                                onclick="openReviewModal({{ $submission->id }}, '{{ $submission->title }}')"
                                                class="p-2 text-[#9B9EA4] hover:text-[#FFF200] hover:bg-[#FFF200]/10 rounded-lg transition-colors duration-200"
                                                title="Quick Review">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                                </svg>
                                            </button>
                                        @endcan

                                        <!-- Status Actions -->
                                        @can('updateStatus', $submission)
                                            <div class="relative" x-data="{ open: false }">
                                                <button 
                                                    @click="open = !open"
                                                    class="p-2 text-[#9B9EA4] hover:text-[#231F20] hover:bg-[#9B9EA4]/10 rounded-lg transition-colors duration-200"
                                                    title="Update Status">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                                    </svg>
                                                </button>
                                                
                                                <div x-show="open" 
                                                     @click.away="open = false"
                                                     x-transition
                                                     class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-[#9B9EA4]/20 z-50">
                                                    <div class="py-1">
                                                        @foreach(['approved', 'rejected', 'under_review'] as $status)
                                                            @if($submission->status !== $status)
                                                                <button 
                                                                    wire:click="updateSubmissionStatus({{ $submission->id }}, '{{ $status }}')"
                                                                    @click="open = false"
                                                                    class="block w-full text-left px-4 py-2 text-sm text-[#231F20] hover:bg-[#F8EBD5] transition-colors duration-200">
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
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-12 h-12 text-[#9B9EA4] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <h3 class="text-lg font-medium text-[#231F20] mb-2">No submissions found</h3>
                                        <p class="text-[#9B9EA4]">
                                            @if($search || $statusFilter)
                                                Try adjusting your search or filter criteria.
                                            @else
                                                No submissions have been made for this challenge yet.
                                            @endif
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($submissions->hasPages())
                <div class="px-6 py-4 border-t border-[#9B9EA4]/20">
                    {{ $submissions->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Quick Review Modal -->
    <div id="reviewModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white/90 backdrop-blur-md rounded-3xl border border-white/20 shadow-xl w-full max-w-2xl">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-[#231F20]">Quick Review</h3>
                        <button onclick="closeReviewModal()" class="text-[#9B9EA4] hover:text-[#231F20]">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form id="reviewForm" onsubmit="submitQuickReview(event)">
                        <input type="hidden" id="submissionId" />
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-[#231F20] mb-2">Submission</label>
                            <p id="submissionTitle" class="text-[#9B9EA4]"></p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="reviewScore" class="block text-sm font-medium text-[#231F20] mb-2">
                                    Score (0-100)
                                </label>
                                <input 
                                    type="number" 
                                    id="reviewScore" 
                                    min="0" 
                                    max="100" 
                                    required
                                    class="w-full px-3 py-2 border border-[#9B9EA4]/40 rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent"
                                />
                            </div>
                        </div>

                        <div class="mb-6">
                            <label for="reviewFeedback" class="block text-sm font-medium text-[#231F20] mb-2">
                                Feedback
                            </label>
                            <textarea 
                                id="reviewFeedback" 
                                rows="4" 
                                required
                                placeholder="Provide detailed feedback on the submission..."
                                class="w-full px-3 py-2 border border-[#9B9EA4]/40 rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent resize-none"></textarea>
                        </div>

                        <div class="flex gap-3 justify-end">
                            <button 
                                type="button" 
                                onclick="closeReviewModal()"
                                class="px-6 py-2 border border-[#9B9EA4]/40 text-[#231F20] rounded-xl hover:bg-[#9B9EA4]/10 transition-colors duration-200">
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="px-6 py-2 bg-[#FFF200] text-[#231F20] rounded-xl hover:bg-[#FFF200]/80 transition-colors duration-200 font-semibold">
                                Submit Review
                            </button>
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
    
    @this.call('reviewSubmission', submissionId, parseInt(score), feedback);
    closeReviewModal();
}

// Close modal on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeReviewModal();
    }
});
</script>
@endpush
