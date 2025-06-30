<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title};
use App\Models\User;
use App\Models\Idea;
use App\Models\Challenge;
use App\Services\AchievementService;

new #[Layout('components.layouts.app')] #[Title('Innovation Dashboard')] class extends Component
{
    public $showWelcome = true;
    
    public function dismissWelcome()
    {
        $this->showWelcome = false;
    }
    
    public function with(): array
    {
        $user = auth()->user();
        $achievementService = app(AchievementService::class);
        
        return [
            'myIdeas' => Idea::where('author_id', $user->id)->latest()->take(5)->get(),
            'mySubmissions' => [], // TODO: Challenge submissions when implemented
            'availableChallenges' => Challenge::where('status', 'active')
                ->where('deadline', '>', now())
                ->latest()
                ->take(3)
                ->get(),
            'stats' => [
                'total_ideas' => Idea::where('author_id', $user->id)->count(),
                'ideas_in_review' => Idea::where('author_id', $user->id)
                    ->whereIn('current_stage', ['manager_review', 'sme_review', 'board_review'])
                    ->count(),
                'completed_ideas' => Idea::where('author_id', $user->id)
                    ->where('current_stage', 'completed')
                    ->count(),
                'collaboration_invites' => 0, // TODO: Implement when collaboration features are ready
            ],
            'gamification' => [
                'total_points' => $user->totalPoints(),
                'monthly_points' => $user->monthlyPoints(),
                'ranking_position' => $user->getRankingPosition(),
                'achievements_count' => count($achievementService->getUserAchievements($user)),
                'next_milestone' => null, // AchievementService does not have getNextMilestone()
            ],
            'user' => $user
        ];
    }
    
}; ?>


{{-- Modern Innovation Dashboard with Glass Morphism & 2024+ UX Design --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Welcome Header with Personalized Greeting --}}
        @if($showWelcome)
            <div class="group relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Gradient Overlay --}}
                <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/10 via-transparent to-[#F8EBD5]/20 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/10"></div>
                
                <div class="relative p-8 flex items-center justify-between">
                    <div class="flex items-center space-x-6">
                        {{-- Avatar with Glow Effect --}}
                        <div class="relative hidden md:flex">
                            <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                <span class="text-2xl font-bold text-[#231F20] dark:text-zinc-900">{{ $user->initials() }}</span>
                            </div>
                            <div class="absolute -inset-2 bg-[#FFF200]/20 dark:bg-yellow-400/20 rounded-2xl blur-lg -z-10"></div>
                        </div>
                        
                        <div>
                            <h1 class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">
                                Welcome back, {{ $user->name }}! 👋
                            </h1>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">
                                Ready to innovate Kenya's highway infrastructure today?
                                <span class="inline-flex items-center ml-2 text-sm font-medium text-[#FFF200] dark:text-yellow-400 bg-[#231F20] dark:bg-zinc-700 px-3 py-1 rounded-full">
                                    {{ now()->format('l, M j') }}
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    {{-- Dismiss Button --}}
                    <button wire:click="dismissWelcome" class="absolute top-0 right-0 m-[10px] opacity-50 hover:opacity-100 transition-all duration-300 p-2 hover:bg-white/30 dark:hover:bg-zinc-700/30 rounded-xl text-[#231F20] dark:text-zinc-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        {{-- Enhanced Statistics Cards with Glass Morphism --}}
        <section aria-labelledby="stats-heading" class="group">
            <h2 id="stats-heading" class="sr-only">Dashboard Statistics</h2>
            
            {{-- Gamification Navigation Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                {{-- Leaderboard Card --}}
                <a href="{{ route('gamification.leaderboard') }}" wire:navigate class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#FFF200]/20 via-white to-[#F8EBD5]/30 border border-[#FFF200]/30 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300">
                    <div class="p-6">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#FFF200] text-[#231F20] shadow-lg">
                                <flux:icon.trophy class="size-6" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-[#231F20] group-hover:text-[#231F20]/80">Leaderboard</h3>
                                <p class="text-sm text-[#9B9EA4]">View your ranking</p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-2xl font-bold text-[#231F20]">#{{ $gamification['ranking_position'] ?? 'N/A' }}</span>
                            <flux:icon.arrow-right class="size-5 text-[#9B9EA4] group-hover:translate-x-1 transition-transform" />
                        </div>
                    </div>
                </a>

                {{-- Points Card --}}
                <a href="{{ route('gamification.points') }}" wire:navigate class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-green-100 via-white to-green-50 border border-green-200 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300">
                    <div class="p-6">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-500 text-white shadow-lg">
                                <flux:icon.star class="size-6" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-green-900 group-hover:text-green-700">Points & History</h3>
                                <p class="text-sm text-green-600">Track your progress</p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-2xl font-bold text-green-900">{{ number_format($gamification['total_points'] ?? 0) }}</span>
                            <flux:icon.arrow-right class="size-5 text-green-600 group-hover:translate-x-1 transition-transform" />
                        </div>
                    </div>
                </a>

                {{-- Achievements Card --}}
                <a href="{{ route('gamification.achievements') }}" wire:navigate class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-purple-100 via-white to-purple-50 border border-purple-200 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300">
                    <div class="p-6">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-500 text-white shadow-lg">
                                <flux:icon.shield-check class="size-6" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-purple-900 group-hover:text-purple-700">Achievements</h3>
                                <p class="text-sm text-purple-600">Unlock rewards</p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-2xl font-bold text-purple-900">{{ $gamification['achievements_count'] ?? 0 }}</span>
                            <flux:icon.arrow-right class="size-5 text-purple-600 group-hover:translate-x-1 transition-transform" />
                        </div>
                    </div>
                </a>
            </div>
            
            {{-- Original Statistics Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Total Ideas Card --}}
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
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">My Ideas</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-blue-600 dark:group-hover/card:text-blue-400 transition-colors duration-300">{{ number_format($stats['total_ideas']) }}</p>
                            
                            {{-- Enhanced Status Badge --}}
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-emerald-500 dark:bg-emerald-400 rounded-full animate-pulse"></div>
                                <span>Active contributor</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Ideas in Review Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 via-transparent to-amber-600/10 dark:from-amber-400/10 dark:via-transparent dark:to-amber-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-amber-500/20 dark:bg-amber-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">In Review</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-amber-600 dark:group-hover/card:text-amber-400 transition-colors duration-300">{{ number_format($stats['ideas_in_review']) }}</p>
                            
                            @if($stats['ideas_in_review'] > 0)
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-3 py-1.5 rounded-full">
                                    <div class="w-2 h-2 bg-amber-500 dark:bg-amber-400 rounded-full animate-ping"></div>
                                    <span>Awaiting feedback</span>
                                </div>
                            @else
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 px-3 py-1.5 rounded-full">
                                    <div class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full"></div>
                                    <span>All clear</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Completed Ideas Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-emerald-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-emerald-500/20 dark:bg-emerald-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Completed</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-emerald-600 dark:group-hover/card:text-emerald-400 transition-colors duration-300">{{ number_format($stats['completed_ideas']) }}</p>
                            
                            @if($stats['completed_ideas'] > 0)
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 rounded-full">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>Impact achieved</span>
                                </div>
                            @else
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 px-3 py-1.5 rounded-full">
                                    <span>Ready to complete</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Collaboration Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-transparent to-purple-600/10 dark:from-purple-400/10 dark:via-transparent dark:to-purple-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-purple-500/20 dark:bg-purple-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Collaborations</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-purple-600 dark:group-hover/card:text-purple-400 transition-colors duration-300">{{ number_format($stats['collaboration_invites']) }}</p>
                            
                            @if($stats['collaboration_invites'] > 0)
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-3 py-1.5 rounded-full">
                                    <div class="w-2 h-2 bg-purple-500 dark:bg-purple-400 rounded-full animate-bounce"></div>
                                    <span>Pending invites</span>
                                </div>
                            @else
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 px-3 py-1.5 rounded-full">
                                    <span>Open to collaborate</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Main Content with Adaptive Layout --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            {{-- Recent Ideas Section - Responsive 2 columns on xl screens --}}
            <div class="xl:col-span-2 group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl h-full">
                    {{-- Header with Modern Typography --}}
                    <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">My Recent Ideas</h3>
                                    <p class="text-[#9B9EA4] text-sm">Track your innovation journey</p>
                                </div>
                            </div>
                            
                            {{-- Enhanced View All Button --}}
                            <flux:button :href="route('ideas.index')" variant="ghost" size="sm" 
                                        class="group/btn flex items-center space-x-2 text-[#9B9EA4] hover:text-[#231F20] hover:bg-[#F8EBD5]/30 rounded-xl px-4 py-2 transition-all duration-300">
                                <span class="font-medium">View All</span>
                                <svg class="w-4 h-4 transform group-hover/btn:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                            </flux:button>
                        </div>
                    </div>
                    
                    {{-- Ideas List with Enhanced Cards --}}
                    <div class="p-8 space-y-6 max-h-96 overflow-y-auto">
                        @forelse($myIdeas as $idea)
                            <div class="group/idea relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-1">
                                {{-- Status Indicator Strip --}}
                                <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b 
                                    @if($idea->current_stage === 'draft') from-gray-400 to-gray-500
                                    @elseif($idea->current_stage === 'submitted') from-blue-400 to-blue-500
                                    @elseif(in_array($idea->current_stage, ['manager_review', 'sme_review'])) from-amber-400 to-amber-500
                                    @elseif($idea->current_stage === 'board_review') from-purple-400 to-purple-500 
                                    @elseif($idea->current_stage === 'completed') from-emerald-400 to-emerald-500
                                    @else from-gray-400 to-gray-500
                                    @endif"></div>
                                
                                <div class="p-6 pl-8">
                                    <div class="flex justify-between items-start mb-4">
                                        <h4 class="font-bold text-xl text-[#231F20] dark:text-zinc-100 group-hover/idea:text-[#FFF200] dark:group-hover/idea:text-yellow-400 transition-colors duration-300 leading-tight">
                                            {{ $idea->title }}
                                        </h4>
                                        
                                        {{-- Enhanced Status Badge --}}
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold shrink-0 ml-4
                                            @if($idea->current_stage === 'draft') bg-gray-100 dark:bg-gray-700/50 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600
                                            @elseif($idea->current_stage === 'submitted') bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-600
                                            @elseif(in_array($idea->current_stage, ['manager_review', 'sme_review'])) bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-600
                                            @elseif($idea->current_stage === 'board_review') bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-600
                                            @elseif($idea->current_stage === 'completed') bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-600
                                            @else bg-gray-100 dark:bg-gray-700/50 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600
                                            @endif">
                                            {{-- Status Icon --}}
                                            @if($idea->current_stage === 'completed')
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            @elseif(in_array($idea->current_stage, ['manager_review', 'sme_review', 'board_review']))
                                                <div class="w-2 h-2 bg-current rounded-full mr-1 animate-pulse"></div>
                                            @endif
                                            {{ ucwords(str_replace('_', ' ', $idea->current_stage)) }}
                                        </span>
                                    </div>
                                    
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 mb-4 leading-relaxed line-clamp-2">{{ Str::limit($idea->description, 140) }}</p>
                                    
                                    {{-- Enhanced Footer --}}
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center space-x-4 text-xs text-[#9B9EA4] dark:text-zinc-400">
                                            <span class="flex items-center space-x-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span>{{ $idea->created_at->diffForHumans() }}</span>
                                            </span>
                                        </div>
                                        
                                        <a href="#" class="inline-flex items-center space-x-2 text-[#231F20] dark:text-zinc-100 font-semibold hover:text-[#FFF200] dark:hover:text-yellow-400 transition-colors duration-300 group/link">
                                            <span>View Details</span>
                                            <svg class="w-4 h-4 transform group-hover/link:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            {{-- Enhanced Empty State --}}
                            <div class="text-center relative">
                                {{-- Floating Elements --}}
                                <div class="absolute inset-0 flex items-center justify-center opacity-5 dark:opacity-10">
                                    <svg class="w-64 h-64 text-[#FFF200] dark:text-yellow-400" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                    </svg>
                                </div>
                                
                                <div class="relative z-10">
                                    <div class="w-20 h-20 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                                        <svg class="w-10 h-10 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                        </svg>
                                    </div>
                                    
                                    <h4 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">Ready to Innovate?</h4>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 mb-6 max-w-md mx-auto leading-relaxed">
                                        Your journey to transforming Kenya's highway infrastructure starts with a single idea. 
                                        What challenge will you solve today?
                                    </p>
                                    
                                    <flux:button :href="route('ideas.create')" variant="primary"  
                                                class="group bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 hover:from-[#231F20] hover:to-[#231F20] dark:hover:from-zinc-800 dark:hover:to-zinc-700 text-[#231F20] dark:text-zinc-900 hover:text-[#FFF200] dark:hover:text-yellow-400 font-bold px-8 py-4 rounded-2xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        <span>Submit Your First Idea</span>
                                        <svg class="ml-2 w-5 h-5 transform group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                        </svg>
                                    </flux:button>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Available Challenges - Enhanced Sidebar --}}
            <div class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl h-full">
                    {{-- Header --}}
                    <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                        <div class="flex items-center space-x-4 mb-2">
                            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-500 dark:from-orange-400 dark:to-red-400 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Challenges</h3>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Compete & showcase your skills</p>
                            </div>
                        </div>
                        
                        <flux:button href="#" variant="ghost" size="sm" 
                                    class="group/btn flex items-center space-x-2 text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-100 hover:bg-[#F8EBD5]/30 dark:hover:bg-zinc-700/30 rounded-xl px-4 py-2 transition-all duration-300">
                            <span class="font-medium">Explore All</span>
                            <svg class="w-4 h-4 transform group-hover/btn:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </flux:button>
                    </div>

                    {{-- Challenges List --}}
                    <div class="p-8 space-y-6 max-h-96 overflow-y-auto">
                        @forelse($availableChallenges as $challenge)
                            <div class="group/challenge relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-orange-50/30 dark:from-zinc-800/50 dark:to-orange-900/20 border border-orange-200/40 dark:border-orange-600/30 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-1">
                                {{-- Challenge Priority Indicator --}}
                                <div class="absolute top-4 right-4 w-3 h-3 bg-orange-500 dark:bg-orange-400 rounded-full animate-pulse"></div>
                                
                                <div class="p-6">
                                    <h4 class="font-bold text-lg text-[#231F20] dark:text-zinc-100 mb-2 group-hover/challenge:text-orange-600 dark:group-hover/challenge:text-orange-400 transition-colors duration-300 leading-tight">
                                        {{ $challenge->title }}
                                    </h4>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 mb-4 text-sm leading-relaxed line-clamp-3">{{ Str::limit($challenge->description, 120) }}</p>
                                    
                                    {{-- Deadline Info --}}
                                    <div class="flex items-center justify-between mb-4">
                                        <span class="inline-flex items-center text-xs text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/30 px-3 py-1.5 rounded-full font-medium">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            {{ $challenge->deadline?->diffForHumans() }}
                                        </span>
                                    </div>
                                    
                                    <flux:button href="#" variant="primary" size="sm" 
                                                class="w-full group bg-gradient-to-r from-orange-500 to-red-500 dark:from-orange-400 dark:to-red-400 hover:from-[#231F20] hover:to-[#231F20] dark:hover:from-zinc-800 dark:hover:to-zinc-700 text-white dark:text-white font-semibold py-3 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-300">
                                        <span>Join Challenge</span>
                                        <svg class="ml-2 w-4 h-4 transform group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                        </svg>
                                    </flux:button>
                                </div>
                            </div>
                        @empty
                            {{-- Enhanced Empty State for Challenges --}}
                            <div class="text-center py-12 relative">
                                <div class="w-16 h-16 bg-gradient-to-br from-orange-500 to-red-500 dark:from-orange-400 dark:to-red-400 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                                
                                <h4 class="text-lg font-bold text-[#231F20] dark:text-zinc-100 mb-2">No Active Challenges</h4>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed">
                                    New challenges are coming soon.<br>
                                    Stay tuned for exciting opportunities!
                                </p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Enhanced Innovation Tips Section with Modern Glass Design --}}
        <section aria-labelledby="innovation-tips-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Animated Background Elements --}}
                <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-64 h-64 bg-gradient-to-tr from-blue-500/5 via-purple-500/5 to-transparent dark:from-blue-400/10 dark:via-purple-400/10 dark:to-transparent rounded-full -ml-32 -mb-32 blur-2xl"></div>
                
                <div class="relative z-10 p-4 md:p-8">
                    {{-- Enhanced Header --}}
                    <div class="flex items-center space-x-4 mb-8">
                        <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 id="innovation-tips-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Innovation Insights</h3>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Proven strategies from successful innovators</p>
                        </div>
                    </div>
                    
                    {{-- Enhanced Tips Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {{-- Tip 1 - Enhanced with Glass Morphism --}}
                        <div class="group/tip relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-blue-50/30 dark:from-zinc-800/50 dark:to-blue-900/20 border border-blue-200/40 dark:border-blue-600/30 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-2">
                            {{-- Tip Number Badge --}}
                            <div class="absolute top-4 right-4 w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-xl flex items-center justify-center shadow-lg">
                                <span class="font-bold text-sm text-white">1</span>
                            </div>
                            
                            <div class="p-6">
                                {{-- Icon with Glow Effect --}}
                                <div class="relative mb-4">
                                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-2xl flex items-center justify-center shadow-lg">
                                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                    </div>
                                    <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-0 group-hover/tip:opacity-100 transition-opacity duration-500"></div>
                                </div>
                                
                                <h4 class="font-bold text-lg text-[#231F20] dark:text-zinc-100 mb-3 group-hover/tip:text-blue-600 dark:group-hover/tip:text-blue-400 transition-colors duration-300">
                                    Think Big, Start Small
                                </h4>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed">
                                    Great innovations often begin with simple observations. Focus on real problems you've encountered in your daily work at KeNHA.
                                </p>
                                
                                {{-- Insight Badge --}}
                                <div class="mt-4 inline-flex items-center text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 rounded-full">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                    <span>Quick wins first</span>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Tip 2 - Enhanced with Glass Morphism --}}
                        <div class="group/tip relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-purple-50/30 dark:from-zinc-800/50 dark:to-purple-900/20 border border-purple-200/40 dark:border-purple-600/30 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-2">
                            <div class="absolute top-4 right-4 w-8 h-8 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-xl flex items-center justify-center shadow-lg">
                                <span class="font-bold text-sm text-white">2</span>
                            </div>
                            
                            <div class="p-6">
                                <div class="relative mb-4">
                                    <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                    <div class="absolute -inset-2 bg-purple-500/20 dark:bg-purple-400/30 rounded-2xl blur-xl opacity-0 group-hover/tip:opacity-100 transition-opacity duration-500"></div>
                                </div>
                                
                                <h4 class="font-bold text-lg text-[#231F20] dark:text-zinc-100 mb-3 group-hover/tip:text-purple-600 dark:group-hover/tip:text-purple-400 transition-colors duration-300">
                                    Collaborate & Connect
                                </h4>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed">
                                    Some of the best ideas come from collaboration. Connect with colleagues across departments for diverse perspectives and expertise.
                                </p>
                                
                                <div class="mt-4 inline-flex items-center text-xs font-medium text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-3 py-1.5 rounded-full">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <span>Team power</span>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Tip 3 - Enhanced with Glass Morphism --}}
                        <div class="group/tip relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/50 to-emerald-50/30 dark:from-zinc-800/50 dark:to-emerald-900/20 border border-emerald-200/40 dark:border-emerald-600/30 backdrop-blur-sm hover:shadow-lg transition-all duration-500 hover:-translate-y-2">
                            <div class="absolute top-4 right-4 w-8 h-8 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-xl flex items-center justify-center shadow-lg">
                                <span class="font-bold text-sm text-white">3</span>
                            </div>
                            
                            <div class="p-6">
                                <div class="relative mb-4">
                                    <div class="w-14 h-14 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                        </svg>
                                    </div>
                                    <div class="absolute -inset-2 bg-emerald-500/20 dark:bg-emerald-400/30 rounded-2xl blur-xl opacity-0 group-hover/tip:opacity-100 transition-opacity duration-500"></div>
                                </div>
                                
                                <h4 class="font-bold text-lg text-[#231F20] dark:text-zinc-100 mb-3 group-hover/tip:text-emerald-600 dark:group-hover/tip:text-emerald-400 transition-colors duration-300">
                                    Focus on Impact
                                </h4>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed">
                                    Consider how your idea will improve road infrastructure, safety, or efficiency for Kenyan citizens and stakeholders nationwide.
                                </p>
                                
                                <div class="mt-4 inline-flex items-center text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 rounded-full">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                    </svg>
                                    <span>Real value</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Call to Action Footer --}}
                    <div class="mt-8 text-center">
                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm mb-4">
                            Ready to put these insights into action?
                        </p>
                        <flux:button :href="route('ideas.create')" variant="primary" size="sm" 
                                    class="group bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 hover:from-[#231F20] hover:to-[#231F20] dark:hover:from-zinc-800 dark:hover:to-zinc-700 text-[#231F20] dark:text-zinc-900 hover:text-[#FFF200] dark:hover:text-yellow-400 font-semibold px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            <span>Start Your Innovation Journey</span>
                            <svg class="ml-2 w-4 h-4 transform group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </flux:button>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Enhanced Floating Action Button with Advanced Interactions --}}
    <div class="fixed bottom-6 right-6 z-50 group/fab">
        {{-- Main FAB Button --}}
        <flux:button :href="route('ideas.create')" 
                    class="group relative w-16 h-16 bg-gradient-to-br from-[#FFF200] via-[#F8EBD5] to-[#FFF200] dark:from-yellow-400 dark:via-amber-400 dark:to-yellow-400 hover:from-[#231F20] hover:to-[#231F20] dark:hover:from-zinc-800 dark:hover:to-zinc-700 text-[#231F20] dark:text-zinc-900 hover:text-[#FFF200] dark:hover:text-yellow-400 rounded-2xl shadow-2xl hover:shadow-3xl flex items-center justify-center transition-all duration-500 ease-out transform hover:scale-110 hover:-translate-y-2">
            
            {{-- Glow Effect --}}
            <div class="absolute -inset-1 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl blur-lg opacity-60 group-hover:opacity-100 transition-opacity duration-500"></div>
            
            {{-- Icon --}}
            <div class="relative z-10">
                <svg class="w-7 h-7 transform group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                </svg>
            </div>
            
            {{-- Ripple Effect --}}
            <div class="absolute inset-0 rounded-2xl overflow-hidden">
                <div class="absolute inset-0 bg-white/20 dark:bg-yellow-400/20 scale-0 group-hover:scale-100 transition-transform duration-500 rounded-2xl"></div>
            </div>
        </flux:button>
        
        {{-- Enhanced Tooltip --}}
        <div class="absolute right-20 top-1/2 transform -translate-y-1/2 opacity-0 group-hover/fab:opacity-100 transition-all duration-300 translate-x-2 group-hover/fab:translate-x-0 pointer-events-none">
            <div class="relative">
                {{-- Tooltip Background --}}
                <div class="bg-[#231F20] dark:bg-zinc-800 text-[#FFF200] dark:text-yellow-400 px-4 py-2 rounded-xl shadow-xl backdrop-blur-sm text-sm font-semibold whitespace-nowrap">
                    Submit New Idea
                </div>
                
                {{-- Tooltip Arrow --}}
                <div class="absolute top-1/2 -right-1 transform -translate-y-1/2 w-2 h-2 bg-[#231F20] dark:bg-zinc-800 rotate-45"></div>
                
                {{-- Tooltip Glow --}}
                <div class="absolute inset-0 bg-[#231F20] dark:bg-zinc-800 rounded-xl blur-md opacity-50 -z-10"></div>
            </div>
        </div>
        
        {{-- Floating Particles Animation --}}
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute w-1 h-1 bg-[#FFF200] dark:bg-yellow-400 rounded-full animate-ping" style="top: 10%; left: 20%; animation-delay: 0s;"></div>
            <div class="absolute w-1 h-1 bg-[#F8EBD5] dark:bg-amber-400 rounded-full animate-ping" style="top: 80%; right: 10%; animation-delay: 1s;"></div>
            <div class="absolute w-1 h-1 bg-[#FFF200] dark:bg-yellow-400 rounded-full animate-ping" style="bottom: 20%; left: 80%; animation-delay: 2s;"></div>
        </div>
    </div>
    
    {{-- Gamification Achievement Notifications --}}
    <livewire:components.achievement-notifications />
</div>