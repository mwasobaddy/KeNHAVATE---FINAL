<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Artesaos\SEOTools\Facades\SEOTools;
use Carbon\Carbon;

new class extends Component
{
    use WithPagination;

    public Challenge $challenge;
    public string $search = '';
    public string $sortBy = 'score';
    public string $sortDirection = 'desc';
    public string $filterStatus = 'reviewed';
    public bool $showOnlyEligible = true;
    
    // Winner selection
    public array $selectedWinners = [];
    public int $maxWinners = 3;
    public bool $showConfirmation = false;
    public string $announcementMessage = '';
    public bool $notifyWinners = true;
    public bool $notifyParticipants = true;

    public function mount(Challenge $challenge): void
    {
        // Authorization check - only managers and admins can select winners
        if (!auth()->user()->hasAnyRole(['manager', administrator, 'developer'])) {
            abort(403, 'Unauthorized access to winner selection.');
        }

        $this->challenge = $challenge->load(['submissions.author', 'submissions.reviews']);
        
        // Set SEO meta tags
        SEOTools::setTitle('Select Winners - ' . $challenge->title . ' - KeNHAVATE Innovation Portal');
        SEOTools::setDescription('Select winners for the ' . $challenge->title . ' innovation challenge.');
        SEOTools::setCanonical(route('challenges.select-winners', $challenge));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedShowOnlyEligible(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }
        $this->resetPage();
    }

    public function toggleWinnerSelection(int $submissionId): void
    {
        if (in_array($submissionId, $this->selectedWinners)) {
            $this->selectedWinners = array_filter($this->selectedWinners, fn($id) => $id !== $submissionId);
        } else {
            if (count($this->selectedWinners) < $this->maxWinners) {
                $this->selectedWinners[] = $submissionId;
            } else {
                $this->addError('max_winners', "You can only select up to {$this->maxWinners} winners.");
            }
        }
        
        // Re-index array to prevent gaps
        $this->selectedWinners = array_values($this->selectedWinners);
    }

    public function clearSelection(): void
    {
        $this->selectedWinners = [];
        $this->clearValidation();
    }

    public function proceedToConfirmation(): void
    {
        if (empty($this->selectedWinners)) {
            $this->addError('selection', 'Please select at least one winner.');
            return;
        }

        $this->showConfirmation = true;
        
        // Generate default announcement message
        $winnerCount = count($this->selectedWinners);
        $this->announcementMessage = "We are excited to announce the " . 
            ($winnerCount === 1 ? 'winner' : 'winners') . 
            " of the '{$this->challenge->title}' challenge! " .
            "Congratulations to all participants for their innovative solutions.";
    }

    public function cancelSelection(): void
    {
        $this->showConfirmation = false;
        $this->announcementMessage = '';
    }

    public function announceWinners(): void
    {
        $this->validate([
            'announcementMessage' => 'required|string|min:50|max:1000',
        ]);

        if (empty($this->selectedWinners)) {
            $this->addError('selection', 'No winners selected.');
            return;
        }

        try {
            \DB::transaction(function () {
                // Update challenge status to completed
                $this->challenge->update([
                    'status' => 'completed',
                    'winners_announced_at' => now(),
                ]);

                // Process winner selections
                $winners = ChallengeSubmission::whereIn('id', $this->selectedWinners)
                    ->with(['author', 'challenge'])
                    ->get();

                foreach ($winners as $index => $winner) {
                    $ranking = $index + 1; // 1st, 2nd, 3rd place
                    
                    $winner->update([
                        'status' => 'winner',
                        'ranking' => $ranking,
                        'winner_announced_at' => now(),
                    ]);

                    // Create audit log
                    activity()
                        ->performedOn($winner)
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'action' => 'winner_selected',
                            'challenge_id' => $this->challenge->id,
                            'ranking' => $ranking,
                            'announcement_message' => $this->announcementMessage
                        ])
                        ->log("Challenge submission selected as winner (rank #{$ranking})");
                }

                // Update non-winning submissions to 'completed' status
                ChallengeSubmission::where('challenge_id', $this->challenge->id)
                    ->whereNotIn('id', $this->selectedWinners)
                    ->where('status', '!=', 'winner')
                    ->update(['status' => 'completed']);

                // Send notifications
                if ($this->notifyWinners) {
                    $this->sendWinnerNotifications($winners);
                }

                if ($this->notifyParticipants) {
                    $this->sendParticipantNotifications($winners);
                }

                // Create global audit log for challenge completion
                activity()
                    ->performedOn($this->challenge)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'action' => 'winners_announced',
                        'winner_count' => count($winners),
                        'total_submissions' => $this->challenge->submissions()->count(),
                        'announcement_message' => $this->announcementMessage
                    ])
                    ->log('Challenge winners announced and challenge completed');
            });

            session()->flash('success', 'Winners have been successfully announced! The challenge has been marked as completed.');
            
            return redirect()->route('challenges.show', $this->challenge);
            
        } catch (\Exception $e) {
            $this->addError('announcement', 'Failed to announce winners: ' . $e->getMessage());
        }
    }

    private function sendWinnerNotifications($winners): void
    {
        foreach ($winners as $winner) {
            $rankingText = match($winner->ranking) {
                1 => '1st place',
                2 => '2nd place', 
                3 => '3rd place',
                default => "#{$winner->ranking} place"
            };

            $winner->author->notifications()->create([
                'type' => 'challenge_winner',
                'title' => '🎉 Congratulations! You Won!',
                'message' => "Your submission '{$winner->title}' has been selected as the {$rankingText} winner of the '{$this->challenge->title}' challenge!",
                'related_id' => $winner->id,
                'related_type' => ChallengeSubmission::class,
            ]);
        }
    }

    private function sendParticipantNotifications($winners): void
    {
        // Get all participants who didn't win
        $allParticipants = User::whereHas('challengeSubmissions', function (Builder $query) {
            $query->where('challenge_id', $this->challenge->id);
        })
        ->whereNotIn('id', $winners->pluck('author.id'))
        ->get();

        foreach ($allParticipants as $participant) {
            $participant->notifications()->create([
                'type' => 'challenge_completed',
                'title' => 'Challenge Results Announced',
                'message' => "The winners of the '{$this->challenge->title}' challenge have been announced. Thank you for your participation!",
                'related_id' => $this->challenge->id,
                'related_type' => Challenge::class,
            ]);
        }
    }

    public function getEligibleSubmissions()
    {
        $query = ChallengeSubmission::with(['author', 'reviews'])
            ->where('challenge_id', $this->challenge->id);

        // Apply search filter
        if ($this->search) {
            $query->where(function (Builder $q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
                  ->orWhereHas('author', function (Builder $subQ) {
                      $subQ->where('name', 'like', "%{$this->search}%")
                           ->orWhere('email', 'like', "%{$this->search}%");
                  });
            });
        }

        // Apply status filter
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        // Show only eligible submissions filter
        if ($this->showOnlyEligible) {
            $query->whereIn('status', ['reviewed', 'approved'])
                  ->whereHas('reviews'); // Must have at least one review
        }

        // Apply sorting
        switch ($this->sortBy) {
            case 'score':
                $query->orderBy('score', $this->sortDirection);
                break;
            case 'title':
                $query->orderBy('title', $this->sortDirection);
                break;
            case 'author':
                $query->join('users', 'challenge_submissions.author_id', '=', 'users.id')
                      ->orderBy('users.name', $this->sortDirection)
                      ->select('challenge_submissions.*');
                break;
            case 'submitted_at':
                $query->orderBy('submitted_at', $this->sortDirection);
                break;
            default:
                $query->orderBy('score', 'desc');
        }

        return $query->paginate(20);
    }

    public function getStatusColor(string $status): string
    {
        return match($status) {
            'pending' => 'text-blue-600 bg-blue-50 border-blue-200',
            'under_review' => 'text-yellow-600 bg-yellow-50 border-yellow-200',
            'reviewed' => 'text-green-600 bg-green-50 border-green-200',
            'approved' => 'text-green-700 bg-green-100 border-green-300',
            'needs_revision' => 'text-orange-600 bg-orange-50 border-orange-200',
            'rejected' => 'text-red-600 bg-red-50 border-red-200',
            'winner' => 'text-yellow-800 bg-yellow-100 border-yellow-300',
            default => 'text-gray-600 bg-gray-50 border-gray-200'
        };
    }

    public function with(): array
    {
        $submissions = $this->getEligibleSubmissions();

        // Calculate statistics
        $totalSubmissions = ChallengeSubmission::where('challenge_id', $this->challenge->id)->count();
        $reviewedSubmissions = ChallengeSubmission::where('challenge_id', $this->challenge->id)
            ->whereIn('status', ['reviewed', 'approved'])
            ->count();
        $averageScore = ChallengeSubmission::where('challenge_id', $this->challenge->id)
            ->whereNotNull('score')
            ->avg('score');
        $topScore = ChallengeSubmission::where('challenge_id', $this->challenge->id)
            ->max('score');

        return [
            'submissions' => $submissions,
            'statistics' => [
                'total_submissions' => $totalSubmissions,
                'reviewed_submissions' => $reviewedSubmissions,
                'average_score' => round($averageScore ?? 0, 1),
                'top_score' => $topScore ?? 0,
                'completion_rate' => $totalSubmissions > 0 ? round(($reviewedSubmissions / $totalSubmissions) * 100, 1) : 0
            ]
        ];
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-[#F8EBD5] via-[#F8EBD5] to-[#E8D5C5] py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-4 mb-6">
                <a href="{{ route('challenges.show', $challenge) }}" 
                   class="inline-flex items-center px-4 py-2 bg-white/80 hover:bg-white text-[#9B9EA4] hover:text-[#231F20] rounded-xl transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Challenge
                </a>
            </div>
            
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-white/50 shadow-lg p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-[#231F20] mb-2">Select Winners</h1>
                        <h2 class="text-xl text-[#9B9EA4] mb-4">{{ $challenge->title }}</h2>
                        <p class="text-[#9B9EA4]">Choose the best submissions to win this challenge</p>
                    </div>
                    
                    <div class="text-right">
                        <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border
                            @if($challenge->status === 'active') bg-green-50 text-green-800 border-green-200
                            @elseif($challenge->status === 'judging') bg-yellow-50 text-yellow-800 border-yellow-200
                            @elseif($challenge->status === 'completed') bg-blue-50 text-blue-800 border-blue-200
                            @else bg-gray-50 text-gray-800 border-gray-200
                            @endif">
                            {{ ucfirst($challenge->status) }}
                        </div>
                        @if($challenge->deadline)
                            <p class="text-sm text-[#9B9EA4] mt-2">
                                Deadline: {{ $challenge->deadline->format('M d, Y g:i A') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 border border-white/50 shadow-lg">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-[#231F20]">{{ $statistics['total_submissions'] }}</p>
                        <p class="text-[#9B9EA4] text-sm">Total Submissions</p>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 border border-white/50 shadow-lg">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-[#231F20]">{{ $statistics['reviewed_submissions'] }}</p>
                        <p class="text-[#9B9EA4] text-sm">Reviewed</p>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 border border-white/50 shadow-lg">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-[#231F20]">{{ $statistics['average_score'] }}%</p>
                        <p class="text-[#9B9EA4] text-sm">Average Score</p>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 border border-white/50 shadow-lg">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-[#231F20]">{{ $statistics['top_score'] }}%</p>
                        <p class="text-[#9B9EA4] text-sm">Top Score</p>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 border border-white/50 shadow-lg">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-indigo-100">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-[#231F20]">{{ $statistics['completion_rate'] }}%</p>
                        <p class="text-[#9B9EA4] text-sm">Review Rate</p>
                    </div>
                </div>
            </div>
        </div>

        @if(!$showConfirmation)
            <!-- Filters and Selection Controls -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-white/50 shadow-lg mb-8">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-4">
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
                            <select wire:model.live="filterStatus" 
                                    class="w-full px-3 py-2 border border-[#9B9EA4]/30 rounded-lg bg-white/90 text-[#231F20] focus:ring-2 focus:ring-[#FFF200]/20 focus:border-[#FFF200] transition-colors">
                                <option value="">All Statuses</option>
                                <option value="reviewed">Reviewed</option>
                                <option value="approved">Approved</option>
                                <option value="needs_revision">Needs Revision</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>

                        <!-- Max Winners -->
                        <div>
                            <label class="block text-sm font-medium text-[#231F20] mb-2">Max Winners</label>
                            <select wire:model="maxWinners" 
                                    class="w-full px-3 py-2 border border-[#9B9EA4]/30 rounded-lg bg-white/90 text-[#231F20] focus:ring-2 focus:ring-[#FFF200]/20 focus:border-[#FFF200] transition-colors">
                                <option value="1">1 Winner</option>
                                <option value="3">3 Winners</option>
                                <option value="5">5 Winners</option>
                                <option value="10">10 Winners</option>
                            </select>
                        </div>

                        <!-- Show Only Eligible -->
                        <div class="flex items-end">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input wire:model.live="showOnlyEligible" 
                                       type="checkbox" 
                                       class="w-4 h-4 text-[#FFF200] border-[#9B9EA4] rounded focus:ring-[#FFF200]/20">
                                <span class="text-sm text-[#231F20]">Eligible only</span>
                            </label>
                        </div>

                        <!-- Clear Selection -->
                        @if(!empty($selectedWinners))
                            <div class="flex items-end">
                                <button wire:click="clearSelection" 
                                        class="w-full px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
                                    Clear Selection
                                </button>
                            </div>
                        @endif
                    </div>

                    <!-- Selection Summary -->
                    @if(!empty($selectedWinners))
                        <div class="bg-[#FFF200]/20 border border-[#FFF200]/40 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-[#231F20] mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                    </svg>
                                    <span class="font-semibold text-[#231F20]">
                                        {{ count($selectedWinners) }} / {{ $maxWinners }} Winners Selected
                                    </span>
                                </div>
                                <button wire:click="proceedToConfirmation" 
                                        class="px-4 py-2 bg-[#FFF200] hover:bg-[#FFE066] text-[#231F20] font-semibold rounded-lg transition-colors">
                                    Proceed to Announcement
                                </button>
                            </div>
                        </div>
                    @endif

                    @error('max_winners')
                        <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
                    @enderror
                    @error('selection')
                        <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Submissions Table -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-white/50 shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-[#231F20]/5 border-b border-[#9B9EA4]/20">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Select</th>
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
                                <th class="px-6 py-4 text-left">
                                    <button wire:click="sortBy('score')" class="flex items-center space-x-1 text-sm font-semibold text-[#231F20] hover:text-[#FFF200] transition-colors">
                                        <span>Score</span>
                                        @if($sortBy === 'score')
                                            <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        @endif
                                    </button>
                                </th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Status</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Reviews</th>
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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#9B9EA4]/10">
                            @forelse($submissions as $submission)
                                @php
                                    $isSelected = in_array($submission->id, $selectedWinners);
                                    $ranking = array_search($submission->id, $selectedWinners);
                                    $isEligible = in_array($submission->status, ['reviewed', 'approved']) && $submission->reviews->isNotEmpty();
                                @endphp
                                <tr class="hover:bg-[#FFF200]/5 transition-colors {{ $isSelected ? 'bg-[#FFF200]/10 border-l-4 border-[#FFF200]' : '' }}">
                                    <td class="px-6 py-4">
                                        @if($isEligible)
                                            <button wire:click="toggleWinnerSelection({{ $submission->id }})" 
                                                    class="relative w-6 h-6 rounded-full border-2 transition-all duration-200 
                                                           {{ $isSelected ? 'bg-[#FFF200] border-[#FFF200]' : 'border-[#9B9EA4] hover:border-[#FFF200]' }}">
                                                @if($isSelected)
                                                    <div class="absolute inset-0 flex items-center justify-center">
                                                        <span class="text-xs font-bold text-[#231F20]">{{ $ranking + 1 }}</span>
                                                    </div>
                                                @endif
                                            </button>
                                        @else
                                            <div class="w-6 h-6 rounded-full border-2 border-gray-300 bg-gray-100 opacity-50"></div>
                                        @endif
                                    </td>
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
                                        @if($submission->score)
                                            <div class="flex items-center">
                                                <span class="text-2xl font-bold text-[#231F20]">{{ $submission->score }}%</span>
                                                <div class="ml-2 flex">
                                                    @for($i = 1; $i <= 5; $i++)
                                                        <svg class="w-4 h-4 {{ $submission->score >= ($i * 20) ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                                        </svg>
                                                    @endfor
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-[#9B9EA4]">Not scored</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-medium border {{ $this->getStatusColor($submission->status) }}">
                                            {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                        </span>
                                        @if(!$isEligible)
                                            <p class="text-xs text-red-600 mt-1">Not eligible for selection</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <span class="font-medium text-[#231F20]">{{ $submission->reviews->count() }}</span>
                                            <span class="text-[#9B9EA4] ml-1">reviews</span>
                                            @if($submission->reviews->count() > 0)
                                                <div class="ml-2 text-xs text-green-600">
                                                    Avg: {{ round($submission->reviews->avg('score') ?? 0, 1) }}%
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="text-sm text-[#231F20]">{{ $submission->submitted_at->format('M d, Y') }}</p>
                                            <p class="text-xs text-[#9B9EA4]">{{ $submission->submitted_at->diffForHumans() }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-12 h-12 text-[#9B9EA4] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                            </svg>
                                            <h3 class="text-lg font-medium text-[#231F20] mb-2">No eligible submissions found</h3>
                                            <p class="text-[#9B9EA4]">No submissions match your current filters or are ready for winner selection.</p>
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

        @else
            <!-- Confirmation Modal -->
            <div class="bg-white/90 backdrop-blur-sm rounded-2xl border border-white/50 shadow-xl p-8">
                <div class="max-w-4xl mx-auto">
                    <div class="text-center mb-8">
                        <div class="text-6xl mb-4">🏆</div>
                        <h2 class="text-3xl font-bold text-[#231F20] mb-2">Announce Winners</h2>
                        <p class="text-[#9B9EA4] text-lg">Review your selection and announce the winners of this challenge</p>
                    </div>

                    <!-- Selected Winners Preview -->
                    <div class="bg-[#FFF200]/10 border border-[#FFF200]/30 rounded-xl p-6 mb-8">
                        <h3 class="text-xl font-semibold text-[#231F20] mb-4">Selected Winners</h3>
                        <div class="space-y-4">
                            @foreach(ChallengeSubmission::whereIn('id', $selectedWinners)->with('author')->get() as $index => $winner)
                                <div class="flex items-center gap-4 p-4 bg-white/80 rounded-lg">
                                    <div class="flex items-center justify-center w-12 h-12 bg-[#FFF200] rounded-full text-[#231F20] font-bold text-lg">
                                        {{ $index + 1 }}
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-[#231F20]">{{ $winner->title }}</h4>
                                        <p class="text-[#9B9EA4]">by {{ $winner->author->name }}</p>
                                        @if($winner->score)
                                            <p class="text-sm text-green-600">Score: {{ $winner->score }}%</p>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <span class="text-2xl">
                                            @if($index === 0) 🥇
                                            @elseif($index === 1) 🥈
                                            @elseif($index === 2) 🥉
                                            @else 🏅
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Announcement Message -->
                    <div class="mb-8">
                        <label class="block text-lg font-semibold text-[#231F20] mb-3">Announcement Message</label>
                        <textarea wire:model="announcementMessage" 
                                  rows="6"
                                  placeholder="Write a message to announce the winners..."
                                  class="w-full px-4 py-3 border border-[#9B9EA4]/30 rounded-xl bg-white/90 text-[#231F20] placeholder-[#9B9EA4] focus:ring-2 focus:ring-[#FFF200]/20 focus:border-[#FFF200] transition-colors resize-none"></textarea>
                        @error('announcementMessage')
                            <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Notification Options -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input wire:model="notifyWinners" 
                                   type="checkbox" 
                                   class="w-5 h-5 text-[#FFF200] border-[#9B9EA4] rounded focus:ring-[#FFF200]/20">
                            <div>
                                <span class="font-medium text-[#231F20]">Notify Winners</span>
                                <p class="text-sm text-[#9B9EA4]">Send congratulatory notifications to winners</p>
                            </div>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input wire:model="notifyParticipants" 
                                   type="checkbox" 
                                   class="w-5 h-5 text-[#FFF200] border-[#9B9EA4] rounded focus:ring-[#FFF200]/20">
                            <div>
                                <span class="font-medium text-[#231F20]">Notify All Participants</span>
                                <p class="text-sm text-[#9B9EA4]">Send result announcements to all participants</p>
                            </div>
                        </label>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-4 justify-center">
                        <button wire:click="cancelSelection" 
                                class="px-8 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-xl transition-colors">
                            Back to Selection
                        </button>
                        
                        <button wire:click="announceWinners" 
                                class="px-8 py-3 bg-[#FFF200] hover:bg-[#FFE066] text-[#231F20] font-bold rounded-xl transition-colors shadow-lg hover:shadow-xl transform hover:scale-105"
                                wire:loading.attr="disabled">
                            <span wire:loading.remove>🎉 Announce Winners</span>
                            <span wire:loading>Announcing...</span>
                        </button>
                    </div>

                    @error('announcement')
                        <p class="text-red-600 text-center mt-4">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        @endif
    </div>
</div>
