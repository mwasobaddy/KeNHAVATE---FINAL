<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Artesaos\SEOTools\Facades\SEOTools;

new class extends Component {
    use WithPagination;
    
    public string $search = '';
    public string $status = 'all';
    public string $category = 'all';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';
    
    public function mount()
    {
        // Set SEO meta tags
        SEOTools::setTitle('Innovation Challenges - KeNHAVATE Innovation Portal');
        SEOTools::setDescription('Explore and participate in innovation challenges designed to solve real-world highway infrastructure problems.');
    }
    
    public function updatedSearch()
    {
        $this->resetPage();
    }
    
    public function updatedStatus()
    {
        $this->resetPage();
    }
    
    public function updatedCategory()
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
    
    public function with()
    {
        $query = Challenge::with(['author', 'submissions'])
            ->withCount('submissions');
            
        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
                  ->orWhere('category', 'like', '%' . $this->search . '%');
            });
        }
        
        // Apply status filter
        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }
        
        // Apply category filter
        if ($this->category !== 'all') {
            $query->where('category', $this->category);
        }
        
        // Apply sorting
        $query->orderBy($this->sortBy, $this->sortDirection);
        
        $challenges = $query->paginate(12);
        
        // Get user's submissions for each challenge
        $userSubmissions = [];
        if (Auth::check()) {
            $userSubmissions = ChallengeSubmission::where('author_id', Auth::id())
                ->whereIn('challenge_id', $challenges->pluck('id'))
                ->get()
                ->keyBy('challenge_id');
        }
        
        $categories = Challenge::distinct('category')->pluck('category')->filter();
        
        return [
            'challenges' => $challenges,
            'userSubmissions' => $userSubmissions,
            'categories' => $categories,
        ];
    }
    
    public function getStatusBadgeClass($status)
    {
        return match($status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'active' => 'bg-green-100 text-green-800',
            'judging' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-blue-100 text-blue-800',
            'cancelled' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    
    public function getDaysRemaining($deadline)
    {
        $now = Carbon::now();
        $deadline = Carbon::parse($deadline);
        
        if ($deadline->isPast()) {
            return 'Expired';
        }
        
        $days = $now->diffInDays($deadline);
        
        if ($days === 0) {
            return 'Due today';
        } elseif ($days === 1) {
            return '1 day left';
        } else {
            return $days . ' days left';
        }
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-[#F8EBD5] via-white to-[#F8EBD5] py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-[#231F20] mb-2">Innovation Challenges</h1>
                    <p class="text-[#9B9EA4] text-lg">Solve real-world highway infrastructure problems and win exciting prizes</p>
                </div>
                
                @if(auth()->user()->hasRole(['manager', 'admin', 'developer']))
                    <flux:button 
                        wire:navigate 
                        href="{{ route('challenges.create') }}" 
                        variant="primary"
                        class="bg-[#FFF200] hover:bg-yellow-400 text-[#231F20] font-semibold px-6 py-3 rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg"
                    >
                        <flux:icon.plus class="w-5 h-5 mr-2" />
                        Create Challenge
                    </flux:button>
                @endif
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div class="md:col-span-2">
                    <flux:input 
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search challenges..."
                        class="w-full rounded-xl border-[#9B9EA4]/30 bg-white/80"
                    >
                        <x-slot name="iconLeading">
                            <flux:icon.magnifying-glass class="w-5 h-5 text-[#9B9EA4]" />
                        </x-slot>
                    </flux:input>
                </div>

                <!-- Status Filter -->
                <div>
                    <flux:select wire:model.live="status" class="w-full rounded-xl border-[#9B9EA4]/30 bg-white/80">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="judging">Judging</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </flux:select>
                </div>

                <!-- Category Filter -->
                <div>
                    <flux:select wire:model.live="category" class="w-full rounded-xl border-[#9B9EA4]/30 bg-white/80">
                        <option value="all">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div wire:loading.flex class="justify-center items-center py-12">
            <div class="flex items-center space-x-3">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#FFF200]"></div>
                <span class="text-[#231F20] font-medium">Loading challenges...</span>
            </div>
        </div>

        <!-- Challenges Grid -->
        <div wire:loading.remove class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            @forelse($challenges as $challenge)
                <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 overflow-hidden hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <!-- Challenge Image/Icon -->
                    <div class="h-48 bg-gradient-to-br from-[#FFF200]/20 to-[#F8EBD5] relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="text-6xl text-[#231F20]/20">
                                @switch($challenge->category)
                                    @case('technology')
                                        🚀
                                        @break
                                    @case('sustainability')
                                        🌱
                                        @break
                                    @case('safety')
                                        🛡️
                                        @break
                                    @case('innovation')
                                        💡
                                        @break
                                    @default
                                        🏗️
                                @endswitch
                            </div>
                        </div>
                        
                        <!-- Status Badge -->
                        <div class="absolute top-4 right-4">
                            <span class="px-3 py-1 rounded-full text-xs font-medium {{ $this->getStatusBadgeClass($challenge->status) }}">
                                {{ ucfirst($challenge->status) }}
                            </span>
                        </div>
                        
                        <!-- Deadline Badge -->
                        @if($challenge->deadline && $challenge->status === 'active')
                            <div class="absolute top-4 left-4">
                                <span class="px-3 py-1 rounded-full text-xs font-medium bg-white/90 text-[#231F20]">
                                    {{ $this->getDaysRemaining($challenge->deadline) }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <!-- Challenge Content -->
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-3">
                            <h3 class="text-xl font-bold text-[#231F20] line-clamp-2">{{ $challenge->title }}</h3>
                        </div>
                        
                        <p class="text-[#9B9EA4] text-sm mb-4 line-clamp-3">{{ $challenge->description }}</p>
                        
                        <!-- Challenge Meta -->
                        <div class="space-y-2 mb-4">
                            <div class="flex items-center text-sm text-[#9B9EA4]">
                                <flux:icon.user class="w-4 h-4 mr-2" />
                                <span>by {{ $challenge->author->name }}</span>
                            </div>
                            
                            <div class="flex items-center text-sm text-[#9B9EA4]">
                                <flux:icon.users class="w-4 h-4 mr-2" />
                                <span>{{ $challenge->submissions_count }} {{ Str::plural('submission', $challenge->submissions_count) }}</span>
                            </div>
                            
                            @if($challenge->prize_description)
                                <div class="flex items-center text-sm text-[#9B9EA4]">
                                    <flux:icon.gift class="w-4 h-4 mr-2" />
                                    <span class="line-clamp-1">{{ $challenge->prize_description }}</span>
                                </div>
                            @endif
                        </div>
                        
                        <!-- User Submission Status -->
                        @auth
                            @if(isset($userSubmissions[$challenge->id]))
                                <div class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                    <div class="flex items-center text-sm text-blue-800">
                                        <flux:icon.check-circle class="w-4 h-4 mr-2" />
                                        <span>You have submitted to this challenge</span>
                                    </div>
                                </div>
                            @endif
                        @endauth
                        
                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <flux:button 
                                wire:navigate 
                                href="{{ route('challenges.show', $challenge) }}" 
                                variant="primary"
                                class="flex-1 bg-[#231F20] hover:bg-gray-800 text-white rounded-xl"
                            >
                                View Details
                            </flux:button>
                            
                            @auth
                                @if($challenge->status === 'active' && !isset($userSubmissions[$challenge->id]))
                                    <flux:button 
                                        wire:navigate 
                                        href="{{ route('challenges.submit', $challenge) }}" 
                                        variant="primary"
                                        class="bg-[#FFF200] hover:bg-yellow-400 text-[#231F20] rounded-xl px-6"
                                    >
                                        Submit
                                    </flux:button>
                                @endif
                            @endauth
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full">
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">🏗️</div>
                        <h3 class="text-xl font-semibold text-[#231F20] mb-2">No challenges found</h3>
                        <p class="text-[#9B9EA4] mb-6">Try adjusting your search criteria or check back later for new challenges.</p>
                        
                        @if(auth()->user()->hasRole(['manager', 'admin', 'developer']))
                            <flux:button 
                                wire:navigate 
                                href="{{ route('challenges.create') }}" 
                                variant="primary"
                                class="bg-[#FFF200] hover:bg-yellow-400 text-[#231F20] rounded-xl"
                            >
                                Create First Challenge
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($challenges->hasPages())
            <div class="flex justify-center">
                {{ $challenges->links() }}
            </div>
        @endif
    </div>
</div>
