<?php

use Livewire\Volt\Component;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Artesaos\SEOTools\Facades\SEOTools;

new class extends Component {
    public Challenge $challenge;
    public ?ChallengeSubmission $userSubmission = null;
    public bool $showRequirements = false;
    public bool $showJudgingCriteria = false;
    
    public function mount(Challenge $challenge)
    {
        $this->challenge = $challenge->load(['author', 'submissions.author']);
        
        // Set SEO meta tags
        SEOTools::setTitle($challenge->title . ' - Challenge - KeNHAVATE Innovation Portal');
        SEOTools::setDescription(Str::limit($challenge->description, 160));
        SEOTools::setCanonical(route('challenges.show', $challenge));
        
        // Check if user has submitted to this challenge
        if (Auth::check()) {
            $this->userSubmission = ChallengeSubmission::where('challenge_id', $challenge->id)
                ->where('author_id', Auth::id())
                ->first();
        }
    }
    
    public function toggleRequirements()
    {
        $this->showRequirements = !$this->showRequirements;
    }
    
    public function toggleJudgingCriteria()
    {
        $this->showJudgingCriteria = !$this->showJudgingCriteria;
    }
    
    public function getStatusBadgeClass()
    {
        return match($this->challenge->status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'active' => 'bg-green-100 text-green-800',
            'judging' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-blue-100 text-blue-800',
            'cancelled' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    
    public function getDaysRemaining()
    {
        if (!$this->challenge->deadline) {
            return null;
        }
        
        $now = Carbon::now();
        $deadline = Carbon::parse($this->challenge->deadline);
        
        if ($deadline->isPast()) {
            return 'Expired';
        }
        
        $days = $now->diffInDays($deadline);
        
        if ($days === 0) {
            return 'Due today';
        } elseif ($days === 1) {
            return '1 day left';
        } else {
            return $days . ' days left';
        }
    }
    
    public function canSubmit()
    {
        return Auth::check() 
            && $this->challenge->status === 'active'
            && !$this->userSubmission
            && (!$this->challenge->deadline || Carbon::parse($this->challenge->deadline)->isFuture());
    }
    
    public function canEdit()
    {
        return Auth::check() && (
            Auth::user()->hasRole([administrator, 'developer']) ||
            (Auth::user()->hasRole('manager') && $this->challenge->author_id === Auth::id())
        );
    }
    
    public function canViewSubmissions()
    {
        return Auth::check() && (
            Auth::user()->hasRole(['administrator', 'developer', 'manager', 'challenge_reviewer']) ||
            $this->challenge->author_id === Auth::id()
        );
    }
}; ?>

{{-- Modern Challenge Detail Page with Glass Morphism & Enhanced UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/60 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/30 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/40 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        {{-- Enhanced Header Navigation with Glass Morphism --}}
        <section aria-labelledby="navigation-heading" class="group">
            <h2 id="navigation-heading" class="sr-only">Challenge Navigation</h2>
            <div class="relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl p-6">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    {{-- Back Navigation --}}
                    <flux:button 
                        wire:navigate 
                        href="{{ route('challenges.index') }}" 
                        variant="subtle"
                        class="group/back inline-flex items-center text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-100 transition-colors duration-300"
                    >
                        <div class="w-8 h-8 rounded-full bg-[#9B9EA4]/10 dark:bg-zinc-600/20 flex items-center justify-center mr-3 group-hover/back:bg-[#231F20]/10 transition-colors duration-300">
                            <flux:icon.arrow-left class="w-4 h-4" />
                        </div>
                        <span class="font-medium">Back to Challenges</span>
                    </flux:button>
                    
                    {{-- Action Buttons --}}
                    <div class="flex flex-wrap gap-3">
                        @if($this->canEdit())
                            <flux:button 
                                wire:navigate 
                                href="{{ route('challenges.edit', $challenge) }}" 
                                variant="outline"
                                class="group/edit relative overflow-hidden rounded-xl border-[#9B9EA4] dark:border-zinc-600 text-[#9B9EA4] dark:text-zinc-400 hover:bg-[#9B9EA4]/10 dark:hover:bg-zinc-600/20 transition-all duration-300 transform hover:-translate-y-1"
                            >
                                <span class="absolute inset-0 bg-gradient-to-r from-blue-500/5 to-indigo-500/10 opacity-0 group-hover/edit:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center">
                                    <flux:icon.pencil class="w-4 h-4 mr-2" />
                                    <span>Edit Challenge</span>
                                </div>
                            </flux:button>
                        @endif
                        
                        @if($this->canViewSubmissions())
                            <flux:button 
                                wire:navigate 
                                href="{{ route('challenges.submissions', $challenge) }}" 
                                variant="outline"
                                class="group/view relative overflow-hidden rounded-xl border-[#231F20] dark:border-zinc-300 text-[#231F20] dark:text-zinc-300 hover:bg-[#231F20]/10 dark:hover:bg-zinc-300/10 transition-all duration-300 transform hover:-translate-y-1"
                            >
                                <span class="absolute inset-0 bg-gradient-to-r from-emerald-500/5 to-teal-500/10 opacity-0 group-hover/view:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center">
                                    <flux:icon.document-text class="w-4 h-4 mr-2" />
                                    <span>View Submissions</span>
                                    <span class="ml-2 px-2 py-1 bg-[#F8EBD5] dark:bg-zinc-700 text-[#231F20] dark:text-zinc-300 rounded-full text-xs font-semibold">
                                        {{ $challenge->submissions->count() }}
                                    </span>
                                </div>
                            </flux:button>
                        @endif
                        
                        @if($this->canSubmit())
                            <flux:button 
                                wire:navigate 
                                href="{{ route('challenges.submit', $challenge) }}" 
                                variant="primary"
                                class="group/submit relative overflow-hidden rounded-xl bg-[#FFF200] hover:bg-yellow-400 text-[#231F20] font-semibold px-6 py-3 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"
                            >
                                <span class="absolute inset-0 bg-gradient-to-r from-yellow-300/20 to-amber-300/20 opacity-0 group-hover/submit:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center">
                                    <flux:icon.plus class="w-4 h-4 mr-2" />
                                    <span>Submit Solution</span>
                                </div>
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            {{-- Main Content --}}
            <div class="xl:col-span-2 space-y-8">
                {{-- Enhanced Challenge Header with Glass Morphism --}}
                <div class="group relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    {{-- Challenge Hero Section --}}
                    <div class="relative h-80 bg-gradient-to-br from-[#FFF200]/20 via-[#F8EBD5]/30 to-[#FFF200]/10 dark:from-yellow-400/10 dark:via-amber-400/20 dark:to-yellow-400/5 overflow-hidden">
                        {{-- Animated Background Pattern --}}
                        <div class="absolute inset-0 opacity-20">
                            <div class="absolute top-10 left-10 w-32 h-32 bg-[#FFF200]/30 dark:bg-yellow-400/20 rounded-full blur-2xl animate-pulse"></div>
                            <div class="absolute bottom-10 right-10 w-40 h-40 bg-[#F8EBD5]/40 dark:bg-amber-400/15 rounded-full blur-3xl animate-pulse delay-700"></div>
                        </div>
                        
                        {{-- Category Icon --}}
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="relative">
                                <div class="text-9xl opacity-30 dark:opacity-20 transform group-hover:scale-110 transition-transform duration-700">
                                    @switch($challenge->category)
                                        @case('technology')
                                            🚀
                                            @break
                                        @case('sustainability')
                                            🌱
                                            @break
                                        @case('safety')
                                            🛡️
                                            @break
                                        @case('innovation')
                                            💡
                                            @break
                                        @case('infrastructure')
                                            🏗️
                                            @break
                                        @case('efficiency')
                                            ⚡
                                            @break
                                        @default
                                            🏗️
                                    @endswitch
                                </div>
                            </div>
                        </div>
                        
                        {{-- Status & Deadline Badges --}}
                        <div class="absolute top-6 left-6 right-6 flex justify-between">
                            @if($challenge->deadline && $challenge->status === 'active')
                                <div class="inline-flex items-center space-x-2 bg-white/90 dark:bg-zinc-800/90 backdrop-blur-sm px-4 py-2 rounded-full border border-white/20 dark:border-zinc-700/50 shadow-lg">
                                    <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                                    <span class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ $this->getDaysRemaining() }}</span>
                                </div>
                            @endif
                            
                            <div class="ml-auto">
                                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-white/90 dark:bg-zinc-800/90 backdrop-blur-sm border border-white/20 dark:border-zinc-700/50 shadow-lg {{ $this->getStatusBadgeClass() }} dark:bg-opacity-90">
                                    {{ ucfirst($challenge->status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Challenge Information --}}
                    <div class="p-8">
                        {{-- Category & Title --}}
                        <div class="mb-6">
                            <span class="inline-flex items-center px-3 py-1 bg-[#F8EBD5] dark:bg-amber-400/20 text-[#231F20] dark:text-amber-300 rounded-full text-sm font-medium mb-4 border border-[#F8EBD5]/50 dark:border-amber-400/30">
                                {{ ucfirst(str_replace('_', ' ', $challenge->category)) }}
                            </span>
                            <h1 class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-4 leading-tight">{{ $challenge->title }}</h1>
                        </div>
                        
                        {{-- Description --}}
                        <div class="prose prose-lg max-w-none text-[#231F20] dark:text-zinc-300 mb-8 leading-relaxed">
                            {!! nl2br(e($challenge->description)) !!}
                        </div>
                        
                        {{-- Enhanced Meta Information --}}
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                            <div class="group/meta flex items-center space-x-3 p-3 rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-md transition-all duration-300">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-xl flex items-center justify-center shadow-lg">
                                    <flux:icon.user class="w-5 h-5 text-white" />
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wide">Author</p>
                                    <p class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ $challenge->author->name }}</p>
                                </div>
                            </div>
                            
                            <div class="group/meta flex items-center space-x-3 p-3 rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-md transition-all duration-300">
                                <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-xl flex items-center justify-center shadow-lg">
                                    <flux:icon.users class="w-5 h-5 text-white" />
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wide">Submissions</p>
                                    <p class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ $challenge->submissions->count() }} {{ Str::plural('solution', $challenge->submissions->count()) }}</p>
                                </div>
                            </div>
                            
                            <div class="group/meta flex items-center space-x-3 p-3 rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-md transition-all duration-300">
                                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-xl flex items-center justify-center shadow-lg">
                                    <flux:icon.calendar class="w-5 h-5 text-white" />
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wide">Created</p>
                                    <p class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ $challenge->created_at->format('M j, Y') }}</p>
                                </div>
                            </div>
                            
                            @if($challenge->deadline)
                                <div class="group/meta flex items-center space-x-3 p-3 rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-md transition-all duration-300">
                                    <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 rounded-xl flex items-center justify-center shadow-lg">
                                        <flux:icon.clock class="w-5 h-5 text-white" />
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wide">Deadline</p>
                                        <p class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ Carbon::parse($challenge->deadline)->format('M j, Y') }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- User Submission Status --}}
                @if($userSubmission)
                    <div class="group relative overflow-hidden rounded-2xl bg-blue-50/70 dark:bg-blue-900/20 backdrop-blur-xl border border-blue-200/50 dark:border-blue-700/50 shadow-xl p-6">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-blue-600/10 dark:from-blue-400/10 dark:to-blue-500/20"></div>
                        <div class="relative flex items-start gap-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <flux:icon.check-circle class="w-6 h-6 text-white" />
                            </div>
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-blue-900 dark:text-blue-100 mb-2">Your Submission Confirmed</h3>
                                <p class="text-blue-700 dark:text-blue-300 mb-4 leading-relaxed">You have successfully submitted a solution to this challenge and are now part of the innovation competition.</p>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div class="flex items-center space-x-2">
                                        <flux:icon.calendar class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                        <span class="text-blue-600 dark:text-blue-400">
                                            <strong>Submitted:</strong> {{ $userSubmission->created_at->format('M j, Y \a\t g:i A') }}
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <flux:icon.check class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                        <span class="text-blue-600 dark:text-blue-400">
                                            <strong>Status:</strong> {{ ucfirst($userSubmission->status) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Enhanced Requirements Section --}}
                <div class="group relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                    <flux:icon.clipboard-document-list class="w-5 h-5 text-[#231F20]" />
                                </div>
                                <h2 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Challenge Requirements</h2>
                            </div>
                            <flux:button 
                                type="button"
                                wire:click="toggleRequirements"
                                variant="subtle"
                                size="sm"
                                class="group/toggle text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-100 transition-colors duration-300"
                            >
                                <flux:icon.chevron-down class="w-5 h-5 transform transition-transform duration-300 {{ $showRequirements ? 'rotate-180' : '' }}" />
                            </flux:button>
                        </div>
                        
                        <div class="{{ $showRequirements ? 'block' : 'hidden' }} transition-all duration-300">
                            <div class="space-y-4">
                                @foreach($challenge->requirements as $index => $requirement)
                                    <div class="group/req flex items-start gap-4 p-4 rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-md transition-all duration-300">
                                        <div class="w-8 h-8 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-full flex items-center justify-center shadow-lg mt-1 flex-shrink-0">
                                            <span class="text-[#231F20] font-bold text-sm">{{ $index + 1 }}</span>
                                        </div>
                                        <p class="text-[#231F20] dark:text-zinc-100 leading-relaxed">{{ $requirement }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        
                        @if(!$showRequirements)
                            <div class="text-center p-4 rounded-xl bg-gradient-to-r from-[#F8EBD5]/30 to-[#FFF200]/20 dark:from-amber-400/10 dark:to-yellow-400/10 border border-[#F8EBD5]/50 dark:border-amber-400/30">
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Click to view {{ count($challenge->requirements) }} detailed requirements</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Enhanced Judging Criteria Section --}}
                <div class="group relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <flux:icon.scale class="w-5 h-5 text-white" />
                                </div>
                                <h2 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Judging Criteria</h2>
                            </div>
                            <flux:button 
                                type="button"
                                wire:click="toggleJudgingCriteria"
                                variant="subtle"
                                size="sm"
                                class="group/toggle text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-100 transition-colors duration-300"
                            >
                                <flux:icon.chevron-down class="w-5 h-5 transform transition-transform duration-300 {{ $showJudgingCriteria ? 'rotate-180' : '' }}" />
                            </flux:button>
                        </div>
                        
                        <div class="{{ $showJudgingCriteria ? 'block' : 'hidden' }} transition-all duration-300">
                            <div class="p-4 rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm">
                                <div class="prose prose-lg max-w-none text-[#231F20] dark:text-zinc-100 leading-relaxed">
                                    {!! nl2br(e($challenge->judging_criteria)) !!}
                                </div>
                            </div>
                        </div>
                        
                        @if(!$showJudgingCriteria)
                            <div class="text-center p-4 rounded-xl bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 border border-purple-200/50 dark:border-purple-700/30">
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Click to view detailed judging criteria and evaluation process</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Enhanced Sidebar --}}
            <div class="space-y-6">
                {{-- Challenge Statistics with Glass Morphism --}}
                <div class="group relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="p-6">
                        <div class="flex items-center space-x-3 mb-6">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 rounded-2xl flex items-center justify-center shadow-lg">
                                <flux:icon.chart-bar class="w-5 h-5 text-white" />
                            </div>
                            <h3 class="text-lg font-bold text-[#231F20] dark:text-zinc-100">Challenge Statistics</h3>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="group/stat flex items-center justify-between p-3 rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-md transition-all duration-300">
                                <span class="text-[#9B9EA4] dark:text-zinc-400 font-medium">Total Submissions</span>
                                <span class="text-[#231F20] dark:text-zinc-100 font-bold text-xl">{{ $challenge->submissions->count() }}</span>
                            </div>
                            
                            <div class="group/stat flex items-center justify-between p-3 rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-md transition-all duration-300">
                                <span class="text-[#9B9EA4] dark:text-zinc-400 font-medium">Participants</span>
                                <span class="text-[#231F20] dark:text-zinc-100 font-bold text-xl">{{ $challenge->submissions->unique('author_id')->count() }}</span>
                            </div>
                            
                            @if($challenge->deadline)
                                <div class="group/stat flex items-center justify-between p-3 rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-md transition-all duration-300">
                                    <span class="text-[#9B9EA4] dark:text-zinc-400 font-medium">Time Remaining</span>
                                    <span class="text-[#231F20] dark:text-zinc-100 font-bold text-xl">{{ $this->getDaysRemaining() }}</span>
                                </div>
                            @endif
                            
                            <div class="group/stat flex items-center justify-between p-3 rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-md transition-all duration-300">
                                <span class="text-[#9B9EA4] dark:text-zinc-400 font-medium">Created</span>
                                <span class="text-[#231F20] dark:text-zinc-100 font-bold">{{ $challenge->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Prize Information with Enhanced Design --}}
                @if($challenge->prize_description)
                    <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#FFF200]/20 via-[#F8EBD5]/30 to-[#FFF200]/10 dark:from-yellow-400/10 dark:via-amber-400/20 dark:to-yellow-400/5 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                        <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/10 to-[#F8EBD5]/20 dark:from-yellow-400/5 dark:to-amber-400/10"></div>
                        <div class="relative p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                    <flux:icon.gift class="w-6 h-6 text-[#231F20]" />
                                </div>
                                <h3 class="text-lg font-bold text-[#231F20] dark:text-zinc-100">Prize & Recognition</h3>
                            </div>
                            
                            <p class="text-[#231F20] dark:text-zinc-100 leading-relaxed">{{ $challenge->prize_description }}</p>
                        </div>
                    </div>
                @endif

                {{-- Recent Submissions with Enhanced Cards --}}
                @if($challenge->submissions->count() > 0)
                    <div class="group relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                        <flux:icon.users class="w-5 h-5 text-white" />
                                    </div>
                                    <h3 class="text-lg font-bold text-[#231F20] dark:text-zinc-100">Recent Submissions</h3>
                                </div>
                                @if($this->canViewSubmissions())
                                    <flux:button 
                                        wire:navigate 
                                        href="{{ route('challenges.submissions', $challenge) }}" 
                                        variant="subtle"
                                        size="sm"
                                        class="text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-100 transition-colors duration-300"
                                    >
                                        View All
                                    </flux:button>
                                @endif
                            </div>
                            
                            <div class="space-y-4">
                                @foreach($challenge->submissions->take(3) as $submission)
                                    <div class="group/submission flex items-center gap-4 p-4 rounded-xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-md transition-all duration-300 transform hover:-translate-y-1">
                                        <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-xl flex items-center justify-center shadow-lg">
                                            <span class="text-[#231F20] font-bold">{{ substr($submission->author->name, 0, 1) }}</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[#231F20] dark:text-zinc-100 font-semibold truncate">{{ $submission->author->name }}</p>
                                            <div class="flex items-center space-x-2 text-xs text-[#9B9EA4] dark:text-zinc-400">
                                                <flux:icon.clock class="w-3 h-3" />
                                                <span>{{ $submission->created_at->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Enhanced Call to Action --}}
                @if($this->canSubmit())
                    <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#FFF200]/30 via-[#F8EBD5]/40 to-[#FFF200]/20 dark:from-yellow-400/20 dark:via-amber-400/30 dark:to-yellow-400/10 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl text-center">
                        <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/20 to-[#F8EBD5]/30 dark:from-yellow-400/10 dark:to-amber-400/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative p-8">
                            <div class="text-6xl mb-4 transform group-hover:scale-110 transition-transform duration-500">🚀</div>
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">Ready to Innovate?</h3>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 mb-6 leading-relaxed">Submit your solution and join the innovation community at KeNHAVATE!</p>
                            
                            <flux:button 
                                wire:navigate 
                                href="{{ route('challenges.submit', $challenge) }}" 
                                variant="primary"
                                class="w-full bg-[#FFF200] hover:bg-yellow-400 text-[#231F20] rounded-xl font-bold py-4 text-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"
                            >
                                Submit Your Solution
                            </flux:button>
                        </div>
                    </div>
                @elseif(!Auth::check())
                    <div class="group relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl text-center">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-indigo-500/10 dark:from-blue-400/10 dark:to-indigo-400/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative p-8">
                            <div class="text-6xl mb-4 transform group-hover:scale-110 transition-transform duration-500">🔐</div>
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">Join the Challenge</h3>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 mb-6 leading-relaxed">Sign in to submit your innovative solution and compete with fellow innovators!</p>
                            
                            <flux:button 
                                wire:navigate 
                                href="{{ route('login') }}" 
                                variant="primary"
                                class="w-full bg-[#231F20] hover:bg-gray-800 text-white rounded-xl font-bold py-4 text-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"
                            >
                                Sign In to Participate
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
