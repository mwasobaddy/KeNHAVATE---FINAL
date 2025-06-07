<?php

use Livewire\Volt\Component;

new class extends Component
{
    // This page is a thin wrapper around existing points components
    // All functionality is handled by the reusable components
}; ?>

{{-- Enhanced Points & History Page with Glass Morphism & Modern UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-emerald-500/20 dark:bg-emerald-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/30 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Enhanced Page Header with Glass Morphism --}}
        <section aria-labelledby="points-header" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Header Background Pattern --}}
                <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-emerald-500/10 via-[#FFF200]/5 to-transparent dark:from-emerald-400/10 dark:via-yellow-400/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl"></div>
                
                <div class="relative z-10 p-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-6">
                            {{-- Icon with Glow Effect --}}
                            <div class="relative">
                                <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 flex items-center justify-center shadow-xl">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="absolute -inset-3 bg-emerald-500/20 dark:bg-emerald-400/30 rounded-3xl blur-xl opacity-60 animate-pulse"></div>
                            </div>
                            
                            {{-- Enhanced Typography --}}
                            <div>
                                <h1 id="points-header" class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">Points & History</h1>
                                <p class="text-lg text-[#9B9EA4] dark:text-zinc-400 leading-relaxed">Track your points, achievements, and contribution history</p>
                            </div>
                        </div>
                        
                        {{-- Enhanced Action Badge --}}
                        <div class="hidden sm:flex items-center space-x-3">
                            <div class="flex items-center space-x-2 bg-emerald-50 dark:bg-emerald-900/30 px-4 py-3 rounded-2xl border border-emerald-200 dark:border-emerald-700/50 shadow-lg">
                                <div class="w-3 h-3 bg-emerald-500 dark:bg-emerald-400 rounded-full animate-pulse"></div>
                                <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Live Points Tracking</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Main Content Grid with Glass Morphism --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
            {{-- Points Widget Section --}}
            <div class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl h-full">
                    {{-- Widget Header --}}
                    <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Your Points</h3>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Current balance and recent activity</p>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Points Widget Content --}}
                    <div class="p-8">
                        <livewire:components.points-widget />
                    </div>
                </div>
            </div>
            
            {{-- Points History Section --}}
            <div class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl h-full">
                    {{-- History Header --}}
                    <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Points History</h3>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Detailed transaction log and trends</p>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Points History Content --}}
                    <div class="p-8">
                        <livewire:components.points-history />
                    </div>
                </div>
            </div>
        </div>

        {{-- Additional Quick Stats Section --}}
        <section aria-labelledby="quick-stats-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Animated Background Elements --}}
                <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl"></div>
                
                <div class="relative z-10 p-8">
                    {{-- Enhanced Header --}}
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 id="quick-stats-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Quick Actions</h3>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Navigate to related gamification features</p>
                        </div>
                    </div>
                    
                    {{-- Enhanced Quick Action Buttons Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <flux:button href="{{ route('gamification.leaderboard') }}" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 p-5 h-full">
                            <span class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-blue-600/20 dark:from-blue-400/20 dark:to-blue-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                            <div class="relative flex flex-col items-center justify-center space-y-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                    </svg>
                                </div>
                                <span class="text-[#231F20] dark:text-zinc-100 font-semibold text-lg">View Leaderboard</span>
                            </div>
                        </flux:button>
                        
                        <flux:button href="{{ route('gamification.achievements') }}" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 p-5 h-full">
                            <span class="absolute inset-0 bg-gradient-to-br from-purple-500/10 to-purple-600/20 dark:from-purple-400/20 dark:to-purple-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                            <div class="relative flex flex-col items-center justify-center space-y-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                </div>
                                <span class="text-[#231F20] dark:text-zinc-100 font-semibold text-lg">View Achievements</span>
                            </div>
                        </flux:button>
                        
                        <flux:button href="{{ route('dashboard') }}" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 p-5 h-full">
                            <span class="absolute inset-0 bg-gradient-to-br from-emerald-500/10 to-emerald-600/20 dark:from-emerald-400/20 dark:to-emerald-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                            <div class="relative flex flex-col items-center justify-center space-y-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2v0a2 2 0 012-2h6l2 2h6a2 2 0 012 2v1M3 7l9 6 9-6"/>
                                    </svg>
                                </div>
                                <span class="text-[#231F20] dark:text-zinc-100 font-semibold text-lg">Back to Dashboard</span>
                            </div>
                        </flux:button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
