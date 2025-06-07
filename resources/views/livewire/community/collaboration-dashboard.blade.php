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

<div class="space-y-6">
    <!-- Dashboard Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-[#231F20]">Collaboration Dashboard</h2>
            <p class="text-gray-600 mt-1">{{ $idea->title }}</p>
        </div>
        
        <button
            wire:click="refreshStats"
            class="px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 font-medium text-sm"
        >
            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Refresh
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6">
        <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Collaborators</h3>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $collaborationStats['total_collaborators'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Pending</h3>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $collaborationStats['pending_invitations'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Comments</h3>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $collaborationStats['total_comments'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Suggestions</h3>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $collaborationStats['total_suggestions'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
            <div class="flex items-center">
                <div class="p-2 bg-emerald-100 rounded-lg">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Accepted</h3>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $collaborationStats['accepted_suggestions'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
            <div class="flex items-center">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Implemented</h3>
                    <p class="text-2xl font-bold text-[#231F20]">{{ $collaborationStats['implemented_suggestions'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 shadow-lg">
        <div class="border-b border-gray-200">
            <nav class="flex space-x-8 px-6" aria-label="Tabs">
                @foreach(['overview' => 'Overview', 'activity' => 'Recent Activity', 'contributors' => 'Top Contributors', 'trends' => 'Activity Trends'] as $tab => $label)
                    <button
                        wire:click="setActiveTab('{{ $tab }}')"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === $tab ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        <div class="p-6">
            <!-- Overview Tab -->
            @if($activeTab === 'overview')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Engagement Score -->
                    <div class="bg-white/60 backdrop-blur-sm rounded-lg p-6 border border-white/30">
                        <h4 class="text-lg font-medium text-[#231F20] mb-4">Community Engagement</h4>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-blue-600 mb-2">
                                {{ $communityEngagement['engagement_score'] }}
                            </div>
                            <p class="text-gray-600">Engagement Score</p>
                            <div class="mt-4 w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min(($communityEngagement['engagement_score'] / 100) * 100, 100) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white/60 backdrop-blur-sm rounded-lg p-6 border border-white/30">
                        <h4 class="text-lg font-medium text-[#231F20] mb-4">Quick Actions</h4>
                        <div class="space-y-3">
                            <a href="#comments" class="block w-full text-left px-4 py-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                    <span class="font-medium">View Comments</span>
                                </div>
                            </a>
                            
                            <a href="#suggestions" class="block w-full text-left px-4 py-3 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                    </svg>
                                    <span class="font-medium">Manage Suggestions</span>
                                </div>
                            </a>
                            
                            <a href="#collaboration" class="block w-full text-left px-4 py-3 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                    </svg>
                                    <span class="font-medium">Manage Collaborations</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Recent Activity Tab -->
            @if($activeTab === 'activity')
                <div class="space-y-4">
                    <h4 class="text-lg font-medium text-[#231F20]">Recent Activity</h4>
                    
                    @forelse($recentActivity as $activity)
                        <div class="flex items-start space-x-4 p-4 bg-white/60 backdrop-blur-sm rounded-lg border border-white/30">
                            <div class="w-8 h-8 bg-gradient-to-r {{ $this->getActivityColor($activity['color']) }} rounded-full flex items-center justify-center text-white flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $this->getActivityIcon($activity['type']) }}" />
                                </svg>
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-[#231F20]">
                                    <span class="font-medium">{{ $activity['user']->name }}</span>
                                    {{ $activity['action'] }}
                                </p>
                                <p class="text-sm text-gray-600 mt-1">{{ $activity['content'] }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ $activity['created_at']->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3 class="text-lg font-medium text-[#231F20] mb-2">No recent activity</h3>
                            <p class="text-gray-500">Start collaborating to see activity here!</p>
                        </div>
                    @endforelse
                </div>
            @endif

            <!-- Top Contributors Tab -->
            @if($activeTab === 'contributors')
                <div class="space-y-4">
                    <h4 class="text-lg font-medium text-[#231F20]">Top Contributors</h4>
                    
                    @forelse($communityEngagement['top_contributors'] as $index => $contributor)
                        <div class="flex items-center space-x-4 p-4 bg-white/60 backdrop-blur-sm rounded-lg border border-white/30">
                            <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                #{{ $index + 1 }}
                            </div>
                            
                            <div class="w-10 h-10 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full flex items-center justify-center text-white font-medium">
                                {{ $contributor['user']->initials() }}
                            </div>
                            
                            <div class="flex-1">
                                <h5 class="font-medium text-[#231F20]">{{ $contributor['user']->name }}</h5>
                                <div class="flex items-center space-x-4 text-sm text-gray-600">
                                    <span>{{ $contributor['comments'] }} comments</span>
                                    <span>{{ $contributor['suggestions'] }} suggestions</span>
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <div class="text-lg font-bold text-blue-600">{{ $contributor['total'] }}</div>
                                <div class="text-xs text-gray-500">contributions</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <h3 class="text-lg font-medium text-[#231F20] mb-2">No contributors yet</h3>
                            <p class="text-gray-500">Encourage community participation!</p>
                        </div>
                    @endforelse
                </div>
            @endif

            <!-- Activity Trends Tab -->
            @if($activeTab === 'trends')
                <div class="space-y-4">
                    <h4 class="text-lg font-medium text-[#231F20]">7-Day Activity Trend</h4>
                    
                    <div class="bg-white/60 backdrop-blur-sm rounded-lg p-6 border border-white/30">
                        <div class="flex items-end space-x-2 h-40">
                            @foreach($communityEngagement['activity_trend'] as $day)
                                <div class="flex-1 flex flex-col items-center">
                                    <div class="w-full flex flex-col items-center space-y-1">
                                        @if($day['comments'] > 0)
                                            <div 
                                                class="w-full bg-blue-500 rounded-t"
                                                style="height: {{ ($day['comments'] / max($communityEngagement['activity_trend']->max('total'), 1)) * 120 }}px"
                                                title="{{ $day['comments'] }} comments"
                                            ></div>
                                        @endif
                                        @if($day['suggestions'] > 0)
                                            <div 
                                                class="w-full bg-yellow-500 {{ $day['comments'] > 0 ? '' : 'rounded-t' }}"
                                                style="height: {{ ($day['suggestions'] / max($communityEngagement['activity_trend']->max('total'), 1)) * 120 }}px"
                                                title="{{ $day['suggestions'] }} suggestions"
                                            ></div>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 mt-2 text-center">
                                        {{ $day['date'] }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="flex items-center justify-center space-x-6 mt-4 text-sm">
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 bg-blue-500 rounded"></div>
                                <span>Comments</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 bg-yellow-500 rounded"></div>
                                <span>Suggestions</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show"
             class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('message') }}
        </div>
    @endif
</div>
