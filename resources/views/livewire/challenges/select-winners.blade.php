<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Artesaos\SEOTools\Facades\SEOTools;
use Carbon\Carbon;
use Livewire\Attributes\{Layout, Title};

new #[Layout('components.layouts.app')] #[Title('Select Challenge Winners')] class extends Component
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
        if (
            !auth()
                ->user()
                ->hasAnyRole(['manager', administrator, 'developer'])
        ) {
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
        $this->announcementMessage = 'We are excited to announce the ' . ($winnerCount === 1 ? 'winner' : 'winners') . " of the '{$this->challenge->title}' challenge! " . 'Congratulations to all participants for their innovative solutions.';
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
                            'announcement_message' => $this->announcementMessage,
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
                        'announcement_message' => $this->announcementMessage,
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
            $rankingText = match ($winner->ranking) {
                1 => '1st place',
                2 => '2nd place',
                3 => '3rd place',
                default => "#{$winner->ranking} place",
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
        $query = ChallengeSubmission::with(['author', 'reviews'])->where('challenge_id', $this->challenge->id);

        // Apply search filter
        if ($this->search) {
            $query->where(function (Builder $q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%")
                    ->orWhereHas('author', function (Builder $subQ) {
                        $subQ->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%");
                    });
            });
        }

        // Apply status filter
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        // Show only eligible submissions filter
        if ($this->showOnlyEligible) {
            $query->whereIn('status', ['reviewed', 'approved'])->whereHas('reviews'); // Must have at least one review
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
                $query->join('users', 'challenge_submissions.author_id', '=', 'users.id')->orderBy('users.name', $this->sortDirection)->select('challenge_submissions.*');
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
        return match ($status) {
            'pending' => 'text-blue-600 bg-blue-50 border-blue-200',
            'under_review' => 'text-yellow-600 bg-yellow-50 border-yellow-200',
            'reviewed' => 'text-green-600 bg-green-50 border-green-200',
            'approved' => 'text-green-700 bg-green-100 border-green-300',
            'needs_revision' => 'text-orange-600 bg-orange-50 border-orange-200',
            'rejected' => 'text-red-600 bg-red-50 border-red-200',
            'winner' => 'text-yellow-800 bg-yellow-100 border-yellow-300',
            default => 'text-gray-600 bg-gray-50 border-gray-200',
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
        $averageScore = ChallengeSubmission::where('challenge_id', $this->challenge->id)->whereNotNull('score')->avg('score');
        $topScore = ChallengeSubmission::where('challenge_id', $this->challenge->id)->max('score');

        return [
            'submissions' => $submissions,
            'statistics' => [
                'total_submissions' => $totalSubmissions,
                'reviewed_submissions' => $reviewedSubmissions,
                'average_score' => round($averageScore ?? 0, 1),
                'top_score' => $topScore ?? 0,
                'completion_rate' => $totalSubmissions > 0 ? round(($reviewedSubmissions / $totalSubmissions) * 100, 1) : 0,
            ],
        ];
    }
}; ?>

{{-- Modern Winner Selection Interface with Glass Morphism & Enhanced UI --}}
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

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Enhanced Header Section --}}
        <section aria-labelledby="winner-selection-heading" class="group">
            <div
                class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Animated Background Gradient --}}
                <div
                    class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl">
                </div>

                <div class="relative z-10 p-8">
                    {{-- Back Navigation --}}
                    <div class="mb-6">
                        <a href="{{ route('challenges.show', $challenge) }}"
                            class="group inline-flex items-center px-6 py-3 bg-white/80 dark:bg-zinc-700/80 hover:bg-white dark:hover:bg-zinc-600 text-[#9B9EA4] dark:text-zinc-300 hover:text-[#231F20] dark:hover:text-white rounded-2xl backdrop-blur-sm border border-white/40 dark:border-zinc-600/40 transition-all duration-300 hover:shadow-lg transform hover:-translate-y-1">
                            <svg class="w-5 h-5 mr-3 transition-transform group-hover:-translate-x-1" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            <span class="font-medium">Back to Challenge</span>
                        </a>
                    </div>

                    {{-- Enhanced Header Content --}}
                    <div class="flex items-start justify-between">
                        <div class="flex items-center space-x-4">
                            <div
                                class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center shadow-xl">
                                <svg class="w-8 h-8 text-[#231F20]" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z">
                                    </path>
                                </svg>
                            </div>
                            <div>
                                <h1 id="winner-selection-heading"
                                    class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">Select Winners
                                </h1>
                                <h2 class="text-2xl font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2">
                                    {{ $challenge->title }}</h2>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 leading-relaxed">Choose the best submissions
                                    to win this challenge</p>
                            </div>
                        </div>

                        <div class="text-right">
                            <div
                                class="inline-flex items-center px-4 py-2 rounded-2xl text-sm font-semibold border backdrop-blur-sm shadow-lg
                                @if ($challenge->status === 'active') bg-emerald-50/80 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-400 border-emerald-200/50 dark:border-emerald-700/50
                                @elseif($challenge->status === 'judging') bg-amber-50/80 dark:bg-amber-900/30 text-amber-800 dark:text-amber-400 border-amber-200/50 dark:border-amber-700/50
                                @elseif($challenge->status === 'completed') bg-blue-50/80 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400 border-blue-200/50 dark:border-blue-700/50
                                @else bg-gray-50/80 dark:bg-gray-700/30 text-gray-800 dark:text-gray-400 border-gray-200/50 dark:border-gray-600/50 @endif">
                                <div
                                    class="w-2 h-2 rounded-full mr-2 animate-pulse
                                    @if ($challenge->status === 'active') bg-emerald-500 dark:bg-emerald-400
                                    @elseif($challenge->status === 'judging') bg-amber-500 dark:bg-amber-400
                                    @elseif($challenge->status === 'completed') bg-blue-500 dark:bg-blue-400
                                    @else bg-gray-500 dark:bg-gray-400 @endif">
                                </div>
                                {{ ucfirst($challenge->status) }}
                            </div>
                            @if ($challenge->deadline)
                                <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mt-3 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Deadline: {{ $challenge->deadline->format('M d, Y g:i A') }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Statistics Cards with Glass Morphism --}}
        <section aria-labelledby="statistics-heading" class="group">
            <h2 id="statistics-heading" class="sr-only">Challenge Statistics</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                {{-- Total Submissions Card --}}
                <div
                    class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500">
                    </div>

                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div
                                class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                    </path>
                                </svg>
                            </div>
                            <div
                                class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500">
                            </div>
                        </div>

                        <div>
                            <p
                                class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">
                                Total Submissions</p>
                            <p
                                class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-blue-600 dark:group-hover/card:text-blue-400 transition-colors duration-300">
                                {{ number_format($statistics['total_submissions']) }}</p>
                        </div>
                    </div>
                </div>

                {{-- Reviewed Submissions Card --}}
                <div
                    class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-emerald-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500">
                    </div>

                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div
                                class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div
                                class="absolute -inset-2 bg-emerald-500/20 dark:bg-emerald-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500">
                            </div>
                        </div>

                        <div>
                            <p
                                class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">
                                Reviewed</p>
                            <p
                                class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-emerald-600 dark:group-hover/card:text-emerald-400 transition-colors duration-300">
                                {{ number_format($statistics['reviewed_submissions']) }}</p>
                        </div>
                    </div>
                </div>

                {{-- Average Score Card --}}
                <div
                    class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-transparent to-purple-600/10 dark:from-purple-400/10 dark:via-transparent dark:to-purple-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500">
                    </div>

                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div
                                class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <div
                                class="absolute -inset-2 bg-purple-500/20 dark:bg-purple-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500">
                            </div>
                        </div>

                        <div>
                            <p
                                class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">
                                Average Score</p>
                            <p
                                class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-purple-600 dark:group-hover/card:text-purple-400 transition-colors duration-300">
                                {{ $statistics['average_score'] }}%</p>
                        </div>
                    </div>
                </div>

                {{-- Top Score Card --}}
                <div
                    class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-amber-500/5 via-transparent to-amber-600/10 dark:from-amber-400/10 dark:via-transparent dark:to-amber-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500">
                    </div>

                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div
                                class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z">
                                    </path>
                                </svg>
                            </div>
                            <div
                                class="absolute -inset-2 bg-amber-500/20 dark:bg-amber-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500">
                            </div>
                        </div>

                        <div>
                            <p
                                class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">
                                Top Score</p>
                            <p
                                class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-amber-600 dark:group-hover/card:text-amber-400 transition-colors duration-300">
                                {{ $statistics['top_score'] }}%</p>
                        </div>
                    </div>
                </div>

                {{-- Review Rate Card --}}
                <div
                    class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 via-transparent to-indigo-600/10 dark:from-indigo-400/10 dark:via-transparent dark:to-indigo-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500">
                    </div>

                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div
                                class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-600 dark:from-indigo-400 dark:to-indigo-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                    </path>
                                </svg>
                            </div>
                            <div
                                class="absolute -inset-2 bg-indigo-500/20 dark:bg-indigo-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500">
                            </div>
                        </div>

                        <div>
                            <p
                                class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">
                                Review Rate</p>
                            <p
                                class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-indigo-600 dark:group-hover/card:text-indigo-400 transition-colors duration-300">
                                {{ $statistics['completion_rate'] }}%</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if (!$showConfirmation)
            {{-- Enhanced Filters and Selection Controls --}}
            <section aria-labelledby="filters-heading" class="group">
                <div
                    class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="relative z-10 p-8">
                        <h3 id="filters-heading"
                            class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-6 flex items-center">
                            <div
                                class="w-8 h-8 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-xl flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-[#231F20]" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z">
                                    </path>
                                </svg>
                            </div>
                            Filters & Selection
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6 mb-6">
                            {{-- Enhanced Search Input --}}
                            <div class="group/input">
                                <label
                                    class="block text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-3">Search
                                    Submissions</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                    <input wire:model.live="search" type="text"
                                        placeholder="Search by title, author..."
                                        class="w-full pl-12 pr-4 py-3 border border-white/40 dark:border-zinc-600/40 rounded-2xl bg-white/90 dark:bg-zinc-700/90 text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] dark:placeholder-zinc-400 focus:ring-2 focus:ring-[#FFF200]/20 focus:border-[#FFF200] backdrop-blur-sm transition-all duration-300 shadow-lg">
                                </div>
                            </div>

                            {{-- Enhanced Status Filter --}}
                            <div class="group/input">
                                <label
                                    class="block text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-3">Filter
                                    by Status</label>
                                <select wire:model.live="filterStatus"
                                    class="w-full px-4 py-3 border border-white/40 dark:border-zinc-600/40 rounded-2xl bg-white/90 dark:bg-zinc-700/90 text-[#231F20] dark:text-zinc-100 focus:ring-2 focus:ring-[#FFF200]/20 focus:border-[#FFF200] backdrop-blur-sm transition-all duration-300 shadow-lg">
                                    <option value="">All Statuses</option>
                                    <option value="reviewed">Reviewed</option>
                                    <option value="approved">Approved</option>
                                    <option value="needs_revision">Needs Revision</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>

                            {{-- Enhanced Max Winners --}}
                            <div class="group/input">
                                <label
                                    class="block text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-3">Maximum
                                    Winners</label>
                                <select wire:model="maxWinners"
                                    class="w-full px-4 py-3 border border-white/40 dark:border-zinc-600/40 rounded-2xl bg-white/90 dark:bg-zinc-700/90 text-[#231F20] dark:text-zinc-100 focus:ring-2 focus:ring-[#FFF200]/20 focus:border-[#FFF200] backdrop-blur-sm transition-all duration-300 shadow-lg">
                                    <option value="1">1 Winner</option>
                                    <option value="3">3 Winners</option>
                                    <option value="5">5 Winners</option>
                                    <option value="10">10 Winners</option>
                                </select>
                            </div>

                            {{-- Enhanced Eligible Filter --}}
                            <div class="group/input flex flex-col justify-end">
                                <label
                                    class="flex items-center space-x-3 cursor-pointer p-4 rounded-2xl bg-white/50 dark:bg-zinc-700/50 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:bg-white/70 dark:hover:bg-zinc-600/70 transition-all duration-300 shadow-lg">
                                    <input wire:model.live="showOnlyEligible" type="checkbox"
                                        class="w-5 h-5 text-[#FFF200] border-[#9B9EA4] dark:border-zinc-500 rounded-lg focus:ring-[#FFF200]/20 bg-white/90 dark:bg-zinc-700">
                                    <span class="text-sm font-medium text-[#231F20] dark:text-zinc-100">Show eligible
                                        only</span>
                                </label>
                            </div>

                            {{-- Enhanced Clear Selection --}}
                            @if (!empty($selectedWinners))
                                <div class="group/input flex flex-col justify-end">
                                    <button wire:click="clearSelection"
                                        class="w-full px-4 py-3 bg-red-100/80 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-2xl hover:bg-red-200/80 dark:hover:bg-red-800/40 border border-red-200/50 dark:border-red-700/50 backdrop-blur-sm transition-all duration-300 shadow-lg font-semibold">
                                        Clear Selection
                                    </button>
                                </div>
                            @endif
                        </div>

                        {{-- Enhanced Selection Summary --}}
                        @if (!empty($selectedWinners))
                            <div
                                class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#FFF200]/20 via-[#FFF200]/10 to-[#F8EBD5]/20 dark:from-yellow-400/20 dark:via-yellow-400/10 dark:to-amber-400/20 border border-[#FFF200]/40 dark:border-yellow-400/40 backdrop-blur-sm p-6 shadow-lg">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div
                                            class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                            <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z">
                                                </path>
                                            </svg>
                                        </div>
                                        <div>
                                            <span class="text-xl font-bold text-[#231F20] dark:text-zinc-100">
                                                {{ count($selectedWinners) }} / {{ $maxWinners }} Winners Selected
                                            </span>
                                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mt-1">
                                                Ready to proceed with winner announcement
                                            </p>
                                        </div>
                                    </div>
                                    <button wire:click="proceedToConfirmation"
                                        class="group relative overflow-hidden px-8 py-4 bg-gradient-to-r from-[#FFF200] to-[#FFE066] hover:from-[#FFE066] hover:to-[#FFF200] text-[#231F20] font-bold rounded-2xl transition-all duration-300 shadow-xl hover:shadow-2xl transform hover:scale-105">
                                        <span class="relative z-10 flex items-center">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 7l5 5-5 5M6 7l5 5-5 5"></path>
                                            </svg>
                                            Proceed to Announcement
                                        </span>
                                    </button>
                                </div>
                            </div>
                        @endif

                        {{-- Error Messages --}}
                        @error('max_winners')
                            <div
                                class="mt-4 p-4 bg-red-50/80 dark:bg-red-900/30 border border-red-200/50 dark:border-red-700/50 rounded-2xl backdrop-blur-sm">
                                <p class="text-red-600 dark:text-red-400 text-sm font-medium">{{ $message }}</p>
                            </div>
                        @enderror
                        @error('selection')
                            <div
                                class="mt-4 p-4 bg-red-50/80 dark:bg-red-900/30 border border-red-200/50 dark:border-red-700/50 rounded-2xl backdrop-blur-sm">
                                <p class="text-red-600 dark:text-red-400 text-sm font-medium">{{ $message }}</p>
                            </div>
                        @enderror
                    </div>
                </div>
            </section>

            {{-- Enhanced Submissions Table --}}
            <section aria-labelledby="submissions-heading" class="group">
                <div
                    class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="relative z-10">
                        {{-- Table Header --}}
                        <div class="p-6 border-b border-gray-100/50 dark:border-zinc-700/50">
                            <h3 id="submissions-heading"
                                class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 flex items-center">
                                <div
                                    class="w-8 h-8 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-xl flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-[#231F20]" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                        </path>
                                    </svg>
                                </div>
                                Challenge Submissions
                            </h3>
                        </div>

                        {{-- Enhanced Table --}}
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead
                                    class="bg-[#231F20]/5 dark:bg-zinc-700/20 border-b border-[#9B9EA4]/20 dark:border-zinc-600/20">
                                    <tr>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-bold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">
                                            Select</th>
                                        <th class="px-6 py-4 text-left">
                                            <button wire:click="sortBy('title')"
                                                class="group flex items-center space-x-2 text-sm font-bold text-[#231F20] dark:text-zinc-100 hover:text-[#FFF200] dark:hover:text-yellow-400 transition-colors uppercase tracking-wider">
                                                <span>Submission</span>
                                                @if ($sortBy === 'title')
                                                    <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }} transition-transform"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                @endif
                                            </button>
                                        </th>
                                        <th class="px-6 py-4 text-left">
                                            <button wire:click="sortBy('author')"
                                                class="group flex items-center space-x-2 text-sm font-bold text-[#231F20] dark:text-zinc-100 hover:text-[#FFF200] dark:hover:text-yellow-400 transition-colors uppercase tracking-wider">
                                                <span>Author</span>
                                                @if ($sortBy === 'author')
                                                    <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }} transition-transform"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                @endif
                                            </button>
                                        </th>
                                        <th class="px-6 py-4 text-left">
                                            <button wire:click="sortBy('score')"
                                                class="group flex items-center space-x-2 text-sm font-bold text-[#231F20] dark:text-zinc-100 hover:text-[#FFF200] dark:hover:text-yellow-400 transition-colors uppercase tracking-wider">
                                                <span>Score</span>
                                                @if ($sortBy === 'score')
                                                    <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }} transition-transform"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                @endif
                                            </button>
                                        </th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-bold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">
                                            Status</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-bold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">
                                            Reviews</th>
                                        <th class="px-6 py-4 text-left">
                                            <button wire:click="sortBy('submitted_at')"
                                                class="group flex items-center space-x-2 text-sm font-bold text-[#231F20] dark:text-zinc-100 hover:text-[#FFF200] dark:hover:text-yellow-400 transition-colors uppercase tracking-wider">
                                                <span>Submitted</span>
                                                @if ($sortBy === 'submitted_at')
                                                    <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }} transition-transform"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                @endif
                                            </button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#9B9EA4]/10 dark:divide-zinc-600/20">
                                    @forelse($submissions as $submission)
                                        @php
                                            $isSelected = in_array($submission->id, $selectedWinners);
                                            $ranking = array_search($submission->id, $selectedWinners);
                                            $isEligible =
                                                in_array($submission->status, ['reviewed', 'approved']) &&
                                                $submission->reviews->isNotEmpty();
                                        @endphp
                                        <tr
                                            class="group/row hover:bg-[#FFF200]/5 dark:hover:bg-yellow-400/5 transition-all duration-300 {{ $isSelected ? 'bg-[#FFF200]/10 dark:bg-yellow-400/10 border-l-4 border-[#FFF200] dark:border-yellow-400' : '' }}">
                                            <td class="px-6 py-6">
                                                @if ($isEligible)
                                                    <button wire:click="toggleWinnerSelection({{ $submission->id }})"
                                                        class="relative w-8 h-8 rounded-2xl border-2 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-110
                                                                   {{ $isSelected ? 'bg-gradient-to-br from-[#FFF200] to-[#FFE066] border-[#FFF200] dark:from-yellow-400 dark:to-yellow-500 dark:border-yellow-400' : 'border-[#9B9EA4] dark:border-zinc-500 hover:border-[#FFF200] dark:hover:border-yellow-400 bg-white/80 dark:bg-zinc-700/80' }}">
                                                        @if ($isSelected)
                                                            <div
                                                                class="absolute inset-0 flex items-center justify-center">
                                                                <span
                                                                    class="text-sm font-bold text-[#231F20]">{{ $ranking + 1 }}</span>
                                                            </div>
                                                        @endif
                                                    </button>
                                                @else
                                                    <div
                                                        class="w-8 h-8 rounded-2xl border-2 border-gray-300 dark:border-zinc-600 bg-gray-100 dark:bg-zinc-700 opacity-50">
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-6">
                                                <div class="space-y-2">
                                                    <h3
                                                        class="font-bold text-[#231F20] dark:text-zinc-100 text-lg leading-tight">
                                                        {{ $submission->title }}</h3>
                                                    <p
                                                        class="text-sm text-[#9B9EA4] dark:text-zinc-400 line-clamp-2 leading-relaxed">
                                                        {{ Str::limit($submission->description, 120) }}</p>
                                                    @if ($submission->team_name)
                                                        <span
                                                            class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold bg-purple-100/80 dark:bg-purple-900/30 text-purple-800 dark:text-purple-400 border border-purple-200/50 dark:border-purple-700/50 backdrop-blur-sm">
                                                            <svg class="w-3 h-3 mr-1.5" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                                                </path>
                                                            </svg>
                                                            {{ $submission->team_name }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-6">
                                                <div class="flex items-center space-x-3">
                                                    <div
                                                        class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#FFE066] dark:from-yellow-400 dark:to-yellow-500 rounded-2xl flex items-center justify-center text-sm font-bold text-[#231F20] shadow-lg">
                                                        {{ strtoupper(substr($submission->author->name, 0, 1)) }}
                                                    </div>
                                                    <div>
                                                        <p class="font-semibold text-[#231F20] dark:text-zinc-100">
                                                            {{ $submission->author->name }}</p>
                                                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                                                            {{ $submission->author->email }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-6">
                                                @if ($submission->score)
                                                    <div class="space-y-2">
                                                        <div class="flex items-center space-x-2">
                                                            <span
                                                                class="text-3xl font-bold text-[#231F20] dark:text-zinc-100">{{ $submission->score }}%</span>
                                                        </div>
                                                        <div class="flex space-x-1">
                                                            @for ($i = 1; $i <= 5; $i++)
                                                                <svg class="w-4 h-4 {{ $submission->score >= $i * 20 ? 'text-yellow-400' : 'text-gray-300 dark:text-zinc-600' }}"
                                                                    fill="currentColor" viewBox="0 0 20 20">
                                                                    <path
                                                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                                                    </path>
                                                                </svg>
                                                            @endfor
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="text-[#9B9EA4] dark:text-zinc-400 font-medium">Not
                                                        scored</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-6">
                                                <div class="space-y-2">
                                                    <span
                                                        class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold border backdrop-blur-sm {{ $this->getStatusColor($submission->status) }}">
                                                        {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                                    </span>
                                                    @if (!$isEligible)
                                                        <p class="text-xs text-red-600 dark:text-red-400 font-medium">
                                                            Not eligible for selection</p>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-6">
                                                <div class="space-y-1">
                                                    <div class="flex items-center space-x-2">
                                                        <span
                                                            class="text-lg font-bold text-[#231F20] dark:text-zinc-100">{{ $submission->reviews->count() }}</span>
                                                        <span
                                                            class="text-[#9B9EA4] dark:text-zinc-400 text-sm">reviews</span>
                                                    </div>
                                                    @if ($submission->reviews->count() > 0)
                                                        <div
                                                            class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                                                            Avg:
                                                            {{ round($submission->reviews->avg('score') ?? 0, 1) }}%
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-6">
                                                <div class="space-y-1">
                                                    <p class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">
                                                        {{ $submission->submitted_at->format('M d, Y') }}</p>
                                                    <p class="text-xs text-[#9B9EA4] dark:text-zinc-400">
                                                        {{ $submission->submitted_at->diffForHumans() }}</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-6 py-16 text-center">
                                                <div class="flex flex-col items-center space-y-4">
                                                    <div
                                                        class="w-20 h-20 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center shadow-xl">
                                                        <svg class="w-10 h-10 text-[#231F20]" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                                            </path>
                                                        </svg>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <h3
                                                            class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">
                                                            No Eligible Submissions Found</h3>
                                                        <p
                                                            class="text-[#9B9EA4] dark:text-zinc-400 max-w-md leading-relaxed">
                                                            No submissions match your current filters or are ready for
                                                            winner selection.</p>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Enhanced Pagination --}}
                        @if ($submissions->hasPages())
                            <div
                                class="px-6 py-4 border-t border-[#9B9EA4]/20 dark:border-zinc-600/20 bg-[#231F20]/5 dark:bg-zinc-700/20">
                                {{ $submissions->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @else
            {{-- Enhanced Confirmation Modal --}}
            <section aria-labelledby="confirmation-heading" class="group">
                <div
                    class="relative overflow-hidden rounded-3xl bg-white/90 dark:bg-zinc-800/90 backdrop-blur-xl border border-white/40 dark:border-zinc-700/40 shadow-2xl">
                    {{-- Animated Background --}}
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/10 via-transparent to-[#F8EBD5]/10 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/10">
                    </div>

                    <div class="relative z-10 p-12 max-w-6xl mx-auto">
                        {{-- Enhanced Header --}}
                        <div class="text-center mb-12">
                            <div
                                class="w-24 h-24 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-2xl">
                                <span class="text-4xl">🏆</span>
                            </div>
                            <h2 id="confirmation-heading"
                                class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-4">Announce Winners</h2>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-xl leading-relaxed max-w-2xl mx-auto">
                                Review your selection and announce the winners of this challenge</p>
                        </div>

                        {{-- Enhanced Selected Winners Preview --}}
                        <div
                            class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#FFF200]/20 via-[#FFF200]/10 to-[#F8EBD5]/20 dark:from-yellow-400/20 dark:via-yellow-400/10 dark:to-amber-400/20 border border-[#FFF200]/30 dark:border-yellow-400/30 backdrop-blur-sm p-8 mb-10 shadow-xl">
                            <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-6 flex items-center">
                                <div
                                    class="w-10 h-10 bg-gradient-to-br from-[#FFF200] to-[#FFE066] dark:from-yellow-400 dark:to-yellow-500 rounded-2xl flex items-center justify-center mr-4 shadow-lg">
                                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z">
                                        </path>
                                    </svg>
                                </div>
                                Selected Winners
                            </h3>
                            <div class="space-y-4">
                                @foreach (ChallengeSubmission::whereIn('id', $selectedWinners)->with('author')->get() as $index => $winner)
                                    <div
                                        class="group/winner relative overflow-hidden rounded-2xl bg-white/80 dark:bg-zinc-700/80 backdrop-blur-sm border border-white/50 dark:border-zinc-600/50 p-6 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                                        <div class="flex items-center space-x-6">
                                            <div
                                                class="flex items-center justify-center w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#FFE066] dark:from-yellow-400 dark:to-yellow-500 rounded-2xl text-[#231F20] font-bold text-2xl shadow-xl">
                                                {{ $index + 1 }}
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="text-xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">
                                                    {{ $winner->title }}</h4>
                                                <p class="text-[#9B9EA4] dark:text-zinc-400 font-medium">by
                                                    {{ $winner->author->name }}</p>
                                                @if ($winner->score)
                                                    <p
                                                        class="text-sm text-emerald-600 dark:text-emerald-400 font-semibold mt-2">
                                                        Score: {{ $winner->score }}%</p>
                                                @endif
                                            </div>
                                            <div class="text-right">
                                                <span class="text-4xl">
                                                    @if ($index === 0)
                                                        🥇
                                                    @elseif($index === 1)
                                                        🥈
                                                    @elseif($index === 2)
                                                        🥉
                                                    @else
                                                        🏅
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Enhanced Announcement Message --}}
                        <div class="mb-10">
                            <label
                                class="block text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-4 flex items-center">
                                <div
                                    class="w-8 h-8 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-xl flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-[#231F20]" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.657 9.168-4z">
                                        </path>
                                    </svg>
                                </div>
                                Announcement Message
                            </label>
                            <div class="relative">
                                <textarea wire:model="announcementMessage" rows="6"
                                    placeholder="Write your announcement message for the winners and participants..."
                                    class="w-full px-6 py-4 border border-white/40 dark:border-zinc-600/40 rounded-2xl bg-white/90 dark:bg-zinc-700/90 text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] dark:placeholder-zinc-400 focus:ring-2 focus:ring-[#FFF200]/20 focus:border-[#FFF200] backdrop-blur-sm transition-all duration-300 shadow-lg resize-none"></textarea>
                                @error('announcementMessage')
                                    <p class="mt-2 text-red-600 dark:text-red-400 text-sm font-medium">{{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>

                        {{-- Enhanced Notification Options --}}
                        <div class="mb-10">
                            <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-6 flex items-center">
                                <div
                                    class="w-8 h-8 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-xl flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-[#231F20]" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 17h5l-5 5-5-5h5V7a1 1 0 011-1h4a1 1 0 011 1v10z"></path>
                                    </svg>
                                </div>
                                Notification Settings
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <label
                                    class="group/option flex items-center space-x-4 p-6 rounded-2xl bg-white/50 dark:bg-zinc-700/50 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:bg-white/70 dark:hover:bg-zinc-600/70 transition-all duration-300 shadow-lg cursor-pointer">
                                    <input wire:model="notifyWinners" type="checkbox"
                                        class="w-6 h-6 text-[#FFF200] border-[#9B9EA4] dark:border-zinc-500 rounded-lg focus:ring-[#FFF200]/20 bg-white/90 dark:bg-zinc-700">
                                    <div class="flex-1">
                                        <span
                                            class="text-lg font-semibold text-[#231F20] dark:text-zinc-100 block">Notify
                                            Winners</span>
                                        <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">Send congratulatory
                                            notifications to winning participants</span>
                                    </div>
                                </label>

                                <label
                                    class="group/option flex items-center space-x-4 p-6 rounded-2xl bg-white/50 dark:bg-zinc-700/50 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:bg-white/70 dark:hover:bg-zinc-600/70 transition-all duration-300 shadow-lg cursor-pointer">
                                    <input wire:model="notifyParticipants" type="checkbox"
                                        class="w-6 h-6 text-[#FFF200] border-[#9B9EA4] dark:border-zinc-500 rounded-lg focus:ring-[#FFF200]/20 bg-white/90 dark:bg-zinc-700">
                                    <div class="flex-1">
                                        <span
                                            class="text-lg font-semibold text-[#231F20] dark:text-zinc-100 block">Notify
                                            All Participants</span>
                                        <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">Send results
                                            announcement to all challenge participants</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {{-- Enhanced Action Buttons --}}
                        <div class="flex items-center justify-between space-x-6">
                            <button wire:click="cancelSelection"
                                class="group relative overflow-hidden px-8 py-4 bg-white/80 dark:bg-zinc-700/80 hover:bg-white dark:hover:bg-zinc-600 text-[#9B9EA4] dark:text-zinc-300 hover:text-[#231F20] dark:hover:text-white rounded-2xl backdrop-blur-sm border border-white/40 dark:border-zinc-600/40 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 font-semibold">
                                <span class="relative z-10 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Cancel
                                </span>
                            </button>

                            <button wire:click="announceWinners" wire:loading.attr="disabled"
                                class="group relative overflow-hidden px-12 py-4 bg-gradient-to-r from-[#FFF200] to-[#FFE066] hover:from-[#FFE066] hover:to-[#FFF200] text-[#231F20] font-bold rounded-2xl transition-all duration-300 shadow-xl hover:shadow-2xl transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="relative z-10 flex items-center" wire:loading.remove>
                                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.657 9.168-4z">
                                        </path>
                                    </svg>
                                    🎉 Announce Winners
                                </span>
                                <span class="relative z-10 flex items-center" wire:loading>
                                    <svg class="w-6 h-6 mr-3 animate-spin" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" class="opacity-25"></circle>
                                        <path fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                            class="opacity-75"></path>
                                    </svg>
                                    Announcing Winners...
                                </span>
                            </button>
                        </div>

                        {{-- Error Display --}}
                        @error('announcement')
                            <div
                                class="mt-6 p-4 bg-red-50/80 dark:bg-red-900/30 border border-red-200/50 dark:border-red-700/50 rounded-2xl backdrop-blur-sm">
                                <p class="text-red-600 dark:text-red-400 text-sm font-medium">{{ $message }}</p>
                            </div>
                        @enderror
                    </div>
                </div>
            </section>
        @endif
    </div>
</div>

{{-- Enhanced Loading States --}}
<div wire:loading
    class="fixed inset-0 bg-black/20 dark:bg-black/40 backdrop-blur-sm flex items-center justify-center z-50">
    <div
        class="bg-white/90 dark:bg-zinc-800/90 backdrop-blur-xl rounded-3xl p-8 shadow-2xl border border-white/40 dark:border-zinc-700/40">
        <div class="flex items-center space-x-4">
            <div
                class="w-8 h-8 border-4 border-[#FFF200] border-t-transparent dark:border-yellow-400 dark:border-t-transparent rounded-full animate-spin">
            </div>
            <span class="text-lg font-semibold text-[#231F20] dark:text-zinc-100">Processing...</span>
        </div>
    </div>
</div>

{{-- Enhanced Success Flash Message --}}
@if (session('success'))
    <div x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2" x-init="setTimeout(() => show = false, 5000)"
        class="fixed top-4 right-4 z-50 max-w-md">
        <div
            class="bg-emerald-50/90 dark:bg-emerald-900/30 border border-emerald-200/50 dark:border-emerald-700/50 rounded-2xl p-6 shadow-2xl backdrop-blur-xl">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-emerald-500 dark:bg-emerald-400 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                        </path>
                    </svg>
                </div>
                <p class="text-emerald-800 dark:text-emerald-400 font-semibold">{{ session('success') }}</p>
                <button @click="show = false"
                    class="ml-auto text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
@endif
