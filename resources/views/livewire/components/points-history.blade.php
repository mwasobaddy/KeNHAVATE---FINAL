<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\UserPoint;
use Carbon\Carbon;

new class extends Component
{
    use WithPagination;

    public string $filter = 'all';
    public string $period = 'all';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    public function updatedFilter()
    {
        $this->resetPage();
    }

    public function updatedPeriod()
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

    public function with()
    {
        $query = UserPoint::where('user_id', auth()->id())
            ->with('related');

        // Apply action filter
        if ($this->filter !== 'all') {
            $query->where('action', $this->filter);
        }

        // Apply period filter
        if ($this->period !== 'all') {
            $startDate = match($this->period) {
                'today' => Carbon::today(),
                'week' => Carbon::now()->startOfWeek(),
                'month' => Carbon::now()->startOfMonth(),
                'year' => Carbon::now()->startOfYear(),
                default => null,
            };

            if ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }
        }

        // Apply sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        return [
            'pointHistory' => $query->paginate(15),
            'totalPoints' => auth()->user()->totalPoints(),
            'monthlyPoints' => auth()->user()->monthlyPoints(),
            'actionSummary' => $this->getActionSummary(),
        ];
    }

    protected function getActionSummary(): array
    {
        return UserPoint::where('user_id', auth()->id())
            ->selectRaw('action, SUM(points) as total_points, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('total_points')
            ->get()
            ->map(function($item) {
                return [
                    'action' => $item->action,
                    'total_points' => $item->total_points,
                    'count' => $item->count,
                    'description' => $this->getActionDescription($item->action),
                ];
            })->toArray();
    }

    public function getActionDescription(string $action): string
    {
        return match($action) {
            'account_creation' => 'Account Creation',
            'daily_login' => 'Daily Login',
            'idea_submission' => 'Idea Submission',
            'challenge_participation' => 'Challenge Participation',
            'collaboration_contribution' => 'Collaboration',
            'review_completion' => 'Review Completion',
            'idea_approved' => 'Idea Approved',
            'challenge_winner' => 'Challenge Winner',
            'bonus_award' => 'Bonus Award',
            default => ucfirst(str_replace('_', ' ', $action))
        };
    }

    public function getActionIcon(string $action): string
    {
        return match($action) {
            'account_creation' => '🎉',
            'daily_login' => '📅',
            'idea_submission' => '💡',
            'challenge_participation' => '🏆',
            'collaboration_contribution' => '🤝',
            'review_completion' => '⭐',
            'idea_approved' => '✅',
            'challenge_winner' => '🥇',
            'bonus_award' => '🎁',
            default => '🔸'
        };
    }

    public function getActionColor(string $action): string
    {
        return match($action) {
            'account_creation' => 'bg-purple-100 text-purple-800',
            'daily_login' => 'bg-blue-100 text-blue-800',
            'idea_submission' => 'bg-yellow-100 text-yellow-800',
            'challenge_participation' => 'bg-green-100 text-green-800',
            'collaboration_contribution' => 'bg-indigo-100 text-indigo-800',
            'review_completion' => 'bg-pink-100 text-pink-800',
            'idea_approved' => 'bg-emerald-100 text-emerald-800',
            'challenge_winner' => 'bg-orange-100 text-orange-800',
            'bonus_award' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getUniqueActions(): array
    {
        return UserPoint::where('user_id', auth()->id())
            ->distinct()
            ->pluck('action')
            ->map(fn($action) => [
                'value' => $action,
                'label' => $this->getActionDescription($action)
            ])
            ->toArray();
    }
}; ?>

<div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 right-20 w-72 h-72 bg-[#FFF200]/10 dark:bg-yellow-400/5 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 left-20 w-96 h-96 bg-[#F8EBD5]/10 dark:bg-amber-400/5 rounded-full blur-3xl animate-pulse delay-1000"></div>
    </div>

    <div class="relative z-10 p-6 md:p-8">
        <!-- Enhanced Header with Modern Layout -->
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
            <div class="flex items-center space-x-4">
                <div class="relative hidden md:block">
                    <div class="w-14 h-14 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                        <svg class="w-7 h-7 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="absolute -inset-2 bg-[#FFF200]/20 dark:bg-yellow-400/20 rounded-2xl blur-lg -z-10"></div>
                </div>
                
                <div>
                    <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-1">Points History</h3>
                    <p class="text-[#9B9EA4] dark:text-zinc-400">Track your point earnings over time</p>
                </div>
            </div>
            
            <!-- Enhanced Summary Stats -->
            <div class="flex items-center space-x-4 mt-6 lg:mt-0">
                <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#F8EBD5]/70 to-[#F8EBD5]/30 dark:from-amber-400/30 dark:to-amber-400/10 backdrop-blur-sm border border-white/20 dark:border-amber-400/20 shadow-lg hover:shadow-xl transition-all duration-300 p-4 w-32">
                    <div class="absolute inset-0 bg-gradient-to-br from-yellow-500/5 via-transparent to-yellow-600/10 dark:from-yellow-400/10 dark:via-transparent dark:to-yellow-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative">
                        <div class="text-xl font-bold text-[#231F20] dark:text-zinc-100 mb-1 group-hover:text-yellow-600 dark:group-hover:text-yellow-400 transition-colors duration-300">{{ number_format($totalPoints) }}</div>
                        <div class="text-xs text-[#9B9EA4] dark:text-zinc-400 font-medium">Total Points</div>
                    </div>
                </div>
                
                <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#F8EBD5]/70 to-[#F8EBD5]/30 dark:from-amber-400/30 dark:to-amber-400/10 backdrop-blur-sm border border-white/20 dark:border-amber-400/20 shadow-lg hover:shadow-xl transition-all duration-300 p-4 w-32">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 via-transparent to-amber-600/10 dark:from-amber-400/10 dark:via-transparent dark:to-amber-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative">
                        <div class="text-xl font-bold text-[#231F20] dark:text-zinc-100 mb-1 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors duration-300">{{ number_format($monthlyPoints) }}</div>
                        <div class="text-xs text-[#9B9EA4] dark:text-zinc-400 font-medium">This Month</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Filters with Glass Morphism -->
        <div class="relative overflow-hidden rounded-2xl bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/20 dark:border-zinc-700/30 shadow-md mb-8">
            <div class="absolute inset-0 bg-gradient-to-br from-[#F8EBD5]/5 to-[#FFF200]/5 dark:from-amber-400/5 dark:to-yellow-400/5 opacity-50"></div>
            <div class="relative p-6">
                <div class="flex flex-col sm:flex-row gap-6">
                    <!-- Action Filter -->
                    <div class="flex-1">
                        <label class="block text-sm font-semibold text-[#231F20] dark:text-zinc-300 mb-3">Filter by Action</label>
                        <div class="relative">
                            <select wire:model.live="filter" class="block w-full bg-white/70 dark:bg-zinc-800/70 backdrop-blur-sm border border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl text-[#231F20] dark:text-zinc-200 px-4 py-3 focus:ring focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20 focus:border-[#FFF200] dark:focus:border-yellow-400 transition-all duration-300">
                                <option value="all">All Actions</option>
                                @foreach($this->getUniqueActions() as $action)
                                    <option value="{{ $action['value'] }}">{{ $action['label'] }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3">
                                <svg class="w-4 h-4 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Period Filter -->
                    <div class="flex-1">
                        <label class="block text-sm font-semibold text-[#231F20] dark:text-zinc-300 mb-3">Time Period</label>
                        <div class="relative">
                            <select wire:model.live="period" class="block w-full bg-white/70 dark:bg-zinc-800/70 backdrop-blur-sm border border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl text-[#231F20] dark:text-zinc-200 px-4 py-3 focus:ring focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20 focus:border-[#FFF200] dark:focus:border-yellow-400 transition-all duration-300">
                                <option value="all">All Time</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="year">This Year</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3">
                                <svg class="w-4 h-4 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Action Summary Cards with Glass Morphism -->
        @if(count($actionSummary) > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                @foreach(array_slice($actionSummary, 0, 6) as $summary)
                    <div class="group/card relative overflow-hidden rounded-2xl bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/20 dark:border-zinc-700/30 shadow-md hover:shadow-xl transform hover:-translate-y-1 transition-all duration-500">
                        <div class="absolute inset-0 bg-gradient-to-br 
                            @if(in_array($summary['action'], ['challenge_winner', 'idea_approved'])) from-emerald-500/5 via-transparent to-emerald-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20
                            @elseif(in_array($summary['action'], ['idea_submission', 'daily_login'])) from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20
                            @elseif(in_array($summary['action'], ['account_creation', 'collaboration_contribution'])) from-purple-500/5 via-transparent to-purple-600/10 dark:from-purple-400/10 dark:via-transparent dark:to-purple-500/20
                            @else from-amber-500/5 via-transparent to-amber-600/10 dark:from-amber-400/10 dark:via-transparent dark:to-amber-500/20
                            @endif
                            opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        
                        <div class="relative p-6">
                            <div class="flex items-center justify-between mb-3">
                                <div class="relative">
                                    <div class="w-12 h-12 rounded-2xl 
                                        @if(in_array($summary['action'], ['challenge_winner', 'idea_approved'])) bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500
                                        @elseif(in_array($summary['action'], ['idea_submission', 'daily_login'])) bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500
                                        @elseif(in_array($summary['action'], ['account_creation', 'collaboration_contribution'])) bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500
                                        @else bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500
                                        @endif
                                        flex items-center justify-center shadow-lg">
                                        <span class="text-2xl">{{ $this->getActionIcon($summary['action']) }}</span>
                                    </div>
                                    <div class="absolute -inset-2 
                                        @if(in_array($summary['action'], ['challenge_winner', 'idea_approved'])) bg-emerald-500/20 dark:bg-emerald-400/30
                                        @elseif(in_array($summary['action'], ['idea_submission', 'daily_login'])) bg-blue-500/20 dark:bg-blue-400/30
                                        @elseif(in_array($summary['action'], ['account_creation', 'collaboration_contribution'])) bg-purple-500/20 dark:bg-purple-400/30
                                        @else bg-amber-500/20 dark:bg-amber-400/30
                                        @endif
                                        rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                                </div>
                                <div class="text-2xl font-bold 
                                    @if(in_array($summary['action'], ['challenge_winner', 'idea_approved'])) text-[#231F20] dark:text-zinc-100 group-hover/card:text-emerald-600 dark:group-hover/card:text-emerald-400
                                    @elseif(in_array($summary['action'], ['idea_submission', 'daily_login'])) text-[#231F20] dark:text-zinc-100 group-hover/card:text-blue-600 dark:group-hover/card:text-blue-400
                                    @elseif(in_array($summary['action'], ['account_creation', 'collaboration_contribution'])) text-[#231F20] dark:text-zinc-100 group-hover/card:text-purple-600 dark:group-hover/card:text-purple-400
                                    @else text-[#231F20] dark:text-zinc-100 group-hover/card:text-amber-600 dark:group-hover/card:text-amber-400
                                    @endif
                                    transition-colors duration-300">
                                    {{ number_format($summary['total_points']) }}
                                </div>
                            </div>
                            
                            <h4 class="text-md font-bold text-[#231F20] dark:text-zinc-100 mb-1">{{ $summary['description'] }}</h4>
                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $summary['count'] }} time{{ $summary['count'] !== 1 ? 's' : '' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Enhanced History Table with Glass Morphism -->
        <div class="relative overflow-hidden rounded-2xl bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/20 dark:border-zinc-700/30 shadow-md mb-8">
            <div class="overflow-x-auto p-1">
                <table class="w-full">
                    <!-- Table Header -->
                    <thead>
                        <tr>
                            <th class="text-left py-4 px-4">
                                <button wire:click="sortBy('created_at')" class="flex items-center space-x-2 text-sm font-bold text-[#231F20] dark:text-zinc-200 hover:text-[#FFF200] dark:hover:text-yellow-400 transition-colors">
                                    <span>Date</span>
                                    @if($sortBy === 'created_at')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }} transition-transform duration-300" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="text-left py-4 px-4">
                                <button wire:click="sortBy('action')" class="flex items-center space-x-2 text-sm font-bold text-[#231F20] dark:text-zinc-200 hover:text-[#FFF200] dark:hover:text-yellow-400 transition-colors">
                                    <span>Action</span>
                                    @if($sortBy === 'action')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }} transition-transform duration-300" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="text-left py-4 px-4">
                                <span class="text-sm font-bold text-[#231F20] dark:text-zinc-200">Description</span>
                            </th>
                            <th class="text-right py-4 px-4">
                                <button wire:click="sortBy('points')" class="flex items-center space-x-2 text-sm font-bold text-[#231F20] dark:text-zinc-200 hover:text-[#FFF200] dark:hover:text-yellow-400 transition-colors ml-auto">
                                    <span>Points</span>
                                    @if($sortBy === 'points')
                                        <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }} transition-transform duration-300" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </button>
                            </th>
                        </tr>
                    </thead>

                    <!-- Table Body -->
                    <tbody>
                        @forelse($pointHistory as $point)
                            <tr class="group/row hover:bg-[#FFF200]/5 dark:hover:bg-yellow-400/10 transition-colors duration-300">
                                <!-- Date -->
                                <td class="py-4 px-4">
                                    <div class="text-sm font-medium text-[#231F20] dark:text-zinc-200">
                                        {{ $point->created_at->format('M j, Y') }}
                                    </div>
                                    <div class="text-xs text-[#9B9EA4] dark:text-zinc-400">
                                        {{ $point->created_at->format('g:i A') }}
                                    </div>
                                </td>

                                <!-- Action -->
                                <td class="py-4 px-4">
                                    <div class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium 
                                        @if(in_array($point->action, ['challenge_winner', 'idea_approved'])) bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-600
                                        @elseif(in_array($point->action, ['idea_submission', 'daily_login'])) bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-600
                                        @elseif(in_array($point->action, ['account_creation', 'collaboration_contribution'])) bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-600
                                        @else bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-600
                                        @endif">
                                        <span class="mr-1.5">{{ $this->getActionIcon($point->action) }}</span>
                                        {{ $this->getActionDescription($point->action) }}
                                    </div>
                                </td>

                                <!-- Description -->
                                <td class="py-4 px-4">
                                    <div class="text-sm text-[#231F20] dark:text-zinc-200">
                                        {{ $point->description }}
                                    </div>
                                    @if($point->related)
                                        <div class="inline-flex items-center space-x-1 text-xs text-[#9B9EA4] dark:text-zinc-400 mt-1 bg-white/50 dark:bg-zinc-800/50 px-2 py-1 rounded-md">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                                            </svg>
                                            <span>{{ class_basename(get_class($point->related)) }}</span>
                                        </div>
                                    @endif
                                </td>

                                <!-- Points -->
                                <td class="py-4 px-4 text-right">
                                    <div class="inline-flex items-center justify-center px-4 py-1.5 rounded-xl text-lg font-bold
                                        {{ $point->points > 0 ? 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-700/30' : 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-700/30' }}">
                                        {{ $point->points > 0 ? '+' : '' }}{{ number_format($point->points) }}
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <!-- Enhanced Empty State -->
                            <tr>
                                <td colspan="4" class="py-12">
                                    <div class="flex flex-col items-center">
                                        <div class="relative mb-6">
                                            <div class="w-20 h-20 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center shadow-lg">
                                                <svg class="w-10 h-10 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            </div>
                                            <div class="absolute -inset-2 bg-[#FFF200]/20 dark:bg-yellow-400/20 rounded-3xl blur-lg -z-10"></div>
                                        </div>
                                        
                                        <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">No Points History Yet</h3>
                                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-center max-w-md mb-6">
                                            Start engaging with the platform by submitting ideas, participating in challenges, 
                                            or collaborating with others to earn points!
                                        </p>
                                        
                                        <flux:button href="#" variant="primary"  
                                                   class="group bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 hover:from-[#231F20] hover:to-[#231F20] dark:hover:from-zinc-800 dark:hover:to-zinc-700 text-[#231F20] dark:text-zinc-900 hover:text-[#FFF200] dark:hover:text-yellow-400 font-bold px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                                            <span>Explore Ways to Earn</span>
                                            <svg class="ml-2 w-5 h-5 transform group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                            </svg>
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Enhanced Pagination -->
        @if($pointHistory->hasPages())
            <div class="mt-6">
                {{ $pointHistory->links() }}
            </div>
        @endif
    </div>
</div>
