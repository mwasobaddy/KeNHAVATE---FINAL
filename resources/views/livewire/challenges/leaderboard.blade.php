<?php

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] #[Title('Challenge Leaderboard')] class extends Component
{
    use WithPagination;

    public Challenge $challenge;
    public string $viewType = 'submissions'; // 'submissions', 'participants', 'teams'
    public string $sortBy = 'average_score';
    public string $sortDirection = 'desc';
    public bool $showOnlyWinners = false;
    public int $topCount = 10;

    public function mount(Challenge $challenge)
    {
        $this->challenge = $challenge;
    }

    public function updatedViewType()
    {
        $this->resetPage();
    }

    public function updatedShowOnlyWinners()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }
        $this->resetPage();
    }

    public function getTopSubmissions()
    {
        return $this->challenge->submissions()
            ->with(['author', 'reviews', 'teamMembers'])
            ->whereHas('reviews')
            ->get()
            ->map(function ($submission) {
                $averageScore = $submission->reviews->avg('score');
                $totalReviews = $submission->reviews->count();
                
                return [
                    'submission' => $submission,
                    'average_score' => $averageScore,
                    'total_reviews' => $totalReviews,
                    'rank' => null, // Will be set after sorting
                ];
            })
            ->sortByDesc('average_score')
            ->values()
            ->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            })
            ->when($this->showOnlyWinners, function ($collection) {
                return $collection->take(3); // Top 3 winners
            })
            ->when(!$this->showOnlyWinners, function ($collection) {
                return $collection->take($this->topCount);
            });
    }

    public function getTopParticipants()
    {
        // Get participants with their submission performance
        $participants = User::whereHas('challengeSubmissions', function ($query) {
                $query->where('challenge_id', $this->challenge->id);
            })
            ->with(['challengeSubmissions' => function ($query) {
                $query->where('challenge_id', $this->challenge->id)
                      ->with('reviews');
            }])
            ->get()
            ->map(function ($user) {
                $submissions = $user->challengeSubmissions;
                $totalSubmissions = $submissions->count();
                $reviewedSubmissions = $submissions->filter(function ($submission) {
                    return $submission->reviews->count() > 0;
                });
                
                $averageScore = $reviewedSubmissions->isNotEmpty() 
                    ? $reviewedSubmissions->avg(function ($submission) {
                        return $submission->reviews->avg('score');
                    })
                    : 0;

                $bestScore = $reviewedSubmissions->isNotEmpty()
                    ? $reviewedSubmissions->max(function ($submission) {
                        return $submission->reviews->avg('score');
                    })
                    : 0;

                return [
                    'user' => $user,
                    'total_submissions' => $totalSubmissions,
                    'reviewed_submissions' => $reviewedSubmissions->count(),
                    'average_score' => $averageScore,
                    'best_score' => $bestScore,
                    'rank' => null,
                ];
            })
            ->sortByDesc($this->sortBy === 'best_score' ? 'best_score' : 'average_score')
            ->values()
            ->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            })
            ->take($this->topCount);

        return $participants;
    }

    public function getTeamLeaderboard()
    {
        return $this->challenge->submissions()
            ->where('is_team_submission', true)
            ->with(['author', 'reviews', 'teamMembers'])
            ->whereHas('reviews')
            ->get()
            ->map(function ($submission) {
                $averageScore = $submission->reviews->avg('score');
                $teamSize = $submission->teamMembers->count() + 1; // +1 for team leader
                
                return [
                    'submission' => $submission,
                    'team_lead' => $submission->author,
                    'team_members' => $submission->teamMembers,
                    'team_size' => $teamSize,
                    'average_score' => $averageScore,
                    'total_reviews' => $submission->reviews->count(),
                    'rank' => null,
                ];
            })
            ->sortByDesc('average_score')
            ->values()
            ->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            })
            ->take($this->topCount);
    }

    public function getChallengeStatistics()
    {
        $submissions = $this->challenge->submissions;
        $reviewedSubmissions = $submissions->filter(function ($submission) {
            return $submission->reviews->count() > 0;
        });

        return [
            'total_submissions' => $submissions->count(),
            'total_participants' => $submissions->unique('author_id')->count(),
            'team_submissions' => $submissions->where('is_team_submission', true)->count(),
            'individual_submissions' => $submissions->where('is_team_submission', false)->count(),
            'reviewed_submissions' => $reviewedSubmissions->count(),
            'average_score' => $reviewedSubmissions->isNotEmpty() 
                ? $reviewedSubmissions->avg(function ($submission) {
                    return $submission->reviews->avg('score');
                })
                : 0,
            'highest_score' => $reviewedSubmissions->isNotEmpty()
                ? $reviewedSubmissions->max(function ($submission) {
                    return $submission->reviews->avg('score');
                })
                : 0,
            'review_completion_rate' => $submissions->count() > 0 
                ? ($reviewedSubmissions->count() / $submissions->count()) * 100 
                : 0,
        ];
    }

    public function with(): array
    {
        $data = [
            'statistics' => $this->getChallengeStatistics(),
        ];

        switch ($this->viewType) {
            case 'participants':
                $data['participants'] = $this->getTopParticipants();
                break;
            case 'teams':
                $data['teams'] = $this->getTeamLeaderboard();
                break;
            default:
                $data['topSubmissions'] = $this->getTopSubmissions();
                break;
        }

        return $data;
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-[#F8EBD5] via-[#F8EBD5] to-[#E8DBB5] p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto">
        <!-- Page Header -->
        <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-[#231F20] mb-2">
                        🏆 Challenge Leaderboard
                    </h1>
                    <p class="text-[#9B9EA4] text-lg mb-4">{{ $challenge->title }}</p>
                    
                    <!-- Challenge Status -->
                    <div class="flex items-center gap-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                            @if($challenge->status === 'draft') bg-gray-100 text-gray-800
                            @elseif($challenge->status === 'active') bg-green-100 text-green-800
                            @elseif($challenge->status === 'judging') bg-yellow-100 text-yellow-800
                            @elseif($challenge->status === 'completed') bg-blue-100 text-blue-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ ucfirst($challenge->status) }}
                        </span>
                        
                        @if($challenge->status === 'active')
                            <span class="text-sm text-[#9B9EA4]">
                                Ends: {{ $challenge->submission_deadline->format('M j, Y g:i A') }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex gap-3">
                    <a href="{{ route('challenges.show', $challenge) }}" 
                       class="px-4 py-2 bg-[#9B9EA4]/20 hover:bg-[#9B9EA4]/30 text-[#231F20] rounded-xl transition-colors duration-200 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Challenge
                    </a>
                    @can('viewSubmissions', $challenge)
                        <a href="{{ route('challenges.submissions', $challenge) }}" 
                           class="px-4 py-2 bg-[#FFF200] hover:bg-[#FFF200]/80 text-[#231F20] rounded-xl transition-colors duration-200 flex items-center gap-2 font-semibold">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Manage Submissions
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-[#9B9EA4]">Total Submissions</p>
                        <p class="text-2xl font-bold text-[#231F20]">{{ $statistics['total_submissions'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-green-100 rounded-2xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-[#9B9EA4]">Participants</p>
                        <p class="text-2xl font-bold text-[#231F20]">{{ $statistics['total_participants'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-yellow-100 rounded-2xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-[#9B9EA4]">Average Score</p>
                        <p class="text-2xl font-bold text-[#231F20]">{{ number_format($statistics['average_score'], 1) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-2xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-[#9B9EA4]">Highest Score</p>
                        <p class="text-2xl font-bold text-[#231F20]">{{ number_format($statistics['highest_score'], 1) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- View Type Tabs and Filters -->
        <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg p-6 mb-8">
            <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
                <!-- View Type Tabs -->
                <div class="flex flex-wrap gap-2">
                    <button 
                        wire:click="$set('viewType', 'submissions')"
                        class="px-4 py-2 rounded-xl transition-colors duration-200 {{ $viewType === 'submissions' ? 'bg-[#FFF200] text-[#231F20] font-semibold' : 'bg-[#9B9EA4]/20 text-[#231F20] hover:bg-[#9B9EA4]/30' }}">
                        Top Submissions
                    </button>
                    <button 
                        wire:click="$set('viewType', 'participants')"
                        class="px-4 py-2 rounded-xl transition-colors duration-200 {{ $viewType === 'participants' ? 'bg-[#FFF200] text-[#231F20] font-semibold' : 'bg-[#9B9EA4]/20 text-[#231F20] hover:bg-[#9B9EA4]/30' }}">
                        Top Participants
                    </button>
                    @if($statistics['team_submissions'] > 0)
                        <button 
                            wire:click="$set('viewType', 'teams')"
                            class="px-4 py-2 rounded-xl transition-colors duration-200 {{ $viewType === 'teams' ? 'bg-[#FFF200] text-[#231F20] font-semibold' : 'bg-[#9B9EA4]/20 text-[#231F20] hover:bg-[#9B9EA4]/30' }}">
                            Team Rankings
                        </button>
                    @endif
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap gap-3 items-center">
                    @if($viewType === 'submissions')
                        <label class="flex items-center gap-2">
                            <input 
                                type="checkbox" 
                                wire:model.live="showOnlyWinners"
                                class="rounded border-[#9B9EA4]/40 text-[#FFF200] focus:ring-[#FFF200]/50"
                            />
                            <span class="text-sm text-[#231F20]">Top 3 Winners Only</span>
                        </label>
                    @endif

                    @if($viewType === 'participants')
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-[#231F20]">Sort by:</span>
                            <select 
                                wire:model.live="sortBy"
                                class="border border-[#9B9EA4]/40 rounded-lg px-3 py-1 text-sm focus:ring-2 focus:ring-[#FFF200] focus:border-transparent">
                                <option value="average_score">Average Score</option>
                                <option value="best_score">Best Score</option>
                            </select>
                        </div>
                    @endif

                    @if(!$showOnlyWinners)
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-[#231F20]">Show:</span>
                            <select 
                                wire:model.live="topCount"
                                class="border border-[#9B9EA4]/40 rounded-lg px-3 py-1 text-sm focus:ring-2 focus:ring-[#FFF200] focus:border-transparent">
                                <option value="10">Top 10</option>
                                <option value="25">Top 25</option>
                                <option value="50">Top 50</option>
                                <option value="100">Top 100</option>
                            </select>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Leaderboard Content -->
        @if($viewType === 'submissions')
            <!-- Top Submissions -->
            <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg overflow-hidden">
                <div class="p-6 border-b border-[#9B9EA4]/20">
                    <h2 class="text-xl font-bold text-[#231F20]">
                        @if($showOnlyWinners)
                            🏆 Winners Podium
                        @else
                            📊 Top Submissions
                        @endif
                    </h2>
                </div>

                @if($showOnlyWinners && isset($topSubmissions) && $topSubmissions->count() >= 3)
                    <!-- Winners Podium -->
                    <div class="p-6">
                        <div class="flex justify-center items-end gap-8 mb-8">
                            <!-- 2nd Place -->
                            @if($topSubmissions->count() > 1)
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center text-2xl mb-2">
                                        🥈
                                    </div>
                                    <div class="bg-gray-200 h-20 w-32 rounded-t-lg flex items-center justify-center">
                                        <span class="text-lg font-bold text-gray-700">2nd</span>
                                    </div>
                                    <div class="text-center mt-3">
                                        <p class="font-semibold text-[#231F20]">{{ $topSubmissions[1]['submission']->author->name }}</p>
                                        <p class="text-sm text-[#9B9EA4]">{{ number_format($topSubmissions[1]['average_score'], 1) }}/100</p>
                                        <p class="text-xs text-[#9B9EA4] mt-1">{{ Str::limit($topSubmissions[1]['submission']->title, 30) }}</p>
                                    </div>
                                </div>
                            @endif

                            <!-- 1st Place -->
                            <div class="flex flex-col items-center">
                                <div class="w-20 h-20 bg-yellow-300 rounded-full flex items-center justify-center text-3xl mb-2">
                                    🥇
                                </div>
                                <div class="bg-yellow-300 h-28 w-36 rounded-t-lg flex items-center justify-center">
                                    <span class="text-xl font-bold text-yellow-800">1st</span>
                                </div>
                                <div class="text-center mt-3">
                                    <p class="font-bold text-[#231F20] text-lg">{{ $topSubmissions[0]['submission']->author->name }}</p>
                                    <p class="text-[#231F20] font-semibold">{{ number_format($topSubmissions[0]['average_score'], 1) }}/100</p>
                                    <p class="text-sm text-[#9B9EA4] mt-1">{{ Str::limit($topSubmissions[0]['submission']->title, 30) }}</p>
                                </div>
                            </div>

                            <!-- 3rd Place -->
                            @if($topSubmissions->count() > 2)
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 bg-orange-200 rounded-full flex items-center justify-center text-2xl mb-2">
                                        🥉
                                    </div>
                                    <div class="bg-orange-200 h-16 w-32 rounded-t-lg flex items-center justify-center">
                                        <span class="text-lg font-bold text-orange-700">3rd</span>
                                    </div>
                                    <div class="text-center mt-3">
                                        <p class="font-semibold text-[#231F20]">{{ $topSubmissions[2]['submission']->author->name }}</p>
                                        <p class="text-sm text-[#9B9EA4]">{{ number_format($topSubmissions[2]['average_score'], 1) }}/100</p>
                                        <p class="text-xs text-[#9B9EA4] mt-1">{{ Str::limit($topSubmissions[2]['submission']->title, 30) }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Submissions List -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-[#231F20]/5 border-b border-[#9B9EA4]/20">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Rank</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Submission</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Author</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Score</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Reviews</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#9B9EA4]/20">
                            @forelse($topSubmissions ?? [] as $item)
                                <tr class="hover:bg-[#231F20]/5 transition-colors duration-200">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            @if($item['rank'] <= 3)
                                                <span class="text-2xl">
                                                    @if($item['rank'] === 1) 🥇
                                                    @elseif($item['rank'] === 2) 🥈
                                                    @else 🥉
                                                    @endif
                                                </span>
                                            @endif
                                            <span class="font-bold text-[#231F20] text-lg">#{{ $item['rank'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="font-semibold text-[#231F20]">{{ $item['submission']->title }}</p>
                                            <p class="text-sm text-[#9B9EA4] mt-1">{{ Str::limit($item['submission']->description, 80) }}</p>
                                            @if($item['submission']->is_team_submission)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mt-2">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                                                    </svg>
                                                    Team
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-[#FFF200] rounded-full flex items-center justify-center text-[#231F20] font-semibold text-sm">
                                                {{ substr($item['submission']->author->name, 0, 1) }}
                                            </div>
                                            <div>
                                                <p class="font-medium text-[#231F20]">{{ $item['submission']->author->name }}</p>
                                                <p class="text-sm text-[#9B9EA4]">{{ $item['submission']->author->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-center">
                                            <div class="text-2xl font-bold text-[#231F20]">{{ number_format($item['average_score'], 1) }}</div>
                                            <div class="text-sm text-[#9B9EA4]">out of 100</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-[#9B9EA4]">{{ $item['total_reviews'] }} review(s)</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-[#9B9EA4]">
                                            {{ $item['submission']->created_at->format('M j, Y') }}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="w-12 h-12 text-[#9B9EA4] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <h3 class="text-lg font-medium text-[#231F20] mb-2">No reviewed submissions</h3>
                                            <p class="text-[#9B9EA4]">Submissions need to be reviewed to appear on the leaderboard.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        @elseif($viewType === 'participants')
            <!-- Top Participants -->
            <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg overflow-hidden">
                <div class="p-6 border-b border-[#9B9EA4]/20">
                    <h2 class="text-xl font-bold text-[#231F20]">👥 Top Participants</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-[#231F20]/5 border-b border-[#9B9EA4]/20">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Rank</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Participant</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Submissions</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Average Score</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Best Score</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#9B9EA4]/20">
                            @forelse($participants ?? [] as $item)
                                <tr class="hover:bg-[#231F20]/5 transition-colors duration-200">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            @if($item['rank'] <= 3)
                                                <span class="text-xl">
                                                    @if($item['rank'] === 1) 🥇
                                                    @elseif($item['rank'] === 2) 🥈
                                                    @else 🥉
                                                    @endif
                                                </span>
                                            @endif
                                            <span class="font-bold text-[#231F20] text-lg">#{{ $item['rank'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-[#FFF200] rounded-full flex items-center justify-center text-[#231F20] font-semibold">
                                                {{ substr($item['user']->name, 0, 1) }}
                                            </div>
                                            <div>
                                                <p class="font-semibold text-[#231F20]">{{ $item['user']->name }}</p>
                                                <p class="text-sm text-[#9B9EA4]">{{ $item['user']->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm">
                                            <div class="font-semibold text-[#231F20]">{{ $item['total_submissions'] }} total</div>
                                            <div class="text-[#9B9EA4]">{{ $item['reviewed_submissions'] }} reviewed</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-center">
                                            <div class="text-xl font-bold text-[#231F20]">{{ number_format($item['average_score'], 1) }}</div>
                                            <div class="text-xs text-[#9B9EA4]">average</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-center">
                                            <div class="text-xl font-bold text-[#231F20]">{{ number_format($item['best_score'], 1) }}</div>
                                            <div class="text-xs text-[#9B9EA4]">best</div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="w-12 h-12 text-[#9B9EA4] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                            <h3 class="text-lg font-medium text-[#231F20] mb-2">No participants found</h3>
                                            <p class="text-[#9B9EA4]">No participants with reviewed submissions yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        @elseif($viewType === 'teams')
            <!-- Team Rankings -->
            <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/20 shadow-lg overflow-hidden">
                <div class="p-6 border-b border-[#9B9EA4]/20">
                    <h2 class="text-xl font-bold text-[#231F20]">👥 Team Rankings</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-[#231F20]/5 border-b border-[#9B9EA4]/20">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Rank</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Team</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Team Lead</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Members</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20]">Score</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#9B9EA4]/20">
                            @forelse($teams ?? [] as $item)
                                <tr class="hover:bg-[#231F20]/5 transition-colors duration-200">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            @if($item['rank'] <= 3)
                                                <span class="text-xl">
                                                    @if($item['rank'] === 1) 🥇
                                                    @elseif($item['rank'] === 2) 🥈
                                                    @else 🥉
                                                    @endif
                                                </span>
                                            @endif
                                            <span class="font-bold text-[#231F20] text-lg">#{{ $item['rank'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="font-semibold text-[#231F20]">{{ $item['submission']->title }}</p>
                                            <p class="text-sm text-[#9B9EA4] mt-1">{{ Str::limit($item['submission']->description, 60) }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-[#FFF200] rounded-full flex items-center justify-center text-[#231F20] font-semibold text-sm">
                                                {{ substr($item['team_lead']->name, 0, 1) }}
                                            </div>
                                            <div>
                                                <p class="font-medium text-[#231F20]">{{ $item['team_lead']->name }}</p>
                                                <p class="text-sm text-[#9B9EA4]">Team Lead</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-[#231F20]">{{ $item['team_size'] }} members</span>
                                            @if($item['team_members']->count() > 0)
                                                <div class="flex -space-x-2">
                                                    @foreach($item['team_members']->take(3) as $member)
                                                        <div class="w-6 h-6 bg-[#9B9EA4] rounded-full flex items-center justify-center text-white text-xs font-semibold border-2 border-white">
                                                            {{ substr($member->name, 0, 1) }}
                                                        </div>
                                                    @endforeach
                                                    @if($item['team_members']->count() > 3)
                                                        <div class="w-6 h-6 bg-[#231F20] rounded-full flex items-center justify-center text-white text-xs font-semibold border-2 border-white">
                                                            +{{ $item['team_members']->count() - 3 }}
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-center">
                                            <div class="text-2xl font-bold text-[#231F20]">{{ number_format($item['average_score'], 1) }}</div>
                                            <div class="text-sm text-[#9B9EA4]">{{ $item['total_reviews'] }} review(s)</div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="w-12 h-12 text-[#9B9EA4] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                            <h3 class="text-lg font-medium text-[#231F20] mb-2">No team submissions</h3>
                                            <p class="text-[#9B9EA4]">No team submissions have been reviewed yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
