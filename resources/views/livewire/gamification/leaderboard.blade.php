<?php

use Livewire\Volt\Component;

new class extends Component
{
    // This page is a thin wrapper around the existing leaderboard component
    // All functionality is handled by the reusable component
}; ?>

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Enhanced Page Header with Glass Morphism --}}
        <section aria-labelledby="leaderboard-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Animated Background Elements --}}
                <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl"></div>
                
                <div class="relative z-10 p-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-6">
                            {{-- Enhanced Trophy Icon with Glow Effect --}}
                            <div class="relative">
                                <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 flex items-center justify-center shadow-2xl">
                                    <svg class="w-9 h-9 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                    </svg>
                                </div>
                                <div class="absolute -inset-4 bg-[#FFF200]/30 dark:bg-yellow-400/20 rounded-3xl blur-2xl opacity-75"></div>
                            </div>
                            
                            {{-- Enhanced Typography --}}
                            <div>
                                <h1 id="leaderboard-heading" class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">Leaderboard</h1>
                                <p class="text-lg text-[#9B9EA4] dark:text-zinc-400 leading-relaxed">See how you rank among your peers and colleagues</p>
                            </div>
                        </div>
                        
                        {{-- Enhanced Status Badge --}}
                        <div class="hidden md:flex items-center space-x-4">
                            <div class="inline-flex items-center space-x-3 text-sm font-medium text-[#FFF200] bg-gradient-to-r from-[#231F20] to-gray-800 dark:from-zinc-700 dark:to-zinc-600 px-6 py-3 rounded-2xl shadow-lg">
                                <div class="w-3 h-3 bg-[#FFF200] rounded-full animate-pulse"></div>
                                <span>Live Rankings</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Leaderboard Component Container --}}
        <section aria-labelledby="rankings-section" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Subtle Background Pattern --}}
                <div class="absolute inset-0 bg-gradient-to-br from-transparent via-[#F8EBD5]/5 to-[#FFF200]/5 dark:from-transparent dark:via-amber-400/5 dark:to-yellow-400/5"></div>
                
                <div class="relative z-10">
                    <h2 id="rankings-section" class="sr-only">User Rankings and Achievements</h2>
                    {{-- Use the existing leaderboard component in full-page mode --}}
                    <livewire:components.leaderboard :mini="false" :admin-view="false" />
                </div>
            </div>
        </section>
    </div>
</div>

