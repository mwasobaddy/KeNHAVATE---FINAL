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

<div class="min-h-screen bg-gradient-to-br from-[#F8EBD5] via-white to-[#F8EBD5] py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Navigation -->
        <div class="mb-8">
            <div class="flex items-center gap-4 mb-6">
                <flux:button 
                    wire:navigate 
                    href="{{ route('challenges.index') }}" 
                    variant="subtle"
                    class="text-[#9B9EA4] hover:text-[#231F20]"
                >
                    <flux:icon.arrow-left class="w-5 h-5 mr-2" />
                    Back to Challenges
                </flux:button>
                
                <!-- Action Buttons -->
                <div class="ml-auto flex gap-3">
                    @if($this->canEdit())
                        <flux:button 
                            wire:navigate 
                            href="{{ route('challenges.edit', $challenge) }}" 
                            variant="outline"
                            class="border-[#9B9EA4] text-[#9B9EA4] hover:bg-[#9B9EA4]/10 rounded-xl"
                        >
                            <flux:icon.pencil class="w-4 h-4 mr-2" />
                            Edit
                        </flux:button>
                    @endif
                    
                    @if($this->canViewSubmissions())
                        <flux:button 
                            wire:navigate 
                            href="{{ route('challenges.submissions', $challenge) }}" 
                            variant="outline"
                            class="border-[#231F20] text-[#231F20] hover:bg-[#231F20]/10 rounded-xl"
                        >
                            <flux:icon.document-text class="w-4 h-4 mr-2" />
                            View Submissions ({{ $challenge->submissions->count() }})
                        </flux:button>
                    @endif
                    
                    @if($this->canSubmit())
                        <flux:button 
                            wire:navigate 
                            href="{{ route('challenges.submit', $challenge) }}" 
                            variant="primary"
                            class="bg-[#FFF200] hover:bg-yellow-400 text-[#231F20] rounded-xl font-semibold px-6"
                        >
                            <flux:icon.plus class="w-4 h-4 mr-2" />
                            Submit Solution
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Challenge Header -->
                <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 overflow-hidden">
                    <!-- Challenge Image/Icon -->
                    <div class="h-64 bg-gradient-to-br from-[#FFF200]/20 to-[#F8EBD5] relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="text-8xl text-[#231F20]/20">
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
                        
                        <!-- Status Badge -->
                        <div class="absolute top-6 right-6">
                            <span class="px-4 py-2 rounded-full text-sm font-medium {{ $this->getStatusBadgeClass() }}">
                                {{ ucfirst($challenge->status) }}
                            </span>
                        </div>
                        
                        <!-- Deadline Badge -->
                        @if($challenge->deadline && $challenge->status === 'active')
                            <div class="absolute top-6 left-6">
                                <span class="px-4 py-2 rounded-full text-sm font-medium bg-white/90 text-[#231F20]">
                                    {{ $this->getDaysRemaining() }}
                                </span>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Challenge Info -->
                    <div class="p-8">
                        <div class="mb-4">
                            <span class="inline-block px-3 py-1 bg-[#F8EBD5] text-[#231F20] rounded-full text-sm font-medium mb-3">
                                {{ ucfirst(str_replace('_', ' ', $challenge->category)) }}
                            </span>
                            <h1 class="text-3xl font-bold text-[#231F20] mb-3">{{ $challenge->title }}</h1>
                        </div>
                        
                        <div class="prose prose-lg max-w-none text-[#231F20] mb-6">
                            {!! nl2br(e($challenge->description)) !!}
                        </div>
                        
                        <!-- Challenge Meta -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-[#9B9EA4]">
                            <div class="flex items-center">
                                <flux:icon.user class="w-4 h-4 mr-2" />
                                <span>by {{ $challenge->author->name }}</span>
                            </div>
                            
                            <div class="flex items-center">
                                <flux:icon.users class="w-4 h-4 mr-2" />
                                <span>{{ $challenge->submissions->count() }} {{ Str::plural('submission', $challenge->submissions->count()) }}</span>
                            </div>
                            
                            <div class="flex items-center">
                                <flux:icon.calendar class="w-4 h-4 mr-2" />
                                <span>Created {{ $challenge->created_at->format('M j, Y') }}</span>
                            </div>
                            
                            @if($challenge->deadline)
                                <div class="flex items-center">
                                    <flux:icon.clock class="w-4 h-4 mr-2" />
                                    <span>Due {{ Carbon::parse($challenge->deadline)->format('M j, Y') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- User Submission Status -->
                @if($userSubmission)
                    <div class="bg-blue-50/70 backdrop-blur-sm rounded-2xl shadow-lg border border-blue-200/50 p-6">
                        <div class="flex items-start gap-4">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <flux:icon.check-circle class="w-6 h-6 text-blue-600" />
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-blue-900 mb-2">Your Submission</h3>
                                <p class="text-blue-700 mb-3">You have successfully submitted a solution to this challenge.</p>
                                <div class="text-sm text-blue-600">
                                    <strong>Submitted:</strong> {{ $userSubmission->created_at->format('M j, Y \a\t g:i A') }}<br>
                                    <strong>Status:</strong> {{ ucfirst($userSubmission->status) }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Requirements Section -->
                <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-[#231F20]">Challenge Requirements</h2>
                        <flux:button 
                            type="button"
                            wire:click="toggleRequirements"
                            variant="subtle"
                            size="sm"
                            class="text-[#9B9EA4] hover:text-[#231F20]"
                        >
                            <flux:icon.chevron-down class="w-4 h-4 transform transition-transform {{ $showRequirements ? 'rotate-180' : '' }}" />
                        </flux:button>
                    </div>
                    
                    <div class="{{ $showRequirements ? 'block' : 'hidden' }}">
                        <ul class="space-y-3">
                            @foreach($challenge->requirements as $requirement)
                                <li class="flex items-start gap-3">
                                    <div class="p-1 bg-[#FFF200]/20 rounded-full mt-1">
                                        <flux:icon.check class="w-3 h-3 text-[#231F20]" />
                                    </div>
                                    <span class="text-[#231F20]">{{ $requirement }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    
                    @if(!$showRequirements)
                        <p class="text-[#9B9EA4] text-sm">Click to view {{ count($challenge->requirements) }} requirements</p>
                    @endif
                </div>

                <!-- Judging Criteria Section -->
                <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-[#231F20]">Judging Criteria</h2>
                        <flux:button 
                            type="button"
                            wire:click="toggleJudgingCriteria"
                            variant="subtle"
                            size="sm"
                            class="text-[#9B9EA4] hover:text-[#231F20]"
                        >
                            <flux:icon.chevron-down class="w-4 h-4 transform transition-transform {{ $showJudgingCriteria ? 'rotate-180' : '' }}" />
                        </flux:button>
                    </div>
                    
                    <div class="{{ $showJudgingCriteria ? 'block' : 'hidden' }}">
                        <div class="prose prose-sm max-w-none text-[#231F20]">
                            {!! nl2br(e($challenge->judging_criteria)) !!}
                        </div>
                    </div>
                    
                    @if(!$showJudgingCriteria)
                        <p class="text-[#9B9EA4] text-sm">Click to view judging criteria and evaluation process</p>
                    @endif
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Challenge Statistics -->
                <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                    <h3 class="text-lg font-semibold text-[#231F20] mb-4">Challenge Stats</h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[#9B9EA4]">Total Submissions</span>
                            <span class="text-[#231F20] font-semibold">{{ $challenge->submissions->count() }}</span>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <span class="text-[#9B9EA4]">Participants</span>
                            <span class="text-[#231F20] font-semibold">{{ $challenge->submissions->unique('author_id')->count() }}</span>
                        </div>
                        
                        @if($challenge->deadline)
                            <div class="flex items-center justify-between">
                                <span class="text-[#9B9EA4]">Time Remaining</span>
                                <span class="text-[#231F20] font-semibold">{{ $this->getDaysRemaining() }}</span>
                            </div>
                        @endif
                        
                        <div class="flex items-center justify-between">
                            <span class="text-[#9B9EA4]">Created</span>
                            <span class="text-[#231F20] font-semibold">{{ $challenge->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Prize Information -->
                @if($challenge->prize_description)
                    <div class="bg-gradient-to-br from-[#FFF200]/10 to-[#F8EBD5]/50 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-[#FFF200]/20 rounded-lg">
                                <flux:icon.gift class="w-6 h-6 text-[#231F20]" />
                            </div>
                            <h3 class="text-lg font-semibold text-[#231F20]">Prize & Recognition</h3>
                        </div>
                        
                        <p class="text-[#231F20]">{{ $challenge->prize_description }}</p>
                    </div>
                @endif

                <!-- Recent Submissions -->
                @if($challenge->submissions->count() > 0)
                    <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-[#231F20]">Recent Submissions</h3>
                            @if($this->canViewSubmissions())
                                <flux:button 
                                    wire:navigate 
                                    href="{{ route('challenges.submissions', $challenge) }}" 
                                    variant="subtle"
                                    size="sm"
                                    class="text-[#9B9EA4] hover:text-[#231F20]"
                                >
                                    View All
                                </flux:button>
                            @endif
                        </div>
                        
                        <div class="space-y-3">
                            @foreach($challenge->submissions->take(3) as $submission)
                                <div class="flex items-center gap-3 p-3 bg-[#F8EBD5]/30 rounded-lg">
                                    <div class="w-8 h-8 bg-[#FFF200]/30 rounded-full flex items-center justify-center">
                                        <span class="text-[#231F20] font-medium text-sm">{{ substr($submission->author->name, 0, 1) }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[#231F20] font-medium text-sm truncate">{{ $submission->author->name }}</p>
                                        <p class="text-[#9B9EA4] text-xs">{{ $submission->created_at->diffForHumans() }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Call to Action -->
                @if($this->canSubmit())
                    <div class="bg-gradient-to-br from-[#FFF200]/20 to-[#F8EBD5] backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6 text-center">
                        <div class="text-4xl mb-4">🚀</div>
                        <h3 class="text-lg font-semibold text-[#231F20] mb-2">Ready to Innovate?</h3>
                        <p class="text-[#9B9EA4] text-sm mb-4">Submit your solution and join the innovation community!</p>
                        
                        <flux:button 
                            wire:navigate 
                            href="{{ route('challenges.submit', $challenge) }}" 
                            variant="primary"
                            class="w-full bg-[#FFF200] hover:bg-yellow-400 text-[#231F20] rounded-xl font-semibold py-3"
                        >
                            Submit Your Solution
                        </flux:button>
                    </div>
                @elseif(!Auth::check())
                    <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6 text-center">
                        <div class="text-4xl mb-4">🔐</div>
                        <h3 class="text-lg font-semibold text-[#231F20] mb-2">Join the Challenge</h3>
                        <p class="text-[#9B9EA4] text-sm mb-4">Sign in to submit your innovative solution!</p>
                        
                        <flux:button 
                            wire:navigate 
                            href="{{ route('login') }}" 
                            variant="primary"
                            class="w-full bg-[#231F20] hover:bg-gray-800 text-white rounded-xl font-semibold py-3"
                        >
                            Sign In to Participate
                        </flux:button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
