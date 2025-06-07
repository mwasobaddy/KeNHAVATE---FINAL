<?php

use Livewire\Volt\Component;
use App\Models\Idea;
use App\Models\Collaboration;
use App\Models\Comment;
use App\Models\Suggestion;

new class extends Component {
    public $idea;
    public $activeTab = 'overview';
    public $collaborationStats = [];
    public $recentActivity = [];
    public $communityEngagement = [];

    public function mount(Idea $idea)
    {
        $this->idea = $idea;
        $this->loadCollaborationStats();
        $this->loadRecentActivity();
        $this->loadCommunityEngagement();
    }

    public function loadCollaborationStats()
    {
        $this->collaborationStats = [
            'total_collaborators' => $this->idea->collaborations()->whereIn('status', ['accepted', 'active'])->count(),
            'pending_invitations' => $this->idea->collaborations()->where('status', 'pending')->count(),
            'total_comments' => $this->idea->comments()->count(),
            'total_suggestions' => $this->idea->suggestions()->count(),
            'accepted_suggestions' => $this->idea->suggestions()->where('status', 'accepted')->count(),
            'implemented_suggestions' => $this->idea->suggestions()->where('status', 'implemented')->count(),
        ];
    }

    public function loadRecentActivity()
    {
        // Combine recent comments, suggestions, and collaborations
        $activities = collect();

        // Recent comments
        $recentComments = $this->idea->comments()
            ->with('author')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($comment) {
                return [
                    'type' => 'comment',
                    'user' => $comment->author,
                    'action' => 'added a comment',
                    'content' => Str::limit($comment->content, 100),
                    'created_at' => $comment->created_at,
                    'icon' => 'chat',
                    'color' => 'blue',
                ];
            });

        // Recent suggestions
        $recentSuggestions = $this->idea->suggestions()
            ->with('author')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($suggestion) {
                return [
                    'type' => 'suggestion',
                    'user' => $suggestion->author,
                    'action' => 'submitted a suggestion',
                    'content' => Str::limit($suggestion->content, 100),
                    'created_at' => $suggestion->created_at,
                    'icon' => 'lightbulb',
                    'color' => 'yellow',
                ];
            });

        // Recent collaborations
        $recentCollaborations = $this->idea->collaborations()
            ->with(['collaborator', 'inviter'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($collaboration) {
                $action = match($collaboration->status) {
                    'pending' => 'was invited to collaborate',
                    'accepted' => 'accepted collaboration invitation',
                    'declined' => 'declined collaboration invitation',
                    'removed' => 'was removed from collaboration',
                    default => 'updated collaboration status',
                };

                return [
                    'type' => 'collaboration',
                    'user' => $collaboration->collaborator,
                    'action' => $action,
                    'content' => "Role: " . ucfirst(str_replace('_', ' ', $collaboration->role)),
                    'created_at' => $collaboration->updated_at ?? $collaboration->created_at,
                    'icon' => 'users',
                    'color' => 'green',
                ];
            });

        // Merge and sort by date
        $this->recentActivity = $activities
            ->merge($recentComments)
            ->merge($recentSuggestions)
            ->merge($recentCollaborations)
            ->sortByDesc('created_at')
            ->take(10)
            ->values();
    }

    public function loadCommunityEngagement()
    {
        $this->communityEngagement = [
            'top_contributors' => $this->getTopContributors(),
            'engagement_score' => $this->calculateEngagementScore(),
            'activity_trend' => $this->getActivityTrend(),
        ];
    }

    private function getTopContributors()
    {
        // Get users with most comments and suggestions
        $commentCounts = $this->idea->comments()
            ->selectRaw('author_id, COUNT(*) as comment_count')
            ->groupBy('author_id')
            ->pluck('comment_count', 'author_id');

        $suggestionCounts = $this->idea->suggestions()
            ->selectRaw('author_id, COUNT(*) as suggestion_count')
            ->groupBy('author_id')
            ->pluck('suggestion_count', 'author_id');

        $contributors = collect();
        
        foreach ($commentCounts as $userId => $commentCount) {
            $suggestionCount = $suggestionCounts->get($userId, 0);
            $contributors->put($userId, $commentCount + $suggestionCount);
        }

        foreach ($suggestionCounts as $userId => $suggestionCount) {
            if (!$contributors->has($userId)) {
                $contributors->put($userId, $suggestionCount);
            }
        }

        return $contributors
            ->sortDesc()
            ->take(5)
            ->map(function ($totalContributions, $userId) use ($commentCounts, $suggestionCounts) {
                $user = \App\Models\User::find($userId);
                return [
                    'user' => $user,
                    'comments' => $commentCounts->get($userId, 0),
                    'suggestions' => $suggestionCounts->get($userId, 0),
                    'total' => $totalContributions,
                ];
            })
            ->values();
    }

    private function calculateEngagementScore()
    {
        $comments = $this->idea->comments()->count();
        $suggestions = $this->idea->suggestions()->count();
        $collaborators = $this->idea->collaborations()->whereIn('status', ['accepted', 'active'])->count();
        
        // Simple engagement score calculation
        return ($comments * 1) + ($suggestions * 2) + ($collaborators * 3);
    }

    private function getActivityTrend()
    {
        // Get activity for the last 7 days
        $days = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayActivity = [
                'date' => $date->format('M j'),
                'comments' => $this->idea->comments()->whereDate('created_at', $date)->count(),
                'suggestions' => $this->idea->suggestions()->whereDate('created_at', $date)->count(),
            ];
            $dayActivity['total'] = $dayActivity['comments'] + $dayActivity['suggestions'];
            $days->push($dayActivity);
        }
        
        return $days;
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function refreshStats()
    {
        $this->loadCollaborationStats();
        $this->loadRecentActivity();
        $this->loadCommunityEngagement();
        
        session()->flash('message', 'Statistics refreshed!');
    }

    public function getActivityIcon($type)
    {
        return match($type) {
            'comment' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
            'suggestion' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
            'collaboration' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z',
            default => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        };
    }

    public function getActivityColor($color)
    {
        return match($color) {
            'blue' => 'from-blue-500 to-blue-600',
            'yellow' => 'from-yellow-500 to-yellow-600',
            'green' => 'from-green-500 to-green-600',
            default => 'from-gray-500 to-gray-600',
        };
    }
}; ?>

{{-- Modern Collaboration Dashboard with Glass Morphism & Enhanced UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">

        {{-- Enhanced Dashboard Header --}}
        <section aria-labelledby="dashboard-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Header Background Pattern --}}
                <div class="absolute top-0 right-0 w-96 h-32 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-48 -mt-16 blur-2xl"></div>
                
                <div class="relative z-10 p-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 id="dashboard-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Collaboration Dashboard</h2>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">{{ $idea->title }}</p>
                            </div>
                        </div>
                        
                        <button
                            wire:click="refreshStats"
                            class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 text-white shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-6 py-3"
                        >
                            <span class="absolute inset-0 bg-gradient-to-br from-blue-400/20 to-blue-600/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                            <div class="relative flex items-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span class="font-medium">Refresh</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Statistics Cards with Glass Morphism --}}
        <section aria-labelledby="stats-heading" class="group">
            <h3 id="stats-heading" class="sr-only">Collaboration Statistics</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6">
                {{-- Collaborators Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Collaborators</p>
                            <p class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-blue-600 dark:group-hover/card:text-blue-400 transition-colors duration-300">{{ number_format($collaborationStats['total_collaborators']) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full animate-pulse"></div>
                                <span>Active contributors</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pending Invitations Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-yellow-500/5 via-transparent to-yellow-600/10 dark:from-yellow-400/10 dark:via-transparent dark:to-yellow-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-yellow-500 to-yellow-600 dark:from-yellow-400 dark:to-yellow-500 flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-yellow-500/20 dark:bg-yellow-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Pending</p>
                            <p class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-yellow-600 dark:group-hover/card:text-yellow-400 transition-colors duration-300">{{ number_format($collaborationStats['pending_invitations']) }}</p>
                            
                            @if($collaborationStats['pending_invitations'] > 0)
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-yellow-600 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/30 px-3 py-1.5 rounded-full">
                                    <div class="w-2 h-2 bg-yellow-500 dark:bg-yellow-400 rounded-full animate-ping"></div>
                                    <span>Awaiting response</span>
                                </div>
                            @else
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 px-3 py-1.5 rounded-full">
                                    <span>No pending invitations</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Comments Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-green-500/5 via-transparent to-green-600/10 dark:from-green-400/10 dark:via-transparent dark:to-green-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-green-500 to-green-600 dark:from-green-400 dark:to-green-500 flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-green-500/20 dark:bg-green-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Comments</p>
                            <p class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-green-600 dark:group-hover/card:text-green-400 transition-colors duration-300">{{ number_format($collaborationStats['total_comments']) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30 px-3 py-1.5 rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                                <span>Community discussions</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Suggestions Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-transparent to-purple-600/10 dark:from-purple-400/10 dark:via-transparent dark:to-purple-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-purple-500/20 dark:bg-purple-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Suggestions</p>
                            <p class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-purple-600 dark:group-hover/card:text-purple-400 transition-colors duration-300">{{ number_format($collaborationStats['total_suggestions']) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-purple-500 dark:bg-purple-400 rounded-full"></div>
                                <span>Innovation ideas</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Accepted Suggestions Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-emerald-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-emerald-500/20 dark:bg-emerald-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Accepted</p>
                            <p class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-emerald-600 dark:group-hover/card:text-emerald-400 transition-colors duration-300">{{ number_format($collaborationStats['accepted_suggestions']) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-emerald-500 dark:bg-emerald-400 rounded-full animate-pulse"></div>
                                <span>Approved suggestions</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Implemented Suggestions Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 via-transparent to-indigo-600/10 dark:from-indigo-400/10 dark:via-transparent dark:to-indigo-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-600 dark:from-indigo-400 dark:to-indigo-500 flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-indigo-500/20 dark:bg-indigo-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Implemented</p>
                            <p class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-indigo-600 dark:group-hover/card:text-indigo-400 transition-colors duration-300">{{ number_format($collaborationStats['implemented_suggestions']) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1.5 rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span>Successfully deployed</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Tab Navigation with Modern Design --}}
        <section aria-labelledby="content-tabs" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Tab Navigation --}}
                <div class="border-b border-gray-100/50 dark:border-zinc-700/50">
                    <nav class="flex space-x-8 px-8 py-6" aria-label="Dashboard Tabs">
                        @foreach(['overview' => ['Overview', 'view-grid'], 'activity' => ['Recent Activity', 'clock'], 'contributors' => ['Top Contributors', 'star'], 'trends' => ['Activity Trends', 'trending-up']] as $tab => [$label, $icon])
                            <button
                                wire:click="setActiveTab('{{ $tab }}')"
                                class="group/tab relative flex items-center space-x-3 py-3 px-1 border-b-2 font-medium text-sm transition-all duration-300 {{ $activeTab === $tab ? 'border-[#FFF200] text-[#231F20] dark:text-zinc-100' : 'border-transparent text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-100 hover:border-gray-300 dark:hover:border-zinc-600' }}"
                            >
                                <svg class="w-5 h-5 transition-transform duration-300 group-hover/tab:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    @if($icon === 'view-grid')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                    @elseif($icon === 'clock')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    @elseif($icon === 'star')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                    @endif
                                </svg>
                                <span>{{ $label }}</span>
                                @if($activeTab === $tab)
                                    <div class="absolute -bottom-2 left-0 right-0 h-0.5 bg-[#FFF200] rounded-full"></div>
                                @endif
                            </button>
                        @endforeach
                    </nav>
                </div>

                {{-- Tab Content --}}
                <div class="p-8">
                    {{-- Overview Tab --}}
                    @if($activeTab === 'overview')
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            {{-- Engagement Score --}}
                            <div class="group/widget relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transition-all duration-300 p-6">
                                <div class="flex items-center space-x-4 mb-6">
                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-2xl flex items-center justify-center shadow-lg">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-bold text-[#231F20] dark:text-zinc-100">Community Engagement</h4>
                                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Overall activity metrics</p>
                                    </div>
                                </div>
                                
                                <div class="text-center">
                                    <div class="text-5xl font-bold text-blue-600 dark:text-blue-400 mb-3">
                                        {{ $communityEngagement['engagement_score'] }}
                                    </div>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 mb-4">Engagement Score</p>
                                    <div class="w-full bg-gray-200 dark:bg-zinc-700 rounded-full h-3 overflow-hidden">
                                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 h-3 rounded-full transition-all duration-1000 ease-out" style="width: {{ min(($communityEngagement['engagement_score'] / 100) * 100, 100) }}%"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Quick Actions --}}
                            <div class="group/widget relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transition-all duration-300 p-6">
                                <div class="flex items-center space-x-4 mb-6">
                                    <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                        <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-bold text-[#231F20] dark:text-zinc-100">Quick Actions</h4>
                                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Navigate to key features</p>
                                    </div>
                                </div>
                                
                                <div class="space-y-3">
                                    <a href="#comments" class="group block w-full text-left px-4 py-3 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded-xl transition-all duration-300 transform hover:-translate-y-1 border border-blue-200 dark:border-blue-700/50">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                            </svg>
                                            <span class="font-medium text-[#231F20] dark:text-zinc-100">View Comments</span>
                                        </div>
                                    </a>
                                    
                                    <a href="#suggestions" class="group block w-full text-left px-4 py-3 bg-yellow-50 dark:bg-yellow-900/20 hover:bg-yellow-100 dark:hover:bg-yellow-900/40 rounded-xl transition-all duration-300 transform hover:-translate-y-1 border border-yellow-200 dark:border-yellow-700/50">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                            </svg>
                                            <span class="font-medium text-[#231F20] dark:text-zinc-100">Manage Suggestions</span>
                                        </div>
                                    </a>
                                    
                                    <a href="#collaboration" class="group block w-full text-left px-4 py-3 bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/40 rounded-xl transition-all duration-300 transform hover:-translate-y-1 border border-green-200 dark:border-green-700/50">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                            </svg>
                                            <span class="font-medium text-[#231F20] dark:text-zinc-100">Manage Collaborations</span>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Recent Activity Tab --}}
                    @if($activeTab === 'activity')
                        <div class="space-y-6">
                            <div class="flex items-center space-x-4 mb-6">
                                <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 dark:from-green-400 dark:to-green-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Recent Activity</h4>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Latest collaboration updates</p>
                                </div>
                            </div>
                            
                            @forelse($recentActivity as $activity)
                                <div class="group/activity relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-1 p-6">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-10 h-10 bg-gradient-to-r {{ $this->getActivityColor($activity['color']) }} rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-lg">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $this->getActivityIcon($activity['type']) }}"/>
                                            </svg>
                                        </div>
                                        
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-[#231F20] dark:text-zinc-100 mb-1">
                                                <span class="font-semibold">{{ $activity['user']->name }}</span>
                                                <span class="text-[#9B9EA4] dark:text-zinc-400">{{ $activity['action'] }}</span>
                                            </p>
                                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mb-2">{{ $activity['content'] }}</p>
                                            <div class="flex items-center space-x-2 text-xs text-[#9B9EA4] dark:text-zinc-500">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <span>{{ $activity['created_at']->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-16 relative">
                                    <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                        <svg class="w-8 h-8 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    
                                    <h3 class="text-xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">No Recent Activity</h3>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed max-w-md mx-auto">
                                        Start collaborating to see activity updates here! Invite contributors, add comments, or submit suggestions.
                                    </p>
                                </div>
                            @endforelse
                        </div>
                    @endif

                    {{-- Top Contributors Tab --}}
                    @if($activeTab === 'contributors')
                        <div class="space-y-6">
                            <div class="flex items-center space-x-4 mb-6">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-500 dark:from-purple-400 dark:to-pink-400 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Top Contributors</h4>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Most active community members</p>
                                </div>
                            </div>
                            
                            @forelse($communityEngagement['top_contributors'] as $index => $contributor)
                                <div class="group/contributor relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-1 p-6">
                                    <div class="flex items-center space-x-4">
                                        <div class="relative">
                                            <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-pink-500 dark:from-purple-400 dark:to-pink-400 rounded-full flex items-center justify-center text-white font-bold text-sm shadow-lg">
                                                #{{ $index + 1 }}
                                            </div>
                                            @if($index === 0)
                                                <div class="absolute -top-1 -right-1 w-4 h-4 bg-[#FFF200] dark:bg-yellow-400 rounded-full flex items-center justify-center">
                                                    <svg class="w-2 h-2 text-[#231F20]" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        
                                        <div class="w-12 h-12 bg-gradient-to-r from-blue-600 to-purple-600 dark:from-blue-400 dark:to-purple-400 rounded-2xl flex items-center justify-center text-white font-semibold shadow-lg">
                                            {{ $contributor['user']->initials() ?? substr($contributor['user']->name, 0, 1) }}
                                        </div>
                                        
                                        <div class="flex-1">
                                            <h5 class="font-semibold text-[#231F20] dark:text-zinc-100 text-lg">{{ $contributor['user']->name }}</h5>
                                            <div class="flex items-center space-x-4 text-sm text-[#9B9EA4] dark:text-zinc-400 mt-1">
                                                <div class="flex items-center space-x-1">
                                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                                    </svg>
                                                    <span>{{ $contributor['comments'] }} comments</span>
                                                </div>
                                                <div class="flex items-center space-x-1">
                                                    <svg class="w-4 h-4 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                                    </svg>
                                                    <span>{{ $contributor['suggestions'] }} suggestions</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="text-right">
                                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $contributor['total'] }}</div>
                                            <div class="text-xs text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wide font-medium">Total Contributions</div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-16 relative">
                                    <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                        <svg class="w-8 h-8 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                    
                                    <h3 class="text-xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">No Contributors Yet</h3>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed max-w-md mx-auto">
                                        Encourage community participation by inviting collaborators and promoting active discussions!
                                    </p>
                                </div>
                            @endforelse
                        </div>
                    @endif

                    {{-- Activity Trends Tab --}}
                    @if($activeTab === 'trends')
                        <div class="space-y-6">
                            <div class="flex items-center space-x-4 mb-6">
                                <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-500 dark:from-emerald-400 dark:to-teal-400 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">7-Day Activity Trend</h4>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Weekly collaboration analytics</p>
                                </div>
                            </div>
                            
                            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg p-8">
                                <div class="flex items-end justify-center space-x-3 h-48 mb-6">
                                    @foreach($communityEngagement['activity_trend'] as $day)
                                        <div class="flex-1 flex flex-col items-center max-w-16">
                                            <div class="w-full flex flex-col items-center space-y-1 relative group">
                                                @if($day['comments'] > 0)
                                                    <div 
                                                        class="w-full bg-gradient-to-t from-blue-500 to-blue-400 dark:from-blue-600 dark:to-blue-500 rounded-t-lg shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
                                                        style="height: {{ ($day['comments'] / max($communityEngagement['activity_trend']->max('total'), 1)) * 160 }}px"
                                                        title="{{ $day['comments'] }} comments on {{ $day['date'] }}"
                                                    ></div>
                                                @endif
                                                @if($day['suggestions'] > 0)
                                                    <div 
                                                        class="w-full bg-gradient-to-t from-yellow-500 to-yellow-400 dark:from-yellow-600 dark:to-yellow-500 {{ $day['comments'] > 0 ? '' : 'rounded-t-lg' }} rounded-b-lg shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
                                                        style="height: {{ ($day['suggestions'] / max($communityEngagement['activity_trend']->max('total'), 1)) * 160 }}px"
                                                        title="{{ $day['suggestions'] }} suggestions on {{ $day['date'] }}"
                                                    ></div>
                                                @endif
                                                @if($day['total'] === 0)
                                                    <div class="w-full h-2 bg-gray-200 dark:bg-zinc-700 rounded-lg"></div>
                                                @endif
                                            </div>
                                            <div class="text-xs text-[#9B9EA4] dark:text-zinc-400 mt-3 text-center font-medium">
                                                {{ $day['date'] }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                
                                <div class="flex items-center justify-center space-x-8 text-sm">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-4 h-4 bg-gradient-to-r from-blue-500 to-blue-400 dark:from-blue-600 dark:to-blue-500 rounded shadow-sm"></div>
                                        <span class="font-medium text-[#231F20] dark:text-zinc-100">Comments</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <div class="w-4 h-4 bg-gradient-to-r from-yellow-500 to-yellow-400 dark:from-yellow-600 dark:to-yellow-500 rounded shadow-sm"></div>
                                        <span class="font-medium text-[#231F20] dark:text-zinc-100">Suggestions</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Enhanced Flash Messages --}}
        @if (session()->has('message'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform translate-y-2"
                 x-transition:enter-end="opacity-100 transform translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 transform translate-y-0"
                 x-transition:leave-end="opacity-0 transform translate-y-2"
                 class="fixed top-4 right-4 bg-gradient-to-r from-green-500 to-emerald-500 text-white px-6 py-4 rounded-2xl shadow-xl backdrop-blur-sm border border-white/20 z-50">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="font-medium">{{ session('message') }}</span>
                </div>
            </div>
        @endif
    </div>
</div>
