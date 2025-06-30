<?php

use Livewire\Volt\Component;
use App\Models\Idea;
use App\Services\AuditService;
use Livewire\Attributes\{Layout, Title};

new #[Layout('components.layouts.app')] #[Title('View Idea')] class extends Component
{
    public Idea $idea;

    public function mount(Idea $idea)
    {
        $this->idea = $idea->load(['author', 'category', 'attachments', 'reviews.reviewer']);
        
        // Set dynamic title using SEO tools or view data
        if (class_exists('\Artesaos\SEOTools\Facades\SEOTools')) {
            \Artesaos\SEOTools\Facades\SEOTools::setTitle($idea->title . ' - KeNHAVATE Innovation Portal');
        }
        
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
        if ($user->hasAnyRole(['administrator', 'developer'])) {
            return true;
        }
        
        // Reviewers can view ideas in review stages
        if ($user->hasAnyRole(['manager', 'sme', 'board_member'])) {
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

    public function stageColor(): string
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

    public function stageLabel(): string
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

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/5 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/3 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 max-w-4xl mx-auto py-8 px-4 sm:px-6">
        {{-- Main Content Container with Glass Morphism --}}
        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
            {{-- Header with Modern Design --}}
            <div class="relative overflow-hidden p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                {{-- Gradient Overlay --}}
                <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/10 via-transparent to-[#F8EBD5]/20 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/10"></div>
                
                <div class="relative flex flex-col sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">{{ $idea->title }}</h1>
                        <div class="flex flex-wrap items-center gap-4 mt-2">
                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                                By {{ $idea->author->first_name }} {{ $idea->author->last_name }}
                            </span>
                            @if($idea->category)
                                <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                                    Category: {{ $idea->category->name }}
                                </span>
                            @endif
                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                                {{ $idea->created_at->format('F j, Y') }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 sm:mt-0 flex flex-col sm:flex-row gap-2">
                        <span class="px-3 py-1 text-sm rounded-full {{ $this->stageColor() }}">
                            {{ $this->stageLabel() }}
                        </span>
                        @if($idea->collaboration_enabled)
                            <span class="px-3 py-1 text-sm rounded-full bg-[#FFF200]/20 dark:bg-yellow-400/20 text-[#231F20] dark:text-zinc-800">
                                Collaboration Enabled
                            </span>
                        @endif
                    </div>
                </div>
                
                @if($this->canEdit())
                    <div class="mt-4">
                        <a href="{{ route('ideas.edit', $idea) }}" 
                            class="group relative inline-flex items-center px-4 py-2 bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 hover:from-[#231F20] hover:to-[#231F20] dark:hover:from-zinc-800 dark:hover:to-zinc-700 text-[#231F20] dark:text-zinc-900 hover:text-[#FFF200] dark:hover:text-yellow-400 rounded-xl shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-300">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit Idea
                            {{-- Ripple Effect --}}
                            <div class="absolute inset-0 rounded-xl overflow-hidden">
                                <div class="absolute inset-0 bg-white/20 dark:bg-yellow-400/20 scale-0 group-hover:scale-100 transition-transform duration-500 rounded-xl"></div>
                            </div>
                        </a>
                    </div>
                @endif
            </div>

            {{-- Content --}}
            <div class="p-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- Main Content --}}
                    <div class="lg:col-span-2 space-y-8">
                        {{-- Description --}}
                        <section aria-labelledby="description-heading" class="group space-y-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <h2 id="description-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Description</h2>
                            </div>
                            
                            <div class="ml-14 p-6 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl shadow-sm">
                                <div class="prose prose-sm max-w-none text-[#231F20] dark:text-zinc-300">
                                    {!! nl2br(e($idea->description)) !!}
                                </div>
                            </div>
                        </section>

                        @if($idea->business_case)
                            {{-- Business Case --}}
                            <section aria-labelledby="business-case-heading" class="group space-y-6">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <h2 id="business-case-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Business Case</h2>
                                </div>
                                
                                <div class="ml-14 p-6 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl shadow-sm">
                                    <div class="prose prose-sm max-w-none text-[#231F20] dark:text-zinc-300">
                                        {!! nl2br(e($idea->business_case)) !!}
                                    </div>
                                </div>
                            </section>
                        @endif

                        @if($idea->expected_impact)
                            {{-- Expected Impact --}}
                            <section aria-labelledby="impact-heading" class="group space-y-6">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-indigo-600 dark:from-indigo-400 dark:to-indigo-500 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                    </div>
                                    <h2 id="impact-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Expected Impact</h2>
                                </div>
                                
                                <div class="ml-14 p-6 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl shadow-sm">
                                    <div class="prose prose-sm max-w-none text-[#231F20] dark:text-zinc-300">
                                        {!! nl2br(e($idea->expected_impact)) !!}
                                    </div>
                                </div>
                            </section>
                        @endif

                        @if($idea->implementation_timeline)
                            {{-- Implementation Timeline --}}
                            <section aria-labelledby="timeline-heading" class="group space-y-6">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <h2 id="timeline-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Implementation Timeline</h2>
                                </div>
                                
                                <div class="ml-14 p-6 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl shadow-sm">
                                    <div class="prose prose-sm max-w-none text-[#231F20] dark:text-zinc-300">
                                        {!! nl2br(e($idea->implementation_timeline)) !!}
                                    </div>
                                </div>
                            </section>
                        @endif

                        @if($idea->resource_requirements)
                            {{-- Resource Requirements --}}
                            <section aria-labelledby="resources-heading" class="group space-y-6">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-gradient-to-br from-pink-500 to-pink-600 dark:from-pink-400 dark:to-pink-500 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                    </div>
                                    <h2 id="resources-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Resource Requirements</h2>
                                </div>
                                
                                <div class="ml-14 p-6 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl shadow-sm">
                                    <div class="prose prose-sm max-w-none text-[#231F20] dark:text-zinc-300">
                                        {!! nl2br(e($idea->resource_requirements)) !!}
                                    </div>
                                </div>
                            </section>
                        @endif

                        {{-- Attachments --}}
                        @if($idea->attachments && $idea->attachments->count() > 0)
                            <section aria-labelledby="attachments-heading" class="group space-y-6">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                        </svg>
                                    </div>
                                    <h2 id="attachments-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Attachments</h2>
                                </div>
                                
                                <div class="ml-14 space-y-3">
                                    @foreach($idea->attachments as $attachment)
                                        <div class="group/file flex items-center justify-between p-4 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
                                                    @if($attachment->isImage())
                                                        <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                        </svg>
                                                    @else
                                                        <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                        </svg>
                                                    @endif
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-[#231F20] dark:text-zinc-200 truncate max-w-xs">
                                                        {{ $attachment->original_filename }}
                                                    </p>
                                                    <p class="text-xs text-[#9B9EA4] dark:text-zinc-400">{{ $attachment->human_size }}</p>
                                                </div>
                                            </div>
                                            <a href="{{ $attachment->url }}" 
                                                target="_blank"
                                                class="group relative px-3 py-1.5 bg-gradient-to-r from-[#FFF200] to-[#F8EBD5]/80 dark:from-yellow-400 dark:to-amber-400/80 text-[#231F20] dark:text-zinc-900 rounded-lg hover:shadow-md transform hover:-translate-y-0.5 transition-all duration-300">
                                                View
                                                <div class="absolute inset-0 rounded-lg overflow-hidden">
                                                    <div class="absolute inset-0 bg-white/20 dark:bg-yellow-400/20 scale-0 group-hover:scale-100 transition-transform duration-500 rounded-lg"></div>
                                                </div>
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>

                    {{-- Sidebar --}}
                    <div class="space-y-8">
                        {{-- Quick Actions --}}
                        <section aria-labelledby="actions-heading" class="group space-y-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-10 h-10 bg-gradient-to-br from-teal-500 to-teal-600 dark:from-teal-400 dark:to-teal-500 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                </div>
                                <h2 id="actions-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Actions</h2>
                            </div>
                            
                            <div class="ml-14 space-y-3">
                                <a href="{{ route('ideas.index') }}" 
                                    class="group relative w-full flex items-center justify-center px-4 py-3 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 text-[#231F20] dark:text-zinc-200 rounded-xl hover:shadow-md transform hover:-translate-y-0.5 transition-all duration-300">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                    </svg>
                                    Back to Ideas
                                    <div class="absolute inset-0 rounded-xl overflow-hidden">
                                        <div class="absolute inset-0 bg-[#F8EBD5]/30 dark:bg-zinc-700/30 scale-0 group-hover:scale-100 transition-transform duration-500 rounded-xl"></div>
                                    </div>
                                </a>
                                
                                @if($idea->collaboration_enabled && $idea->current_stage === 'collaboration')
                                    <button 
                                        class="group relative w-full flex items-center justify-center px-4 py-3 bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 text-[#231F20] dark:text-zinc-900 rounded-xl hover:shadow-md transform hover:-translate-y-0.5 transition-all duration-300">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                        Request Collaboration
                                        <div class="absolute inset-0 rounded-xl overflow-hidden">
                                            <div class="absolute inset-0 bg-white/20 dark:bg-yellow-400/20 scale-0 group-hover:scale-100 transition-transform duration-500 rounded-xl"></div>
                                        </div>
                                    </button>
                                @endif
                            </div>
                        </section>

                        {{-- Progress Tracker --}}
                        <section aria-labelledby="progress-heading" class="group space-y-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 dark:from-blue-400 dark:to-indigo-500 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17l6-6-6-6"></path>
                                    </svg>
                                </div>
                                <h2 id="progress-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Progress</h2>
                            </div>
                            
                            <div class="ml-14 p-4 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl shadow-sm space-y-4">
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
                                    
                                    <div class="flex items-center group/stage hover:bg-[#F8EBD5]/10 dark:hover:bg-amber-400/5 p-2 rounded-lg transition-colors duration-200">
                                        <div class="flex-shrink-0">
                                            @if($isCompleted)
                                                <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-green-600 dark:from-green-400 dark:to-green-500 rounded-full flex items-center justify-center shadow-md">
                                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                            @elseif($isCurrent)
                                                <div class="w-8 h-8 bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-full flex items-center justify-center shadow-md p-1.5">
                                                    <div class="w-full h-full bg-[#231F20] dark:bg-zinc-900 rounded-full"></div>
                                                </div>
                                            @else
                                                <div class="w-8 h-8 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm border border-white/30 dark:border-zinc-600/30 rounded-full shadow-sm"></div>
                                            @endif
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium {{ $isCurrent ? 'text-[#231F20] dark:text-zinc-100' : ($isCompleted ? 'text-green-600 dark:text-green-400' : 'text-[#9B9EA4] dark:text-zinc-500') }}">
                                                {{ $label }}
                                            </p>
                                        </div>
                                    </div>
                                    @if(!$loop->last)
                                        <div class="ml-4 h-4 border-l border-[#9B9EA4]/20 dark:border-zinc-700/50"></div>
                                    @endif
                                @endforeach
                            </div>
                        </section>
                    </div>

                    <div class="lg:col-span-3 space-y-8">

                        {{-- Collaboration Management --}}
                        @if($idea->collaboration_enabled || auth()->user()->hasAnyRole(['administrator', 'developer', 'manager', 'sme']))
                            <section aria-labelledby="collaboration-heading" class="group space-y-6">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-gradient-to-br from-violet-500 to-violet-600 dark:from-violet-400 dark:to-violet-500 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                    <h2 id="collaboration-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Collaboration</h2>
                                </div>
                                
                                <div class="ml-14">
                                    @livewire('community.collaboration-management', ['idea' => $idea])
                                </div>
                            </section>
                        @endif

                        {{-- Comments & Discussion --}}
                        <section aria-labelledby="comments-heading" class="group space-y-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-10 h-10 bg-gradient-to-br from-cyan-500 to-cyan-600 dark:from-cyan-400 dark:to-cyan-500 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                </div>
                                <h2 id="comments-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Discussion</h2>
                            </div>
                            
                            <div class="ml-14">
                                @livewire('community.comments-section', ['commentable' => $idea])
                            </div>
                        </section>

                        {{-- Suggestions & Improvements --}}
                        <section aria-labelledby="suggestions-heading" class="group space-y-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-10 h-10 bg-gradient-to-br from-teal-500 to-teal-600 dark:from-teal-400 dark:to-teal-500 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                    </svg>
                                </div>
                                <h2 id="suggestions-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Suggestions & Improvements</h2>
                            </div>
                            
                            <div class="ml-14">
                                @livewire('community.suggestions-section', ['suggestable' => $idea])
                            </div>
                        </section>

                        {{-- Version Comparison --}}
                        @if($idea->collaboration_enabled && auth()->user()->can('collaborate', $idea))
                            <section aria-labelledby="versions-heading" class="group space-y-6">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-indigo-600 dark:from-indigo-400 dark:to-indigo-500 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                    </div>
                                    <h2 id="versions-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Version History</h2>
                                </div>
                                
                                <div class="ml-14">
                                    @livewire('community.version-comparison', ['idea' => $idea])
                                </div>
                            </section>
                        @endif

                        {{-- Review History --}}
                        @if($idea->reviews && $idea->reviews->count() > 0)
                            <section aria-labelledby="reviews-heading" class="group space-y-6">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-orange-600 dark:from-orange-400 dark:to-orange-500 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <h2 id="reviews-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Review History</h2>
                                </div>
                                
                                <div class="ml-14 space-y-4">
                                    @foreach($idea->reviews->sortByDesc('created_at') as $review)
                                        <div class="p-4 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border-l-4 border-[#FFF200] dark:border-yellow-400 rounded-r-xl shadow-sm transform transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                                            <div class="flex items-start justify-between">
                                                <div>
                                                    <p class="text-sm font-semibold text-[#231F20] dark:text-zinc-200">
                                                        {{ $review->reviewer->first_name }} {{ $review->reviewer->last_name }}
                                                    </p>
                                                    <p class="text-xs text-[#9B9EA4] dark:text-zinc-400">{{ $review->created_at->format('M j, Y g:i A') }}</p>
                                                </div>
                                                <span class="px-2.5 py-1 text-xs rounded-full {{ $review->status === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : ($review->status === 'rejected' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400') }}">
                                                    {{ ucfirst($review->status) }}
                                                </span>
                                            </div>
                                            @if($review->comments)
                                                <p class="text-sm text-[#231F20] dark:text-zinc-300 mt-3 p-2 bg-[#F8EBD5]/20 dark:bg-amber-400/5 rounded-lg">{{ $review->comments }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>