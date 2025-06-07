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
        return auth()->user()->hasAnyRole(['manager', 'sme', 'admin', 'developer']);
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

<div class="space-y-6">
    <!-- Suggestions Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <h3 class="text-lg font-semibold text-[#231F20]">
                Suggestions & Improvements ({{ $suggestions->count() }})
            </h3>
            
            <!-- Filter Buttons -->
            <div class="flex space-x-2">
                @foreach(['all', 'pending', 'accepted', 'rejected', 'implemented'] as $status)
                    <button
                        wire:click="setFilterStatus('{{ $status }}')"
                        class="px-3 py-1 text-xs rounded-full transition-colors {{ $filterStatus === $status ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                    >
                        {{ ucfirst($status) }}
                    </button>
                @endforeach
            </div>
        </div>
        
        <div class="flex items-center space-x-3">
            @if(!$showAll && $suggestions->count() >= $perPage)
                <button 
                    wire:click="showAllSuggestions"
                    class="text-sm text-blue-600 hover:text-blue-800 transition-colors"
                >
                    Show all suggestions
                </button>
            @endif
            
            @auth
                <button
                    wire:click="toggleForm"
                    class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all duration-200 font-medium text-sm"
                >
                    {{ $showForm ? 'Cancel' : 'Add Suggestion' }}
                </button>
            @endauth
        </div>
    </div>

    <!-- Add New Suggestion Form -->
    @auth
        @if($showForm)
            <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
                <h4 class="text-lg font-medium text-[#231F20] mb-4">Submit a Suggestion</h4>
                
                <form wire:submit.prevent="addSuggestion" class="space-y-4">
                    <div>
                        <label for="suggestionType" class="block text-sm font-medium text-[#231F20] mb-2">
                            Suggestion Type
                        </label>
                        <select
                            wire:model="newSuggestionType"
                            id="suggestionType"
                            class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/80 backdrop-blur-sm"
                        >
                            @foreach($this->getSuggestionTypes() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('newSuggestionType') 
                            <span class="text-red-600 text-sm mt-1">{{ $message }}</span> 
                        @enderror
                    </div>
                    
                    <div>
                        <label for="suggestionContent" class="block text-sm font-medium text-[#231F20] mb-2">
                            Suggestion Details
                        </label>
                        <textarea
                            wire:model="newSuggestion"
                            id="suggestionContent"
                            rows="5"
                            class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none bg-white/80 backdrop-blur-sm"
                            placeholder="Describe your suggestion in detail. What specific improvement or feature are you proposing? How would it benefit the idea?"
                        ></textarea>
                        @error('newSuggestion') 
                            <span class="text-red-600 text-sm mt-1">{{ $message }}</span> 
                        @enderror
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">
                            {{ 2000 - strlen($newSuggestion) }} characters remaining
                        </span>
                        <div class="flex space-x-3">
                            <button
                                type="button"
                                wire:click="toggleForm"
                                class="px-6 py-2 text-gray-600 hover:text-gray-800 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-6 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all duration-200 font-medium"
                                {{ strlen($newSuggestion) < 10 ? 'disabled' : '' }}
                            >
                                Submit Suggestion
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    @endauth

    <!-- Suggestions List -->
    <div class="space-y-4">
        @forelse($suggestions as $suggestion)
            <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
                <!-- Suggestion Header -->
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-gradient-to-r from-green-600 to-blue-600 rounded-full flex items-center justify-center text-white text-sm font-medium">
                            {{ $suggestion->author->initials() }}
                        </div>
                        <div>
                            <h4 class="font-medium text-[#231F20]">{{ $suggestion->author->name }}</h4>
                            <div class="flex items-center space-x-2 mt-1">
                                <p class="text-sm text-gray-500">{{ $suggestion->created_at->diffForHumans() }}</p>
                                <span class="px-2 py-1 text-xs rounded-full {{ $this->getTypeBadgeClass($suggestion->suggestion_type) }}">
                                    {{ $this->getSuggestionTypes()[$suggestion->suggestion_type] }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <span class="px-3 py-1 text-xs rounded-full border {{ $this->getStatusBadgeClass($suggestion->status) }}">
                        {{ ucfirst($suggestion->status) }}
                    </span>
                </div>

                <!-- Suggestion Content -->
                <div class="mb-4">
                    <p class="text-[#231F20] leading-relaxed">{{ $suggestion->content }}</p>
                    
                    @if($suggestion->implementation_notes)
                        <div class="mt-3 p-3 bg-blue-50/80 backdrop-blur-sm rounded-lg border border-blue-200">
                            <p class="text-sm font-medium text-blue-800 mb-1">Implementation Notes:</p>
                            <p class="text-sm text-blue-700">{{ $suggestion->implementation_notes }}</p>
                        </div>
                    @endif
                </div>

                <!-- Suggestion Actions -->
                <div class="flex items-center justify-between">
                    @auth
                        <!-- Voting -->
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-2">
                                <button
                                    wire:click="voteSuggestion({{ $suggestion->id }}, 'upvote')"
                                    class="flex items-center space-x-1 text-sm {{ auth()->user()->hasVotedOnSuggestion($suggestion) && auth()->user()->getSuggestionVote($suggestion)?->vote_type === 'upvote' ? 'text-green-600' : 'text-gray-500 hover:text-green-600' }} transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ $suggestion->upvotes_count }}</span>
                                </button>
                                
                                <button
                                    wire:click="voteSuggestion({{ $suggestion->id }}, 'downvote')"
                                    class="flex items-center space-x-1 text-sm {{ auth()->user()->hasVotedOnSuggestion($suggestion) && auth()->user()->getSuggestionVote($suggestion)?->vote_type === 'downvote' ? 'text-red-600' : 'text-gray-500 hover:text-red-600' }} transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ $suggestion->downvotes_count }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Status Management (for authorized users) -->
                        @if($this->canUpdateStatus() && $suggestion->status === 'pending')
                            <div class="flex items-center space-x-2">
                                <button
                                    wire:click="updateSuggestionStatus({{ $suggestion->id }}, 'accepted')"
                                    wire:confirm="Are you sure you want to accept this suggestion?"
                                    class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition-colors"
                                >
                                    Accept
                                </button>
                                <button
                                    wire:click="updateSuggestionStatus({{ $suggestion->id }}, 'rejected')"
                                    wire:confirm="Are you sure you want to reject this suggestion?"
                                    class="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 transition-colors"
                                >
                                    Reject
                                </button>
                            </div>
                        @elseif($this->canUpdateStatus() && $suggestion->status === 'accepted')
                            <button
                                wire:click="updateSuggestionStatus({{ $suggestion->id }}, 'implemented')"
                                wire:confirm="Mark this suggestion as implemented?"
                                class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
                            >
                                Mark Implemented
                            </button>
                        @endif
                    @endauth
                </div>
            </div>
        @empty
            <div class="text-center py-12 bg-white/30 backdrop-blur-md rounded-xl border border-white/20">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                <h3 class="text-lg font-medium text-[#231F20] mb-2">No suggestions yet</h3>
                <p class="text-gray-500">Be the first to suggest an improvement!</p>
            </div>
        @endforelse
    </div>

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show"
             class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('message') }}
        </div>
    @endif
</div>
