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
            || auth()->user()->hasAnyRole(['manager', 'sme', 'admin', 'developer']);
    }

    private function canRemoveCollaborator($collaboration)
    {
        return $this->idea->author_id === auth()->id() 
            || $collaboration->collaborator_id === auth()->id()
            || auth()->user()->hasAnyRole(['admin', 'developer']);
    }

    private function canUpdateRole($collaboration)
    {
        return $this->idea->author_id === auth()->id() 
            || auth()->user()->hasAnyRole(['admin', 'developer']);
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

<div class="space-y-6">
    <!-- Collaboration Header -->
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-[#231F20]">
            Collaboration Management
        </h3>
        
        @if($this->canInvite())
            <button
                wire:click="toggleInviteForm"
                class="px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 font-medium text-sm"
            >
                {{ $showInviteForm ? 'Cancel' : 'Invite Collaborator' }}
            </button>
        @endif
    </div>

    <!-- Invite Collaborator Form -->
    @if($showInviteForm && $this->canInvite())
        <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
            <h4 class="text-lg font-medium text-[#231F20] mb-4">Invite New Collaborator</h4>
            
            <!-- User Search -->
            <div class="space-y-4">
                <div class="relative">
                    <label for="userSearch" class="block text-sm font-medium text-[#231F20] mb-2">
                        Search Users
                    </label>
                    <input
                        wire:model.live.debounce.300ms="searchQuery"
                        wire:focus="showSearch = true"
                        type="text"
                        id="userSearch"
                        class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/80 backdrop-blur-sm"
                        placeholder="Type to search by name or email..."
                    >
                    
                    <!-- Search Results Dropdown -->
                    @if($showSearch && count($searchResults) > 0)
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            @foreach($searchResults as $user)
                                <button
                                    wire:click="inviteUserById({{ $user->id }})"
                                    class="w-full px-4 py-3 text-left hover:bg-gray-50 border-b border-gray-100 last:border-b-0 focus:bg-gray-50 focus:outline-none"
                                >
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full flex items-center justify-center text-white text-sm font-medium">
                                            {{ $user->initials() }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-[#231F20]">{{ $user->name }}</p>
                                            <p class="text-sm text-gray-500">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Manual Email Input -->
                <div class="border-t pt-4">
                    <p class="text-sm text-gray-600 mb-3">Or enter email manually:</p>
                    <form wire:submit.prevent="inviteUser" class="space-y-4">
                        <div>
                            <label for="inviteEmail" class="block text-sm font-medium text-[#231F20] mb-2">
                                Email Address
                            </label>
                            <input
                                wire:model="inviteEmail"
                                type="email"
                                id="inviteEmail"
                                class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/80 backdrop-blur-sm"
                                placeholder="Enter email address..."
                            >
                            @error('inviteEmail') 
                                <span class="text-red-600 text-sm mt-1">{{ $message }}</span> 
                            @enderror
                        </div>
                        
                        <div>
                            <label for="inviteRole" class="block text-sm font-medium text-[#231F20] mb-2">
                                Collaboration Role
                            </label>
                            <select
                                wire:model="inviteRole"
                                id="inviteRole"
                                class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/80 backdrop-blur-sm"
                            >
                                @foreach($this->getCollaborationRoles() as $value => $description)
                                    <option value="{{ $value }}">{{ $description }}</option>
                                @endforeach
                            </select>
                            @error('inviteRole') 
                                <span class="text-red-600 text-sm mt-1">{{ $message }}</span> 
                            @enderror
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button
                                type="button"
                                wire:click="toggleInviteForm"
                                class="px-6 py-2 text-gray-600 hover:text-gray-800 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 font-medium"
                            >
                                Send Invitation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Current Collaborations -->
    <div class="space-y-4">
        @if($collaborations->count() > 0)
            <h4 class="text-md font-medium text-[#231F20]">Current Collaborators</h4>
            
            @foreach($collaborations as $collaboration)
                <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-10 h-10 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full flex items-center justify-center text-white font-medium">
                                {{ $collaboration->collaborator->initials() }}
                            </div>
                            
                            <div>
                                <h5 class="font-medium text-[#231F20]">{{ $collaboration->collaborator->name }}</h5>
                                <p class="text-sm text-gray-500">{{ $collaboration->collaborator->email }}</p>
                                <div class="flex items-center space-x-2 mt-1">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $this->getRoleBadgeClass($collaboration->role) }}">
                                        {{ ucfirst(str_replace('_', ' ', $collaboration->role)) }}
                                    </span>
                                    <span class="px-2 py-1 text-xs rounded-full border {{ $this->getStatusBadgeClass($collaboration->status) }}">
                                        {{ ucfirst($collaboration->status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <!-- Pending Invitation Actions -->
                            @if($collaboration->status === 'pending' && $collaboration->collaborator_id === auth()->id())
                                <button
                                    wire:click="respondToInvitation({{ $collaboration->id }}, 'accepted')"
                                    class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition-colors"
                                >
                                    Accept
                                </button>
                                <button
                                    wire:click="respondToInvitation({{ $collaboration->id }}, 'declined')"
                                    class="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 transition-colors"
                                >
                                    Decline
                                </button>
                            @endif
                            
                            <!-- Role Update (for authorized users) -->
                            @if($collaboration->status === 'accepted' && $this->canUpdateRole($collaboration))
                                <select
                                    wire:change="updateCollaboratorRole({{ $collaboration->id }}, $event.target.value)"
                                    class="text-xs border border-gray-200 rounded px-2 py-1 bg-white"
                                >
                                    @foreach($this->getCollaborationRoles() as $value => $description)
                                        <option value="{{ $value }}" {{ $collaboration->role === $value ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $value)) }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            
                            <!-- Remove Collaborator -->
                            @if($this->canRemoveCollaborator($collaboration) && $collaboration->status !== 'removed')
                                <button
                                    wire:click="removeCollaborator({{ $collaboration->id }})"
                                    wire:confirm="Are you sure you want to remove this collaborator?"
                                    class="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 transition-colors"
                                >
                                    Remove
                                </button>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Collaboration Details -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                            <div>
                                <span class="font-medium">Invited by:</span>
                                {{ $collaboration->inviter->name }}
                            </div>
                            <div>
                                <span class="font-medium">Invited on:</span>
                                {{ $collaboration->created_at->format('M j, Y') }}
                            </div>
                            @if($collaboration->responded_at)
                                <div>
                                    <span class="font-medium">Responded on:</span>
                                    {{ $collaboration->responded_at->format('M j, Y') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="text-center py-12 bg-white/30 backdrop-blur-md rounded-xl border border-white/20">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                </svg>
                <h3 class="text-lg font-medium text-[#231F20] mb-2">No collaborators yet</h3>
                <p class="text-gray-500">Invite team members to collaborate on this idea!</p>
            </div>
        @endif
    </div>

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show"
             class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show"
             class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('error') }}
        </div>
    @endif
</div>
