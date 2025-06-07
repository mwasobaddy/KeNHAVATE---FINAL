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

{{-- Modern Collaboration Dashboard with Glass Morphism & Enhanced UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-blue-500/10 dark:bg-blue-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-purple-500/20 dark:bg-purple-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Enhanced Page Header --}}
        <div class="mb-8">
            <div class="flex items-center space-x-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 dark:from-blue-400 dark:to-indigo-500 flex items-center justify-center shadow-lg">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-4xl font-bold text-[#231F20] dark:text-zinc-100">Collaboration Dashboard</h1>
                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">Manage your collaborative innovation projects</p>
                </div>
            </div>
        </div>

        {{-- Enhanced Statistics Cards with Glass Morphism --}}
        <section aria-labelledby="stats-heading" class="group">
            <h2 id="stats-heading" class="sr-only">Collaboration Statistics</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Active Collaborations Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Active Collaborations</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-blue-600 dark:group-hover/card:text-blue-400 transition-colors duration-300">{{ number_format($collaborationStats['total_collaborations']) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full animate-pulse"></div>
                                <span>Currently active</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pending Invites Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 via-transparent to-amber-600/10 dark:from-amber-400/10 dark:via-transparent dark:to-amber-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-amber-500/20 dark:bg-amber-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Pending Invites</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-amber-600 dark:group-hover/card:text-amber-400 transition-colors duration-300">{{ number_format($collaborationStats['pending_invites']) }}</p>
                            
                            @if($collaborationStats['pending_invites'] > 0)
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-3 py-1.5 rounded-full">
                                    <div class="w-2 h-2 bg-amber-500 dark:bg-amber-400 rounded-full animate-ping"></div>
                                    <span>Awaiting response</span>
                                </div>
                            @else
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 px-3 py-1.5 rounded-full">
                                    <span>No pending invites</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- My Shared Ideas Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-emerald-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-emerald-500/20 dark:bg-emerald-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">My Shared Ideas</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-emerald-600 dark:group-hover/card:text-emerald-400 transition-colors duration-300">{{ number_format($collaborationStats['shared_ideas']) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                                <span>Open for collaboration</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Ideas Contributed Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-transparent to-purple-600/10 dark:from-purple-400/10 dark:via-transparent dark:to-purple-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-purple-500/20 dark:bg-purple-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Ideas Contributed</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-purple-600 dark:group-hover/card:text-purple-400 transition-colors duration-300">{{ number_format($collaborationStats['contributed_ideas']) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-purple-500 dark:bg-purple-400 rounded-full"></div>
                                <span>Contribution history</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Main Content with Adaptive Layout --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            {{-- Left Column: Active Collaborations & Pending Invites --}}
            <div class="xl:col-span-2 space-y-8">
                {{-- Active Collaborations Section --}}
                <div class="group">
                    <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                        {{-- Header with Modern Typography --}}
                        <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Active Collaborations</h2>
                                    <p class="text-[#9B9EA4] text-sm">Ideas you're currently collaborating on</p>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Collaborations List --}}
                        <div class="p-8">
                            @if(count($activeCollaborations) > 0)
                                <div class="space-y-4">
                                    @foreach($activeCollaborations as $collaboration)
                                        <div class="group/collab relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-1 p-6">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1">
                                                    <h3 class="font-semibold text-lg text-[#231F20] dark:text-zinc-100 mb-2">
                                                        <a href="{{ route('ideas.show', $collaboration['idea_id']) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-300">
                                                            {{ $collaboration['idea_title'] }}
                                                        </a>
                                                    </h3>
                                                    <p class="text-[#9B9EA4] dark:text-zinc-400 mb-3">
                                                        by {{ $collaboration['idea_author'] }}
                                                    </p>
                                                    <div class="flex flex-wrap items-center gap-4 text-sm">
                                                        <div class="inline-flex items-center space-x-2 text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 rounded-full">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            </svg>
                                                            <span>Joined {{ $collaboration['joined_at']->diffForHumans() }}</span>
                                                        </div>
                                                        <div class="inline-flex items-center space-x-2 text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-3 py-1.5 rounded-full">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                            </svg>
                                                            <span>{{ ucfirst($collaboration['role']) }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <flux:button size="sm" href="{{ route('ideas.show', $collaboration['idea_id']) }}" wire:navigate class="ml-4">
                                                    View Idea
                                                </flux:button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-16 relative">
                                    <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-indigo-600 dark:from-blue-400 dark:to-indigo-500 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                    
                                    <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">No Active Collaborations</h3>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg leading-relaxed mb-8 max-w-md mx-auto">
                                        Browse innovative ideas and join collaborations to start contributing to exciting projects.
                                    </p>
                                    
                                    <flux:button href="{{ route('ideas.index') }}" wire:navigate class="shadow-lg">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        Browse Ideas
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Pending Invites Section --}}
                @if(count($pendingInvites) > 0)
                    <div class="group">
                        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                            {{-- Animated Border for Pending Invites --}}
                            <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/20 via-amber-500/10 to-[#FFF200]/20 blur-sm"></div>
                            
                            <div class="relative">
                                {{-- Header --}}
                                <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-amber-500 rounded-2xl flex items-center justify-center shadow-lg">
                                            <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Pending Invitations</h2>
                                            <p class="text-[#9B9EA4] text-sm">{{ count($pendingInvites) }} invitation(s) awaiting your response</p>
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- Invites List --}}
                                <div class="p-8 space-y-4">
                                    @foreach($pendingInvites as $invite)
                                        <div class="group/invite relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#FFF200]/10 via-amber-50/50 to-[#FFF200]/10 dark:from-amber-900/20 dark:via-amber-800/30 dark:to-amber-900/20 border-2 border-[#FFF200]/30 dark:border-amber-500/30 backdrop-blur-sm p-6 hover:shadow-lg transition-all duration-300">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1">
                                                    <h3 class="font-semibold text-xl text-[#231F20] dark:text-zinc-100 mb-2">{{ $invite['idea_title'] }}</h3>
                                                    <div class="flex items-center space-x-4 mb-3">
                                                        <p class="text-[#9B9EA4] dark:text-zinc-400">
                                                            Invited by <span class="font-medium text-[#231F20] dark:text-zinc-100">{{ $invite['inviter_name'] }}</span>
                                                        </p>
                                                        <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $invite['invited_at']->diffForHumans() }}</span>
                                                    </div>
                                                    @if($invite['message'])
                                                        <div class="bg-white/50 dark:bg-zinc-700/50 rounded-xl p-4 mb-4">
                                                            <p class="text-[#231F20] dark:text-zinc-100 italic">"{{ $invite['message'] }}"</p>
                                                        </div>
                                                    @endif
                                                    <div class="inline-flex items-center space-x-2 text-sm font-medium text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-3 py-1.5 rounded-full">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                        </svg>
                                                        <span>Role: {{ ucfirst($invite['role']) }}</span>
                                                    </div>
                                                </div>
                                                <div class="flex space-x-3 ml-6">
                                                    <flux:button variant="primary" wire:click="acceptInvite({{ $invite['id'] }})" class="shadow-lg">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                        Accept
                                                    </flux:button>
                                                    <flux:button variant="subtle" wire:click="declineInvite({{ $invite['id'] }})" class="shadow-lg">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                        Decline
                                                    </flux:button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right Column: My Shared Ideas & Recent Activity --}}
            <div class="space-y-8">
                {{-- My Shared Ideas Section --}}
                <div class="group">
                    <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl h-full">
                        {{-- Header --}}
                        <div class="p-6 border-b border-gray-100/50 dark:border-zinc-700/50">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">My Shared Ideas</h2>
                                    <p class="text-[#9B9EA4] text-sm">Ideas open for collaboration</p>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Ideas List --}}
                        <div class="p-6">
                            @if(count($mySharedIdeas) > 0)
                                <div class="space-y-4 max-h-80 overflow-y-auto">
                                    @foreach($mySharedIdeas as $idea)
                                        <div class="group/idea relative overflow-hidden rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-md transition-all duration-300 p-4">
                                            <h3 class="font-medium text-[#231F20] dark:text-zinc-100 mb-2">
                                                <a href="{{ route('ideas.show', $idea['id']) }}" wire:navigate class="hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors duration-300">
                                                    {{ Str::limit($idea['title'], 50) }}
                                                </a>
                                            </h3>
                                            <div class="flex justify-between items-center">
                                                <div class="flex items-center space-x-4 text-sm">
                                                    <div class="inline-flex items-center space-x-1 text-emerald-600 dark:text-emerald-400">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                        </svg>
                                                        <span>{{ $idea['collaborators_count'] }} collaborators</span>
                                                    </div>
                                                </div>
                                                <span class="text-xs text-[#9B9EA4] dark:text-zinc-400">{{ $idea['last_activity']->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                        </svg>
                                    </div>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">No shared ideas yet.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Recent Activity Section --}}
                <div class="group">
                    <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl h-full">
                        {{-- Header --}}
                        <div class="p-6 border-b border-gray-100/50 dark:border-zinc-700/50">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Recent Activity</h2>
                                    <p class="text-[#9B9EA4] text-sm">Your collaboration timeline</p>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Activity List --}}
                        <div class="p-6">
                            @if(count($recentActivity) > 0)
                                <div class="space-y-4 max-h-80 overflow-y-auto">
                                    @foreach($recentActivity as $activity)
                                        <div class="group/activity relative overflow-hidden rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4">
                                            <div class="flex items-start space-x-3">
                                                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 dark:from-blue-400 dark:to-indigo-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                    </svg>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm text-[#231F20] dark:text-zinc-100 leading-relaxed">{{ $activity['message'] }}</p>
                                                    <p class="text-xs text-[#9B9EA4] dark:text-zinc-400 mt-1">{{ $activity['date']->diffForHumans() }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 dark:from-blue-400 dark:to-indigo-500 rounded-xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                    </div>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">No recent activity.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
