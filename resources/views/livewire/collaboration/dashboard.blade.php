<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title};
use App\Models\{Idea, Challenge, Collaboration};
use Illuminate\Database\Eloquent\Builder;

new #[Layout('components.layouts.app')] #[Title('Collaboration Dashboard')] class extends Component
{
    public $activeCollaborations = [];
    public $pendingInvites = [];
    public $mySharedIdeas = [];
    public $recentActivity = [];
    public $collaborationStats = [
        'total_collaborations' => 0,
        'pending_invites' => 0,
        'shared_ideas' => 0,
        'contributed_ideas' => 0
    ];

    public function mount()
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        $user = auth()->user();
        
        // Active collaborations (ideas where user is collaborating)
        $this->activeCollaborations = Collaboration::where('collaborator_id', $user->id)
            ->with(['idea.author', 'idea.category'])
            ->where('status', 'active')
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($collaboration) {
                return [
                    'id' => $collaboration->id,
                    'idea_id' => $collaboration->idea->id,
                    'idea_title' => $collaboration->idea->title,
                    'idea_author' => $collaboration->idea->author->name,
                    'joined_at' => $collaboration->joined_at ?? $collaboration->created_at,
                    'last_activity' => $collaboration->updated_at,
                    'role' => $collaboration->role ?? 'contributor'
                ];
            });

        // Pending collaboration invites
        $this->pendingInvites = Collaboration::where('collaborator_id', $user->id)
            ->with(['idea.author', 'inviter'])
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($invite) {
                return [
                    'id' => $invite->id,
                    'idea_id' => $invite->idea->id,
                    'idea_title' => $invite->idea->title,
                    'inviter_name' => $invite->inviter->name,
                    'invited_at' => $invite->invited_at ?? $invite->created_at,
                    'message' => $invite->invitation_message,
                    'role' => $invite->role ?? 'contributor'
                ];
            });

        // User's ideas that have collaboration enabled
        $this->mySharedIdeas = $user->ideas()
            ->with(['category'])
            ->where('collaboration_enabled', true)
            ->withCount(['collaborations' => function ($query) {
                $query->where('status', 'active');
            }])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($idea) {
                return [
                    'id' => $idea->id,
                    'title' => $idea->title,
                    'collaborators_count' => $idea->collaborations_count,
                    'last_activity' => $idea->updated_at,
                    'category' => $idea->category->name ?? 'Uncategorized'
                ];
            });

        // Collaboration statistics
        $this->collaborationStats = [
            'total_collaborations' => Collaboration::where('collaborator_id', $user->id)->where('status', 'active')->count(),
            'pending_invites' => Collaboration::where('collaborator_id', $user->id)->where('status', 'pending')->count(),
            'shared_ideas' => $user->ideas()->where('collaboration_enabled', true)->count(),
            'contributed_ideas' => Collaboration::where('collaborator_id', $user->id)->distinct('idea_id')->count()
        ];

        // Recent collaboration activity (simplified)
        $this->recentActivity = collect([
            ...Collaboration::where('collaborator_id', $user->id)
                ->with(['idea'])
                ->where('status', 'active')
                ->latest()
                ->take(3)
                ->get()
                ->map(function ($collab) {
                    return [
                        'type' => 'collaboration_joined',
                        'message' => "You joined collaboration on \"{$collab->idea->title}\"",
                        'date' => $collab->joined_at ?? $collab->created_at,
                        'idea_id' => $collab->idea->id
                    ];
                }),
            ...Collaboration::where('collaborator_id', $user->id)
                ->with(['idea'])
                ->where('status', 'accepted')
                ->latest()
                ->take(3)
                ->get()
                ->map(function ($invite) {
                    return [
                        'type' => 'invite_accepted',
                        'message' => "You accepted invitation for \"{$invite->idea->title}\"",
                        'date' => $invite->responded_at ?? $invite->updated_at,
                        'idea_id' => $invite->idea->id
                    ];
                })
        ])->sortByDesc('date')->take(5)->values();
    }

    public function acceptInvite($inviteId)
    {
        $invite = Collaboration::findOrFail($inviteId);
        
        // Verify this invite belongs to the current user
        if ($invite->collaborator_id !== auth()->id()) {
            $this->addError('invite', 'You are not authorized to accept this invitation.');
            return;
        }

        if ($invite->status !== 'pending') {
            $this->addError('invite', 'This invitation is no longer available.');
            return;
        }

        // Accept the invitation using the model method
        $invite->accept();

        session()->flash('message', 'Collaboration invitation accepted successfully!');
        $this->loadDashboardData();
    }

    public function declineInvite($inviteId)
    {
        $invite = Collaboration::findOrFail($inviteId);
        
        if ($invite->collaborator_id !== auth()->id()) {
            $this->addError('invite', 'You are not authorized to decline this invitation.');
            return;
        }

        $invite->update([
            'status' => 'declined',
            'responded_at' => now()
        ]);
        
        session()->flash('message', 'Collaboration invitation declined.');
        $this->loadDashboardData();
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-[#F8EBD5]/20 via-white to-[#F8EBD5]/10 dark:from-zinc-900/50 dark:via-zinc-800 dark:to-zinc-900/30">
    <div class="container mx-auto px-4 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-[#231F20] dark:text-white mb-2">Collaboration Dashboard</h1>
            <p class="text-[#9B9EA4] dark:text-zinc-400">Manage your collaborative innovation projects</p>
        </div>

        <!-- Collaboration Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6 backdrop-blur-sm bg-white/40 dark:bg-zinc-800/40 border border-white/20 dark:border-zinc-700/20">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon.users class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400 truncate">Active Collaborations</dt>
                            <dd class="text-lg font-medium text-[#231F20] dark:text-white">{{ $collaborationStats['total_collaborations'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6 backdrop-blur-sm bg-white/40 dark:bg-zinc-800/40 border border-white/20 dark:border-zinc-700/20">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon.clock class="h-8 w-8 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400 truncate">Pending Invites</dt>
                            <dd class="text-lg font-medium text-[#231F20] dark:text-white">{{ $collaborationStats['pending_invites'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6 backdrop-blur-sm bg-white/40 dark:bg-zinc-800/40 border border-white/20 dark:border-zinc-700/20">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon.share class="h-8 w-8 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400 truncate">My Shared Ideas</dt>
                            <dd class="text-lg font-medium text-[#231F20] dark:text-white">{{ $collaborationStats['shared_ideas'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6 backdrop-blur-sm bg-white/40 dark:bg-zinc-800/40 border border-white/20 dark:border-zinc-700/20">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon.light-bulb class="h-8 w-8 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400 truncate">Ideas Contributed</dt>
                            <dd class="text-lg font-medium text-[#231F20] dark:text-white">{{ $collaborationStats['contributed_ideas'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Active Collaborations & Pending Invites -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Active Collaborations -->
                <div class="glass-card rounded-xl p-6 backdrop-blur-sm bg-white/40 dark:bg-zinc-800/40 border border-white/20 dark:border-zinc-700/20">
                    <h2 class="text-xl font-semibold text-[#231F20] dark:text-white mb-4">Active Collaborations</h2>
                    @if(count($activeCollaborations) > 0)
                        <div class="space-y-4">
                            @foreach($activeCollaborations as $collaboration)
                                <div class="border border-[#9B9EA4]/20 dark:border-zinc-600/20 rounded-lg p-4 hover:bg-white/20 dark:hover:bg-zinc-700/20 transition-colors">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h3 class="font-medium text-[#231F20] dark:text-white">
                                                <a href="{{ route('ideas.show', $collaboration['idea_id']) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400">
                                                    {{ $collaboration['idea_title'] }}
                                                </a>
                                            </h3>
                                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mt-1">
                                                by {{ $collaboration['idea_author'] }}
                                            </p>
                                            <div class="flex items-center mt-2 text-xs text-[#9B9EA4] dark:text-zinc-400">
                                                <span>Joined {{ $collaboration['joined_at']->diffForHumans() }}</span>
                                                <span class="mx-2">•</span>
                                                <span>Role: {{ ucfirst($collaboration['role']) }}</span>
                                            </div>
                                        </div>
                                        <flux:button size="sm" href="{{ route('ideas.show', $collaboration['idea_id']) }}" wire:navigate>
                                            View
                                        </flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <flux:icon.users class="mx-auto h-12 w-12 text-[#9B9EA4] dark:text-zinc-400" />
                            <h3 class="mt-2 text-sm font-medium text-[#231F20] dark:text-white">No active collaborations</h3>
                            <p class="mt-1 text-sm text-[#9B9EA4] dark:text-zinc-400">
                                Browse ideas and join collaborations to get started.
                            </p>
                            <div class="mt-6">
                                <flux:button href="{{ route('ideas.index') }}" wire:navigate>
                                    Browse Ideas
                                </flux:button>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Pending Invites -->
                @if(count($pendingInvites) > 0)
                    <div class="glass-card rounded-xl p-6 backdrop-blur-sm bg-white/40 dark:bg-zinc-800/40 border border-white/20 dark:border-zinc-700/20">
                        <h2 class="text-xl font-semibold text-[#231F20] dark:text-white mb-4">Pending Invitations</h2>
                        <div class="space-y-4">
                            @foreach($pendingInvites as $invite)
                                <div class="border border-[#FFF200]/20 bg-[#FFF200]/5 rounded-lg p-4">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h3 class="font-medium text-[#231F20] dark:text-white">{{ $invite['idea_title'] }}</h3>
                                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mt-1">
                                                Invited by {{ $invite['inviter_name'] }} • {{ $invite['invited_at']->diffForHumans() }}
                                            </p>
                                            @if($invite['message'])
                                                <p class="text-sm text-[#231F20] dark:text-white mt-2 italic">"{{ $invite['message'] }}"</p>
                                            @endif
                                            <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">Role: {{ ucfirst($invite['role']) }}</p>
                                        </div>
                                        <div class="flex space-x-2 ml-4">
                                            <flux:button size="sm" variant="primary" wire:click="acceptInvite({{ $invite['id'] }})">
                                                Accept
                                            </flux:button>
                                            <flux:button size="sm" variant="subtle" wire:click="declineInvite({{ $invite['id'] }})">
                                                Decline
                                            </flux:button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Right Column: My Shared Ideas & Recent Activity -->
            <div class="space-y-8">
                <!-- My Shared Ideas -->
                <div class="glass-card rounded-xl p-6 backdrop-blur-sm bg-white/40 dark:bg-zinc-800/40 border border-white/20 dark:border-zinc-700/20">
                    <h2 class="text-lg font-semibold text-[#231F20] dark:text-white mb-4">My Shared Ideas</h2>
                    @if(count($mySharedIdeas) > 0)
                        <div class="space-y-3">
                            @foreach($mySharedIdeas as $idea)
                                <div class="border border-[#9B9EA4]/20 dark:border-zinc-600/20 rounded-lg p-3">
                                    <h3 class="font-medium text-[#231F20] dark:text-white text-sm">
                                        <a href="{{ route('ideas.show', $idea['id']) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400">
                                            {{ Str::limit($idea['title'], 40) }}
                                        </a>
                                    </h3>
                                    <div class="flex justify-between items-center mt-2 text-xs text-[#9B9EA4] dark:text-zinc-400">
                                        <span>{{ $idea['collaborators_count'] }} collaborators</span>
                                        <span>{{ $idea['last_activity']->diffForHumans() }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">No shared ideas yet.</p>
                    @endif
                </div>

                <!-- Recent Activity -->
                <div class="glass-card rounded-xl p-6 backdrop-blur-sm bg-white/40 dark:bg-zinc-800/40 border border-white/20 dark:border-zinc-700/20">
                    <h2 class="text-lg font-semibold text-[#231F20] dark:text-white mb-4">Recent Activity</h2>
                    @if(count($recentActivity) > 0)
                        <div class="space-y-3">
                            @foreach($recentActivity as $activity)
                                <div class="text-sm">
                                    <p class="text-[#231F20] dark:text-white">{{ $activity['message'] }}</p>
                                    <p class="text-xs text-[#9B9EA4] dark:text-zinc-400 mt-1">{{ $activity['date']->diffForHumans() }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">No recent activity.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
