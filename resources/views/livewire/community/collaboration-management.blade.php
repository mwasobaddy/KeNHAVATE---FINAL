<?php

use Livewire\Volt\Component;
use App\Models\Collaboration;
use App\Models\User;
use App\Models\Idea;

new class extends Component {
    public $idea;
    public $collaborations = [];
    public $inviteEmail = '';
    public $inviteRole = 'contributor';
    public $showInviteForm = false;
    public $searchQuery = '';
    public $searchResults = [];
    public $showSearch = false;

    protected $collaborationRoles = [
        'contributor' => 'Contributor - Can provide input and feedback',
        'co_author' => 'Co-Author - Can edit and modify the idea',
        'reviewer' => 'Reviewer - Can provide formal reviews and assessments',
    ];

    public function mount(Idea $idea)
    {
        $this->idea = $idea;
        $this->loadCollaborations();
    }

    public function loadCollaborations()
    {
        $this->collaborations = Collaboration::with(['collaborator', 'inviter'])
            ->where('idea_id', $this->idea->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function searchUsers()
    {
        if (strlen($this->searchQuery) < 2) {
            $this->searchResults = [];
            return;
        }

        // Search for users excluding current author and existing collaborators
        $existingCollaboratorIds = $this->collaborations
            ->pluck('collaborator_id')
            ->push($this->idea->author_id)
            ->toArray();

        $this->searchResults = User::where(function ($query) {
                $query->where('first_name', 'like', '%' . $this->searchQuery . '%')
                      ->orWhere('last_name', 'like', '%' . $this->searchQuery . '%')
                      ->orWhere('email', 'like', '%' . $this->searchQuery . '%');
            })
            ->whereNotIn('id', $existingCollaboratorIds)
            ->where('account_status', 'active')
            ->limit(10)
            ->get();

        $this->showSearch = true;
    }

    public function inviteUserById($userId)
    {
        $user = User::findOrFail($userId);
        $this->inviteUser($user->email);
        $this->clearSearch();
    }

    public function inviteUser($email = null)
    {
        $emailToInvite = $email ?? $this->inviteEmail;
        
        $this->validate([
            'inviteRole' => 'required|in:contributor,co_author,reviewer',
        ]);

        // Validate email format
        if (!filter_var($emailToInvite, FILTER_VALIDATE_EMAIL)) {
            session()->flash('error', 'Please provide a valid email address.');
            return;
        }

        // Check if user exists
        $user = User::where('email', $emailToInvite)->first();
        if (!$user) {
            session()->flash('error', 'No user found with that email address.');
            return;
        }

        // Check if user is the idea author
        if ($user->id === $this->idea->author_id) {
            session()->flash('error', 'You cannot invite yourself to collaborate.');
            return;
        }

        // Check if collaboration already exists
        $existingCollaboration = Collaboration::where('idea_id', $this->idea->id)
            ->where('collaborator_id', $user->id)
            ->first();

        if ($existingCollaboration) {
            if ($existingCollaboration->status === 'pending') {
                session()->flash('error', 'This user already has a pending invitation.');
            } else {
                session()->flash('error', 'This user is already collaborating on this idea.');
            }
            return;
        }

        // Check authorization - only idea author, managers, SMEs, and admins can invite
        if (!$this->canInvite()) {
            abort(403, 'You are not authorized to invite collaborators.');
        }

        // Create collaboration invitation
        $collaboration = Collaboration::create([
            'idea_id' => $this->idea->id,
            'collaborator_id' => $user->id,
            'invited_by' => auth()->id(),
            'role' => $this->inviteRole,
            'status' => 'pending',
        ]);

        // Create audit log
        app('audit')->log('collaboration_invited', 'Collaboration', $collaboration->id, null, [
            'idea_id' => $this->idea->id,
            'collaborator_id' => $user->id,
            'role' => $this->inviteRole,
        ]);

        // Send notification to invited user
        $this->sendInvitationNotification($user, $collaboration);

        $this->reset(['inviteEmail', 'showInviteForm']);
        $this->loadCollaborations();
        
        session()->flash('message', "Collaboration invitation sent to {$user->name}!");
    }

    public function respondToInvitation($collaborationId, $response)
    {
        $collaboration = Collaboration::findOrFail($collaborationId);
        
        // Check if user is the invited collaborator
        if ($collaboration->collaborator_id !== auth()->id()) {
            abort(403);
        }

        // Check if invitation is still pending
        if ($collaboration->status !== 'pending') {
            session()->flash('error', 'This invitation is no longer valid.');
            return;
        }

        $collaboration->update([
            'status' => $response,
            'responded_at' => now(),
        ]);

        // Create audit log
        app('audit')->log('collaboration_' . $response, 'Collaboration', $collaboration->id, 
            ['status' => 'pending'], 
            ['status' => $response]
        );

        // Notify idea author of response
        $this->notifyAuthorOfResponse($collaboration, $response);

        $this->loadCollaborations();
        
        $message = $response === 'accepted' 
            ? 'Collaboration invitation accepted!' 
            : 'Collaboration invitation declined.';
        
        session()->flash('message', $message);
    }

    public function removeCollaborator($collaborationId)
    {
        $collaboration = Collaboration::findOrFail($collaborationId);
        
        // Check authorization - only idea author, the collaborator themselves, or admins can remove
        if (!$this->canRemoveCollaborator($collaboration)) {
            abort(403);
        }

        $collaboratorName = $collaboration->collaborator->name;
        
        $collaboration->update(['status' => 'removed']);

        // Create audit log
        app('audit')->log('collaboration_removed', 'Collaboration', $collaboration->id, 
            ['status' => $collaboration->status], 
            ['status' => 'removed']
        );

        // Notify affected parties
        $this->notifyCollaboratorRemoval($collaboration);

        $this->loadCollaborations();
        
        session()->flash('message', "{$collaboratorName} has been removed from the collaboration.");
    }

    public function updateCollaboratorRole($collaborationId, $newRole)
    {
        $collaboration = Collaboration::findOrFail($collaborationId);
        
        // Check authorization - only idea author and admins can update roles
        if (!$this->canUpdateRole($collaboration)) {
            abort(403);
        }

        $oldRole = $collaboration->role;
        $collaboration->update(['role' => $newRole]);

        // Create audit log
        app('audit')->log('collaboration_role_updated', 'Collaboration', $collaboration->id, 
            ['role' => $oldRole], 
            ['role' => $newRole]
        );

        $this->loadCollaborations();
        
        session()->flash('message', 'Collaborator role updated successfully!');
    }

    private function canInvite()
    {
        return $this->idea->author_id === auth()->id() 
            || auth()->user()->hasAnyRole(['manager', 'sme', administrator, 'developer']);
    }

    private function canRemoveCollaborator($collaboration)
    {
        return $this->idea->author_id === auth()->id() 
            || $collaboration->collaborator_id === auth()->id()
            || auth()->user()->hasAnyRole([administrator, 'developer']);
    }

    private function canUpdateRole($collaboration)
    {
        return $this->idea->author_id === auth()->id() 
            || auth()->user()->hasAnyRole([administrator, 'developer']);
    }

    private function sendInvitationNotification($user, $collaboration)
    {
        // Implementation for sending notification
        // This would integrate with the notification system
    }

    private function notifyAuthorOfResponse($collaboration, $response)
    {
        // Implementation for notifying idea author
        // This would integrate with the notification system
    }

    private function notifyCollaboratorRemoval($collaboration)
    {
        // Implementation for notifying about removal
        // This would integrate with the notification system
    }

    public function toggleInviteForm()
    {
        $this->showInviteForm = !$this->showInviteForm;
        if (!$this->showInviteForm) {
            $this->reset(['inviteEmail', 'inviteRole']);
            $this->clearSearch();
        }
    }

    public function clearSearch()
    {
        $this->reset(['searchQuery', 'searchResults', 'showSearch']);
    }

    public function getCollaborationRoles()
    {
        return $this->collaborationRoles;
    }

    public function getStatusBadgeClass($status)
    {
        return match($status) {
            'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'accepted' => 'bg-green-100 text-green-800 border-green-200',
            'declined' => 'bg-red-100 text-red-800 border-red-200',
            'removed' => 'bg-gray-100 text-gray-800 border-gray-200',
            default => 'bg-gray-100 text-gray-800 border-gray-200',
        };
    }

    public function getRoleBadgeClass($role)
    {
        return match($role) {
            'contributor' => 'bg-blue-100 text-blue-800',
            'co_author' => 'bg-purple-100 text-purple-800',
            'reviewer' => 'bg-orange-100 text-orange-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}; ?>
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-10 left-10 w-48 h-48 bg-blue-500/20 dark:bg-blue-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-10 right-10 w-64 h-64 bg-purple-500/20 dark:bg-purple-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/3 right-1/3 w-40 h-40 bg-[#FFF200]/30 dark:bg-yellow-400/10 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 space-y-8">
        {{-- Enhanced Collaboration Header with Glass Morphism --}}
        <section aria-labelledby="collaboration-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Animated Gradient Background --}}
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-purple-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-purple-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                
                <div class="relative p-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 dark:from-blue-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 id="collaboration-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Collaboration Management</h3>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Manage team collaboration and invitations</p>
                            </div>
                        </div>
                        
                        @if($this->canInvite())
                            <flux:button 
                                wire:click="toggleInviteForm"
                                class="group/btn relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-6 py-3"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-purple-600/20 dark:from-blue-400/20 dark:to-purple-500/30 opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></span>
                                <span class="relative flex items-center space-x-2 text-[#231F20] dark:text-zinc-100 font-semibold">
                                    @if($showInviteForm)
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        <span>Cancel</span>
                                    @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        <span>Invite Collaborator</span>
                                    @endif
                                </span>
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Invite Collaborator Form --}}
        @if($showInviteForm && $this->canInvite())
            <section aria-labelledby="invite-form-heading" class="group" x-data="{ isExpanded: true }" x-show="isExpanded" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    {{-- Animated Background --}}
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-blue-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-blue-500/20"></div>
                    
                    <div class="relative p-8">
                        {{-- Form Header --}}
                        <div class="flex items-center space-x-4 mb-6">
                            <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-blue-600 dark:from-emerald-400 dark:to-blue-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                </svg>
                            </div>
                            <div>
                                <h4 id="invite-form-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Invite New Collaborator</h4>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Search for users or invite by email</p>
                            </div>
                        </div>
                        
                        {{-- User Search Section --}}
                        <div class="space-y-6">
                            <div class="relative">
                                <label for="userSearch" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-3">
                                    Search Users
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="w-5 h-5 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                    <input
                                        wire:model.live.debounce.300ms="searchQuery"
                                        wire:focus="showSearch = true"
                                        type="text"
                                        id="userSearch"
                                        class="w-full pl-12 pr-4 py-4 border border-white/20 dark:border-zinc-700/50 rounded-2xl focus:ring-2 focus:ring-blue-500/50 focus:border-transparent bg-white/80 dark:bg-zinc-800/80 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] transition-all duration-300"
                                        placeholder="Type to search by name or email..."
                                    >
                                </div>
                                
                                {{-- Enhanced Search Results Dropdown --}}
                                @if($showSearch && count($searchResults) > 0)
                                    <div class="absolute z-20 w-full mt-2 bg-white/90 dark:bg-zinc-800/90 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl shadow-xl max-h-80 overflow-y-auto">
                                        @foreach($searchResults as $user)
                                            <button
                                                wire:click="inviteUserById({{ $user->id }})"
                                                class="w-full p-4 text-left hover:bg-white/50 dark:hover:bg-zinc-700/50 border-b border-white/10 dark:border-zinc-700/30 last:border-b-0 focus:bg-white/50 dark:focus:bg-zinc-700/50 focus:outline-none transition-all duration-200 group"
                                            >
                                                <div class="flex items-center space-x-4">
                                                    <div class="relative">
                                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 dark:from-blue-400 dark:to-purple-500 rounded-xl flex items-center justify-center text-white text-sm font-semibold shadow-lg">
                                                            {{ $user->initials() }}
                                                        </div>
                                                        <div class="absolute -inset-1 bg-blue-500/20 dark:bg-blue-400/30 rounded-xl blur opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="font-semibold text-[#231F20] dark:text-zinc-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors duration-200">{{ $user->name }}</p>
                                                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->email }}</p>
                                                    </div>
                                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                        <svg class="w-5 h-5 text-blue-500 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Enhanced Manual Email Input Section --}}
                            <div class="relative">
                                <div class="absolute inset-0 bg-gradient-to-r from-white/30 to-white/10 dark:from-zinc-700/30 dark:to-zinc-800/10 rounded-2xl"></div>
                                <div class="relative border-t border-white/20 dark:border-zinc-700/50 pt-6">
                                    <p class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400 mb-4 flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                        Or enter email manually
                                    </p>
                                    
                                    <form wire:submit.prevent="inviteUser" class="space-y-6">
                                        <div>
                                            <label for="inviteEmail" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-3">
                                                Email Address
                                            </label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                    <svg class="w-5 h-5 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                                                    </svg>
                                                </div>
                                                <input
                                                    wire:model="inviteEmail"
                                                    type="email"
                                                    id="inviteEmail"
                                                    class="w-full pl-12 pr-4 py-4 border border-white/20 dark:border-zinc-700/50 rounded-2xl focus:ring-2 focus:ring-blue-500/50 focus:border-transparent bg-white/80 dark:bg-zinc-800/80 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] transition-all duration-300"
                                                    placeholder="Enter email address..."
                                                >
                                            </div>
                                            @error('inviteEmail') 
                                                <span class="text-red-500 dark:text-red-400 text-sm mt-2 flex items-center">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    {{ $message }}
                                                </span> 
                                            @enderror
                                        </div>
                                        
                                        <div>
                                            <label for="inviteRole" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-3">
                                                Collaboration Role
                                            </label>
                                            <select
                                                wire:model="inviteRole"
                                                id="inviteRole"
                                                class="w-full px-4 py-4 border border-white/20 dark:border-zinc-700/50 rounded-2xl focus:ring-2 focus:ring-blue-500/50 focus:border-transparent bg-white/80 dark:bg-zinc-800/80 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 transition-all duration-300"
                                            >
                                                @foreach($this->getCollaborationRoles() as $value => $description)
                                                    <option value="{{ $value }}">{{ $description }}</option>
                                                @endforeach
                                            </select>
                                            @error('inviteRole') 
                                                <span class="text-red-500 dark:text-red-400 text-sm mt-2 flex items-center">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    {{ $message }}
                                                </span> 
                                            @enderror
                                        </div>
                                        
                                        {{-- Enhanced Action Buttons --}}
                                        <div class="flex justify-end space-x-4 pt-4">
                                            <flux:button
                                                type="button"
                                                wire:click="toggleInviteForm"
                                                class="px-6 py-3 text-[#9B9EA4] hover:text-[#231F20] dark:hover:text-zinc-100 transition-colors duration-200 font-medium"
                                            >
                                                Cancel
                                            </flux:button>
                                            <flux:button
                                                type="submit"
                                                class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 text-white shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-8 py-3"
                                            >
                                                <span class="absolute inset-0 bg-gradient-to-br from-blue-600 to-blue-700 dark:from-blue-500 dark:to-blue-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                                <span class="relative flex items-center space-x-2 font-semibold">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                                    </svg>
                                                    <span>Send Invitation</span>
                                                </span>
                                            </flux:button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- Enhanced Current Collaborations Section --}}
        <section aria-labelledby="collaborators-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Animated Background --}}
                <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-transparent to-indigo-600/10 dark:from-purple-400/10 dark:via-transparent dark:to-indigo-500/20"></div>
                
                <div class="relative p-8">
                    @if($collaborations->count() > 0)
                        {{-- Section Header --}}
                        <div class="flex items-center space-x-4 mb-8">
                            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 dark:from-purple-400 dark:to-indigo-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h4 id="collaborators-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Current Collaborators</h4>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">{{ $collaborations->count() }} team {{ Str::plural('member', $collaborations->count()) }}</p>
                            </div>
                        </div>
                        
                        {{-- Collaborators Grid --}}
                        <div class="space-y-6">
                            @foreach($collaborations as $collaboration)
                                <div class="group/collab relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-1">
                                    {{-- Card Glow Effect --}}
                                    <div class="absolute inset-0 bg-gradient-to-r from-blue-500/5 via-transparent to-purple-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-purple-500/20 opacity-0 group-hover/collab:opacity-100 transition-opacity duration-500"></div>
                                    
                                    <div class="relative p-6">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-4">
                                                {{-- Enhanced Avatar --}}
                                                <div class="relative">
                                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 dark:from-blue-400 dark:to-purple-500 rounded-2xl flex items-center justify-center text-white font-semibold shadow-lg">
                                                        {{ $collaboration->collaborator->initials() }}
                                                    </div>
                                                    <div class="absolute -inset-1 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur opacity-0 group-hover/collab:opacity-100 transition-opacity duration-500"></div>
                                                </div>
                                                
                                                <div class="flex-1">
                                                    <h5 class="font-semibold text-[#231F20] dark:text-zinc-100 text-lg">{{ $collaboration->collaborator->name }}</h5>
                                                    <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mb-2">{{ $collaboration->collaborator->email }}</p>
                                                    
                                                    {{-- Enhanced Badges --}}
                                                    <div class="flex items-center space-x-3">
                                                        <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-full {{ $this->getRoleBadgeClass($collaboration->role) }} border">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                            </svg>
                                                            {{ ucfirst(str_replace('_', ' ', $collaboration->role)) }}
                                                        </span>
                                                        <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-full border {{ $this->getStatusBadgeClass($collaboration->status) }}">
                                                            @if($collaboration->status === 'pending')
                                                                <div class="w-2 h-2 bg-yellow-500 rounded-full mr-1.5 animate-pulse"></div>
                                                            @elseif($collaboration->status === 'accepted')
                                                                <div class="w-2 h-2 bg-green-500 rounded-full mr-1.5"></div>
                                                            @endif
                                                            {{ ucfirst($collaboration->status) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            {{-- Enhanced Action Buttons --}}
                                            <div class="flex items-center space-x-3">
                                                {{-- Pending Invitation Actions --}}
                                                @if($collaboration->status === 'pending' && $collaboration->collaborator_id === auth()->id())
                                                    <flux:button
                                                        wire:click="respondToInvitation({{ $collaboration->id }}, 'accepted')"
                                                        class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 text-white shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-4 py-2"
                                                    >
                                                        <span class="relative flex items-center space-x-1 text-xs font-semibold">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                            <span>Accept</span>
                                                        </span>
                                                    </flux:button>
                                                    <flux:button
                                                        wire:click="respondToInvitation({{ $collaboration->id }}, 'declined')"
                                                        class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-red-500 to-red-600 dark:from-red-400 dark:to-red-500 text-white shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-4 py-2"
                                                    >
                                                        <span class="relative flex items-center space-x-1 text-xs font-semibold">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                            <span>Decline</span>
                                                        </span>
                                                    </flux:button>
                                                @endif
                                                
                                                {{-- Role Update Dropdown --}}
                                                @if($collaboration->status === 'accepted' && $this->canUpdateRole($collaboration))
                                                    <select
                                                        wire:change="updateCollaboratorRole({{ $collaboration->id }}, $event.target.value)"
                                                        class="text-xs border border-white/20 dark:border-zinc-700/50 rounded-xl px-3 py-2 bg-white/80 dark:bg-zinc-800/80 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 focus:ring-2 focus:ring-blue-500/50 transition-all duration-300"
                                                    >
                                                        @foreach($this->getCollaborationRoles() as $value => $description)
                                                            <option value="{{ $value }}" {{ $collaboration->role === $value ? 'selected' : '' }}>
                                                                {{ ucfirst(str_replace('_', ' ', $value)) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                                
                                                {{-- Remove Collaborator Button --}}
                                                @if($this->canRemoveCollaborator($collaboration) && $collaboration->status !== 'removed')
                                                    <flux:button
                                                        wire:click="removeCollaborator({{ $collaboration->id }})"
                                                        wire:confirm="Are you sure you want to remove this collaborator?"
                                                        class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-red-500 to-red-600 dark:from-red-400 dark:to-red-500 text-white shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-4 py-2"
                                                    >
                                                        <span class="relative flex items-center space-x-1 text-xs font-semibold">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                            <span>Remove</span>
                                                        </span>
                                                    </flux:button>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        {{-- Enhanced Collaboration Details --}}
                                        <div class="mt-6 pt-6 border-t border-white/20 dark:border-zinc-700/50">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div class="flex items-center space-x-2 text-sm text-[#9B9EA4] dark:text-zinc-400">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                    </svg>
                                                    <span class="font-medium">Invited by:</span>
                                                    <span class="text-[#231F20] dark:text-zinc-100">{{ $collaboration->inviter->name }}</span>
                                                </div>
                                                <div class="flex items-center space-x-2 text-sm text-[#9B9EA4] dark:text-zinc-400">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4v10a2 2 0 002 2h4a2 2 0 002-2V11M8 11h8"/>
                                                    </svg>
                                                    <span class="font-medium">Invited on:</span>
                                                    <span class="text-[#231F20] dark:text-zinc-100">{{ $collaboration->created_at->format('M j, Y') }}</span>
                                                </div>
                                                @if($collaboration->responded_at)
                                                    <div class="flex items-center space-x-2 text-sm text-[#9B9EA4] dark:text-zinc-400">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        <span class="font-medium">Responded on:</span>
                                                        <span class="text-[#231F20] dark:text-zinc-100">{{ $collaboration->responded_at->format('M j, Y') }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        {{-- Enhanced Empty State --}}
                        <div class="text-center py-16">
                            <div class="relative mb-6">
                                <div class="w-20 h-20 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center mx-auto shadow-xl">
                                    <svg class="w-10 h-10 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                </div>
                                <div class="absolute -inset-4 bg-[#FFF200]/20 dark:bg-yellow-400/30 rounded-3xl blur-xl"></div>
                            </div>
                            
                            <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">No collaborators yet</h3>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg leading-relaxed max-w-md mx-auto">
                                Invite team members to collaborate on this idea and bring diverse perspectives to your innovation!
                            </p>
                            
                            @if($this->canInvite())
                                <flux:button 
                                    wire:click="toggleInviteForm"
                                    class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 text-white shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-8 py-3 mt-6"
                                >
                                    <span class="relative flex items-center space-x-2 font-semibold">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        <span>Invite Your First Collaborator</span>
                                    </span>
                                </flux:button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Enhanced Flash Messages --}}
        @if (session()->has('message'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="fixed top-4 right-4 bg-gradient-to-r from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 text-white px-6 py-4 rounded-2xl shadow-xl z-50 backdrop-blur-xl border border-white/20">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="font-semibold">{{ session('message') }}</span>
                </div>
            </div>
        @endif

        @if (session()->has('error'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="fixed top-4 right-4 bg-gradient-to-r from-red-500 to-red-600 dark:from-red-400 dark:to-red-500 text-white px-6 py-4 rounded-2xl shadow-xl z-50 backdrop-blur-xl border border-white/20">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="font-semibold">{{ session('error') }}</span>
                </div>
            </div>
        @endif
    </div>
</div>
