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

<div class="space-y-6">
    
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-[#231F20]">My Reviews</h1>
            <p class="text-[#9B9EA4] mt-1">Review and provide feedback on submitted ideas</p>
        </div>
        
        <div class="flex items-center space-x-2 text-sm text-[#9B9EA4]">
            <span>Role{{ count($userRoles) > 1 ? 's' : '' }}:</span>
            @foreach($userRoles as $role)
                <span class="px-2 py-1 bg-[#F8EBD5] text-[#231F20] rounded-full capitalize">
                    {{ str_replace('_', ' ', $role) }}
                </span>
            @endforeach
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Pending Reviews -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">Pending Reviews</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ $stats['total_pending'] }}</p>
                </div>
                <div class="w-12 h-12 bg-[#FFF200] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Completed Today -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">Completed Today</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ $stats['completed_today'] }}</p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- This Week -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">This Week</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ $stats['this_week'] }}</p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Average Score -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">Avg Score Given</p>
                    <p class="text-2xl font-bold text-[#231F20]">
                        {{ $stats['avg_score'] ? number_format($stats['avg_score'], 1) . '/10' : 'N/A' }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0 md:space-x-4">
            <!-- Search -->
            <div class="flex-1">
                <input type="text" 
                       wire:model.live.debounce.300ms="search"
                       placeholder="Search ideas, authors, or descriptions..."
                       class="w-full px-4 py-2 border border-[#9B9EA4] rounded-lg focus:ring-2 focus:ring-[#FFF200] focus:border-transparent">
            </div>
            
            <!-- Stage Filter -->
            @if(!empty($availableStages))
            <div>
                <select wire:model.live="stageFilter" 
                        class="px-4 py-2 border border-[#9B9EA4] rounded-lg focus:ring-2 focus:ring-[#FFF200] focus:border-transparent">
                    <option value="all">All Stages</option>
                    @foreach($availableStages as $stage)
                        <option value="{{ $stage }}">{{ ucfirst(str_replace('_', ' ', $stage)) }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            
            <!-- Status Filter -->
            <div>
                <select wire:model.live="statusFilter" 
                        class="px-4 py-2 border border-[#9B9EA4] rounded-lg focus:ring-2 focus:ring-[#FFF200] focus:border-transparent">
                    <option value="all">All Status</option>
                    <option value="pending">Pending Review</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Reviews List -->
    <div class="bg-white rounded-lg border border-[#9B9EA4] overflow-hidden">
        @if($ideas->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-[#F8EBD5]">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-[#231F20] uppercase tracking-wider">Idea</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-[#231F20] uppercase tracking-wider">Author</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-[#231F20] uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-[#231F20] uppercase tracking-wider">Stage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-[#231F20] uppercase tracking-wider">Submitted</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-[#231F20] uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-[#231F20] uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#9B9EA4]">
                        @foreach($ideas as $idea)
                        @php
                            $userReview = $idea->reviews->first();
                            $isCompleted = $userReview && $userReview->completed_at;
                        @endphp
                        <tr class="hover:bg-[#F8EBD5] transition-colors">
                            <td class="px-6 py-4">
                                <div>
                                    <h4 class="font-medium text-[#231F20]">{{ Str::limit($idea->title, 50) }}</h4>
                                    <p class="text-sm text-[#9B9EA4] mt-1">{{ Str::limit($idea->description, 80) }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm">
                                    <p class="font-medium text-[#231F20]">{{ $idea->author->name }}</p>
                                    <p class="text-[#9B9EA4]">{{ $idea->author->email }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 bg-[#F8EBD5] text-[#231F20] rounded-full text-sm">
                                    {{ $idea->category->name }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                                    {{ ucfirst(str_replace('_', ' ', $idea->current_stage)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-[#9B9EA4]">
                                {{ $idea->submitted_at?->format('M d, Y') ?? 'N/A' }}
                                @if($idea->submitted_at)
                                    <br>
                                    <span class="text-xs">{{ $idea->submitted_at->diffForHumans() }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($isCompleted)
                                    <div class="flex items-center">
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                            Completed
                                        </span>
                                        @if($userReview->overall_score)
                                            <span class="ml-2 text-sm text-[#9B9EA4]">
                                                {{ $userReview->overall_score }}/10
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                                        Pending
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <!-- View Idea -->
                                    <a href="{{ route('ideas.show', $idea) }}" 
                                       class="text-[#9B9EA4] hover:text-[#231F20] transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    
                                    <!-- Review Action -->
                                    @if(!$isCompleted)
                                        <a href="{{ route('reviews.idea', $idea) }}" 
                                           class="px-3 py-1 bg-[#FFF200] text-[#231F20] rounded text-sm font-medium hover:bg-yellow-300 transition-colors">
                                            Review
                                        </a>
                                    @else
                                        <a href="{{ route('reviews.idea', $idea) }}" 
                                           class="px-3 py-1 bg-[#9B9EA4] text-white rounded text-sm font-medium hover:bg-gray-600 transition-colors">
                                            View Review
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-[#9B9EA4]">
                {{ $ideas->links() }}
            </div>
            
        @else
            <!-- Empty State -->
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <h3 class="mt-4 text-lg font-medium text-[#231F20]">No reviews found</h3>
                <p class="mt-2 text-[#9B9EA4]">
                    @if($search || $statusFilter !== 'all' || $stageFilter !== 'all')
                        Try adjusting your filters to see more results.
                    @else
                        There are no ideas currently assigned for your review.
                    @endif
                </p>
                
                @if($search || $statusFilter !== 'all' || $stageFilter !== 'all')
                <button wire:click="$set('search', ''); $set('statusFilter', 'all'); $set('stageFilter', 'all')" 
                        class="mt-4 px-4 py-2 bg-[#FFF200] text-[#231F20] rounded-lg hover:bg-yellow-300 transition-colors">
                    Clear Filters
                </button>
                @endif
            </div>
        @endif
    </div>
    
</div>
