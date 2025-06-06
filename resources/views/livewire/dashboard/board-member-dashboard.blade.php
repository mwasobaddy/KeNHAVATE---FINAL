<?php

use Livewire\Volt\Component;
use App\Models\Idea;
use App\Models\Review;
use App\Models\Challenge;


new #[Layout('components.layouts.app', title: 'Board Member Dashboard')] class extends Component
{
    public $pendingApprovals;
    public $recentDecisions;
    public $systemStats;
    public $monthlyMetrics;

    public function mount()
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        // Ideas requiring board approval
        $this->pendingApprovals = Idea::where('current_stage', 'board_review')
            ->with(['author', 'category', 'reviews'])
            ->orderBy('submitted_at')
            ->take(10)
            ->get();

        // Recent board decisions
        $this->recentDecisions = Review::where('reviewer_id', auth()->id())
            ->where('review_stage', 'board_review')
            ->with(['idea.author'])
            ->latest()
            ->take(8)
            ->get();

        // High-level system statistics
        $this->systemStats = [
            'total_ideas' => Idea::count(),
            'ideas_this_month' => Idea::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'pending_board_review' => Idea::where('current_stage', 'board_review')->count(),
            'approved_this_month' => Review::where('reviewer_id', auth()->id())
                ->where('review_stage', 'board_review')
                ->where('decision', 'approved')
                ->whereMonth('created_at', now()->month)
                ->count(),
            'total_challenges' => Challenge::count(),
            'active_challenges' => Challenge::where('status', 'active')
                ->where('deadline', '>', now())
                ->count(),
        ];

        // Monthly metrics for trend analysis
        $this->monthlyMetrics = $this->getMonthlyMetrics();
    }

    private function getMonthlyMetrics()
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = [
                'month' => $date->format('M'),
                'ideas' => Idea::whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->count(),
                'approvals' => Review::where('reviewer_id', auth()->id())
                    ->where('review_stage', 'board_review')
                    ->where('decision', 'approved')
                    ->whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->count(),
            ];
        }
        return $months;
    }

    public function quickApprove($ideaId)
    {
        $idea = Idea::findOrFail($ideaId);
        
        // Create board review
        Review::create([
            'idea_id' => $idea->id,
            'reviewer_id' => auth()->id(),
            'review_stage' => 'board_review',
            'decision' => 'approved',
            'rating' => 5,
            'comments' => 'Quick approval - meets strategic objectives',
        ]);

        // Update idea stage
        $idea->update(['current_stage' => 'implementation']);

        $this->loadDashboardData();
        $this->dispatch('idea-approved');
    }

    public function viewIdea($ideaId)
    {
        return redirect()->route('ideas.show', $ideaId);
    }
}; ?>

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 right-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 left-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 right-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Welcome Header with Personalized Greeting --}}
        <div class="group relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
            {{-- Gradient Overlay --}}
            <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/10 via-transparent to-[#F8EBD5]/20 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/10"></div>
            
            <div class="relative p-8 flex items-center justify-between">
                <div class="flex items-center space-x-6">
                    {{-- Avatar with Glow Effect --}}
                    <div class="relative hidden md:flex">
                        <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                            <span class="text-2xl font-bold text-[#231F20] dark:text-zinc-900">{{ auth()->user()->initials() }}</span>
                        </div>
                        <div class="absolute -inset-2 bg-[#FFF200]/20 dark:bg-yellow-400/20 rounded-2xl blur-lg -z-10"></div>
                    </div>
                    
                    <div>
                        <h1 class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">
                            Welcome, {{ auth()->user()->first_name }}! 👋
                        </h1>
                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">
                            Your strategic oversight drives KeNHA's innovation forward.
                            <span class="inline-flex items-center ml-2 text-sm font-medium text-[#FFF200] dark:text-yellow-400 bg-[#231F20] dark:bg-zinc-700 px-3 py-1 rounded-full">
                                {{ now()->format('l, M j') }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Enhanced Statistics Cards with Glass Morphism --}}
        <section aria-labelledby="stats-heading" class="group">
            <h2 id="stats-heading" class="sr-only">Dashboard Statistics</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Pending Approvals Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    {{-- Animated Gradient Background --}}
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        {{-- Icon with Glow Effect --}}
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Pending Approvals</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-blue-600 dark:group-hover/card:text-blue-400 transition-colors duration-300">{{ $systemStats['pending_board_review'] }}</p>
                            
                            {{-- Enhanced Status Badge --}}
                            @if($systemStats['pending_board_review'] > 0)
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 rounded-full">
                                    <div class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full animate-pulse"></div>
                                    <span>Needs attention</span>
                                </div>
                            @else
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 px-3 py-1.5 rounded-full">
                                    <div class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full"></div>
                                    <span>All caught up</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Ideas This Month Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-green-500/5 via-transparent to-green-600/10 dark:from-green-400/10 dark:via-transparent dark:to-green-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-green-500 to-green-600 dark:from-green-400 dark:to-green-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-green-500/20 dark:bg-green-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Ideas This Month</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-green-600 dark:group-hover/card:text-green-400 transition-colors duration-300">{{ $systemStats['ideas_this_month'] }}</p>
                            
                            {{-- Progress Badge --}}
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30 px-3 py-1.5 rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                                <span>Monthly progress</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Approved This Month Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/5 via-transparent to-amber-600/10 dark:from-[#FFF200]/10 dark:via-transparent dark:to-amber-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#FFF200] to-amber-500 dark:from-[#FFF200] dark:to-amber-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-[#FFF200]/20 dark:bg-[#FFF200]/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Approved This Month</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-amber-600 dark:group-hover/card:text-amber-400 transition-colors duration-300">{{ $systemStats['approved_this_month'] }}</p>
                            
                            {{-- Decision Badge --}}
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-3 py-1.5 rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Strategic decisions</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Active Challenges Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-transparent to-purple-600/10 dark:from-purple-400/10 dark:via-transparent dark:to-purple-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-purple-500/20 dark:bg-purple-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Active Challenges</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-purple-600 dark:group-hover/card:text-purple-400 transition-colors duration-300">{{ $systemStats['active_challenges'] }}</p>
                            
                            {{-- Challenge Badge --}}
                            @if($systemStats['active_challenges'] > 0)
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-3 py-1.5 rounded-full">
                                    <div class="w-2 h-2 bg-purple-500 dark:bg-purple-400 rounded-full animate-pulse"></div>
                                    <span>Ongoing competitions</span>
                                </div>
                            @else
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 px-3 py-1.5 rounded-full">
                                    <span>No active challenges</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Monthly Trends with Enhanced Visualization --}}
        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-500/5 via-transparent to-green-500/5 dark:from-blue-400/5 dark:via-transparent dark:to-green-400/5"></div>
            
            <div class="relative px-8 py-6 border-b border-gray-100/50 dark:border-zinc-700/50">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-green-500 dark:from-blue-400 dark:to-green-400 rounded-2xl flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Innovation Trends</h3>
                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Track submission and approval patterns over the last 6 months</p>
                    </div>
                </div>
            </div>
            
            <div class="relative p-8">
                <div class="grid grid-cols-6 gap-4">
                    @foreach($monthlyMetrics as $month)
                        <div class="text-center group">
                            <div class="mb-2">
                                <div class="h-32 flex items-end justify-center space-x-3">
                                    <div class="relative">
                                        <div class="bg-gradient-to-t from-blue-500 to-blue-400 dark:from-blue-600 dark:to-blue-400 rounded-t-lg w-5 transform hover:scale-110 transition-all duration-300" 
                                             style="height: {{ max($month['ideas'] * 5, 10) }}px;" title="Ideas: {{ $month['ideas'] }}">
                                        </div>
                                        <div class="absolute -top-6 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                            <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded">{{ $month['ideas'] }}</span>
                                        </div>
                                    </div>
                                    <div class="relative">
                                        <div class="bg-gradient-to-t from-green-500 to-green-400 dark:from-green-600 dark:to-green-400 rounded-t-lg w-5 transform hover:scale-110 transition-all duration-300" 
                                             style="height: {{ max($month['approvals'] * 10, 5) }}px;" title="Approvals: {{ $month['approvals'] }}">
                                        </div>
                                        <div class="absolute -top-6 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                            <span class="bg-green-500 text-white text-xs px-2 py-1 rounded">{{ $month['approvals'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm font-medium text-[#231F20] dark:text-zinc-100">{{ $month['month'] }}</p>
                            <p class="text-xs text-[#9B9EA4] dark:text-zinc-400">{{ $month['ideas'] }} / {{ $month['approvals'] }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-6 flex justify-center space-x-8 text-sm">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-gradient-to-r from-blue-500 to-blue-400 dark:from-blue-600 dark:to-blue-400 rounded mr-2"></div>
                        <span class="text-[#231F20] dark:text-zinc-100">Ideas Submitted</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-gradient-to-r from-green-500 to-green-400 dark:from-green-600 dark:to-green-400 rounded mr-2"></div>
                        <span class="text-[#231F20] dark:text-zinc-100">Board Approvals</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content Grid --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
            {{-- Pending Strategic Decisions --}}
            <div class="group relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl h-full">
                {{-- Header with Modern Typography --}}
                <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-500 dark:from-blue-400 dark:to-purple-400 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Pending Strategic Decisions</h3>
                            <p class="text-[#9B9EA4] text-sm">Ideas requiring board-level approval</p>
                        </div>
                    </div>
                </div>
                
                {{-- Ideas List --}}
                <div class="p-8 space-y-6 max-h-[32rem] overflow-y-auto">
                    @forelse($pendingApprovals as $idea)
                        <div class="group/idea relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-1">
                            {{-- Status Indicator Strip --}}
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-blue-400 to-purple-500"></div>
                            
                            <div class="p-5 pl-8">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h4 class="font-bold text-lg text-[#231F20] dark:text-zinc-100 group-hover/idea:text-blue-600 dark:group-hover/idea:text-blue-400 transition-colors duration-300 leading-tight">
                                            {{ $idea->title }}
                                        </h4>
                                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">By {{ $idea->author->first_name }} {{ $idea->author->last_name }} · {{ $idea->submitted_at?
