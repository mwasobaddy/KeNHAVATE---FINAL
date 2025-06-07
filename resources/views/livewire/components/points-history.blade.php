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

<div class="bg-white rounded-xl shadow-lg border border-[#9B9EA4]/20 p-6">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6">
        <div>
            <h3 class="text-xl font-bold text-[#231F20] mb-1">Points History</h3>
            <p class="text-sm text-[#9B9EA4]">Track your point earnings over time</p>
        </div>
        
        <!-- Summary Stats -->
        <div class="grid grid-cols-2 gap-4 mt-4 lg:mt-0">
            <div class="text-center p-3 bg-[#F8EBD5] rounded-lg">
                <div class="text-lg font-bold text-[#231F20]">{{ number_format($totalPoints) }}</div>
                <div class="text-xs text-[#9B9EA4]">Total Points</div>
            </div>
            <div class="text-center p-3 bg-[#F8EBD5] rounded-lg">
                <div class="text-lg font-bold text-[#231F20]">{{ number_format($monthlyPoints) }}</div>
                <div class="text-xs text-[#9B9EA4]">This Month</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-4 mb-6 p-4 bg-[#F8EBD5]/30 rounded-lg">
        <!-- Action Filter -->
        <div class="flex-1">
            <label class="block text-sm font-medium text-[#231F20] mb-2">Filter by Action</label>
            <select wire:model.live="filter" class="w-full px-3 py-2 border border-[#9B9EA4]/30 rounded-lg text-sm text-[#231F20] bg-white focus:ring-2 focus:ring-[#FFF200] focus:border-transparent">
                <option value="all">All Actions</option>
                @foreach($this->getUniqueActions() as $action)
                    <option value="{{ $action['value'] }}">{{ $action['label'] }}</option>
                @endforeach
            </select>
        </div>

        <!-- Period Filter -->
        <div class="flex-1">
            <label class="block text-sm font-medium text-[#231F20] mb-2">Time Period</label>
            <select wire:model.live="period" class="w-full px-3 py-2 border border-[#9B9EA4]/30 rounded-lg text-sm text-[#231F20] bg-white focus:ring-2 focus:ring-[#FFF200] focus:border-transparent">
                <option value="all">All Time</option>
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
                <option value="year">This Year</option>
            </select>
        </div>
    </div>

    <!-- Action Summary Cards -->
    @if(count($actionSummary) > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            @foreach(array_slice($actionSummary, 0, 6) as $summary)
                <div class="border border-[#9B9EA4]/20 rounded-lg p-4 hover:bg-[#F8EBD5]/20 transition-colors">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-2xl">{{ $this->getActionIcon($summary['action']) }}</span>
                        <span class="text-lg font-bold text-[#231F20]">{{ number_format($summary['total_points']) }}</span>
                    </div>
                    <h4 class="text-sm font-medium text-[#231F20] mb-1">{{ $summary['description'] }}</h4>
                    <p class="text-xs text-[#9B9EA4]">{{ $summary['count'] }} time{{ $summary['count'] !== 1 ? 's' : '' }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <!-- History Table -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <!-- Table Header -->
            <thead>
                <tr class="border-b border-[#9B9EA4]/20">
                    <th class="text-left py-3 px-2">
                        <button wire:click="sortBy('created_at')" class="flex items-center space-x-1 text-sm font-semibold text-[#231F20] hover:text-[#FFF200] transition-colors">
                            <span>Date</span>
                            @if($sortBy === 'created_at')
                                <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </button>
                    </th>
                    <th class="text-left py-3 px-2">
                        <button wire:click="sortBy('action')" class="flex items-center space-x-1 text-sm font-semibold text-[#231F20] hover:text-[#FFF200] transition-colors">
                            <span>Action</span>
                            @if($sortBy === 'action')
                                <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </button>
                    </th>
                    <th class="text-left py-3 px-2">Description</th>
                    <th class="text-right py-3 px-2">
                        <button wire:click="sortBy('points')" class="flex items-center space-x-1 text-sm font-semibold text-[#231F20] hover:text-[#FFF200] transition-colors ml-auto">
                            <span>Points</span>
                            @if($sortBy === 'points')
                                <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
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
                    <tr class="border-b border-[#9B9EA4]/10 hover:bg-[#F8EBD5]/20 transition-colors">
                        <!-- Date -->
                        <td class="py-4 px-2">
                            <div class="text-sm text-[#231F20] font-medium">
                                {{ $point->created_at->format('M j, Y') }}
                            </div>
                            <div class="text-xs text-[#9B9EA4]">
                                {{ $point->created_at->format('g:i A') }}
                            </div>
                        </td>

                        <!-- Action -->
                        <td class="py-4 px-2">
                            <div class="flex items-center space-x-2">
                                <span class="text-lg">{{ $this->getActionIcon($point->action) }}</span>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $this->getActionColor($point->action) }}">
                                    {{ $this->getActionDescription($point->action) }}
                                </span>
                            </div>
                        </td>

                        <!-- Description -->
                        <td class="py-4 px-2">
                            <div class="text-sm text-[#231F20] max-w-xs">
                                {{ $point->description }}
                            </div>
                            @if($point->related)
                                <div class="text-xs text-[#9B9EA4] mt-1">
                                    Related to: {{ class_basename(get_class($point->related)) }}
                                </div>
                            @endif
                        </td>

                        <!-- Points -->
                        <td class="py-4 px-2 text-right">
                            <div class="text-lg font-bold {{ $point->points > 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $point->points > 0 ? '+' : '' }}{{ number_format($point->points) }}
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-[#9B9EA4]/20 rounded-full flex items-center justify-center mb-4">
                                    <svg class="w-8 h-8 text-[#9B9EA4]" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-[#231F20] mb-2">No Points History</h3>
                                <p class="text-[#9B9EA4]">Start engaging with the platform to earn points!</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($pointHistory->hasPages())
        <div class="mt-6">
            {{ $pointHistory->links() }}
        </div>
    @endif
</div>
