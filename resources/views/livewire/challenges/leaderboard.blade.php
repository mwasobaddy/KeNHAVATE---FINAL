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

{{-- KeNHAVATE Challenge Leaderboard - Modern Glass Morphism Design --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Enhanced Page Header with Glass Morphism --}}
        <section aria-labelledby="challenge-header" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Header Background Gradient --}}
                <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl"></div>
                
                <div class="relative z-10 p-8">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div class="space-y-4">
                            {{-- Enhanced Title with Icon --}}
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-8 h-8 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h1 id="challenge-header" class="text-3xl font-bold text-[#231F20] dark:text-zinc-100">Challenge Leaderboard</h1>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg font-medium">{{ $challenge->title }}</p>
                                </div>
                            </div>
                            
                            {{-- Enhanced Challenge Status with Better Design --}}
                            <div class="flex flex-wrap items-center gap-4">
                                <div class="inline-flex items-center space-x-2 px-4 py-2 rounded-full font-medium text-sm
                                    @if($challenge->status === 'draft') bg-gray-100 dark:bg-gray-800/50 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700
                                    @elseif($challenge->status === 'active') bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-700
                                    @elseif($challenge->status === 'judging') bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-700
                                    @elseif($challenge->status === 'completed') bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-700
                                    @else bg-gray-100 dark:bg-gray-800/50 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700
                                    @endif">
                                    <div class="w-2 h-2 rounded-full
                                        @if($challenge->status === 'active') bg-emerald-500 dark:bg-emerald-400 animate-pulse
                                        @elseif($challenge->status === 'judging') bg-amber-500 dark:bg-amber-400
                                        @elseif($challenge->status === 'completed') bg-blue-500 dark:bg-blue-400
                                        @else bg-gray-500 dark:bg-gray-400
                                        @endif">
                                    </div>
                                    <span>{{ ucfirst($challenge->status) }}</span>
                                </div>
                                
                                @if($challenge->status === 'active')
                                    <div class="flex items-center space-x-2 text-sm text-[#9B9EA4] dark:text-zinc-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span class="font-medium">Ends: {{ $challenge->submission_deadline->format('M j, Y g:i A') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Enhanced Action Buttons --}}
                        <div class="flex flex-wrap gap-3">
                            <flux:button href="{{ route('challenges.show', $challenge) }}" variant="ghost" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                                <span class="absolute inset-0 bg-gradient-to-br from-gray-500/10 to-gray-600/20 dark:from-gray-400/20 dark:to-gray-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                    </svg>
                                    <span>Back to Challenge</span>
                                </div>
                            </flux:button>
                            
                            @can('viewSubmissions', $challenge)
                                <flux:button href="{{ route('challenges.submissions', $challenge) }}" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 border border-yellow-200 dark:border-yellow-600 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                                    <span class="absolute inset-0 bg-gradient-to-br from-yellow-500/10 to-yellow-600/20 dark:from-yellow-400/20 dark:to-yellow-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                    <div class="relative flex items-center space-x-2 text-[#231F20] font-semibold">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span>Manage Submissions</span>
                                    </div>
                                </flux:button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Statistics Cards with Glass Morphism --}}
        <section aria-labelledby="stats-heading" class="group">
            <h2 id="stats-heading" class="sr-only">Challenge Statistics</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Total Submissions Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Total Submissions</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-blue-600 dark:group-hover/card:text-blue-400 transition-colors duration-300">{{ number_format($statistics['total_submissions']) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full animate-pulse"></div>
                                <span>Submitted solutions</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Participants Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-emerald-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-emerald-500/20 dark:bg-emerald-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Participants</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-emerald-600 dark:group-hover/card:text-emerald-400 transition-colors duration-300">{{ number_format($statistics['total_participants']) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <span>Active innovators</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Average Score Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 via-transparent to-amber-600/10 dark:from-amber-400/10 dark:via-transparent dark:to-amber-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-amber-500/20 dark:bg-amber-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Average Score</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-amber-600 dark:group-hover/card:text-amber-400 transition-colors duration-300">{{ number_format($statistics['average_score'], 1) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-3 py-1.5 rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                                <span>Performance metric</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Highest Score Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-transparent to-purple-600/10 dark:from-purple-400/10 dark:via-transparent dark:to-purple-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-purple-500/20 dark:bg-purple-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Highest Score</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-purple-600 dark:group-hover/card:text-purple-400 transition-colors duration-300">{{ number_format($statistics['highest_score'], 1) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-purple-500 dark:bg-purple-400 rounded-full"></div>
                                <span>Peak achievement</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced View Type Tabs and Filters --}}
        <section aria-labelledby="filters-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="p-8">
                    <div class="flex flex-col lg:flex-row gap-6 items-start lg:items-center justify-between">
                        {{-- Enhanced View Type Tabs --}}
                        <div class="space-y-3">
                            <h3 id="filters-heading" class="text-lg font-semibold text-[#231F20] dark:text-zinc-100">View Options</h3>
                            <div class="flex flex-wrap gap-3">
                                <button 
                                    wire:click="$set('viewType', 'submissions')"
                                    class="group relative overflow-hidden px-5 py-3 rounded-2xl transition-all duration-300 transform hover:-translate-y-1 font-medium
                                        {{ $viewType === 'submissions' 
                                            ? 'bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] text-[#231F20] shadow-lg border border-yellow-200 dark:border-yellow-600' 
                                            : 'bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-700/50 dark:to-zinc-800/30 text-[#231F20] dark:text-zinc-100 border border-white/40 dark:border-zinc-600/40 hover:shadow-md' 
                                        }}">
                                    <span class="relative flex items-center space-x-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span>Top Submissions</span>
                                    </span>
                                </button>
                                
                                <button 
                                    wire:click="$set('viewType', 'participants')"
                                    class="group relative overflow-hidden px-5 py-3 rounded-2xl transition-all duration-300 transform hover:-translate-y-1 font-medium
                                        {{ $viewType === 'participants' 
                                            ? 'bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] text-[#231F20] shadow-lg border border-yellow-200 dark:border-yellow-600' 
                                            : 'bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-700/50 dark:to-zinc-800/30 text-[#231F20] dark:text-zinc-100 border border-white/40 dark:border-zinc-600/40 hover:shadow-md' 
                                        }}">
                                    <span class="relative flex items-center space-x-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                        </svg>
                                        <span>Top Participants</span>
                                    </span>
                                </button>
                                
                                @if($statistics['team_submissions'] > 0)
                                    <button 
                                        wire:click="$set('viewType', 'teams')"
                                        class="group relative overflow-hidden px-5 py-3 rounded-2xl transition-all duration-300 transform hover:-translate-y-1 font-medium
                                            {{ $viewType === 'teams' 
                                                ? 'bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] text-[#231F20] shadow-lg border border-yellow-200 dark:border-yellow-600' 
                                                : 'bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-700/50 dark:to-zinc-800/30 text-[#231F20] dark:text-zinc-100 border border-white/40 dark:border-zinc-600/40 hover:shadow-md' 
                                            }}">
                                        <span class="relative flex items-center space-x-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                            <span>Team Rankings</span>
                                        </span>
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- Enhanced Filters --}}
                        <div class="space-y-3">
                            <h4 class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wider">Filters</h4>
                            <div class="flex flex-wrap gap-4 items-center">
                                @if($viewType === 'submissions')
                                    <label class="flex items-center space-x-3 cursor-pointer group">
                                        <input 
                                            type="checkbox" 
                                            wire:model.live="showOnlyWinners"
                                            class="w-4 h-4 rounded border-[#9B9EA4]/40 text-[#FFF200] focus:ring-[#FFF200]/50 focus:ring-2 transition-colors duration-200"
                                        />
                                        <span class="text-sm font-medium text-[#231F20] dark:text-zinc-100 group-hover:text-[#FFF200] dark:group-hover:text-yellow-400 transition-colors duration-200">Top 3 Winners Only</span>
                                    </label>
                                @endif

                                @if($viewType === 'participants')
                                    <div class="flex items-center space-x-3">
                                        <span class="text-sm font-medium text-[#231F20] dark:text-zinc-100">Sort by:</span>
                                        <select 
                                            wire:model.live="sortBy"
                                            class="border border-[#9B9EA4]/40 dark:border-zinc-600 rounded-xl px-4 py-2 text-sm bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-2 focus:ring-[#FFF200] focus:border-transparent transition-all duration-200">
                                            <option value="average_score">Average Score</option>
                                            <option value="best_score">Best Score</option>
                                        </select>
                                    </div>
                                @endif

                                @if(!$showOnlyWinners)
                                    <div class="flex items-center space-x-3">
                                        <span class="text-sm font-medium text-[#231F20] dark:text-zinc-100">Show:</span>
                                        <select 
                                            wire:model.live="topCount"
                                            class="border border-[#9B9EA4]/40 dark:border-zinc-600 rounded-xl px-4 py-2 text-sm bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-2 focus:ring-[#FFF200] focus:border-transparent transition-all duration-200">
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
                </div>
            </div>
        </section>

        {{-- Enhanced Leaderboard Content --}}
        @if($viewType === 'submissions')
            {{-- Top Submissions with Glass Morphism --}}
            <section aria-labelledby="submissions-heading" class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    {{-- Enhanced Header --}}
                    <div class="p-8 border-b border-[#9B9EA4]/20 dark:border-zinc-700/50">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                @if($showOnlyWinners)
                                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                    </svg>
                                @else
                                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <h2 id="submissions-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">
                                    @if($showOnlyWinners)
                                        Winners Podium
                                    @else
                                        Top Submissions
                                    @endif
                                </h2>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Performance rankings and achievements</p>
                            </div>
                        </div>
                    </div>

                    @if($showOnlyWinners && isset($topSubmissions) && $topSubmissions->count() >= 3)
                        {{-- Enhanced Winners Podium --}}
                        <div class="p-8">
                            <div class="flex justify-center items-end gap-8 mb-8">
                                {{-- 2nd Place --}}
                                @if($topSubmissions->count() > 1)
                                    <div class="flex flex-col items-center transform hover:scale-105 transition-transform duration-300">
                                        <div class="w-20 h-20 bg-gradient-to-br from-gray-300 to-gray-400 dark:from-gray-600 dark:to-gray-700 rounded-full flex items-center justify-center text-3xl mb-4 shadow-lg">
                                            🥈
                                        </div>
                                        <div class="bg-gradient-to-t from-gray-300 to-gray-200 dark:from-gray-600 dark:to-gray-500 h-24 w-36 rounded-t-2xl flex items-center justify-center shadow-lg">
                                            <span class="text-xl font-bold text-gray-700 dark:text-gray-200">2nd</span>
                                        </div>
                                        <div class="text-center mt-4 p-4 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm rounded-2xl border border-white/20 dark:border-zinc-700/50">
                                            <p class="font-semibold text-[#231F20] dark:text-zinc-100">{{ $topSubmissions[1]['submission']->author->name }}</p>
                                            <p class="text-lg font-bold text-gray-600 dark:text-gray-300">{{ number_format($topSubmissions[1]['average_score'], 1) }}/100</p>
                                            <p class="text-xs text-[#9B9EA4] dark:text-zinc-400 mt-1">{{ Str::limit($topSubmissions[1]['submission']->title, 30) }}</p>
                                        </div>
                                    </div>
                                @endif

                                {{-- 1st Place --}}
                                <div class="flex flex-col items-center transform hover:scale-105 transition-transform duration-300">
                                    <div class="w-24 h-24 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-full flex items-center justify-center text-4xl mb-4 shadow-xl animate-pulse">
                                        🥇
                                    </div>
                                    <div class="bg-gradient-to-t from-yellow-400 to-yellow-300 h-32 w-40 rounded-t-2xl flex items-center justify-center shadow-xl">
                                        <span class="text-2xl font-bold text-yellow-800">1st</span>
                                    </div>
                                    <div class="text-center mt-4 p-4 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm rounded-2xl border border-white/20 dark:border-zinc-700/50">
                                        <p class="font-bold text-[#231F20] dark:text-zinc-100 text-lg">{{ $topSubmissions[0]['submission']->author->name }}</p>
                                        <p class="text-xl font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($topSubmissions[0]['average_score'], 1) }}/100</p>
                                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mt-1">{{ Str::limit($topSubmissions[0]['submission']->title, 30) }}</p>
                                    </div>
                                </div>

                                {{-- 3rd Place --}}
                                @if($topSubmissions->count() > 2)
                                    <div class="flex flex-col items-center transform hover:scale-105 transition-transform duration-300">
                                        <div class="w-20 h-20 bg-gradient-to-br from-orange-400 to-orange-500 dark:from-orange-600 dark:to-orange-700 rounded-full flex items-center justify-center text-3xl mb-4 shadow-lg">
                                            🥉
                                        </div>
                                        <div class="bg-gradient-to-t from-orange-400 to-orange-300 dark:from-orange-600 dark:to-orange-500 h-20 w-36 rounded-t-2xl flex items-center justify-center shadow-lg">
                                            <span class="text-xl font-bold text-orange-700 dark:text-orange-200">3rd</span>
                                        </div>
                                        <div class="text-center mt-4 p-4 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm rounded-2xl border border-white/20 dark:border-zinc-700/50">
                                            <p class="font-semibold text-[#231F20] dark:text-zinc-100">{{ $topSubmissions[2]['submission']->author->name }}</p>
                                            <p class="text-lg font-bold text-orange-600 dark:text-orange-400">{{ number_format($topSubmissions[2]['average_score'], 1) }}/100</p>
                                            <p class="text-xs text-[#9B9EA4] dark:text-zinc-400 mt-1">{{ Str::limit($topSubmissions[2]['submission']->title, 30) }}</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Enhanced Submissions Table --}}
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-[#231F20]/5 dark:bg-zinc-900/20 border-b border-[#9B9EA4]/20 dark:border-zinc-700/50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Rank</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Submission</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Author</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Score</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Reviews</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Submitted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#9B9EA4]/20 dark:divide-zinc-700/50">
                                @forelse($topSubmissions ?? [] as $item)
                                    <tr class="group hover:bg-[#231F20]/5 dark:hover:bg-zinc-700/20 transition-all duration-300">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                @if($item['rank'] <= 3)
                                                    <span class="text-3xl">
                                                        @if($item['rank'] === 1) 🥇
                                                        @elseif($item['rank'] === 2) 🥈
                                                        @else 🥉
                                                        @endif
                                                    </span>
                                                @endif
                                                <span class="font-bold text-[#231F20] dark:text-zinc-100 text-xl">#{{ $item['rank'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <p class="font-semibold text-[#231F20] dark:text-zinc-100 group-hover:text-[#FFF200] dark:group-hover:text-yellow-400 transition-colors duration-200">{{ $item['submission']->title }}</p>
                                                <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 leading-relaxed">{{ Str::limit($item['submission']->description, 80) }}</p>
                                                @if($item['submission']->is_team_submission)
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-700">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                                                        </svg>
                                                        Team Submission
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-xl flex items-center justify-center text-[#231F20] font-semibold shadow-lg">
                                                    {{ substr($item['submission']->author->name, 0, 1) }}
                                                </div>
                                                <div>
                                                    <p class="font-medium text-[#231F20] dark:text-zinc-100">{{ $item['submission']->author->name }}</p>
                                                    <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $item['submission']->author->email }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-center">
                                                <div class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-1">{{ number_format($item['average_score'], 1) }}</div>
                                                <div class="text-sm text-[#9B9EA4] dark:text-zinc-400">out of 100</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-2">
                                                <div class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full"></div>
                                                <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">{{ $item['total_reviews'] }} review(s)</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-2 text-sm text-[#9B9EA4] dark:text-zinc-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                                <span>{{ $item['submission']->created_at->format('M j, Y') }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                                    <svg class="w-8 h-8 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                </div>
                                                <h3 class="text-lg font-bold text-[#231F20] dark:text-zinc-100 mb-2">No Reviewed Submissions</h3>
                                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed max-w-md">
                                                    Submissions need to be reviewed to appear on the leaderboard. Check back once reviews are completed.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        @elseif($viewType === 'participants')
            {{-- Top Participants with Enhanced Design --}}
            <section aria-labelledby="participants-heading" class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="p-8 border-b border-[#9B9EA4]/20 dark:border-zinc-700/50">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 id="participants-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Top Participants</h2>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Most active and high-performing contributors</p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-[#231F20]/5 dark:bg-zinc-900/20 border-b border-[#9B9EA4]/20 dark:border-zinc-700/50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Rank</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Participant</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Submissions</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Average Score</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Best Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#9B9EA4]/20 dark:divide-zinc-700/50">
                                @forelse($participants ?? [] as $item)
                                    <tr class="group hover:bg-[#231F20]/5 dark:hover:bg-zinc-700/20 transition-all duration-300">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                @if($item['rank'] <= 3)
                                                    <span class="text-2xl">
                                                        @if($item['rank'] === 1) 🥇
                                                        @elseif($item['rank'] === 2) 🥈
                                                        @else 🥉
                                                        @endif
                                                    </span>
                                                @endif
                                                <span class="font-bold text-[#231F20] dark:text-zinc-100 text-xl">#{{ $item['rank'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-4">
                                                <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-xl flex items-center justify-center text-[#231F20] font-bold text-lg shadow-lg">
                                                    {{ substr($item['user']->name, 0, 1) }}
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-[#231F20] dark:text-zinc-100 group-hover:text-[#FFF200] dark:group-hover:text-yellow-400 transition-colors duration-200">{{ $item['user']->name }}</p>
                                                    <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $item['user']->email }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-1">
                                                <div class="flex items-center space-x-2">
                                                    <div class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full"></div>
                                                    <span class="font-semibold text-[#231F20] dark:text-zinc-100">{{ $item['total_submissions'] }} total</span>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <div class="w-2 h-2 bg-emerald-500 dark:bg-emerald-400 rounded-full"></div>
                                                    <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $item['reviewed_submissions'] }} reviewed</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">{{ number_format($item['average_score'], 1) }}</div>
                                                                                                <div class="text-sm text-[#9B9EA4] dark:text-zinc-400">out of 100</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">{{ number_format($item['best_score'], 1) }}</div>
                                                <div class="text-sm text-[#9B9EA4] dark:text-zinc-400">peak score</div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                                    <svg class="w-8 h-8 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                                    </svg>
                                                </div>
                                                <h3 class="text-lg font-bold text-[#231F20] dark:text-zinc-100 mb-2">No Participants Yet</h3>
                                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed max-w-md">
                                                    No participants have submitted solutions to this challenge yet. Be the first to contribute!
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        @elseif($viewType === 'teams')
            {{-- Team Rankings with Enhanced Design --}}
            <section aria-labelledby="teams-heading" class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="p-8 border-b border-[#9B9EA4]/20 dark:border-zinc-700/50">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 id="teams-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Team Rankings</h2>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Collaborative submissions and team performance</p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-[#231F20]/5 dark:bg-zinc-900/20 border-b border-[#9B9EA4]/20 dark:border-zinc-700/50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Rank</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Team Submission</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Team Lead</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Team Size</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Score</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Reviews</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#9B9EA4]/20 dark:divide-zinc-700/50">
                                @forelse($teams ?? [] as $item)
                                    <tr class="group hover:bg-[#231F20]/5 dark:hover:bg-zinc-700/20 transition-all duration-300">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                @if($item['rank'] <= 3)
                                                    <span class="text-2xl">
                                                        @if($item['rank'] === 1) 🥇
                                                        @elseif($item['rank'] === 2) 🥈
                                                        @else 🥉
                                                        @endif
                                                    </span>
                                                @endif
                                                <span class="font-bold text-[#231F20] dark:text-zinc-100 text-xl">#{{ $item['rank'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <p class="font-semibold text-[#231F20] dark:text-zinc-100 group-hover:text-[#FFF200] dark:group-hover:text-yellow-400 transition-colors duration-200">{{ $item['submission']->title }}</p>
                                                <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 leading-relaxed">{{ Str::limit($item['submission']->description, 80) }}</p>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-700">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                                                    </svg>
                                                    Team Submission
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-xl flex items-center justify-center text-[#231F20] font-semibold shadow-lg">
                                                    {{ substr($item['team_lead']->name, 0, 1) }}
                                                </div>
                                                <div>
                                                    <p class="font-medium text-[#231F20] dark:text-zinc-100">{{ $item['team_lead']->name }}</p>
                                                    <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">Team Leader</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-3">
                                                <div class="flex -space-x-2">
                                                    {{-- Team Lead Avatar --}}
                                                    <div class="w-8 h-8 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-full flex items-center justify-center text-[#231F20] text-xs font-semibold border-2 border-white dark:border-zinc-800 shadow-sm">
                                                        {{ substr($item['team_lead']->name, 0, 1) }}
                                                    </div>
                                                    {{-- Team Members Avatars --}}
                                                    @foreach($item['team_members']->take(3) as $member)
                                                        <div class="w-8 h-8 bg-gradient-to-br from-blue-400 to-blue-500 dark:from-blue-500 dark:to-blue-600 rounded-full flex items-center justify-center text-white text-xs font-semibold border-2 border-white dark:border-zinc-800 shadow-sm">
                                                            {{ substr($member->name, 0, 1) }}
                                                        </div>
                                                    @endforeach
                                                    @if($item['team_members']->count() > 3)
                                                        <div class="w-8 h-8 bg-gradient-to-br from-gray-400 to-gray-500 dark:from-gray-500 dark:to-gray-600 rounded-full flex items-center justify-center text-white text-xs font-semibold border-2 border-white dark:border-zinc-800 shadow-sm">
                                                            +{{ $item['team_members']->count() - 3 }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">{{ $item['team_size'] }} members</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">{{ number_format($item['average_score'], 1) }}</div>
                                                <div class="text-sm text-[#9B9EA4] dark:text-zinc-400">out of 100</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-2">
                                                <div class="w-2 h-2 bg-purple-500 dark:bg-purple-400 rounded-full"></div>
                                                <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">{{ $item['total_reviews'] }} review(s)</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                                    <svg class="w-8 h-8 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                    </svg>
                                                </div>
                                                <h3 class="text-lg font-bold text-[#231F20] dark:text-zinc-100 mb-2">No Team Submissions</h3>
                                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed max-w-md">
                                                    No team submissions have been reviewed for this challenge yet. Teams need to submit and get reviewed to appear here.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

        {{-- Enhanced Call-to-Action Section --}}
        @if($challenge->status === 'active')
            <section aria-labelledby="cta-heading" class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    {{-- CTA Background Gradient --}}
                    <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent"></div>
                    
                    <div class="relative z-10 p-8 text-center">
                        <div class="max-w-2xl mx-auto space-y-6">
                            <div class="w-20 h-20 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-full flex items-center justify-center mx-auto shadow-xl">
                                <svg class="w-10 h-10 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            
                            <div>
                                <h2 id="cta-heading" class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-4">Ready to Join the Innovation?</h2>
                                <p class="text-lg text-[#9B9EA4] dark:text-zinc-400 leading-relaxed">
                                    Share your creative solutions and compete with Kenya's brightest minds. 
                                    Every submission makes a difference in shaping our nation's infrastructure future.
                                </p>
                            </div>
                            
                            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                                @auth
                                    @if(!$challenge->submissions()->where('author_id', auth()->id())->exists())
                                        <flux:button href="{{ route('challenges.participate', $challenge) }}" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 border border-yellow-200 dark:border-yellow-600 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 px-8 py-4">
                                            <span class="absolute inset-0 bg-gradient-to-br from-yellow-500/10 to-yellow-600/20 dark:from-yellow-400/20 dark:to-yellow-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                            <div class="relative flex items-center space-x-3 text-[#231F20] font-bold">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                <span>Submit Your Solution</span>
                                            </div>
                                        </flux:button>
                                    @else
                                        <div class="inline-flex items-center space-x-3 px-6 py-3 rounded-2xl bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-700 font-medium">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span>You've already participated!</span>
                                        </div>
                                    @endif
                                @else
                                    <flux:button href="{{ route('login') }}" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 px-8 py-4">
                                        <span class="absolute inset-0 bg-gradient-to-br from-gray-500/10 to-gray-600/20 dark:from-gray-400/20 dark:to-gray-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                        <div class="relative flex items-center space-x-3 text-[#231F20] dark:text-zinc-100 font-semibold">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                            </svg>
                                            <span>Login to Participate</span>
                                        </div>
                                    </flux:button>
                                @endauth
                                
                                <flux:button href="{{ route('challenges.show', $challenge) }}" variant="ghost" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-white/30 dark:from-zinc-700/50 dark:to-zinc-800/30 border border-white/40 dark:border-zinc-600/40 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-6 py-3">
                                    <span class="absolute inset-0 bg-gradient-to-br from-gray-500/10 to-gray-600/20 dark:from-gray-400/20 dark:to-gray-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                    <div class="relative flex items-center space-x-2 text-[#231F20] dark:text-zinc-100 font-medium">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span>View Challenge Details</span>
                                    </div>
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endif
    </div>
</div>

{{-- Enhanced JavaScript for animations and interactions --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // GSAP animations for enhanced user experience
    if (typeof gsap !== 'undefined') {
        // Animate statistics cards on load
        gsap.from('.group\\/card', {
            duration: 0.8,
            y: 50,
            opacity: 0,
            stagger: 0.1,
            ease: "power2.out"
        });

        // Animate leaderboard rows on load
        gsap.from('tbody tr', {
            duration: 0.6,
            x: -30,
            opacity: 0,
            stagger: 0.05,
            ease: "power2.out",
            delay: 0.3
        });

        // Animate tab buttons when clicked
        document.querySelectorAll('[wire\\:click*="viewType"]').forEach(button => {
            button.addEventListener('click', function() {
                gsap.to(this, {
                    duration: 0.2,
                    scale: 0.95,
                    yoyo: true,
                    repeat: 1,
                    ease: "power2.inOut"
                });
            });
        });

        // Animate winner podium elements
        gsap.from('.winner-podium', {
            duration: 1,
            y: 100,
            opacity: 0,
            stagger: 0.2,
            ease: "bounce.out",
            delay: 0.5
        });

        // Parallax effect for background elements
        gsap.to('.absolute.blur-3xl', {
            duration: 20,
            rotation: 360,
            repeat: -1,
            ease: "none"
        });
    }

    // Enhanced hover effects for leaderboard rows
    document.querySelectorAll('tbody tr').forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(8px)';
            this.style.transition = 'transform 0.3s ease';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });

    // Real-time updates notification
    window.addEventListener('livewire:navigated', function() {
        // Show a subtle notification when data updates
        if (document.querySelector('[wire\\:poll]')) {
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-emerald-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300';
            notification.textContent = 'Leaderboard updated';
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
    });
});
</script>

{{-- Add polling for real-time updates --}}
<div wire:poll.30s></div>
