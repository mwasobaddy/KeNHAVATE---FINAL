<?php

use Livewire\Volt\Component;
use App\Models\Idea;
use App\Services\AuditService;

new class extends Component
{
    public Idea $idea;

    public function mount(Idea $idea)
    {
        $this->idea = $idea->load(['author', 'category', 'attachments', 'reviews.reviewer']);
        
        // Check if user can view this idea
        if (!$this->canViewIdea()) {
            abort(403, 'You do not have permission to view this idea.');
        }
    }

    private function canViewIdea(): bool
    {
        $user = auth()->user();
        
        // Author can always view their own idea
        if ($this->idea->author_id === $user->id) {
            return true;
        }
        
        // Admins and developers can view all ideas
        if ($user->hasAnyRole(['admin', 'developer'])) {
            return true;
        }
        
        // Reviewers can view ideas in review stages
        if ($user->hasAnyRole(['manager', 'sme', 'idea_reviewer', 'board_member'])) {
            return in_array($this->idea->current_stage, [
                'submitted', 'manager_review', 'sme_review', 'board_review', 'implementation', 'completed'
            ]);
        }
        
        // Users can view ideas in collaboration stage if collaboration is enabled
        if ($this->idea->collaboration_enabled && $this->idea->current_stage === 'collaboration') {
            return true;
        }
        
        return false;
    }

    public function canEdit(): bool
    {
        $user = auth()->user();
        
        // Only author can edit in draft stage
        return $this->idea->author_id === $user->id && $this->idea->current_stage === 'draft';
    }

    public function getStageColorAttribute(): string
    {
        return match($this->idea->current_stage) {
            'draft' => 'bg-gray-100 text-gray-800',
            'submitted' => 'bg-blue-100 text-blue-800',
            'manager_review' => 'bg-yellow-100 text-yellow-800',
            'sme_review' => 'bg-orange-100 text-orange-800',
            'collaboration' => 'bg-purple-100 text-purple-800',
            'board_review' => 'bg-indigo-100 text-indigo-800',
            'implementation' => 'bg-green-100 text-green-800',
            'completed' => 'bg-green-200 text-green-900',
            'archived' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getStageLabelAttribute(): string
    {
        return match($this->idea->current_stage) {
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'manager_review' => 'Manager Review',
            'sme_review' => 'SME Review',
            'collaboration' => 'Collaboration',
            'board_review' => 'Board Review',
            'implementation' => 'Implementation',
            'completed' => 'Completed',
            'archived' => 'Archived',
            default => 'Unknown'
        };
    }
}; ?>

<x-layouts.app :title="$idea->title">
    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 overflow-hidden">
            <div class="bg-[#F8EBD5] px-6 py-4 border-b border-[#9B9EA4]/20">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold text-[#231F20]">{{ $idea->title }}</h1>
                        <div class="flex flex-wrap items-center gap-4 mt-2">
                            <span class="text-sm text-[#9B9EA4]">
                                By {{ $idea->author->first_name }} {{ $idea->author->last_name }}
                            </span>
                            @if($idea->category)
                                <span class="text-sm text-[#9B9EA4]">
                                    Category: {{ $idea->category->name }}
                                </span>
                            @endif
                            <span class="text-sm text-[#9B9EA4]">
                                {{ $idea->created_at->format('F j, Y') }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 sm:mt-0 flex flex-col sm:flex-row gap-2">
                        <span class="px-3 py-1 text-sm rounded-full {{ $this->stageColor }}">
                            {{ $this->stageLabel }}
                        </span>
                        @if($idea->collaboration_enabled)
                            <span class="px-3 py-1 text-sm rounded-full bg-[#FFF200]/20 text-[#231F20]">
                                Collaboration Enabled
                            </span>
                        @endif
                    </div>
                </div>
                
                @if($this->canEdit())
                    <div class="mt-4">
                        <a href="{{ route('ideas.edit', $idea) }}" 
                           class="inline-flex items-center px-4 py-2 bg-[#FFF200] text-[#231F20] rounded-md hover:bg-[#FFF200]/80 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit Idea
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Content --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Content --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Description --}}
                <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                    <h2 class="text-lg font-semibold text-[#231F20] mb-4">Description</h2>
                    <div class="prose prose-sm max-w-none text-[#231F20]">
                        {!! nl2br(e($idea->description)) !!}
                    </div>
                </div>

                @if($idea->business_case)
                    {{-- Business Case --}}
                    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                        <h2 class="text-lg font-semibold text-[#231F20] mb-4">Business Case</h2>
                        <div class="prose prose-sm max-w-none text-[#231F20]">
                            {!! nl2br(e($idea->business_case)) !!}
                        </div>
                    </div>
                @endif

                @if($idea->expected_impact)
                    {{-- Expected Impact --}}
                    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                        <h2 class="text-lg font-semibold text-[#231F20] mb-4">Expected Impact</h2>
                        <div class="prose prose-sm max-w-none text-[#231F20]">
                            {!! nl2br(e($idea->expected_impact)) !!}
                        </div>
                    </div>
                @endif

                @if($idea->implementation_timeline)
                    {{-- Implementation Timeline --}}
                    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                        <h2 class="text-lg font-semibold text-[#231F20] mb-4">Implementation Timeline</h2>
                        <div class="prose prose-sm max-w-none text-[#231F20]">
                            {!! nl2br(e($idea->implementation_timeline)) !!}
                        </div>
                    </div>
                @endif

                @if($idea->resource_requirements)
                    {{-- Resource Requirements --}}
                    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                        <h2 class="text-lg font-semibold text-[#231F20] mb-4">Resource Requirements</h2>
                        <div class="prose prose-sm max-w-none text-[#231F20]">
                            {!! nl2br(e($idea->resource_requirements)) !!}
                        </div>
                    </div>
                @endif

                {{-- Attachments --}}
                @if($idea->attachments->count() > 0)
                    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                        <h2 class="text-lg font-semibold text-[#231F20] mb-4">Attachments</h2>
                        <div class="space-y-3">
                            @foreach($idea->attachments as $attachment)
                                <div class="flex items-center justify-between p-3 border border-[#9B9EA4]/20 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="p-2 bg-[#F8EBD5] rounded">
                                            @if($attachment->isImage())
                                                <svg class="w-5 h-5 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                            @else
                                                <svg class="w-5 h-5 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-[#231F20]">{{ $attachment->original_filename }}</p>
                                            <p class="text-xs text-[#9B9EA4]">{{ $attachment->human_size }}</p>
                                        </div>
                                    </div>
                                    <a href="{{ $attachment->url }}" 
                                       target="_blank"
                                       class="px-3 py-1 text-sm bg-[#FFF200] text-[#231F20] rounded hover:bg-[#FFF200]/80 transition-colors">
                                        View
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Quick Actions --}}
                <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                    <h3 class="text-lg font-semibold text-[#231F20] mb-4">Actions</h3>
                    <div class="space-y-3">
                        <a href="{{ route('ideas.index') }}" 
                           class="w-full flex items-center justify-center px-4 py-2 border border-[#9B9EA4] text-[#231F20] rounded-md hover:bg-[#F8EBD5] transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back to Ideas
                        </a>
                        
                        @if($idea->collaboration_enabled && $idea->current_stage === 'collaboration')
                            <button 
                                class="w-full flex items-center justify-center px-4 py-2 bg-[#FFF200] text-[#231F20] rounded-md hover:bg-[#FFF200]/80 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Request Collaboration
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Progress Tracker --}}
                <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                    <h3 class="text-lg font-semibold text-[#231F20] mb-4">Progress</h3>
                    <div class="space-y-3">
                        @php
                            $stages = [
                                'draft' => 'Draft',
                                'submitted' => 'Submitted',
                                'manager_review' => 'Manager Review',
                                'sme_review' => 'SME Review',
                                'collaboration' => 'Collaboration',
                                'board_review' => 'Board Review',
                                'implementation' => 'Implementation',
                                'completed' => 'Completed'
                            ];
                            $currentStageIndex = array_search($idea->current_stage, array_keys($stages));
                        @endphp
                        
                        @foreach($stages as $stage => $label)
                            @php
                                $stageIndex = array_search($stage, array_keys($stages));
                                $isCompleted = $stageIndex < $currentStageIndex;
                                $isCurrent = $stage === $idea->current_stage;
                                $isUpcoming = $stageIndex > $currentStageIndex;
                            @endphp
                            
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    @if($isCompleted)
                                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                    @elseif($isCurrent)
                                        <div class="w-8 h-8 bg-[#FFF200] rounded-full flex items-center justify-center">
                                            <div class="w-3 h-3 bg-[#231F20] rounded-full"></div>
                                        </div>
                                    @else
                                        <div class="w-8 h-8 bg-[#9B9EA4]/20 rounded-full"></div>
                                    @endif
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium {{ $isCurrent ? 'text-[#231F20]' : ($isCompleted ? 'text-green-600' : 'text-[#9B9EA4]') }}">
                                        {{ $label }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Review History --}}
                @if($idea->reviews->count() > 0)
                    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                        <h3 class="text-lg font-semibold text-[#231F20] mb-4">Review History</h3>
                        <div class="space-y-4">
                            @foreach($idea->reviews->sortByDesc('created_at') as $review)
                                <div class="border-l-4 border-[#FFF200] pl-4">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-[#231F20]">
                                                {{ $review->reviewer->first_name }} {{ $review->reviewer->last_name }}
                                            </p>
                                            <p class="text-xs text-[#9B9EA4]">{{ $review->created_at->format('M j, Y g:i A') }}</p>
                                        </div>
                                        <span class="px-2 py-1 text-xs rounded {{ $review->status === 'approved' ? 'bg-green-100 text-green-800' : ($review->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                            {{ ucfirst($review->status) }}
                                        </span>
                                    </div>
                                    @if($review->comments)
                                        <p class="text-sm text-[#9B9EA4] mt-2">{{ $review->comments }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
