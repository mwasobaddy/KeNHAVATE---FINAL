<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\ChallengeSubmission;
use App\Models\Challenge;
use App\Models\ChallengeReview;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $priorityFilter = 'all';
    public string $challengeFilter = 'all';
    public string $sortBy = 'deadline';
    public string $sortDirection = 'asc';
    public bool $showMyReviewsOnly = false;

    public function mount(): void
    {
        // Authorization check - only reviewers can access this page
        if (!auth()->user()->hasAnyRole(['manager', 'challenge_reviewer', 'sme', 'admin', 'developer'])) {
            abort(403, 'Unauthorized access to challenge reviews.');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPriorityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedChallengeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedShowMyReviewsOnly(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function getReviewPriority(ChallengeSubmission $submission): string
    {
        $daysUntilDeadline = Carbon::parse($submission->challenge->deadline)->diffInDays(now(), false);
        
        if ($daysUntilDeadline <= 1) {
            return 'urgent';
        } elseif ($daysUntilDeadline <= 3) {
            return 'high';
        } elseif ($daysUntilDeadline <= 7) {
            return 'medium';
        }
        
        return 'low';
    }

    public function getPriorityColor(string $priority): string
    {
        return match($priority) {
            'urgent' => 'text-red-600 bg-red-50 border-red-200',
            'high' => 'text-orange-600 bg-orange-50 border-orange-200',
            'medium' => 'text-yellow-600 bg-yellow-50 border-yellow-200',
            'low' => 'text-green-600 bg-green-50 border-green-200',
            default => 'text-gray-600 bg-gray-50 border-gray-200'
        };
    }

    public function getStatusColor(string $status): string
    {
        return match($status) {
            'pending' => 'text-blue-600 bg-blue-50 border-blue-200',
            'under_review' => 'text-yellow-600 bg-yellow-50 border-yellow-200',
            'reviewed' => 'text-green-600 bg-green-50 border-green-200',
            'needs_revision' => 'text-orange-600 bg-orange-50 border-orange-200',
            'rejected' => 'text-red-600 bg-red-50 border-red-200',
            default => 'text-gray-600 bg-gray-50 border-gray-200'
        };
    }

    public function assignToMe(ChallengeSubmission $submission): void
    {
        // Check if already assigned to someone else
        if ($submission->assigned_reviewer_id && $submission->assigned_reviewer_id !== auth()->id()) {
            $this->addError('assignment', 'This submission is already assigned to another reviewer.');
            return;
        }

        // Assign to current user
        $submission->update([
            'assigned_reviewer_id' => auth()->id(),
            'status' => 'under_review'
        ]);

        // Create audit log
        activity()
            ->performedOn($submission)
            ->causedBy(auth()->user())
            ->withProperties([
                'action' => 'reviewer_assigned',
                'reviewer_id' => auth()->id(),
                'challenge_id' => $submission->challenge_id
            ])
            ->log('Challenge submission assigned to reviewer');

        // Send notification to submission author
        $submission->author->notifications()->create([
            'type' => 'review_assigned',
            'title' => 'Review Started',
            'message' => "Your submission '{$submission->title}' is now under review.",
            'related_id' => $submission->id,
            'related_type' => ChallengeSubmission::class,
        ]);

        $this->dispatch('notification', [
            'type' => 'success',
            'message' => 'Submission assigned to you successfully.'
        ]);
    }

    public function with(): array
    {
        $query = ChallengeSubmission::with([
                'challenge:id,title,deadline,status',
                'author:id,name,email',
                'assignedReviewer:id,name',
                'reviews' => function ($query) {
                    $query->where('reviewer_id', auth()->id());
                }
            ])
            ->whereHas('challenge', function (Builder $query) {
                $query->where('status', 'active')
                    ->orWhere('status', 'judging');
            });

        // Apply filters
        if ($this->search) {
            $query->where(function (Builder $q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
                  ->orWhereHas('author', function (Builder $subQ) {
                      $subQ->where('name', 'like', "%{$this->search}%")
                           ->orWhere('email', 'like', "%{$this->search}%");
                  })
                  ->orWhereHas('challenge', function (Builder $subQ) {
                      $subQ->where('title', 'like', "%{$this->search}%");
                  });
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->challengeFilter !== 'all') {
            $query->where('challenge_id', $this->challengeFilter);
        }

        if ($this->showMyReviewsOnly) {
            $query->where('assigned_reviewer_id', auth()->id());
        }

        // Apply priority filter
        if ($this->priorityFilter !== 'all') {
            $query->whereHas('challenge', function (Builder $q) {
                $now = now();
                switch ($this->priorityFilter) {
                    case 'urgent':
                        $q->where('deadline', '<=', $now->copy()->addDay());
                        break;
                    case 'high':
                        $q->where('deadline', '<=', $now->copy()->addDays(3))
                          ->where('deadline', '>', $now->copy()->addDay());
                        break;
                    case 'medium':
                        $q->where('deadline', '<=', $now->copy()->addWeek())
                          ->where('deadline', '>', $now->copy()->addDays(3));
                        break;
                    case 'low':
                        $q->where('deadline', '>', $now->copy()->addWeek());
                        break;
                }
            });
        }

        // Apply sorting
        switch ($this->sortBy) {
            case 'deadline':
                $query->join('challenges', 'challenge_submissions.challenge_id', '=', 'challenges.id')
                      ->orderBy('challenges.deadline', $this->sortDirection)
                      ->select('challenge_submissions.*');
                break;
            case 'submitted_at':
                $query->orderBy('submitted_at', $this->sortDirection);
                break;
            case 'title':
                $query->orderBy('title', $this->sortDirection);
                break;
            case 'author':
                $query->join('users', 'challenge_submissions.author_id', '=', 'users.id')
                      ->orderBy('users.name', $this->sortDirection)
                      ->select('challenge_submissions.*');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        $submissions = $query->paginate(15);

        // Get available challenges for filter
        $challenges = Challenge::where('status', 'active')
            ->orWhere('status', 'judging')
            ->orderBy('title')
            ->get(['id', 'title']);

        // Calculate statistics
        $totalPending = ChallengeSubmission::whereIn('status', ['pending', 'under_review'])->count();
        $myAssigned = ChallengeSubmission::where('assigned_reviewer_id', auth()->id())->count();
        $completedToday = ChallengeSubmission::where('status', 'reviewed')
            ->whereHas('reviews', function (Builder $q) {
                $q->where('reviewer_id', auth()->id())
                  ->whereDate('created_at', today());
            })->count();
        $urgentReviews = ChallengeSubmission::whereIn('status', ['pending', 'under_review'])
            ->whereHas('challenge', function (Builder $q) {
                $q->where('deadline', '<=', now()->addDay());
            })->count();

        return [
            'submissions' => $submissions,
            'challenges' => $challenges,
            'statistics' => [
                'total_pending' => $totalPending,
                'my_assigned' => $myAssigned,
                'completed_today' => $completedToday,
                'urgent_reviews' => $urgentReviews
            ]
        ];
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-[#F8EBD5] via-[#F8EBD5] to-[#E8D5C5] py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-[#231F20] mb-2">Challenge Reviews</h1>
                    <p class="text-[#9B9EA4] text-lg">Review and evaluate challenge submissions</p>
                </div>
                
                <div class="mt-4 sm:mt-0 flex items-center space-x-3">
                    <div class="flex items-center space-x-2 text-sm text-[#9B9EA4]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>{{ now()->format('M d, Y • g:i A') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 border border-white/50 shadow-lg hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-[#231F20]">{{ $statistics['total_pending'] }}</p>
                        <p class="text-[#9B9EA4] text-sm">Pending Reviews</p>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 border border-white/50 shadow-lg hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-[#231F20]">{{ $statistics['my_assigned'] }}</p>
                        <p class="text-[#9B9EA4] text-sm">Assigned to Me</p>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 border border-white/50 shadow-lg hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-[#231F20]">{{ $statistics['completed_today'] }}</p>
                        <p class="text-[#9B9EA4] text-sm">Completed Today</p>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 border border-white/50 shadow-lg hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-[#231F20]">{{ $statistics['urgent_reviews'] }}</p>
                        <p class="text-[#9B9EA4] text-sm">Urgent Reviews</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-white/50 shadow-lg mb-8">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-[#231F20] mb-2">Search</label>
                        <input wire:model.live="search" 
                               type="text" 
                               placeholder="Search submissions..."
                               class="w-full px-3 py-2 border border-[#9B9EA4]/30 rounded-lg bg-white/90 text-[#231F20] placeholder-[#9B9EA4] focus:ring-2 focus:ring-[#FFF200]/20 focus:border-[#FFF200] transition-colors">
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-[#231F20] mb-2">Status</label>
                        <select wire:model.live="statusFilter" 
                                class="w-full px-3 py-2 border border-[#9B9EA4]/30 rounded-lg bg-white/90 text-[#231F20] focus:ring-2 focus:ring-[#FFF200]/20 focus:border-[#FFF200] transition-colors">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="under_review">Under Review</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="needs_revision">Needs Revision</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>

                    <!-- Challenge Filter -->
                    <div>
                        <label class="block text-sm font-medium text-[#231F20] mb-2">Challenge</label>
                        <select wire:model.live="challengeFilter" 
                                class="w-full px-3 py-2 border border-[#9B9EA4]/30 rounded-lg bg-white/90 text-[#231F20] focus:ring-2 focus:ring-[#FFF200]/20 focus:border-[#FFF200] transition-colors">
                            <option value="all">All Challenges</option>
                            @foreach($challenges as $challenge)
                                <option value="{{ $challenge->id }}">{{ $challenge->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Priority Filter -->
                    <div>
                        <label class="block text-sm font-medium text-[#231F20] mb-2">Priority</label>
                        <select wire:model.live="priorityFilter" 
                                class="w-full px-3 py-2 border border-[#9B9EA4]/30 rounded-lg bg-white/90 text-[#231F20] focus:ring-2 focus:ring-[#FFF200]/20 focus:border-[#FFF200] transition-colors">
                            <option value="all">All Priorities</option>
                            <option value="urgent">🔴 Urgent</option>
                            <option value="high">🟠 High</option>
                            <option value="medium">🟡 Medium</option>
                            <option value="low">🟢 Low</option>
                        </select>
                    </div>

                    <!-- My Reviews Only -->
                    <div class="flex items-end">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input wire:model.live="showMyReviewsOnly" 
                                   type="checkbox" 
                                   class="w-4 h-4 text-[#FFF200] border-[#9B9EA4] rounded focus:ring-[#FFF200]/20">
                            <span class="text-sm text-[#231F20]">My reviews only</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reviews Table -->
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-white/50 shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-[#231F20]/5 border-b border-[#9B9EA4]/20">
                        <tr>
                            <th class="px-6 py-4 text-left">
                                <button wire:click="sortBy('title')" class="flex items-center space-x-1 text-sm font-semibold text-[#231F20] hover:text-[#FFF200] transition-colors">
                                    <span>Submission</span>
                                    @if($sortBy === 'title')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-4 text-left">
                                <button wire:click="sortBy('author')" class="flex items-center space-x-1 text-sm font-semibold text-[#231F20] hover:text-[#FFF200] transition-colors">
                                    <span>Author</span>
                                    @if($sortBy === 'author')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Challenge</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Status</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Priority</th>
                            <th class="px-6 py-4 text-left">
                                <button wire:click="sortBy('deadline')" class="flex items-center space-x-1 text-sm font-semibold text-[#231F20] hover:text-[#FFF200] transition-colors">
                                    <span>Deadline</span>
                                    @if($sortBy === 'deadline')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-4 text-left">
                                <button wire:click="sortBy('submitted_at')" class="flex items-center space-x-1 text-sm font-semibold text-[#231F20] hover:text-[#FFF200] transition-colors">
                                    <span>Submitted</span>
                                    @if($sortBy === 'submitted_at')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-[#231F20]">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#9B9EA4]/10">
                        @forelse($submissions as $submission)
                            @php
                                $priority = $this->getReviewPriority($submission);
                                $isMyReview = $submission->assigned_reviewer_id === auth()->id();
                                $hasMyReview = $submission->reviews->isNotEmpty();
                            @endphp
                            <tr class="hover:bg-[#FFF200]/5 transition-colors">
                                <td class="px-6 py-4">
                                    <div>
                                        <h3 class="font-semibold text-[#231F20] mb-1">{{ $submission->title }}</h3>
                                        <p class="text-sm text-[#9B9EA4] line-clamp-2">{{ Str::limit($submission->description, 100) }}</p>
                                        @if($submission->team_name)
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 mt-1">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                </svg>
                                                {{ $submission->team_name }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-gradient-to-br from-[#FFF200] to-[#FFE066] rounded-full flex items-center justify-center text-sm font-semibold text-[#231F20] mr-3">
                                            {{ strtoupper(substr($submission->author->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-[#231F20]">{{ $submission->author->name }}</p>
                                            <p class="text-sm text-[#9B9EA4]">{{ $submission->author->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-[#231F20]">{{ $submission->challenge->title }}</p>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ ucfirst(str_replace('_', ' ', $submission->challenge->status)) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-medium border {{ $this->getStatusColor($submission->status) }}">
                                        {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                    </span>
                                    @if($isMyReview && !$hasMyReview)
                                        <span class="block text-xs text-blue-600 mt-1">Assigned to you</span>
                                    @elseif($hasMyReview)
                                        <span class="block text-xs text-green-600 mt-1">You reviewed this</span>
                                    @elseif($submission->assigned_reviewer_id)
                                        <span class="block text-xs text-[#9B9EA4] mt-1">{{ $submission->assignedReviewer->name }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-medium border {{ $this->getPriorityColor($priority) }}">
                                        {{ ucfirst($priority) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="text-sm text-[#231F20]">{{ $submission->challenge->deadline->format('M d, Y') }}</p>
                                        <p class="text-xs text-[#9B9EA4]">{{ $submission->challenge->deadline->format('g:i A') }}</p>
                                        @if($submission->challenge->deadline->isPast())
                                            <span class="text-xs text-red-600">Overdue</span>
                                        @else
                                            <span class="text-xs text-[#9B9EA4]">{{ $submission->challenge->deadline->diffForHumans() }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="text-sm text-[#231F20]">{{ $submission->submitted_at->format('M d, Y') }}</p>
                                        <p class="text-xs text-[#9B9EA4]">{{ $submission->submitted_at->diffForHumans() }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center space-x-2">
                                        @if(!$submission->assigned_reviewer_id || $isMyReview)
                                            @if(!$submission->assigned_reviewer_id)
                                                <button wire:click="assignToMe({{ $submission->id }})" 
                                                        class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                    </svg>
                                                    Assign to Me
                                                </button>
                                            @endif
                                            
                                            <a href="{{ route('challenge-reviews.review', $submission) }}" 
                                               class="inline-flex items-center px-3 py-1.5 bg-[#FFF200] text-[#231F20] text-xs font-medium rounded-lg hover:bg-[#FFE066] transition-colors">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                {{ $hasMyReview ? 'View Review' : 'Review' }}
                                            </a>
                                        @else
                                            <span class="text-xs text-[#9B9EA4]">Assigned to {{ $submission->assignedReviewer->name }}</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-12 h-12 text-[#9B9EA4] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                        <h3 class="text-lg font-medium text-[#231F20] mb-2">No submissions found</h3>
                                        <p class="text-[#9B9EA4]">No challenge submissions match your current filters.</p>
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
</div>
