<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Idea;
use App\Models\Category;
use Livewire\Attributes\{Layout, Title};

new #[Layout('components.layouts.app')] #[Title('Ideas')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $category_filter = '';
    public $stage_filter = '';
    public $sort_by = 'created_at';
    public $sort_direction = 'desc';

    public $categories;

    public function mount()
    {
        $this->categories = Category::active()->ordered()->get();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter()
    {
        $this->resetPage();
    }

    public function updatedStageFilter()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sort_by === $field) {
            $this->sort_direction = $this->sort_direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort_by = $field;
            $this->sort_direction = 'asc';
        }
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->reset(['search', 'category_filter', 'stage_filter']);
        $this->resetPage();
    }

    public function with()
    {
        $query = Idea::with(['author', 'category'])
            ->when($this->search, function ($q) {
                $q->where(function ($subQ) {
                    $subQ->where('title', 'like', '%' . $this->search . '%')
                         ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->category_filter, function ($q) {
                $q->where('category_id', $this->category_filter);
            })
            ->when($this->stage_filter, function ($q) {
                $q->where('current_stage', $this->stage_filter);
            });

        // If user is not administrator/manager, only show their own ideas unless they're reviewers
        if (!auth()->user()->hasAnyRole(['administrator', 'developer', 'manager', 'sme', 'board_member'])) {
            $query->where('author_id', auth()->id());
        }

        $ideas = $query->orderBy($this->sort_by, $this->sort_direction)
                      ->paginate(12);

        $stages = [
            'draft' => 'Draft',
            'submitted' => 'Submitted', 
            'manager_review' => 'Manager Review',
            'sme_review' => 'SME Review',
            'collaboration' => 'Collaboration',
            'board_review' => 'Board Review',
            'implementation' => 'Implementation',
            'completed' => 'Completed',
            'archived' => 'Archived'
        ];

        return [
            'ideas' => $ideas,
            'stages' => $stages,
            'can_create' => auth()->user()->hasAnyRole(['user', 'manager', 'administrator', 'sme', 'developer'])
        ];
    }
}; ?>

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/5 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/3 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 max-w-7xl mx-auto space-y-6 p-6">
        {{-- Enhanced Header with Glass Morphism --}}
        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
            {{-- Gradient Overlay --}}
            <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/10 via-transparent to-[#F8EBD5]/20 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/10"></div>
            
            <div class="relative p-8 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-[#231F20] dark:text-zinc-100">Innovation Ideas</h1>
                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Explore and manage innovative ideas for Kenya's highway infrastructure</p>
                    </div>
                </div>
                
                @if($can_create)
                    <div class="mt-4 sm:mt-0">
                        <a href="{{ route('ideas.create') }}" 
                           class="group flex items-center px-5 py-3 bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 hover:from-[#231F20] hover:to-[#231F20] dark:hover:from-zinc-800 dark:hover:to-zinc-700 text-[#231F20] dark:text-zinc-900 hover:text-[#FFF200] dark:hover:text-yellow-400 font-bold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            <span>Submit New Idea</span>
                            <svg class="ml-2 w-5 h-5 transform group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Enhanced Filters with Glass Morphism --}}
        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-purple-500/5 dark:from-blue-400/10 dark:via-transparent dark:to-purple-500/10"></div>
            
            <div class="relative p-6 md:p-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    {{-- Search --}}
                    <div class="relative">
                        <label class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">Search Ideas</label>
                        <div class="relative">
                            <input 
                                type="text" 
                                wire:model.live.debounce.300ms="search"
                                placeholder="Search by title or description..."
                                class="w-full rounded-xl border-white/20 dark:border-zinc-600 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:border-[#FFF200] focus:ring-[#FFF200] dark:focus:border-yellow-400 dark:focus:ring-yellow-400 pl-10 pr-4 py-3 shadow-sm"
                            />
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Category Filter --}}
                    <div>
                        <label class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">Category</label>
                        <div class="relative">
                            <select wire:model.live="category_filter" class="w-full rounded-xl border-white/20 dark:border-zinc-600 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:border-[#FFF200] focus:ring-[#FFF200] dark:focus:border-yellow-400 dark:focus:ring-yellow-400 pl-10 pr-4 py-3 shadow-sm appearance-none">
                                <option value="">All Categories</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                                </svg>
                            </div>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Stage Filter --}}
                    <div>
                        <label class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">Stage</label>
                        <div class="relative">
                            <select wire:model.live="stage_filter" class="w-full rounded-xl border-white/20 dark:border-zinc-600 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:border-[#FFF200] focus:ring-[#FFF200] dark:focus:border-yellow-400 dark:focus:ring-yellow-400 pl-10 pr-4 py-3 shadow-sm appearance-none">
                                <option value="">All Stages</option>
                                @foreach($stages as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Reset Filters --}}
                    <div class="flex items-end">
                        <button 
                            wire:click="resetFilters"
                            class="group w-full flex justify-center items-center px-5 py-3 border border-[#9B9EA4]/30 dark:border-zinc-600/30 text-[#231F20] dark:text-zinc-200 bg-white/30 dark:bg-zinc-800/30 backdrop-blur-sm rounded-xl hover:bg-[#F8EBD5]/50 dark:hover:bg-amber-900/20 transition-all duration-300 transform hover:-translate-y-1 shadow hover:shadow-md"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Reset Filters
                            <span class="absolute right-4 opacity-0 group-hover:opacity-100 transform translate-x-2 group-hover:translate-x-0 transition-all duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Enhanced Ideas Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($ideas as $idea)
                <div class="group/idea relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    {{-- Status Indicator Strip --}}
                    <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-gradient-to-b 
                        @if($idea->current_stage === 'draft') from-gray-400 to-gray-500 dark:from-gray-400 dark:to-gray-500
                        @elseif($idea->current_stage === 'submitted') from-blue-400 to-blue-500 dark:from-blue-400 dark:to-blue-500
                        @elseif(in_array($idea->current_stage, ['manager_review', 'sme_review'])) from-amber-400 to-amber-500 dark:from-amber-400 dark:to-amber-500
                        @elseif($idea->current_stage === 'collaboration') from-purple-400 to-purple-500 dark:from-purple-400 dark:to-purple-500
                        @elseif($idea->current_stage === 'board_review') from-pink-400 to-pink-500 dark:from-pink-400 dark:to-pink-500
                        @elseif($idea->current_stage === 'implementation') from-indigo-400 to-indigo-500 dark:from-indigo-400 dark:to-indigo-500
                        @elseif($idea->current_stage === 'completed') from-emerald-400 to-emerald-500 dark:from-emerald-400 dark:to-emerald-500
                        @else from-gray-400 to-gray-500 dark:from-gray-400 dark:to-gray-500
                        @endif"></div>
                    
                    {{-- Header --}}
                    <div class="p-6 border-b border-white/10 dark:border-zinc-700/30">
                        <div class="flex items-start justify-between">
                            <h3 class="font-bold text-xl text-[#231F20] dark:text-zinc-100 group-hover/idea:text-[#FFF200] dark:group-hover/idea:text-yellow-400 transition-colors duration-300 line-clamp-2">
                                <a href="{{ route('ideas.show', $idea) }}" class="hover:text-[#FFF200] dark:hover:text-yellow-400">
                                    {{ $idea->title }}
                                </a>
                            </h3>
                            
                            {{-- Enhanced Status Badge --}}
                            <span class="ml-4 shrink-0 inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold 
                                @if($idea->current_stage === 'draft') bg-gray-100/70 dark:bg-gray-700/50 text-gray-700 dark:text-gray-300 border border-gray-200/50 dark:border-gray-600/30
                                @elseif($idea->current_stage === 'submitted') bg-blue-50/70 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border border-blue-200/50 dark:border-blue-600/30
                                @elseif(in_array($idea->current_stage, ['manager_review', 'sme_review'])) bg-amber-50/70 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border border-amber-200/50 dark:border-amber-600/30
                                @elseif($idea->current_stage === 'collaboration') bg-purple-50/70 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 border border-purple-200/50 dark:border-purple-600/30
                                @elseif($idea->current_stage === 'board_review') bg-pink-50/70 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300 border border-pink-200/50 dark:border-pink-600/30
                                @elseif($idea->current_stage === 'implementation') bg-indigo-50/70 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border border-indigo-200/50 dark:border-indigo-600/30
                                @elseif($idea->current_stage === 'completed') bg-emerald-50/70 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200/50 dark:border-emerald-600/30
                                @else bg-gray-100/70 dark:bg-gray-700/50 text-gray-700 dark:text-gray-300 border border-gray-200/50 dark:border-gray-600/30
                                @endif backdrop-blur-sm">
                                {{-- Status Icon --}}
                                @if($idea->current_stage === 'completed')
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                @elseif(in_array($idea->current_stage, ['manager_review', 'sme_review', 'board_review']))
                                    <div class="w-2 h-2 bg-current rounded-full mr-1 animate-pulse"></div>
                                @elseif($idea->current_stage === 'implementation')
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                    </svg>
                                @elseif($idea->current_stage === 'collaboration')
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                @endif
                                {{ $stages[$idea->current_stage] }}
                            </span>
                        </div>
                        
                        @if($idea->category)
                            <div class="mt-2 inline-flex items-center text-xs font-medium text-[#9B9EA4] dark:text-zinc-400 bg-[#F8EBD5]/30 dark:bg-amber-900/20 px-3 py-1.5 rounded-full backdrop-blur-sm">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                                {{ $idea->category->name }}
                            </div>
                        @endif
                    </div>

                    {{-- Content --}}
                    <div class="p-6">
                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed line-clamp-3">
                            {{ $idea->description }}
                        </p>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-between p-6 border-t border-white/10 dark:border-zinc-700/30 bg-[#F8EBD5]/20 dark:bg-amber-900/10 backdrop-blur-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-8 h-8 bg-gradient-to-br from-[#FFF200]/70 to-[#F8EBD5]/70 dark:from-yellow-400/70 dark:to-amber-400/70 rounded-full flex items-center justify-center shadow text-xs font-bold text-[#231F20] dark:text-zinc-900">
                                {{ $idea->author->initials() }}
                            </div>
                            <div>
                                <p class="text-xs text-[#231F20] dark:text-zinc-200 font-medium">
                                    {{ $idea->author->first_name }} {{ $idea->author->last_name }}
                                </p>
                                <p class="text-xs text-[#9B9EA4] dark:text-zinc-400">
                                    {{ $idea->created_at->format('M j, Y') }}
                                </p>
                            </div>
                        </div>
                        
                        @if($idea->collaboration_enabled)
                            <div class="inline-flex items-center px-3 py-1.5 text-xs font-medium bg-[#FFF200]/20 dark:bg-yellow-400/20 text-[#231F20] dark:text-zinc-100 rounded-full backdrop-blur-sm">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Collaboration
                            </div>
                        @endif
                        
                        <a href="{{ route('ideas.show', $idea) }}" class="inline-flex items-center space-x-1 text-[#231F20] dark:text-zinc-100 font-semibold text-sm hover:text-[#FFF200] dark:hover:text-yellow-400 transition-colors duration-300 group/link">
                            <span>Details</span>
                            <svg class="w-4 h-4 transform group-hover/link:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            @empty
                {{-- Enhanced Empty State --}}
                <div class="col-span-full">
                    <div class="text-center relative py-16">
                        {{-- Floating Elements --}}
                        <div class="absolute inset-0 flex items-center justify-center opacity-5 dark:opacity-10">
                            <svg class="w-64 h-64 text-[#FFF200] dark:text-yellow-400" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                        </div>
                        
                        <div class="relative z-10">
                            <div class="w-20 h-20 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                                <svg class="w-10 h-10 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                            
                            <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">No Ideas Found</h3>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 mb-6 max-w-md mx-auto leading-relaxed">
                                @if($search || $category_filter || $stage_filter)
                                    We couldn't find any ideas matching your current filters.
                                    Try adjusting your search criteria to see more results.
                                @else
                                    Your journey to transforming Kenya's highway infrastructure starts with a single idea. 
                                    What challenge will you solve today?
                                @endif
                            </p>
                            
                            @if($search || $category_filter || $stage_filter)
                                <button 
                                    wire:click="resetFilters"
                                    class="inline-flex items-center space-x-2 px-5 py-2 bg-[#231F20] dark:bg-zinc-700 text-[#FFF200] dark:text-yellow-400 font-medium rounded-xl shadow-md hover:shadow-lg transition-all duration-300">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <span>Reset Filters</span>
                                </button>
                            @elseif($can_create)
                                <a href="{{ route('ideas.create') }}" 
                                   class="group inline-flex items-center px-8 py-4 bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 hover:from-[#231F20] hover:to-[#231F20] dark:hover:from-zinc-800 dark:hover:to-zinc-700 text-[#231F20] dark:text-zinc-900 hover:text-[#FFF200] dark:hover:text-yellow-400 font-bold rounded-2xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    <span>Submit Your First Idea</span>
                                    <svg class="ml-2 w-5 h-5 transform group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforelse
        </div>

        {{-- Enhanced Pagination --}}
        @if($ideas->hasPages())
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl p-6">
                <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/5 via-transparent to-[#F8EBD5]/10 dark:from-yellow-400/5 dark:via-transparent dark:to-amber-400/10"></div>
                <div class="relative">
                    {{ $ideas->links() }}
                </div>
            </div>
        @endif
        
        {{-- Enhanced Floating Action Button with Advanced Interactions --}}
        @if($can_create)
            <div class="fixed bottom-6 right-6 z-50 group/fab">
                {{-- Main FAB Button --}}
                <a href="{{ route('ideas.create') }}" 
                   class="group relative w-16 h-16 bg-gradient-to-br from-[#FFF200] via-[#F8EBD5] to-[#FFF200] dark:from-yellow-400 dark:via-amber-400 dark:to-yellow-400 hover:from-[#231F20] hover:to-[#231F20] dark:hover:from-zinc-800 dark:hover:to-zinc-700 text-[#231F20] dark:text-zinc-900 hover:text-[#FFF200] dark:hover:text-yellow-400 rounded-2xl shadow-2xl hover:shadow-3xl flex items-center justify-center transition-all duration-500 ease-out transform hover:scale-110 hover:-translate-y-2">
                    
                    {{-- Glow Effect --}}
                    <div class="absolute -inset-1 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl blur-lg opacity-60 group-hover:opacity-100 transition-opacity duration-500"></div>
                    
                    {{-- Icon --}}
                    <div class="relative z-10">
                        <svg class="w-7 h-7 transform group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                        </svg>
                    </div>
                    
                    {{-- Ripple Effect --}}
                    <div class="absolute inset-0 rounded-2xl overflow-hidden">
                        <div class="absolute inset-0 bg-white/20 dark:bg-yellow-400/20 scale-0 group-hover:scale-100 transition-transform duration-500 rounded-2xl"></div>
                    </div>
                </a>
                
                {{-- Enhanced Tooltip --}}
                <div class="absolute right-20 top-1/2 transform -translate-y-1/2 opacity-0 group-hover/fab:opacity-100 transition-all duration-300 translate-x-2 group-hover/fab:translate-x-0 pointer-events-none">
                    <div class="relative">
                        {{-- Tooltip Background --}}
                        <div class="bg-[#231F20] dark:bg-zinc-800 text-[#FFF200] dark:text-yellow-400 px-4 py-2 rounded-xl shadow-xl backdrop-blur-sm text-sm font-semibold whitespace-nowrap">
                            Submit New Idea
                        </div>
                        
                        {{-- Tooltip Arrow --}}
                        <div class="absolute top-1/2 -right-1 transform -translate-y-1/2 w-2 h-2 bg-[#231F20] dark:bg-zinc-800 rotate-45"></div>
                        
                        {{-- Tooltip Glow --}}
                        <div class="absolute inset-0 bg-[#231F20] dark:bg-zinc-800 rounded-xl blur-md opacity-50 -z-10"></div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
