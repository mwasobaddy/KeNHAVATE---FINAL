<?php

use Livewire\Volt\Component;
use App\Services\AnalyticsService;
use App\Models\User;
use App\Models\Idea;
use App\Models\Challenge;
use App\Models\Review;

new #[Layout('components.layouts.app', title: 'Advanced Analytics Dashboard')] class extends Component
{
    public $selectedTimeframe = 'last_30_days';
    public $selectedMetric = 'overview';
    public $selectedChart = 'line';
    public $analyticsData = [];
    public $isLoading = false;

    public function mount()
    {
        $this->loadAnalytics();
    }

    public function loadAnalytics()
    {
        $this->isLoading = true;
        
        $analyticsService = app(AnalyticsService::class);
        
        $this->analyticsData = [
            'overview' => $analyticsService->getSystemOverview(),
            'workflow' => $analyticsService->getIdeaWorkflowAnalytics(),
            'engagement' => $analyticsService->getUserEngagementAnalytics(),
            'performance' => $analyticsService->getPerformanceAnalytics(),
            'gamification' => $analyticsService->getGamificationAnalytics(),
        ];
        
        $this->isLoading = false;
    }

    public function updatedSelectedTimeframe()
    {
        $this->loadAnalytics();
    }

    public function updatedSelectedMetric()
    {
        $this->loadAnalytics();
    }

    public function exportData($format = 'csv')
    {
        $analyticsService = app(AnalyticsService::class);
        $filename = $analyticsService->exportData($this->analyticsData, $format);
        
        $this->dispatch('download-ready', ['filename' => $filename]);
        session()->flash('success', "Analytics data exported as {$format}. Download will start shortly.");
    }

    public function refreshData()
    {
        $this->loadAnalytics();
        session()->flash('success', 'Analytics data refreshed successfully.');
    }

}; ?>

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-gradient-to-r from-green-500/20 to-blue-500/20 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-gradient-to-r from-yellow-500/20 to-orange-500/20 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 lg:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Header Section --}}
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-[#231F20] dark:text-white mb-4">
                Advanced Analytics Dashboard
            </h1>
            <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">
                Comprehensive insights and performance metrics for the KeNHAVATE Innovation Portal
            </p>
        </div>

        {{-- Controls Section --}}
        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
            <div class="flex flex-col lg:flex-row gap-4 items-center justify-between">
                {{-- Timeframe Selector --}}
                <div class="flex items-center space-x-4">
                    <label class="text-sm font-semibold text-[#231F20] dark:text-white">Time Range:</label>
                    <select wire:model.live="selectedTimeframe" 
                            class="bg-white/90 dark:bg-zinc-700/90 backdrop-blur-sm border border-[#9B9EA4]/30 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="last_7_days">Last 7 Days</option>
                        <option value="last_30_days">Last 30 Days</option>
                        <option value="last_3_months">Last 3 Months</option>
                        <option value="last_6_months">Last 6 Months</option>
                        <option value="last_year">Last Year</option>
                    </select>
                </div>

                {{-- Metric Selector --}}
                <div class="flex items-center space-x-4">
                    <label class="text-sm font-semibold text-[#231F20] dark:text-white">Focus Area:</label>
                    <select wire:model.live="selectedMetric" 
                            class="bg-white/90 dark:bg-zinc-700/90 backdrop-blur-sm border border-[#9B9EA4]/30 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="overview">System Overview</option>
                        <option value="workflow">Workflow Analytics</option>
                        <option value="engagement">User Engagement</option>
                        <option value="performance">Performance Metrics</option>
                        <option value="gamification">Gamification Stats</option>
                    </select>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center space-x-3">
                    <button wire:click="refreshData" 
                            class="flex items-center space-x-2 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors duration-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Refresh</span>
                    </button>

                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" 
                                class="flex items-center space-x-2 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors duration-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <span>Export</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" @click.away="open = false"
                             class="absolute right-0 mt-2 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-xl border border-[#9B9EA4]/20 z-50">
                            <div class="py-2">
                                <button wire:click="exportData('csv')" 
                                        class="w-full text-left px-4 py-2 text-sm text-[#231F20] dark:text-white hover:bg-gray-100 dark:hover:bg-zinc-700">
                                    Export as CSV
                                </button>
                                <button wire:click="exportData('excel')" 
                                        class="w-full text-left px-4 py-2 text-sm text-[#231F20] dark:text-white hover:bg-gray-100 dark:hover:bg-zinc-700">
                                    Export as Excel
                                </button>
                                <button wire:click="exportData('pdf')" 
                                        class="w-full text-left px-4 py-2 text-sm text-[#231F20] dark:text-white hover:bg-gray-100 dark:hover:bg-zinc-700">
                                    Export as PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Loading State --}}
        @if($isLoading)
            <div class="text-center py-12">
                <div class="inline-flex items-center space-x-3">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                    <span class="text-[#231F20] dark:text-white">Loading analytics data...</span>
                </div>
            </div>
        @else
            {{-- System Overview Section --}}
            @if($selectedMetric === 'overview' && isset($analyticsData['overview']))
                @php $overview = $analyticsData['overview']; @endphp
                
                {{-- Key Metrics Cards --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                    {{-- Total Users --}}
                    <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider">Total Users</p>
                                <p class="text-3xl font-bold text-[#231F20] dark:text-white">{{ number_format($overview['totals']['users']) }}</p>
                                <p class="text-sm text-green-600 mt-1">
                                    +{{ $overview['current_month']['new_users'] }} this month
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Total Ideas --}}
                    <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider">Total Ideas</p>
                                <p class="text-3xl font-bold text-[#231F20] dark:text-white">{{ number_format($overview['totals']['ideas']) }}</p>
                                <p class="text-sm text-green-600 mt-1">
                                    +{{ $overview['current_month']['new_ideas'] }} this month
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-amber-500 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Total Challenges --}}
                    <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider">Challenges</p>
                                <p class="text-3xl font-bold text-[#231F20] dark:text-white">{{ number_format($overview['totals']['challenges']) }}</p>
                                <p class="text-sm text-green-600 mt-1">
                                    +{{ $overview['current_month']['new_challenges'] }} this month
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Total Reviews --}}
                    <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider">Reviews</p>
                                <p class="text-3xl font-bold text-[#231F20] dark:text-white">{{ number_format($overview['totals']['reviews']) }}</p>
                                <p class="text-sm text-green-600 mt-1">
                                    +{{ $overview['current_month']['reviews_completed'] }} completed
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Total Points --}}
                    <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider">Points Awarded</p>
                                <p class="text-3xl font-bold text-[#231F20] dark:text-white">{{ number_format($overview['totals']['points_awarded']) }}</p>
                                <p class="text-sm text-green-600 mt-1">
                                    +{{ $overview['current_month']['points_awarded'] }} this month
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-500 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Growth Rates Chart --}}
                @if(isset($overview['growth_rates']))
                    <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl mb-8">
                        <h3 class="text-xl font-bold text-[#231F20] dark:text-white mb-6">Growth Rates (Month over Month)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            @foreach($overview['growth_rates'] as $metric => $rate)
                                <div class="text-center">
                                    <div class="relative inline-flex items-center justify-center w-20 h-20 rounded-full {{ $rate >= 0 ? 'bg-green-100 dark:bg-green-900/20' : 'bg-red-100 dark:bg-red-900/20' }} mb-3">
                                        <span class="text-2xl font-bold {{ $rate >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $rate >= 0 ? '+' : '' }}{{ $rate }}%
                                        </span>
                                    </div>
                                    <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider">{{ ucfirst(str_replace('_', ' ', $metric)) }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif

            {{-- Workflow Analytics Section --}}
            @if($selectedMetric === 'workflow' && isset($analyticsData['workflow']))
                @php $workflow = $analyticsData['workflow']; @endphp
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {{-- Stage Distribution --}}
                    @if(isset($workflow['stage_distribution']))
                        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-white mb-6">Ideas by Stage</h3>
                            <div class="space-y-4">
                                @foreach($workflow['stage_distribution'] as $stage => $count)
                                    @php 
                                        $total = array_sum($workflow['stage_distribution']);
                                        $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                                    @endphp
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-4 h-4 bg-blue-500 rounded-full"></div>
                                            <span class="text-sm font-medium text-[#231F20] dark:text-white capitalize">
                                                {{ str_replace('_', ' ', $stage) }}
                                            </span>
                                        </div>
                                        <div class="flex items-center space-x-3">
                                            <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                                            </div>
                                            <span class="text-sm font-bold text-[#231F20] dark:text-white w-12 text-right">{{ $count }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Processing Times --}}
                    @if(isset($workflow['processing_times']))
                        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-white mb-6">Average Processing Time (Hours)</h3>
                            <div class="space-y-4">
                                @foreach($workflow['processing_times'] as $stage => $hours)
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-[#231F20] dark:text-white capitalize">
                                            {{ str_replace('_', ' ', $stage) }}
                                        </span>
                                        <div class="flex items-center space-x-2">
                                            <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                @php $maxHours = max($workflow['processing_times']); @endphp
                                                <div class="bg-green-500 h-2 rounded-full" style="width: {{ $maxHours > 0 ? ($hours / $maxHours) * 100 : 0 }}%"></div>
                                            </div>
                                            <span class="text-sm font-bold text-[#231F20] dark:text-white w-12 text-right">{{ round($hours, 1) }}h</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Conversion Rates & Bottlenecks --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
                    @if(isset($workflow['conversion_rates']))
                        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-white mb-6">Conversion Rates</h3>
                            <div class="space-y-4">
                                @foreach($workflow['conversion_rates'] as $conversion => $rate)
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-[#231F20] dark:text-white">
                                            {{ ucfirst(str_replace('_', ' → ', $conversion)) }}
                                        </span>
                                        <div class="flex items-center space-x-2">
                                            <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $rate }}%"></div>
                                            </div>
                                            <span class="text-sm font-bold text-[#231F20] dark:text-white w-12 text-right">{{ $rate }}%</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(isset($workflow['bottlenecks']) && count($workflow['bottlenecks']) > 0)
                        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-white mb-6">Identified Bottlenecks</h3>
                            <div class="space-y-4">
                                @foreach($workflow['bottlenecks'] as $bottleneck)
                                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-red-800 dark:text-red-200 capitalize">
                                                {{ str_replace('_', ' ', $bottleneck['stage']) }}
                                            </span>
                                            <span class="text-sm font-bold text-red-600 dark:text-red-400">
                                                {{ $bottleneck['stuck_count'] }} ideas stuck
                                            </span>
                                        </div>
                                        <p class="text-xs text-red-600 dark:text-red-400 mt-1">
                                            Over {{ round($bottleneck['avg_time_hours'], 1) }} hours average processing time
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Performance Metrics Section --}}
            @if($selectedMetric === 'performance' && isset($analyticsData['performance']))
                @php $performance = $analyticsData['performance']; @endphp
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {{-- Innovation Scores --}}
                    @if(isset($performance['innovation_scores']))
                        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-white mb-6">Innovation Metrics</h3>
                            <div class="space-y-6">
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                                        {{ round($performance['innovation_scores']['avg_idea_rating'], 1) }}/5
                                    </div>
                                    <p class="text-sm text-[#9B9EA4]">Average Idea Rating</p>
                                </div>
                                
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-green-600 dark:text-green-400 mb-2">
                                        {{ $performance['innovation_scores']['implementation_rate'] }}%
                                    </div>
                                    <p class="text-sm text-[#9B9EA4]">Implementation Rate</p>
                                </div>
                                
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-purple-600 dark:text-purple-400 mb-2">
                                        {{ round($performance['innovation_scores']['innovation_index'], 1) }}
                                    </div>
                                    <p class="text-sm text-[#9B9EA4]">Innovation Index</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Review Performance --}}
                    @if(isset($performance['review_performance']))
                        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-white mb-6">Review Performance</h3>
                            <div class="space-y-4">
                                <div class="text-center mb-4">
                                    <div class="text-2xl font-bold text-[#231F20] dark:text-white mb-1">
                                        {{ round($performance['review_performance']['avg_review_time'], 1) }} hours
                                    </div>
                                    <p class="text-sm text-[#9B9EA4]">Average Review Time</p>
                                </div>
                                
                                @if(isset($performance['review_performance']['reviews_by_stage']))
                                    @foreach($performance['review_performance']['reviews_by_stage'] as $stage => $count)
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm capitalize text-[#231F20] dark:text-white">
                                                {{ str_replace('_', ' ', $stage) }}
                                            </span>
                                            <span class="text-sm font-bold text-[#231F20] dark:text-white">{{ $count }}</span>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Top Reviewers --}}
                    @if(isset($performance['review_performance']['reviewer_performance']))
                        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-white mb-6">Top Reviewers</h3>
                            <div class="space-y-3">
                                @foreach(array_slice($performance['review_performance']['reviewer_performance'], 0, 5) as $reviewer)
                                    <div class="flex items-center justify-between p-3 bg-white/50 dark:bg-zinc-700/50 rounded-lg">
                                        <div>
                                            <p class="font-medium text-[#231F20] dark:text-white text-sm">{{ $reviewer['name'] }}</p>
                                            <p class="text-xs text-[#9B9EA4]">{{ $reviewer['review_count'] }} reviews</p>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-bold text-yellow-600 dark:text-yellow-400">
                                                {{ round($reviewer['avg_rating'], 1) }}/5
                                            </div>
                                            <p class="text-xs text-[#9B9EA4]">Avg Rating</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- User Engagement Section --}}
            @if($selectedMetric === 'engagement' && isset($analyticsData['engagement']))
                @php $engagement = $analyticsData['engagement']; @endphp
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {{-- Active Users --}}
                    @if(isset($engagement['active_users']))
                        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-white mb-6">Active Users</h3>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-green-600 dark:text-green-400 mb-2">
                                        {{ $engagement['active_users']['daily'] }}
                                    </div>
                                    <p class="text-sm text-[#9B9EA4]">Daily</p>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                                        {{ $engagement['active_users']['weekly'] }}
                                    </div>
                                    <p class="text-sm text-[#9B9EA4]">Weekly</p>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mb-2">
                                        {{ $engagement['active_users']['monthly'] }}
                                    </div>
                                    <p class="text-sm text-[#9B9EA4]">Monthly</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Role Distribution --}}
                    @if(isset($engagement['role_distribution']))
                        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-white mb-6">Users by Role</h3>
                            <div class="space-y-4">
                                @foreach($engagement['role_distribution'] as $role => $count)
                                    @php 
                                        $total = array_sum($engagement['role_distribution']);
                                        $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                                        $colors = [
                                            'user' => 'bg-blue-500',
                                            'manager' => 'bg-green-500', 
                                            'sme' => 'bg-purple-500',
                                            'board_member' => 'bg-red-500',
                                            'administrator' => 'bg-yellow-500',
                                        ];
                                        $color = $colors[$role] ?? 'bg-gray-500';
                                    @endphp
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-4 h-4 {{ $color }} rounded-full"></div>
                                            <span class="text-sm font-medium text-[#231F20] dark:text-white capitalize">
                                                {{ str_replace('_', ' ', $role) }}
                                            </span>
                                        </div>
                                        <div class="flex items-center space-x-3">
                                            <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                <div class="{{ $color }} h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                                            </div>
                                            <span class="text-sm font-bold text-[#231F20] dark:text-white w-8 text-right">{{ $count }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Top Contributors --}}
                @if(isset($engagement['top_contributors']))
                    <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl mt-8">
                        <h3 class="text-xl font-bold text-[#231F20] dark:text-white mb-6">Top Contributors</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-[#9B9EA4]/20">
                                        <th class="text-left py-2 text-[#9B9EA4]">Name</th>
                                        <th class="text-center py-2 text-[#9B9EA4]">Ideas</th>
                                        <th class="text-center py-2 text-[#9B9EA4]">Reviews</th>
                                        <th class="text-center py-2 text-[#9B9EA4]">Points</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(array_slice($engagement['top_contributors'], 0, 10) as $contributor)
                                        <tr class="border-b border-[#9B9EA4]/10">
                                            <td class="py-3 text-[#231F20] dark:text-white font-medium">
                                                {{ $contributor['name'] }}
                                            </td>
                                            <td class="py-3 text-center text-[#231F20] dark:text-white">
                                                {{ $contributor['ideas_count'] }}
                                            </td>
                                            <td class="py-3 text-center text-[#231F20] dark:text-white">
                                                {{ $contributor['reviews_count'] }}
                                            </td>
                                            <td class="py-3 text-center text-[#231F20] dark:text-white font-bold">
                                                {{ number_format($contributor['total_points']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif

            {{-- Gamification Analytics Section --}}
            @if($selectedMetric === 'gamification' && isset($analyticsData['gamification']))
                @php $gamification = $analyticsData['gamification']; @endphp
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {{-- Point Distribution --}}
                    @if(isset($gamification['point_distribution']))
                        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-white mb-6">Points by Activity</h3>
                            <div class="space-y-4">
                                @foreach(array_slice($gamification['point_distribution'], 0, 8) as $activity)
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-[#231F20] dark:text-white capitalize">
                                            {{ str_replace('_', ' ', $activity['reason'] ?? 'Unknown Activity') }}
                                        </span>
                                        <div class="flex items-center space-x-3">
                                            <span class="text-xs text-[#9B9EA4]">{{ $activity['count'] ?? 0 }}x</span>
                                            <span class="text-sm font-bold text-[#231F20] dark:text-white">
                                                {{ number_format($activity['total_points'] ?? 0) }} pts
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Achievement Statistics --}}
                    @if(isset($gamification['achievement_stats']))
                        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-white mb-6">Achievement Distribution</h3>
                            <div class="space-y-4">
                                @foreach($gamification['achievement_stats'] as $achievement => $data)
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-[#231F20] dark:text-white">
                                            {{ is_array($data) ? ($data['name'] ?? $achievement) : $achievement }}
                                        </span>
                                        <span class="text-sm font-bold text-[#231F20] dark:text-white">
                                            {{ is_array($data) ? ($data['count'] ?? 0) : $data }} users
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        @endif

        {{-- Success Message --}}
        @if(session('success'))
            <div class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-xl z-50" 
                 x-data="{ show: true }" 
                 x-show="show" 
                 x-transition 
                 x-init="setTimeout(() => show = false, 5000)">
                {{ session('success') }}
            </div>
        @endif
    </div>
</div>

{{-- Alpine.js for interactivity --}}
<script>
    // Auto-refresh data every 5 minutes
    setInterval(() => {
        @this.call('refreshData');
    }, 300000);

    // Handle download ready event
    document.addEventListener('livewire:init', () => {
        Livewire.on('download-ready', (event) => {
            // Trigger download - this would be implemented based on file storage solution
            console.log('Download ready:', event.filename);
        });
    });
</script>
