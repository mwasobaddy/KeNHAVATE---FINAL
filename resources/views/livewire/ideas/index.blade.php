<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Idea;
use App\Models\Category;

new #[Layout('components.layouts.app', title: 'Ideas')] class extends Component
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

        // If user is not admin/manager, only show their own ideas unless they're reviewers
        if (!auth()->user()->hasAnyRole(['admin', 'developer', 'manager', 'idea_reviewer', 'sme', 'board_member'])) {
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
            'can_create' => auth()->user()->hasAnyRole(['user', 'manager', 'admin', 'sme', 'developer'])
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-[#231F20]">Innovation Ideas</h1>
                <p class="text-sm text-[#9B9EA4] mt-1">Explore and manage innovative ideas</p>
            </div>
            @if($can_create)
                <div class="mt-4 sm:mt-0">
                    <a href="{{ route('ideas.create') }}" 
                        class="inline-flex items-center px-4 py-2 bg-[#FFF200] text-[#231F20] rounded-md hover:bg-[#FFF200]/80 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Submit New Idea
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Search --}}
            <div>
                <label class="block text-sm font-medium text-[#231F20] mb-2">Search</label>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search ideas..."
                    class="w-full rounded-md border-[#9B9EA4] focus:border-[#FFF200] focus:ring-[#FFF200]"
                />
            </div>

            {{-- Category Filter --}}
            <div>
                <label class="block text-sm font-medium text-[#231F20] mb-2">Category</label>
                <select wire:model.live="category_filter" class="w-full rounded-md border-[#9B9EA4] focus:border-[#FFF200] focus:ring-[#FFF200]">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Stage Filter --}}
            <div>
                <label class="block text-sm font-medium text-[#231F20] mb-2">Stage</label>
                <select wire:model.live="stage_filter" class="w-full rounded-md border-[#9B9EA4] focus:border-[#FFF200] focus:ring-[#FFF200]">
                    <option value="">All Stages</option>
                    @foreach($stages as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Actions --}}
            <div class="flex items-end">
                <button 
                    wire:click="resetFilters"
                    class="w-full px-4 py-2 border border-[#9B9EA4] text-[#231F20] rounded-md hover:bg-[#F8EBD5] transition-colors"
                >
                    Reset Filters
                </button>
            </div>
        </div>
    </div>

    {{-- Ideas Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($ideas as $idea)
            <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 overflow-hidden hover:shadow-md transition-shadow">
                {{-- Header --}}
                <div class="p-4 border-b border-[#9B9EA4]/20">
                    <div class="flex items-start justify-between">
                        <h3 class="font-semibold text-[#231F20] line-clamp-2">
                            <a href="{{ route('ideas.show', $idea) }}" class="hover:text-[#FFF200]">
                                {{ $idea->title }}
                            </a>
                        </h3>
                        <span class="ml-2 px-2 py-1 text-xs rounded-full bg-[#F8EBD5] text-[#231F20]">
                            {{ $stages[$idea->current_stage] }}
                        </span>
                    </div>
                    @if($idea->category)
                        <p class="text-xs text-[#9B9EA4] mt-1">{{ $idea->category->name }}</p>
                    @endif
                </div>

                {{-- Content --}}
                <div class="p-4">
                    <p class="text-sm text-[#9B9EA4] line-clamp-3">{{ $idea->description }}</p>
                </div>

                {{-- Footer --}}
                <div class="p-4 border-t border-[#9B9EA4]/20 bg-[#F8EBD5]/50">
                    <div class="flex items-center justify-between text-xs text-[#9B9EA4]">
                        <span>By {{ $idea->author->first_name }} {{ $idea->author->last_name }}</span>
                        <span>{{ $idea->created_at->format('M j, Y') }}</span>
                    </div>
                    @if($idea->collaboration_enabled)
                        <div class="mt-2">
                            <span class="inline-flex items-center px-2 py-1 text-xs bg-[#FFF200]/20 text-[#231F20] rounded">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Collaboration Enabled
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-[#231F20]">No ideas found</h3>
                    <p class="mt-1 text-sm text-[#9B9EA4]">
                        @if($search || $category_filter || $stage_filter)
                            Try adjusting your filters to see more results.
                        @else
                            Get started by submitting your first innovative idea.
                        @endif
                    </p>
                    @if($can_create && !($search || $category_filter || $stage_filter))
                        <div class="mt-6">
                            <a href="{{ route('ideas.create') }}" 
                                class="inline-flex items-center px-4 py-2 bg-[#FFF200] text-[#231F20] rounded-md hover:bg-[#FFF200]/80 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Submit Your First Idea
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($ideas->hasPages())
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-4">
            {{ $ideas->links() }}
        </div>
    @endif
</div>
