<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Artesaos\SEOTools\Facades\SEOTools;
use Livewire\Attributes\{Layout, Title};

new #[Layout('components.layouts.app')] #[Title('Challenge Submissions')] class extends Component
{
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

{{-- Modern Innovation Challenges Interface with Glass Morphism & Enhanced UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Enhanced Header Section with Glass Morphism --}}
        <section aria-labelledby="challenges-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Animated Background Elements --}}
                <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl"></div>
                
                <div class="relative z-10 p-8">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div class="flex items-center space-x-6">
                            <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center shadow-xl">
                                <svg class="w-8 h-8 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                            <div>
                                <h1 id="challenges-heading" class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">Innovation Challenges</h1>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg leading-relaxed">Solve real-world highway infrastructure problems and win exciting prizes</p>
                            </div>
                        </div>
                        
                        @if(auth()->user()->hasRole(['manager', 'administrator', 'developer']))
                            <flux:button 
                                wire:navigate 
                                href="{{ route('challenges.create') }}" 
                                variant="primary"
                                class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#FFF200] to-yellow-400 hover:from-yellow-400 hover:to-[#FFF200] text-[#231F20] font-semibold px-8 py-4 transition-all duration-500 transform hover:scale-105 shadow-xl hover:shadow-2xl border border-yellow-300/50"
                            >
                                <span class="absolute inset-0 bg-white/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center space-x-3">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <span>Create Challenge</span>
                                </div>
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Filters Section with Glass Morphism --}}
        <section aria-labelledby="filters-heading" class="group">
            <h2 id="filters-heading" class="sr-only">Challenge Filters</h2>
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        {{-- Enhanced Search --}}
                        <div class="md:col-span-2">
                            <div class="relative group">
                                <flux:input 
                                    wire:model.live.debounce.300ms="search"
                                    placeholder="Search challenges..."
                                    class="w-full rounded-2xl border border-white/40 dark:border-zinc-600/40 bg-white/80 dark:bg-zinc-700/50 backdrop-blur-sm shadow-lg focus:shadow-xl transition-all duration-300 pl-12"
                                />
                                <div class="absolute left-4 top-1/2 transform -translate-y-1/2">
                                    <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400 group-focus-within:text-[#FFF200] transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        {{-- Enhanced Status Filter --}}
                        <div class="relative">
                            <flux:select 
                                wire:model.live="status" 
                                class="w-full rounded-2xl border border-white/40 dark:border-zinc-600/40 bg-white/80 dark:bg-zinc-700/50 backdrop-blur-sm shadow-lg focus:shadow-xl transition-all duration-300"
                            >
                                <option value="all">All Status</option>
                                <option value="active">Active</option>
                                <option value="judging">Judging</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </flux:select>
                        </div>

                        {{-- Enhanced Category Filter --}}
                        <div class="relative">
                            <flux:select 
                                wire:model.live="category" 
                                class="w-full rounded-2xl border border-white/40 dark:border-zinc-600/40 bg-white/80 dark:bg-zinc-700/50 backdrop-blur-sm shadow-lg focus:shadow-xl transition-all duration-300"
                            >
                                <option value="all">All Categories</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Loading State --}}
        <div wire:loading.flex class="justify-center items-center py-16">
            <div class="relative">
                <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-yellow-400 rounded-2xl flex items-center justify-center shadow-xl animate-pulse">
                    <svg class="w-8 h-8 text-[#231F20] animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <div class="absolute -inset-2 bg-[#FFF200]/30 rounded-2xl blur-xl animate-pulse"></div>
            </div>
        </div>

        {{-- Enhanced Challenges Grid --}}
        <section aria-labelledby="challenges-grid-heading" wire:loading.remove>
            <h2 id="challenges-grid-heading" class="sr-only">Available Challenges</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @forelse($challenges as $challenge)
                    <div class="group/card relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                        {{-- Animated Gradient Background --}}
                        <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/5 via-transparent to-[#F8EBD5]/10 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        
                        {{-- Enhanced Challenge Image/Icon --}}
                        <div class="relative h-56 bg-gradient-to-br from-[#FFF200]/20 to-[#F8EBD5] dark:from-yellow-400/20 dark:to-amber-400/30 overflow-hidden">
                            {{-- Category Icon with Glow Effect --}}
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="relative text-7xl opacity-80 group-hover/card:scale-110 transition-transform duration-500">
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
                            
                            {{-- Enhanced Status Badge --}}
                            <div class="absolute top-4 right-4">
                                <span class="px-4 py-2 rounded-xl text-xs font-semibold backdrop-blur-sm {{ $this->getStatusBadgeClass($challenge->status) }} shadow-lg border border-white/20">
                                    {{ ucfirst($challenge->status) }}
                                </span>
                            </div>
                            
                            {{-- Enhanced Deadline Badge --}}
                            @if($challenge->deadline && $challenge->status === 'active')
                                <div class="absolute top-4 left-4">
                                    <span class="px-4 py-2 rounded-xl text-xs font-semibold bg-white/90 dark:bg-zinc-800/90 text-[#231F20] dark:text-zinc-100 backdrop-blur-sm shadow-lg border border-white/20">
                                        {{ $this->getDaysRemaining($challenge->deadline) }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- Enhanced Challenge Content --}}
                        <div class="relative p-8">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 line-clamp-2 group-hover/card:text-[#FFF200] dark:group-hover/card:text-yellow-400 transition-colors duration-300">
                                    {{ $challenge->title }}
                                </h3>
                            </div>
                            
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm mb-6 line-clamp-3 leading-relaxed">
                                {{ $challenge->description }}
                            </p>
                            
                            {{-- Enhanced Challenge Meta --}}
                            <div class="space-y-3 mb-6">
                                <div class="flex items-center text-sm text-[#9B9EA4] dark:text-zinc-400">
                                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 rounded-xl flex items-center justify-center mr-3">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </div>
                                    <span>by {{ $challenge->author->name }}</span>
                                </div>
                                
                                <div class="flex items-center text-sm text-[#9B9EA4] dark:text-zinc-400">
                                    <div class="w-8 h-8 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-xl flex items-center justify-center mr-3">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                    <span>{{ $challenge->submissions_count }} {{ Str::plural('submission', $challenge->submissions_count) }}</span>
                                </div>
                                
                                @if($challenge->prize_description)
                                    <div class="flex items-center text-sm text-[#9B9EA4] dark:text-zinc-400">
                                        <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-xl flex items-center justify-center mr-3">
                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                                            </svg>
                                        </div>
                                        <span class="line-clamp-1">{{ $challenge->prize_description }}</span>
                                    </div>
                                @endif
                            </div>
                            
                            {{-- Enhanced User Submission Status --}}
                            @auth
                                @if(isset($userSubmissions[$challenge->id]))
                                    <div class="mb-6 p-4 bg-blue-50/80 dark:bg-blue-900/20 rounded-2xl border border-blue-200/50 dark:border-blue-700/50 backdrop-blur-sm">
                                        <div class="flex items-center text-sm text-blue-800 dark:text-blue-400">
                                            <div class="w-6 h-6 bg-blue-500 dark:bg-blue-400 rounded-lg flex items-center justify-center mr-3">
                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </div>
                                            <span class="font-medium">You have submitted to this challenge</span>
                                        </div>
                                    </div>
                                @endif
                            @endauth
                            
                            {{-- Enhanced Action Buttons --}}
                            <div class="flex gap-3">
                                <flux:button 
                                    wire:navigate 
                                    href="{{ route('challenges.show', $challenge) }}" 
                                    variant="primary"
                                    class="flex-1 group relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#231F20] to-gray-800 dark:from-zinc-700 dark:to-zinc-800 text-white font-semibold py-3 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105"
                                >
                                    <span class="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                    <span class="relative">View Details</span>
                                </flux:button>
                                
                                @auth
                                    @if($challenge->status === 'active' && !isset($userSubmissions[$challenge->id]))
                                        <flux:button 
                                            wire:navigate 
                                            href="{{ route('challenges.submit', $challenge) }}" 
                                            variant="primary"
                                            class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#FFF200] to-yellow-400 hover:from-yellow-400 hover:to-[#FFF200] text-[#231F20] font-semibold px-6 py-3 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105"
                                        >
                                            <span class="absolute inset-0 bg-white/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                            <span class="relative">Submit</span>
                                        </flux:button>
                                    @endif
                                @endauth
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full">
                        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                            <div class="text-center py-16 relative">
                                <div class="w-24 h-24 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-xl">
                                    <div class="text-4xl">🏗️</div>
                                </div>
                                
                                <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">No Challenges Found</h3>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 mb-8 max-w-md mx-auto leading-relaxed">
                                    Try adjusting your search criteria or check back later for new challenges to showcase your innovation skills.
                                </p>
                                
                                @if(auth()->user()->hasRole(['manager', 'administrator', 'developer']))
                                    <flux:button 
                                        wire:navigate 
                                        href="{{ route('challenges.create') }}" 
                                        variant="primary"
                                        class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#FFF200] to-yellow-400 hover:from-yellow-400 hover:to-[#FFF200] text-[#231F20] font-semibold px-8 py-4 shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:scale-105"
                                    >
                                        <span class="absolute inset-0 bg-white/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                        <span class="relative">Create First Challenge</span>
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Enhanced Pagination --}}
        @if($challenges->hasPages())
            <div class="flex justify-center">
                <div class="relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-lg p-4">
                    {{ $challenges->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
