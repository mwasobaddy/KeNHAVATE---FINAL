<?php

use Livewire\Volt\Component;
use App\Models\Suggestion;
use App\Models\SuggestionVote;
use App\Models\Idea;

new class extends Component {
    public $suggestionable;
    public $suggestionableType;
    public $suggestionableId;
    public $newSuggestion = '';
    public $newSuggestionType = 'improvement';
    public $suggestions = [];
    public $filterStatus = 'all';
    public $showForm = false;
    public $perPage = 10;
    public $showAll = false;

    protected $suggestionTypes = [
        'improvement' => 'Improvement',
        'feature' => 'New Feature',
        'technical' => 'Technical Enhancement',
        'ui_ux' => 'UI/UX Enhancement',
        'performance' => 'Performance Optimization',
        'security' => 'Security Enhancement',
    ];

    public function mount($suggestionable = null, $suggestionableType = null, $suggestionableId = null)
    {
        if ($suggestionable) {
            $this->suggestionable = $suggestionable;
            $this->suggestionableType = get_class($suggestionable);
            $this->suggestionableId = $suggestionable->id;
        } else {
            $this->suggestionableType = $suggestionableType;
            $this->suggestionableId = $suggestionableId;
        }
        
        $this->loadSuggestions();
    }

    public function loadSuggestions()
    {
        $query = Suggestion::with(['author', 'votes'])
            ->where('suggestionable_type', $this->suggestionableType)
            ->where('suggestionable_id', $this->suggestionableId);

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        $query->orderBy('created_at', 'desc');

        $this->suggestions = $this->showAll 
            ? $query->get()
            : $query->take($this->perPage)->get();
    }

    public function addSuggestion()
    {
        $this->validate([
            'newSuggestion' => 'required|string|min:10|max:2000',
            'newSuggestionType' => 'required|in:' . implode(',', array_keys($this->suggestionTypes)),
        ]);

        $suggestion = Suggestion::create([
            'content' => $this->newSuggestion,
            'suggestion_type' => $this->newSuggestionType,
            'author_id' => auth()->id(),
            'suggestionable_type' => $this->suggestionableType,
            'suggestionable_id' => $this->suggestionableId,
            'status' => 'pending',
        ]);

        // Create audit log
        app('audit')->log('suggestion_created', 'Suggestion', $suggestion->id, null, [
            'content' => $this->newSuggestion,
            'suggestion_type' => $this->newSuggestionType,
            'suggestionable_type' => $this->suggestionableType,
            'suggestionable_id' => $this->suggestionableId,
        ]);

        // Notify the idea author or relevant stakeholders
        $this->notifyStakeholders($suggestion);

        $this->reset(['newSuggestion', 'newSuggestionType', 'showForm']);
        $this->loadSuggestions();
        
        session()->flash('message', 'Suggestion submitted successfully!');
    }

    public function voteSuggestion($suggestionId, $voteType)
    {
        $suggestion = Suggestion::findOrFail($suggestionId);
        
        // Check if user already voted
        $existingVote = SuggestionVote::where('suggestion_id', $suggestionId)
            ->where('user_id', auth()->id())
            ->first();

        if ($existingVote) {
            if ($existingVote->vote_type === $voteType) {
                // Remove vote if clicking same vote type
                $existingVote->delete();
                $this->updateVoteCounts($suggestion);
                return;
            } else {
                // Update vote type
                $existingVote->update(['vote_type' => $voteType]);
            }
        } else {
            // Create new vote
            SuggestionVote::create([
                'suggestion_id' => $suggestionId,
                'user_id' => auth()->id(),
                'vote_type' => $voteType,
            ]);
        }

        $this->updateVoteCounts($suggestion);
        $this->loadSuggestions();
    }

    private function updateVoteCounts($suggestion)
    {
        $upvotes = $suggestion->votes()->where('vote_type', 'upvote')->count();
        $downvotes = $suggestion->votes()->where('vote_type', 'downvote')->count();
        
        $suggestion->update([
            'upvotes_count' => $upvotes,
            'downvotes_count' => $downvotes,
        ]);
    }

    public function updateSuggestionStatus($suggestionId, $status, $implementationNote = null)
    {
        // Only idea authors, managers, SMEs, and admins can update status
        if (!$this->canUpdateStatus()) {
            abort(403);
        }

        $suggestion = Suggestion::findOrFail($suggestionId);
        $oldStatus = $suggestion->status;

        $suggestion->update([
            'status' => $status,
            'implementation_notes' => $implementationNote,
        ]);

        // Create audit log
        app('audit')->log('suggestion_status_updated', 'Suggestion', $suggestion->id, 
            ['status' => $oldStatus], 
            ['status' => $status, 'implementation_notes' => $implementationNote]
        );

        // Notify suggestion author
        $this->notifyAuthor($suggestion, $status);

        $this->loadSuggestions();
        
        session()->flash('message', 'Suggestion status updated successfully!');
    }

    private function canUpdateStatus()
    {
        // Check if user is the idea author
        if ($this->suggestionableType === 'App\\Models\\Idea') {
            $idea = \App\Models\Idea::find($this->suggestionableId);
            if ($idea && $idea->author_id === auth()->id()) {
                return true;
            }
        }

        // Check if user has appropriate role
        return auth()->user()->hasAnyRole(['manager', 'sme', 'administrator', 'developer']);
    }

    private function notifyStakeholders($suggestion)
    {
        // Implementation for notifying relevant stakeholders
        // This would integrate with the notification system
    }

    private function notifyAuthor($suggestion, $newStatus)
    {
        // Implementation for notifying suggestion author of status change
        // This would integrate with the notification system
    }

    public function setFilterStatus($status)
    {
        $this->filterStatus = $status;
        $this->loadSuggestions();
    }

    public function toggleForm()
    {
        $this->showForm = !$this->showForm;
        if (!$this->showForm) {
            $this->reset(['newSuggestion', 'newSuggestionType']);
        }
    }

    public function showAllSuggestions()
    {
        $this->showAll = true;
        $this->loadSuggestions();
    }

    public function getSuggestionTypes()
    {
        return $this->suggestionTypes;
    }

    public function getStatusBadgeClass($status)
    {
        return match($status) {
            'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'accepted' => 'bg-green-100 text-green-800 border-green-200',
            'rejected' => 'bg-red-100 text-red-800 border-red-200',
            'implemented' => 'bg-blue-100 text-blue-800 border-blue-200',
            default => 'bg-gray-100 text-gray-800 border-gray-200',
        };
    }

    public function getTypeBadgeClass($type)
    {
        return match($type) {
            'improvement' => 'bg-purple-100 text-purple-800',
            'feature' => 'bg-indigo-100 text-indigo-800',
            'technical' => 'bg-orange-100 text-orange-800',
            'ui_ux' => 'bg-pink-100 text-pink-800',
            'performance' => 'bg-green-100 text-green-800',
            'security' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}; ?>

{{-- Enhanced Suggestions Section with Glass Morphism & Modern UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-10 left-10 w-64 h-64 bg-[#FFF200]/30 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-10 right-10 w-80 h-80 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/3 left-1/2 w-48 h-48 bg-[#FFF200]/40 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 space-y-8">
        {{-- Enhanced Header Section --}}
        <section aria-labelledby="suggestions-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Gradient Background Overlay --}}
                <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/5 via-transparent to-[#F8EBD5]/10 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/20"></div>
                
                <div class="relative z-10 p-8">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-6 lg:space-y-0">
                        {{-- Header with Icon and Count --}}
                        <div class="flex items-center space-x-4">
                            <div class="w-14 h-14 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 id="suggestions-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">
                                    Suggestions & Improvements
                                </h3>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">
                                    {{ $suggestions->count() }} community suggestions
                                </p>
                            </div>
                        </div>

                        {{-- Enhanced Filter Buttons --}}
                        <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-4 sm:space-y-0 sm:space-x-4">
                            <div class="flex flex-wrap gap-2">
                                @foreach(['all', 'pending', 'accepted', 'rejected', 'implemented'] as $status)
                                    <button
                                        wire:click="setFilterStatus('{{ $status }}')"
                                        class="group relative overflow-hidden px-4 py-2 text-sm font-medium rounded-xl transition-all duration-300 transform hover:-translate-y-0.5 {{ $filterStatus === $status 
                                            ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-lg' 
                                            : 'bg-white/60 dark:bg-zinc-700/60 text-[#231F20] dark:text-zinc-100 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-md' }}"
                                    >
                                        <span class="relative z-10">{{ ucfirst($status) }}</span>
                                        @if($filterStatus !== $status)
                                            <div class="absolute inset-0 bg-gradient-to-r from-blue-500/10 to-blue-600/10 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                        @endif
                                    </button>
                                @endforeach
                            </div>

                            <div class="flex items-center space-x-3">
                                @if(!$showAll && $suggestions->count() >= $perPage)
                                    <button 
                                        wire:click="showAllSuggestions"
                                        class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors duration-200 flex items-center space-x-1"
                                    >
                                        <span>Show all suggestions</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                        </svg>
                                    </button>
                                @endif
                                
                                @auth
                                    <button
                                        wire:click="toggleForm"
                                        class="group relative overflow-hidden px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 text-white rounded-xl hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 font-medium text-sm"
                                    >
                                        <span class="absolute inset-0 bg-gradient-to-r from-emerald-600 to-emerald-700 dark:from-emerald-500 dark:to-emerald-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                        <span class="relative z-10 flex items-center space-x-2">
                                            @if($showForm)
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                                <span>Cancel</span>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                </svg>
                                                <span>Add Suggestion</span>
                                            @endif
                                        </span>
                                    </button>
                                @endauth
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Add New Suggestion Form --}}
        @auth
            @if($showForm)
                <section aria-labelledby="new-suggestion-heading" class="group">
                    <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                        {{-- Animated Form Background --}}
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-emerald-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20"></div>
                        
                        <div class="relative z-10 p-8">
                            {{-- Form Header --}}
                            <div class="flex items-center space-x-4 mb-6">
                                <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 id="new-suggestion-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Submit a Suggestion</h4>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Share your ideas for improvement</p>
                                </div>
                            </div>
                            
                            <form wire:submit.prevent="addSuggestion" class="space-y-6">
                                {{-- Suggestion Type Selection --}}
                                <div class="group/field">
                                    <label for="suggestionType" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-3">
                                        Suggestion Type
                                    </label>
                                    <div class="relative">
                                        <select
                                            wire:model="newSuggestionType"
                                            id="suggestionType"
                                            class="w-full px-4 py-4 border border-white/40 dark:border-zinc-600/40 rounded-xl focus:ring-2 focus:ring-emerald-500 dark:focus:ring-emerald-400 focus:border-transparent bg-white/80 dark:bg-zinc-800/80 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 font-medium transition-all duration-300 appearance-none"
                                        >
                                            @foreach($this->getSuggestionTypes() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                            <svg class="w-5 h-5 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    </div>
                                    @error('newSuggestionType') 
                                        <p class="text-red-500 dark:text-red-400 text-sm mt-2 flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span>{{ $message }}</span>
                                        </p> 
                                    @enderror
                                </div>
                                
                                {{-- Suggestion Content --}}
                                <div class="group/field">
                                    <label for="suggestionContent" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-3">
                                        Suggestion Details
                                    </label>
                                    <div class="relative">
                                        <textarea
                                            wire:model="newSuggestion"
                                            id="suggestionContent"
                                            rows="6"
                                            class="w-full px-4 py-4 border border-white/40 dark:border-zinc-600/40 rounded-xl focus:ring-2 focus:ring-emerald-500 dark:focus:ring-emerald-400 focus:border-transparent resize-none bg-white/80 dark:bg-zinc-800/80 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 font-medium transition-all duration-300"
                                            placeholder="Describe your suggestion in detail. What specific improvement or feature are you proposing? How would it benefit the idea?"
                                        ></textarea>
                                        {{-- Character count overlay --}}
                                        <div class="absolute bottom-3 right-3 text-xs text-[#9B9EA4] dark:text-zinc-400 bg-white/80 dark:bg-zinc-700/80 backdrop-blur-sm px-2 py-1 rounded-lg">
                                            {{ 2000 - strlen($newSuggestion) }} left
                                        </div>
                                    </div>
                                    @error('newSuggestion') 
                                        <p class="text-red-500 dark:text-red-400 text-sm mt-2 flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span>{{ $message }}</span>
                                        </p> 
                                    @enderror
                                </div>
                                
                                {{-- Form Actions --}}
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-4 sm:space-y-0 pt-4">
                                    <div class="text-sm text-[#9B9EA4] dark:text-zinc-400 flex items-center space-x-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span>Minimum 10 characters required</span>
                                    </div>
                                    
                                    <div class="flex space-x-3">
                                        <button
                                            type="button"
                                            wire:click="toggleForm"
                                            class="px-6 py-3 text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-100 transition-colors duration-200 font-medium"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            class="group relative overflow-hidden px-8 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 text-white rounded-xl hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 font-medium {{ strlen($newSuggestion) < 10 ? 'opacity-50 cursor-not-allowed' : '' }}"
                                            {{ strlen($newSuggestion) < 10 ? 'disabled' : '' }}
                                        >
                                            <span class="absolute inset-0 bg-gradient-to-r from-emerald-600 to-emerald-700 dark:from-emerald-500 dark:to-emerald-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                            <span class="relative z-10 flex items-center space-x-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                                </svg>
                                                <span>Submit Suggestion</span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            @endif
        @endauth

        {{-- Enhanced Suggestions List --}}
        <section aria-labelledby="suggestions-list-heading" class="space-y-6">
            <h3 id="suggestions-list-heading" class="sr-only">Suggestions List</h3>
            
            @forelse($suggestions as $suggestion)
                <div class="group/suggestion relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-500">
                    {{-- Suggestion Background Gradient --}}
                    <div class="absolute inset-0 bg-gradient-to-br from-white/10 via-transparent to-{{ $this->getTypeBadgeClass($suggestion->suggestion_type) }}/5 opacity-0 group-hover/suggestion:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative z-10 p-8">
                        {{-- Enhanced Suggestion Header --}}
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between space-y-4 lg:space-y-0 mb-6">
                            <div class="flex items-center space-x-4">
                                {{-- Enhanced User Avatar --}}
                                <div class="relative">
                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 rounded-2xl flex items-center justify-center text-white font-bold text-lg shadow-lg">
                                        {{ $suggestion->author->initials() }}
                                    </div>
                                    <div class="absolute -inset-1 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-sm opacity-0 group-hover/suggestion:opacity-100 transition-opacity duration-500"></div>
                                </div>
                                
                                <div>
                                    <h4 class="font-bold text-lg text-[#231F20] dark:text-zinc-100">{{ $suggestion->author->name }}</h4>
                                    <div class="flex flex-wrap items-center gap-3 mt-2">
                                        <div class="flex items-center space-x-1 text-sm text-[#9B9EA4] dark:text-zinc-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span>{{ $suggestion->created_at->diffForHumans() }}</span>
                                        </div>
                                        <span class="px-3 py-1.5 text-xs font-semibold rounded-xl {{ $this->getTypeBadgeClass($suggestion->suggestion_type) }} border">
                                            {{ $this->getSuggestionTypes()[$suggestion->suggestion_type] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Enhanced Status Badge --}}
                            <div class="flex items-center">
                                <span class="px-4 py-2 text-sm font-semibold rounded-xl border-2 {{ $this->getStatusBadgeClass($suggestion->status) }} shadow-sm">
                                    {{ ucfirst($suggestion->status) }}
                                </span>
                            </div>
                        </div>

                        {{-- Enhanced Suggestion Content --}}
                        <div class="mb-6">
                            <div class="prose prose-lg max-w-none">
                                <p class="text-[#231F20] dark:text-zinc-100 leading-relaxed font-medium">{{ $suggestion->content }}</p>
                            </div>
                            
                            @if($suggestion->implementation_notes)
                                <div class="mt-6 relative overflow-hidden rounded-2xl bg-blue-50/80 dark:bg-blue-900/30 backdrop-blur-sm border border-blue-200/50 dark:border-blue-700/50 p-5">
                                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-blue-600"></div>
                                    <div class="flex items-start space-x-3">
                                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-blue-800 dark:text-blue-200 mb-2">Implementation Notes</p>
                                            <p class="text-sm text-blue-700 dark:text-blue-300 leading-relaxed">{{ $suggestion->implementation_notes }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Enhanced Suggestion Actions --}}
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                            @auth
                                {{-- Enhanced Voting Section --}}
                                <div class="flex items-center space-x-6">
                                    <div class="flex items-center space-x-3">
                                        <button
                                            wire:click="voteSuggestion({{ $suggestion->id }}, 'upvote')"
                                            class="group/vote flex items-center space-x-2 px-4 py-2 rounded-xl transition-all duration-300 {{ auth()->user()->hasVotedOnSuggestion($suggestion) && auth()->user()->getSuggestionVote($suggestion)?->vote_type === 'upvote' 
                                                ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-700' 
                                                : 'text-[#9B9EA4] dark:text-zinc-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20' }}"
                                        >
                                            <svg class="w-5 h-5 transform group-hover/vote:-translate-y-0.5 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                            </svg>
                                            <span class="font-semibold">{{ $suggestion->upvotes_count }}</span>
                                        </button>
                                        
                                        <button
                                            wire:click="voteSuggestion({{ $suggestion->id }}, 'downvote')"
                                            class="group/vote flex items-center space-x-2 px-4 py-2 rounded-xl transition-all duration-300 {{ auth()->user()->hasVotedOnSuggestion($suggestion) && auth()->user()->getSuggestionVote($suggestion)?->vote_type === 'downvote' 
                                                ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-700' 
                                                : 'text-[#9B9EA4] dark:text-zinc-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20' }}"
                                        >
                                            <svg class="w-5 h-5 transform group-hover/vote:translate-y-0.5 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                            <span class="font-semibold">{{ $suggestion->downvotes_count }}</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Enhanced Status Management --}}
                                @if($this->canUpdateStatus())
                                    <div class="flex items-center space-x-3">
                                        @if($suggestion->status === 'pending')
                                            <button
                                                wire:click="updateSuggestionStatus({{ $suggestion->id }}, 'accepted')"
                                                wire:confirm="Are you sure you want to accept this suggestion?"
                                                class="group relative overflow-hidden px-4 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-xl hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-300 font-medium text-sm"
                                            >
                                                <span class="absolute inset-0 bg-gradient-to-r from-emerald-600 to-emerald-700 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                                <span class="relative z-10 flex items-center space-x-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    <span>Accept</span>
                                                </span>
                                            </button>
                                            <button
                                                wire:click="updateSuggestionStatus({{ $suggestion->id }}, 'rejected')"
                                                wire:confirm="Are you sure you want to reject this suggestion?"
                                                class="group relative overflow-hidden px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-300 font-medium text-sm"
                                            >
                                                <span class="absolute inset-0 bg-gradient-to-r from-red-600 to-red-700 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                                <span class="relative z-10 flex items-center space-x-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                    <span>Reject</span>
                                                </span>
                                            </button>
                                        @elseif($suggestion->status === 'accepted')
                                            <button
                                                wire:click="updateSuggestionStatus({{ $suggestion->id }}, 'implemented')"
                                                wire:confirm="Mark this suggestion as implemented?"
                                                class="group relative overflow-hidden px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-300 font-medium text-sm"
                                            >
                                                <span class="absolute inset-0 bg-gradient-to-r from-blue-600 to-blue-700 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                                <span class="relative z-10 flex items-center space-x-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    <span>Mark Implemented</span>
                                                </span>
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            @endauth
                        </div>
                    </div>
                </div>
            @empty
                {{-- Enhanced Empty State --}}
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/5 via-transparent to-[#F8EBD5]/10 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/20"></div>
                    
                    <div class="relative z-10 text-center py-16 px-8">
                        <div class="w-20 h-20 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                            <svg class="w-10 h-10 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">No suggestions yet</h3>
                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg leading-relaxed">
                            Be the first to suggest an improvement and help shape the future of this innovation!
                        </p>
                    </div>
                </div>
            @endforelse
        </section>

        {{-- Enhanced Flash Messages --}}
        @if (session()->has('message'))
            <div 
                x-data="{ show: true }" 
                x-init="setTimeout(() => show = false, 4000)" 
                x-show="show"
                x-transition:enter="transform ease-out duration-300 transition"
                x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed top-6 right-6 z-50 max-w-sm w-full"
            >
                <div class="relative overflow-hidden rounded-2xl bg-emerald-500/90 dark:bg-emerald-600/90 backdrop-blur-xl border border-emerald-400/20 shadow-xl">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-400/20 to-emerald-600/20"></div>
                    <div class="relative z-10 p-6 flex items-center space-x-3">
                        <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <p class="text-white font-medium">{{ session('message') }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
