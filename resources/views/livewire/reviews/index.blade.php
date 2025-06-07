<?php

use Livewire\Volt\Component;
use App\Models\Idea;
use App\Models\Review;
use App\Services\IdeaWorkflowService;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    
    use WithPagination;
    
    public string $search = '';
    public string $statusFilter = 'all';
    public string $stageFilter = 'all';
    
    protected IdeaWorkflowService $workflowService;
    
    public function boot(IdeaWorkflowService $workflowService): void
    {
        $this->workflowService = $workflowService;
    }
    
    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    
    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }
    
    public function updatingStageFilter(): void
    {
        $this->resetPage();
    }
    
    public function with(): array
    {
        $user = Auth::user();
        
        // Get user's review stages based on roles
        $reviewStages = [];
        if ($user->hasRole('manager')) $reviewStages[] = 'manager_review';
        if ($user->hasRole('sme')) $reviewStages[] = 'sme_review';
        if ($user->hasRole('board_member')) $reviewStages[] = 'board_review';
        
        // Build query for pending reviews
        $query = Idea::query()
            ->when(!empty($reviewStages), function ($q) use ($reviewStages) {
                return $q->whereIn('current_stage', $reviewStages);
            })
            ->where('author_id', '!=', $user->id) // Exclude own ideas
            ->with(['author', 'category', 'reviews' => function ($q) use ($user) {
                $q->where('reviewer_id', $user->id);
            }]);
        
        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
                  ->orWhereHas('author', function ($subQ) {
                      $subQ->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }
        
        // Apply stage filter
        if ($this->stageFilter !== 'all') {
            $query->where('current_stage', $this->stageFilter);
        }
        
        // Apply status filter
        if ($this->statusFilter === 'pending') {
            $query->whereDoesntHave('reviews', function ($q) use ($user) {
                $q->where('reviewer_id', $user->id)
                  ->whereNotNull('completed_at');
            });
        } elseif ($this->statusFilter === 'completed') {
            $query->whereHas('reviews', function ($q) use ($user) {
                $q->where('reviewer_id', $user->id)
                  ->whereNotNull('completed_at');
            });
        }
        
        $ideas = $query->orderBy('submitted_at')->paginate(10);
        
        // Calculate statistics
        $stats = [
            'total_pending' => $this->workflowService->getPendingReviews($user)->count(),
            'completed_today' => Review::where('reviewer_id', $user->id)
                ->whereDate('completed_at', today())
                ->count(),
            'this_week' => Review::where('reviewer_id', $user->id)
                ->whereBetween('completed_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'avg_score' => Review::where('reviewer_id', $user->id)
                ->whereNotNull('overall_score')
                ->avg('overall_score')
        ];
        
        return [
            'ideas' => $ideas,
            'stats' => $stats,
            'availableStages' => $reviewStages,
            'userRoles' => $user->roles->pluck('name')->toArray()
        ];
    }
    
}; ?>

{{-- Modern Reviews Dashboard with Glass Morphism & Enhanced UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">

        {{-- Enhanced Page Header --}}
        <section aria-labelledby="reviews-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl p-8">
                {{-- Animated Gradient Background --}}
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-purple-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-purple-500/20 opacity-70"></div>
                
                <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] rounded-3xl flex items-center justify-center shadow-lg">
                            <svg class="w-8 h-8 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                        </div>
                        <div>
                            <h1 id="reviews-heading" class="text-4xl font-bold text-[#231F20] dark:text-zinc-100">My Reviews</h1>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg mt-1">Review and provide feedback on submitted ideas</p>
                        </div>
                    </div>
                    
                    {{-- Role Badges --}}
                    <div class="flex items-center space-x-3">
                        <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Role{{ count($userRoles) > 1 ? 's' : '' }}:</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach($userRoles as $role)
                                <span class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 rounded-full text-sm font-semibold capitalize shadow-lg">
                                    {{ str_replace('_', ' ', $role) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Statistics Cards with Glass Morphism --}}
        <section aria-labelledby="stats-heading" class="group">
            <h2 id="stats-heading" class="sr-only">Review Statistics</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Pending Reviews Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-red-500/5 via-transparent to-red-600/10 dark:from-red-400/10 dark:via-transparent dark:to-red-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-500 to-red-600 dark:from-red-400 dark:to-red-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-red-500/20 dark:bg-red-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Pending Reviews</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-red-600 dark:group-hover/card:text-red-400 transition-colors duration-300">{{ number_format($stats['total_pending']) }}</p>
                            
                            @if($stats['total_pending'] > 0)
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 px-3 py-1.5 rounded-full">
                                    <div class="w-2 h-2 bg-red-500 dark:bg-red-400 rounded-full animate-ping"></div>
                                    <span>Awaiting review</span>
                                </div>
                            @else
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 px-3 py-1.5 rounded-full">
                                    <span>All caught up</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Completed Today Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-emerald-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-emerald-500/20 dark:bg-emerald-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Completed Today</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-emerald-600 dark:group-hover/card:text-emerald-400 transition-colors duration-300">{{ number_format($stats['completed_today']) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                                <span>Daily progress</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- This Week Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">This Week</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-blue-600 dark:group-hover/card:text-blue-400 transition-colors duration-300">{{ number_format($stats['this_week']) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full"></div>
                                <span>Weekly activity</span>
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
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Avg Score Given</p>
                            <p class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-amber-600 dark:group-hover/card:text-amber-400 transition-colors duration-300">
                                {{ $stats['avg_score'] ? number_format($stats['avg_score'], 1) . '/10' : 'N/A' }}
                            </p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-3 py-1.5 rounded-full">
                                <span>Review quality</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Filters Section --}}
        <section aria-labelledby="filters-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="p-8">
                    <h3 id="filters-heading" class="sr-only">Filter Options</h3>
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0 lg:space-x-6">
                        {{-- Search Input --}}
                        <div class="flex-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input type="text" 
                                   wire:model.live.debounce.300ms="search"
                                   placeholder="Search ideas, authors, or descriptions..."
                                   class="w-full pl-12 pr-4 py-3 bg-white/50 dark:bg-zinc-700/50 border border-white/40 dark:border-zinc-600/40 rounded-2xl focus:ring-2 focus:ring-[#FFF200]/50 focus:border-transparent backdrop-blur-sm text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] dark:placeholder-zinc-400 transition-all duration-300">
                        </div>
                        
                        {{-- Stage Filter --}}
                        @if(!empty($availableStages))
                        <div class="relative">
                            <select wire:model.live="stageFilter" 
                                    class="px-6 py-3 bg-white/50 dark:bg-zinc-700/50 border border-white/40 dark:border-zinc-600/40 rounded-2xl focus:ring-2 focus:ring-[#FFF200]/50 focus:border-transparent backdrop-blur-sm text-[#231F20] dark:text-zinc-100 transition-all duration-300 appearance-none pr-12">
                                <option value="all">All Stages</option>
                                @foreach($availableStages as $stage)
                                    <option value="{{ $stage }}">{{ ucfirst(str_replace('_', ' ', $stage)) }}</option>
                                @endforeach
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                        @endif
                        
                        {{-- Status Filter --}}
                        <div class="relative">
                            <select wire:model.live="statusFilter" 
                                    class="px-6 py-3 bg-white/50 dark:bg-zinc-700/50 border border-white/40 dark:border-zinc-600/40 rounded-2xl focus:ring-2 focus:ring-[#FFF200]/50 focus:border-transparent backdrop-blur-sm text-[#231F20] dark:text-zinc-100 transition-all duration-300 appearance-none pr-12">
                                <option value="all">All Status</option>
                                <option value="pending">Pending Review</option>
                                <option value="completed">Completed</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Reviews List --}}
        <section aria-labelledby="reviews-list-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <h3 id="reviews-list-heading" class="sr-only">Reviews List</h3>
                
                @if($ideas->count() > 0)
                    {{-- Enhanced Table Header --}}
                    <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Review Queue</h3>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">{{ $ideas->total() }} {{ Str::plural('idea', $ideas->total()) }} found</p>
                            </div>
                        </div>
                    </div>

                    {{-- Reviews Table --}}
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gradient-to-r from-[#F8EBD5]/80 to-[#F8EBD5]/60 dark:from-zinc-700/80 dark:to-zinc-800/60">
                                    <th class="px-8 py-4 text-left text-sm font-bold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Idea Details</th>
                                    <th class="px-8 py-4 text-left text-sm font-bold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Author</th>
                                    <th class="px-8 py-4 text-left text-sm font-bold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Category</th>
                                    <th class="px-8 py-4 text-left text-sm font-bold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Stage</th>
                                    <th class="px-8 py-4 text-left text-sm font-bold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Submitted</th>
                                    <th class="px-8 py-4 text-left text-sm font-bold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Status</th>
                                    <th class="px-8 py-4 text-left text-sm font-bold text-[#231F20] dark:text-zinc-100 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100/50 dark:divide-zinc-700/50">
                                @foreach($ideas as $idea)
                                @php
                                    $userReview = $idea->reviews->first();
                                    $isCompleted = $userReview && $userReview->completed_at;
                                @endphp
                                <tr class="group/row hover:bg-gradient-to-r hover:from-white/90 hover:to-white/60 dark:hover:from-zinc-800/90 dark:hover:to-zinc-700/60 transition-all duration-300">
                                    <td class="px-8 py-6">
                                        <div class="max-w-sm">
                                            <h4 class="font-bold text-[#231F20] dark:text-zinc-100 text-lg mb-2 group-hover/row:text-blue-600 dark:group-hover/row:text-blue-400 transition-colors duration-300">
                                                {{ Str::limit($idea->title, 40) }}
                                            </h4>
                                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 leading-relaxed">
                                                {{ Str::limit($idea->description, 100) }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 rounded-xl flex items-center justify-center text-white font-semibold">
                                                {{ substr($idea->author->name, 0, 1) }}
                                            </div>
                                            <div>
                                                <p class="font-medium text-[#231F20] dark:text-zinc-100">{{ $idea->author->name }}</p>
                                                <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $idea->author->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <span class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 rounded-full text-sm font-medium shadow-lg">
                                            {{ $idea->category->name }}
                                        </span>
                                    </td>
                                    <td class="px-8 py-6">
                                        <span class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-100 to-blue-50 dark:from-blue-900/50 dark:to-blue-800/50 text-blue-800 dark:text-blue-200 rounded-full text-sm font-semibold shadow-lg">
                                            {{ ucfirst(str_replace('_', ' ', $idea->current_stage)) }}
                                        </span>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="text-sm">
                                            <p class="font-medium text-[#231F20] dark:text-zinc-100">
                                                {{ $idea->submitted_at?->format('M d, Y') ?? 'N/A' }}
                                            </p>
                                            @if($idea->submitted_at)
                                                <p class="text-[#9B9EA4] dark:text-zinc-400 mt-1">
                                                    {{ $idea->submitted_at->diffForHumans() }}
                                                </p>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        @if($isCompleted)
                                            <div class="flex flex-col space-y-2">
                                                <span class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-green-100 to-green-50 dark:from-green-900/50 dark:to-green-800/50 text-green-800 dark:text-green-200 rounded-full text-sm font-semibold shadow-lg">
                                                    Completed
                                                </span>
                                                @if($userReview->overall_score)
                                                    <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">
                                                        Score: {{ $userReview->overall_score }}/10
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-yellow-100 to-yellow-50 dark:from-yellow-900/50 dark:to-yellow-800/50 text-yellow-800 dark:text-yellow-200 rounded-full text-sm font-semibold shadow-lg">
                                                <div class="w-2 h-2 bg-yellow-500 dark:bg-yellow-400 rounded-full animate-pulse mr-2"></div>
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center space-x-3">
                                            {{-- View Idea --}}
                                            <a href="{{ route('ideas.show', $idea) }}" 
                                               class="group/action relative p-2 rounded-xl bg-white/50 dark:bg-zinc-700/50 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                                                <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400 group-hover/action:text-blue-600 dark:group-hover/action:text-blue-400 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                            
                                            {{-- Review Action --}}
                                            @if(!$isCompleted)
                                                <a href="{{ route('reviews.idea', $idea) }}" 
                                                   class="group/action relative overflow-hidden px-6 py-3 bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] text-[#231F20] rounded-2xl font-semibold hover:shadow-lg transition-all duration-300 hover:-translate-y-1 transform">
                                                    <span class="absolute inset-0 bg-gradient-to-r from-yellow-300 to-yellow-200 opacity-0 group-hover/action:opacity-100 transition-opacity duration-300"></span>
                                                    <span class="relative">Review Now</span>
                                                </a>
                                            @else
                                                <a href="{{ route('reviews.idea', $idea) }}" 
                                                   class="group/action relative overflow-hidden px-6 py-3 bg-gradient-to-r from-gray-100 to-gray-50 dark:from-zinc-700 dark:to-zinc-600 text-[#231F20] dark:text-zinc-100 rounded-2xl font-semibold hover:shadow-lg transition-all duration-300 hover:-translate-y-1 transform">
                                                    <span class="relative">View Review</span>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    {{-- Enhanced Pagination --}}
                    <div class="px-8 py-6 border-t border-gray-100/50 dark:border-zinc-700/50 bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30">
                        {{ $ideas->links() }}
                    </div>
                    
                @else
                    {{-- Enhanced Empty State --}}
                    <div class="p-16 text-center relative">
                        {{-- Animated Background Elements --}}
                        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full blur-3xl"></div>
                        
                        <div class="relative">
                            <div class="w-24 h-24 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-xl">
                                <svg class="w-12 h-12 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            </div>
                            
                            <h3 class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-4">No Reviews Found</h3>
                            <p class="text-lg text-[#9B9EA4] dark:text-zinc-400 mb-8 max-w-md mx-auto leading-relaxed">
                                @if($search || $statusFilter !== 'all' || $stageFilter !== 'all')
                                    No reviews match your current filters. Try adjusting your search criteria to see more results.
                                @else
                                    You're all caught up! There are no ideas currently assigned for your review.
                                @endif
                            </p>
                            
                            @if($search || $statusFilter !== 'all' || $stageFilter !== 'all')
                                <button wire:click="$set('search', ''); $set('statusFilter', 'all'); $set('stageFilter', 'all')" 
                                        class="group relative overflow-hidden px-8 py-4 bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] text-[#231F20] rounded-2xl font-bold hover:shadow-xl transition-all duration-300 hover:-translate-y-1 transform">
                                    <span class="absolute inset-0 bg-gradient-to-r from-yellow-300 to-yellow-200 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                    <span class="relative flex items-center space-x-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        <span>Clear All Filters</span>
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>
</div>
