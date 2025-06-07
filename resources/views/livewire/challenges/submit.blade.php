<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Artesaos\SEOTools\Facades\SEOTools;

new class extends Component {
    use WithFileUploads;
    
    public Challenge $challenge;
    public string $title = '';
    public string $description = '';
    public string $solution_approach = '';
    public string $implementation_plan = '';
    public array $attachments = [];
    public bool $team_submission = false;
    public string $team_members = '';
    
    public function mount(Challenge $challenge)
    {
        $this->challenge = $challenge->load('author');
        
        // Check if challenge is active and accepting submissions
        if ($challenge->status !== 'active') {
            abort(404, 'This challenge is not accepting submissions.');
        }
        
        if ($challenge->deadline && Carbon::parse($challenge->deadline)->isPast()) {
            abort(404, 'Submission deadline has passed.');
        }
        
        // Check if user already submitted
        $existingSubmission = ChallengeSubmission::where('challenge_id', $challenge->id)
            ->where('author_id', auth()->id())
            ->first();
            
        if ($existingSubmission) {
            session()->flash('error', 'You have already submitted to this challenge.');
            return redirect()->route('challenges.show', $challenge);
        }
        
        // Set SEO meta tags
        SEOTools::setTitle('Submit Solution - ' . $challenge->title . ' - KeNHAVATE Innovation Portal');
        SEOTools::setDescription('Submit your innovative solution to the ' . $challenge->title . ' challenge.');
    }
    
    public function rules()
    {
        $fileSecurityService = app(\App\Services\FileUploadSecurityService::class);
        $maxFileSize = $fileSecurityService->getMaxFileSize('documents'); // 10MB in bytes
        $maxFileSizeKB = round($maxFileSize / 1024); // Convert to KB for Laravel validation
        
        return [
            'title' => ['required', 'string', 'max:255', 'min:10'],
            'description' => ['required', 'string', 'min:50'],
            'solution_approach' => ['required', 'string', 'min:100'],
            'implementation_plan' => ['required', 'string', 'min:50'],
            'attachments' => ['nullable', 'array', 'max:10'], // Maximum 10 files
            'attachments.*' => [
                'nullable', 
                'file', 
                "max:{$maxFileSizeKB}", // Dynamic max size
                'mimes:pdf,doc,docx,ppt,pptx,txt,csv,jpg,jpeg,png,gif,webp,zip,rar,mp4,avi,mov', // Allowed extensions
                'mimetypes:' . implode(',', $fileSecurityService->getAllowedFileTypes()) // Secure MIME validation
            ],
            'team_submission' => ['boolean'],
            'team_members' => ['required_if:team_submission,true', 'nullable', 'string', 'max:1000'],
        ];
    }
    
    public function validationAttributes()
    {
        return [
            'title' => 'submission title',
            'description' => 'submission description',
            'solution_approach' => 'solution approach',
            'implementation_plan' => 'implementation plan',
            'attachments.*' => 'attachment',
            'team_members' => 'team members',
        ];
    }
    
    public function updatedTeamSubmission()
    {
        if (!$this->team_submission) {
            $this->team_members = '';
        }
    }
    
    public function removeAttachment($index)
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }
    
    public function submit()
    {
        $this->validate();
        
        // Double-check challenge is still accepting submissions
        if ($this->challenge->status !== 'active' || 
            ($this->challenge->deadline && Carbon::parse($this->challenge->deadline)->isPast())) {
            session()->flash('error', 'This challenge is no longer accepting submissions.');
            return;
        }
        
        // Check for duplicate submission again
        $existingSubmission = ChallengeSubmission::where('challenge_id', $this->challenge->id)
            ->where('author_id', auth()->id())
            ->first();
            
        if ($existingSubmission) {
            session()->flash('error', 'You have already submitted to this challenge.');
            return redirect()->route('challenges.show', $this->challenge);
        }
        
        // Handle file uploads with enhanced security
        $uploadedFiles = [];
        if (!empty($this->attachments)) {
            $fileSecurityService = app(\App\Services\FileUploadSecurityService::class);
            
            foreach ($this->attachments as $attachment) {
                if ($attachment) {
                    // Secure file upload with validation
                    $uploadResult = $fileSecurityService->storeFile(
                        $attachment, 
                        'challenge-submissions/' . $this->challenge->id, 
                        'challenge_submission'
                    );
                    
                    if ($uploadResult['success']) {
                        $uploadedFiles[] = [
                            'original_name' => $uploadResult['metadata']['original_name'],
                            'filename' => $uploadResult['metadata']['stored_filename'],
                            'path' => $uploadResult['path'],
                            'size' => $uploadResult['metadata']['size'],
                            'mime_type' => $uploadResult['metadata']['mime_type'],
                            'hash' => $uploadResult['metadata']['hash'],
                            'upload_timestamp' => $uploadResult['metadata']['upload_timestamp'],
                        ];
                    } else {
                        // Handle upload errors
                        session()->flash('error', 'File upload failed: ' . implode(', ', $uploadResult['errors']));
                        return;
                    }
                }
            }
        }
        
        // Create the submission
        $submission = ChallengeSubmission::create([
            'challenge_id' => $this->challenge->id,
            'author_id' => auth()->id(),
            'title' => $this->title,
            'description' => $this->description,
            'solution_approach' => $this->solution_approach,
            'implementation_plan' => $this->implementation_plan,
            'attachments' => $uploadedFiles,
            'team_submission' => $this->team_submission,
            'team_members' => $this->team_submission ? $this->team_members : null,
            'status' => 'draft',
        ]);
        
        // Use ChallengeWorkflowService to submit solution (includes gamification)
        $challengeWorkflowService = app(\App\Services\ChallengeWorkflowService::class);
        $challengeWorkflowService->submitSolution($submission, auth()->user());
        
        // Check for points notification in session
        if (session()->has('points_awarded')) {
            $pointsData = session()->get('points_awarded');
            session()->flash('points_notification', [
                'points' => $pointsData['points'],
                'message' => $pointsData['message'],
                'type' => $pointsData['type'] ?? 'challenge_participation'
            ]);
        }
        
        // Log the action
        app(App\Services\AuditService::class)->log(
            'challenge_participation',
            'ChallengeSubmission',
            $submission->id,
            null,
            $submission->toArray()
        );
        
        // Send notifications
        // Notify challenge author/managers
        app(App\Services\NotificationService::class)->sendToRoles(
            ['manager', administrator, 'challenge_reviewer'],
            'challenge_submission',
            [
                'title' => 'New Challenge Submission',
                'message' => "A new submission '{$submission->title}' has been made to challenge '{$this->challenge->title}' by " . auth()->user()->name,
                'related_id' => $submission->id,
                'related_type' => 'ChallengeSubmission',
            ]
        );
        
        // Notify user of successful submission
        app(App\Services\NotificationService::class)->send(
            auth()->user(),
            'submission_confirmation',
            [
                'title' => 'Submission Confirmed',
                'message' => "Your submission '{$submission->title}' to challenge '{$this->challenge->title}' has been received successfully.",
                'related_id' => $submission->id,
                'related_type' => 'ChallengeSubmission',
            ]
        );
        
        session()->flash('success', 'Your submission has been sent successfully! You will be notified when it\'s reviewed.');
        
        return redirect()->route('challenges.show', $this->challenge);
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
}; ?>

{{-- Modern Challenge Submission Form with Glass Morphism & Enhanced UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-5xl mx-auto">

        {{-- Enhanced Header with Glass Morphism --}}
        <section aria-labelledby="submission-header" class="group">
            <div class="mb-8">
                {{-- Back Navigation with Enhanced Style --}}
                <div class="flex items-center gap-4 mb-6">
                    <flux:button 
                        wire:navigate 
                        href="{{ route('challenges.show', $challenge) }}" 
                        class="group/back relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-4 py-3"
                    >
                        <span class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-0 group-hover/back:opacity-100 transition-opacity duration-500"></span>
                        <div class="relative flex items-center space-x-2 text-[#9B9EA4] dark:text-zinc-400 group-hover/back:text-blue-600 dark:group-hover/back:text-blue-400 transition-colors duration-300">
                            <flux:icon.arrow-left class="w-5 h-5" />
                            <span class="font-medium">Back to Challenge</span>
                        </div>
                    </flux:button>
                </div>
                
                {{-- Enhanced Header Card --}}
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    {{-- Animated Gradient Background --}}
                    <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/5 via-transparent to-[#F8EBD5]/10 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/20"></div>
                    
                    <div class="relative p-8">
                        <div class="flex items-start gap-6">
                            {{-- Category Icon with Enhanced Styling --}}
                            <div class="relative">
                                <div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 flex items-center justify-center shadow-xl text-4xl">
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
                                <div class="absolute -inset-3 bg-[#FFF200]/20 dark:bg-yellow-400/30 rounded-3xl blur-xl"></div>
                            </div>
                            
                            <div class="flex-1">
                                <h1 id="submission-header" class="text-3xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">Submit Your Solution</h1>
                                <h2 class="text-xl text-[#9B9EA4] dark:text-zinc-400 mb-4 font-medium">{{ $challenge->title }}</h2>
                                
                                <div class="flex flex-wrap items-center gap-6 text-sm">
                                    <div class="flex items-center space-x-2 text-[#9B9EA4] dark:text-zinc-400">
                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-xl flex items-center justify-center shadow-lg">
                                            <flux:icon.user class="w-5 h-5 text-white" />
                                        </div>
                                        <span class="font-medium">by {{ $challenge->author->name }}</span>
                                    </div>
                                    
                                    @if($challenge->deadline)
                                        <div class="flex items-center space-x-2">
                                            <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 rounded-xl flex items-center justify-center shadow-lg">
                                                <flux:icon.clock class="w-5 h-5 text-white" />
                                            </div>
                                            <span class="font-medium {{ Carbon::parse($challenge->deadline)->diffInDays() <= 3 ? 'text-red-600 dark:text-red-400' : 'text-[#9B9EA4] dark:text-zinc-400' }}">
                                                {{ $this->getDaysRemaining() }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Submission Form --}}
        <form wire:submit="submit" class="space-y-8">
            {{-- Basic Information Section --}}
            <section aria-labelledby="basic-info-heading" class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    {{-- Section Header --}}
                    <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 id="basic-info-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Submission Details</h2>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Provide the basic information about your solution</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-8 space-y-6">
                        {{-- Submission Title --}}
                        <div class="group/field">
                            <flux:field>
                                <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold text-lg mb-3">Submission Title</flux:label>
                                <flux:input 
                                    wire:model="title" 
                                    placeholder="Give your solution a compelling title..."
                                    class="rounded-2xl border-[#9B9EA4]/30 dark:border-zinc-600/30 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] dark:placeholder-zinc-400 text-lg p-4 focus:ring-2 focus:ring-[#FFF200]/50 dark:focus:ring-yellow-400/50 transition-all duration-300"
                                />
                                <flux:error name="title" />
                                <flux:description class="text-[#9B9EA4] dark:text-zinc-400 mt-2">Choose a clear, descriptive title for your solution</flux:description>
                            </flux:field>
                        </div>
                        
                        {{-- Team Submission Toggle --}}
                        <div class="group/toggle relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#F8EBD5]/30 to-[#FFF200]/20 dark:from-amber-400/10 dark:to-yellow-400/10 border border-[#F8EBD5]/50 dark:border-amber-400/30 p-6 hover:shadow-lg transition-all duration-300">
                            <div class="flex items-center gap-4">
                                <flux:checkbox 
                                    wire:model.live="team_submission" 
                                    id="team_submission"
                                    class="w-5 h-5"
                                />
                                <label for="team_submission" class="text-[#231F20] dark:text-zinc-100 font-semibold text-lg cursor-pointer flex items-center space-x-2">
                                    <svg class="w-6 h-6 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <span>This is a team submission</span>
                                </label>
                            </div>
                        </div>
                        
                        {{-- Team Members (conditional with animation) --}}
                        <div 
                            x-data="{ show: @entangle('team_submission') }"
                            x-show="show"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 transform -translate-y-4"
                            x-transition:enter-end="opacity-100 transform translate-y-0"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 transform translate-y-0"
                            x-transition:leave-end="opacity-0 transform -translate-y-4"
                            style="display: none;"
                        >
                            @if($team_submission)
                                <div class="group/field">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold text-lg mb-3">Team Members</flux:label>
                                        <flux:textarea 
                                            wire:model="team_members" 
                                            rows="4"
                                            placeholder="List all team members with their roles (e.g., John Doe - Team Lead, Jane Smith - Developer, etc.)"
                                            class="rounded-2xl border-[#9B9EA4]/30 dark:border-zinc-600/30 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] dark:placeholder-zinc-400 p-4 focus:ring-2 focus:ring-[#FFF200]/50 dark:focus:ring-yellow-400/50 transition-all duration-300"
                                        />
                                        <flux:error name="team_members" />
                                        <flux:description class="text-[#9B9EA4] dark:text-zinc-400 mt-2">Include names and roles of all team members</flux:description>
                                    </flux:field>
                                </div>
                            @endif
                        </div>
                        
                        {{-- Description --}}
                        <div class="group/field">
                            <flux:field>
                                <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold text-lg mb-3">Solution Overview</flux:label>
                                <flux:textarea 
                                    wire:model="description" 
                                    rows="5"
                                    placeholder="Provide a brief overview of your solution and how it addresses the challenge..."
                                    class="rounded-2xl border-[#9B9EA4]/30 dark:border-zinc-600/30 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] dark:placeholder-zinc-400 p-4 focus:ring-2 focus:ring-[#FFF200]/50 dark:focus:ring-yellow-400/50 transition-all duration-300"
                                />
                                <flux:error name="description" />
                                <flux:description class="text-[#9B9EA4] dark:text-zinc-400 mt-2">Summarize your solution in a few paragraphs</flux:description>
                            </flux:field>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Technical Details Section --}}
            <section aria-labelledby="technical-details-heading" class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    {{-- Section Header --}}
                    <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 id="technical-details-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Technical Approach</h2>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Describe your methodology and implementation strategy</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-8 space-y-6">
                        {{-- Solution Approach --}}
                        <div class="group/field">
                            <flux:field>
                                <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold text-lg mb-3">Solution Approach & Methodology</flux:label>
                                <flux:textarea 
                                    wire:model="solution_approach" 
                                    rows="6"
                                    placeholder="Describe your technical approach, methodology, and the reasoning behind your solution design..."
                                    class="rounded-2xl border-[#9B9EA4]/30 dark:border-zinc-600/30 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] dark:placeholder-zinc-400 p-4 focus:ring-2 focus:ring-purple-500/50 dark:focus:ring-purple-400/50 transition-all duration-300"
                                />
                                <flux:error name="solution_approach" />
                                <flux:description class="text-[#9B9EA4] dark:text-zinc-400 mt-2">Explain the technical details and methodology of your solution</flux:description>
                            </flux:field>
                        </div>
                        
                        {{-- Implementation Plan --}}
                        <div class="group/field">
                            <flux:field>
                                <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold text-lg mb-3">Implementation Plan</flux:label>
                                <flux:textarea 
                                    wire:model="implementation_plan" 
                                    rows="5"
                                    placeholder="Outline how your solution would be implemented, including timeline, resources needed, and potential challenges..."
                                    class="rounded-2xl border-[#9B9EA4]/30 dark:border-zinc-600/30 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] dark:placeholder-zinc-400 p-4 focus:ring-2 focus:ring-purple-500/50 dark:focus:ring-purple-400/50 transition-all duration-300"
                                />
                                <flux:error name="implementation_plan" />
                                <flux:description class="text-[#9B9EA4] dark:text-zinc-400 mt-2">Provide a practical implementation roadmap</flux:description>
                            </flux:field>
                        </div>
                    </div>
                </div>
            </section>

            {{-- File Attachments Section --}}
            <section aria-labelledby="attachments-heading" class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    {{-- Section Header --}}
                    <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                </svg>
                            </div>
                            <div>
                                <h2 id="attachments-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Supporting Documents</h2>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Add files to support your submission</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-8 space-y-6">
                        {{-- Enhanced File Upload --}}
                        <div class="group/upload">
                            <flux:field>
                                <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold text-lg mb-3">Attachments (Optional)</flux:label>
                                <div class="relative">
                                    <input 
                                        type="file" 
                                        wire:model="attachments" 
                                        multiple
                                        accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.gif,.webp,.zip,.rar,.mp4,.avi,.mov"
                                        class="block w-full text-[#231F20] dark:text-zinc-100 file:mr-4 file:py-4 file:px-6 file:rounded-2xl file:border-0 file:text-sm file:font-semibold file:bg-gradient-to-r file:from-[#FFF200] file:to-yellow-400 file:text-[#231F20] hover:file:from-yellow-400 hover:file:to-yellow-500 file:cursor-pointer file:shadow-lg file:transition-all file:duration-300 border-2 border-dashed border-[#9B9EA4]/30 dark:border-zinc-600/30 rounded-2xl p-6 bg-white/30 dark:bg-zinc-800/30 backdrop-blur-sm hover:border-[#FFF200]/50 dark:hover:border-yellow-400/50 transition-all duration-300"
                                    />
                                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-emerald-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20 opacity-0 group-hover/upload:opacity-100 transition-opacity duration-500 rounded-2xl pointer-events-none"></div>
                                </div>
                                <flux:error name="attachments.*" />
                                <div class="mt-4 p-4 bg-gradient-to-r from-blue-50/70 to-indigo-50/70 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-2xl border border-blue-200/50 dark:border-blue-700/50">
                                    <div class="space-y-2 text-sm text-[#9B9EA4] dark:text-zinc-400">
                                        <p class="flex items-center space-x-2"><span class="font-semibold text-[#231F20] dark:text-zinc-100">Supported formats:</span> <span>PDF, Word, PowerPoint, Images (JPG, PNG, GIF, WebP), Archives (ZIP, RAR), Videos (MP4, AVI, MOV)</span></p>
                                        <p class="flex items-center space-x-2"><span class="font-semibold text-emerald-600 dark:text-emerald-400">Security:</span> <span>All files are scanned for security threats and validated for content integrity</span></p>
                                        <p class="flex items-center space-x-2"><span class="font-semibold text-amber-600 dark:text-amber-400">Limits:</span> <span>Maximum 10 files, 10MB per document/image, 50MB per archive, 100MB per video</span></p>
                                        <p class="flex items-center space-x-2"><span class="font-semibold text-red-600 dark:text-red-400">Note:</span> <span>Executable files (.exe, .bat, .script files) are not allowed for security reasons</span></p>
                                    </div>
                                </div>
                            </flux:field>
                        </div>
                        
                        {{-- Enhanced Loading State --}}
                        <div wire:loading wire:target="attachments" class="flex items-center justify-center space-x-3 p-6 bg-gradient-to-r from-[#FFF200]/10 to-yellow-400/10 dark:from-yellow-400/10 dark:to-amber-400/10 rounded-2xl border border-[#FFF200]/30 dark:border-yellow-400/30">
                            <div class="relative">
                                <div class="animate-spin rounded-full h-8 w-8 border-3 border-[#FFF200]/30 dark:border-yellow-400/30"></div>
                                <div class="animate-spin rounded-full h-8 w-8 border-3 border-t-[#FFF200] dark:border-t-yellow-400 absolute inset-0"></div>
                            </div>
                            <span class="text-[#231F20] dark:text-zinc-100 font-medium">Uploading files...</span>
                        </div>
                        
                        {{-- Enhanced Selected Files Display --}}
                        @if(!empty($attachments))
                            <div class="space-y-4">
                                <h4 class="text-lg font-semibold text-[#231F20] dark:text-zinc-100 flex items-center space-x-2">
                                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>Selected Files:</span>
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    @foreach($attachments as $index => $attachment)
                                        @if($attachment)
                                            <div class="group/file relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transition-all duration-300 p-4">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center space-x-3 flex-1 min-w-0">
                                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 rounded-xl flex items-center justify-center shadow-lg">
                                                            <flux:icon.document class="w-5 h-5 text-white" />
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-sm font-medium text-[#231F20] dark:text-zinc-100 truncate">{{ $attachment->getClientOriginalName() }}</p>
                                                            <p class="text-xs text-[#9B9EA4] dark:text-zinc-400">{{ number_format($attachment->getSize() / 1024, 1) }} KB</p>
                                                        </div>
                                                    </div>
                                                    <flux:button 
                                                        type="button"
                                                        wire:click="removeAttachment({{ $index }})"
                                                        class="group/remove w-8 h-8 rounded-xl bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50 flex items-center justify-center transition-all duration-300 hover:scale-110"
                                                    >
                                                        <flux:icon.trash class="w-4 h-4 text-red-600 dark:text-red-400" />
                                                    </flux:button>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Enhanced Challenge Requirements Reminder --}}
            <section aria-labelledby="requirements-heading" class="group">
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-50/70 to-indigo-50/70 dark:from-blue-900/20 dark:to-indigo-900/20 backdrop-blur-xl border border-blue-200/50 dark:border-blue-700/50 shadow-xl">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-indigo-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-indigo-500/20"></div>
                    
                    <div class="relative p-8">
                        <div class="flex items-start space-x-6">
                            <div class="relative">
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 dark:from-blue-400 dark:to-indigo-500 rounded-3xl flex items-center justify-center shadow-xl">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-3xl blur-xl"></div>
                            </div>
                            <div class="flex-1">
                                <h3 id="requirements-heading" class="text-2xl font-bold text-blue-900 dark:text-blue-100 mb-4">Reminder: Challenge Requirements</h3>
                                <div class="space-y-3">
                                    @foreach($challenge->requirements as $requirement)
                                        <div class="flex items-start space-x-3">
                                            <div class="w-6 h-6 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-lg flex items-center justify-center mt-0.5 flex-shrink-0 shadow-lg">
                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </div>
                                            <span class="text-blue-800 dark:text-blue-200 text-sm leading-relaxed">{{ $requirement }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Enhanced Form Actions --}}
            <section aria-labelledby="form-actions-heading" class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="p-8">
                        <div class="flex flex-col sm:flex-row gap-6 justify-end">
                            <flux:button 
                                type="button"
                                wire:navigate 
                                href="{{ route('challenges.show', $challenge) }}"
                                class="group/cancel relative overflow-hidden rounded-2xl bg-white/90 dark:bg-zinc-700/90 border border-[#9B9EA4]/30 dark:border-zinc-600/30 text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-100 hover:border-[#9B9EA4]/50 dark:hover:border-zinc-500/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-8 py-4 font-semibold text-lg"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-gray-500/5 via-transparent to-gray-600/10 dark:from-gray-400/10 dark:via-transparent dark:to-gray-500/20 opacity-0 group-hover/cancel:opacity-100 transition-opacity duration-500"></span>
                                <span class="relative">Cancel</span>
                            </flux:button>
                            
                            <flux:button 
                                type="submit"
                                class="group/submit relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#FFF200] to-yellow-400 hover:from-yellow-400 hover:to-yellow-500 text-[#231F20] backdrop-blur-sm shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 px-8 py-4 font-bold text-lg"
                                wire:loading.attr="disabled"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-yellow-300/20 via-transparent to-yellow-500/30 opacity-0 group-hover/submit:opacity-100 transition-opacity duration-500"></span>
                                <div class="relative flex items-center space-x-2">
                                    <span wire:loading.remove>Submit Solution</span>
                                    <span wire:loading class="flex items-center space-x-2">
                                        <div class="animate-spin rounded-full h-5 w-5 border-2 border-[#231F20]/30 border-t-[#231F20]"></div>
                                        <span>Submitting...</span>
                                    </span>
                                </div>
                            </flux:button>
                        </div>
                        
                        <div class="mt-6 p-4 bg-gradient-to-r from-amber-50/70 to-yellow-50/70 dark:from-amber-900/20 dark:to-yellow-900/20 rounded-2xl border border-amber-200/50 dark:border-amber-700/50">
                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 text-center leading-relaxed">
                                <strong class="text-[#231F20] dark:text-zinc-100">Important:</strong> By submitting, you confirm that your solution is original work and adheres to the challenge requirements. All submissions are subject to review and validation.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </form>
    </div>
</div>
