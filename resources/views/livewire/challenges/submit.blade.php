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
            'status' => 'submitted',
        ]);
        
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
            ['manager', 'admin', 'challenge_reviewer'],
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

<div class="min-h-screen bg-gradient-to-br from-[#F8EBD5] via-white to-[#F8EBD5] py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-4 mb-6">
                <flux:button 
                    wire:navigate 
                    href="{{ route('challenges.show', $challenge) }}" 
                    variant="subtle"
                    class="text-[#9B9EA4] hover:text-[#231F20]"
                >
                    <flux:icon.arrow-left class="w-5 h-5 mr-2" />
                    Back to Challenge
                </flux:button>
            </div>
            
            <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                <div class="flex items-start gap-4">
                    <div class="text-4xl">
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
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold text-[#231F20] mb-2">Submit Your Solution</h1>
                        <h2 class="text-xl text-[#9B9EA4] mb-3">{{ $challenge->title }}</h2>
                        
                        <div class="flex items-center gap-6 text-sm text-[#9B9EA4]">
                            <div class="flex items-center">
                                <flux:icon.user class="w-4 h-4 mr-2" />
                                <span>by {{ $challenge->author->name }}</span>
                            </div>
                            
                            @if($challenge->deadline)
                                <div class="flex items-center">
                                    <flux:icon.clock class="w-4 h-4 mr-2" />
                                    <span class="font-medium {{ Carbon::parse($challenge->deadline)->diffInDays() <= 3 ? 'text-red-600' : '' }}">
                                        {{ $this->getDaysRemaining() }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submission Form -->
        <form wire:submit="submit" class="space-y-6">
            <!-- Basic Information -->
            <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                <h2 class="text-xl font-semibold text-[#231F20] mb-6">Submission Details</h2>
                
                <div class="space-y-4">
                    <!-- Submission Title -->
                    <div>
                        <flux:field>
                            <flux:label>Submission Title</flux:label>
                            <flux:input 
                                wire:model="title" 
                                placeholder="Give your solution a compelling title..."
                                class="rounded-xl border-[#9B9EA4]/30"
                            />
                            <flux:error name="title" />
                            <flux:description>Choose a clear, descriptive title for your solution</flux:description>
                        </flux:field>
                    </div>
                    
                    <!-- Team Submission Toggle -->
                    <div class="flex items-center gap-3 p-4 bg-[#F8EBD5]/30 rounded-xl">
                        <flux:checkbox 
                            wire:model.live="team_submission" 
                            id="team_submission"
                        />
                        <label for="team_submission" class="text-[#231F20] font-medium cursor-pointer">
                            This is a team submission
                        </label>
                    </div>
                    
                    <!-- Team Members (conditional) -->
                    @if($team_submission)
                        <div>
                            <flux:field>
                                <flux:label>Team Members</flux:label>
                                <flux:textarea 
                                    wire:model="team_members" 
                                    rows="3"
                                    placeholder="List all team members with their roles (e.g., John Doe - Team Lead, Jane Smith - Developer, etc.)"
                                    class="rounded-xl border-[#9B9EA4]/30"
                                />
                                <flux:error name="team_members" />
                                <flux:description>Include names and roles of all team members</flux:description>
                            </flux:field>
                        </div>
                    @endif
                    
                    <!-- Description -->
                    <div>
                        <flux:field>
                            <flux:label>Solution Overview</flux:label>
                            <flux:textarea 
                                wire:model="description" 
                                rows="4"
                                placeholder="Provide a brief overview of your solution and how it addresses the challenge..."
                                class="rounded-xl border-[#9B9EA4]/30"
                            />
                            <flux:error name="description" />
                            <flux:description>Summarize your solution in a few paragraphs</flux:description>
                        </flux:field>
                    </div>
                </div>
            </div>

            <!-- Technical Details -->
            <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                <h2 class="text-xl font-semibold text-[#231F20] mb-6">Technical Approach</h2>
                
                <div class="space-y-4">
                    <!-- Solution Approach -->
                    <div>
                        <flux:field>
                            <flux:label>Solution Approach & Methodology</flux:label>
                            <flux:textarea 
                                wire:model="solution_approach" 
                                rows="6"
                                placeholder="Describe your technical approach, methodology, and the reasoning behind your solution design..."
                                class="rounded-xl border-[#9B9EA4]/30"
                            />
                            <flux:error name="solution_approach" />
                            <flux:description>Explain the technical details and methodology of your solution</flux:description>
                        </flux:field>
                    </div>
                    
                    <!-- Implementation Plan -->
                    <div>
                        <flux:field>
                            <flux:label>Implementation Plan</flux:label>
                            <flux:textarea 
                                wire:model="implementation_plan" 
                                rows="5"
                                placeholder="Outline how your solution would be implemented, including timeline, resources needed, and potential challenges..."
                                class="rounded-xl border-[#9B9EA4]/30"
                            />
                            <flux:error name="implementation_plan" />
                            <flux:description>Provide a practical implementation roadmap</flux:description>
                        </flux:field>
                    </div>
                </div>
            </div>

            <!-- File Attachments -->
            <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                <h2 class="text-xl font-semibold text-[#231F20] mb-6">Supporting Documents</h2>
                
                <div class="space-y-4">
                    <!-- File Upload -->
                    <div>
                        <flux:field>
                            <flux:label>Attachments (Optional)</flux:label>
                            <input 
                                type="file" 
                                wire:model="attachments" 
                                multiple
                                accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.gif,.webp,.zip,.rar,.mp4,.avi,.mov"
                                class="block w-full text-sm text-[#231F20] file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-[#FFF200] file:text-[#231F20] hover:file:bg-yellow-400 file:cursor-pointer border border-[#9B9EA4]/30 rounded-xl"
                            />
                            <flux:error name="attachments.*" />
                            <flux:description>
                                <div class="space-y-1 text-sm text-[#9B9EA4]">
                                    <p><strong>Supported formats:</strong> PDF, Word, PowerPoint, Images (JPG, PNG, GIF, WebP), Archives (ZIP, RAR), Videos (MP4, AVI, MOV)</p>
                                    <p><strong>Security:</strong> All files are scanned for security threats and validated for content integrity</p>
                                    <p><strong>Limits:</strong> Maximum 10 files, 10MB per document/image, 50MB per archive, 100MB per video</p>
                                    <p><strong>Note:</strong> Executable files (.exe, .bat, .script files) are not allowed for security reasons</p>
                                </div>
                            </flux:description>
                        </flux:field>
                    </div>
                    
                    <!-- Loading State for Files -->
                    <div wire:loading wire:target="attachments" class="flex items-center gap-2 text-[#9B9EA4]">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-[#FFF200]"></div>
                        <span>Uploading files...</span>
                    </div>
                    
                    <!-- Display Selected Files -->
                    @if(!empty($attachments))
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-[#231F20]">Selected Files:</h4>
                            @foreach($attachments as $index => $attachment)
                                @if($attachment)
                                    <div class="flex items-center justify-between p-3 bg-[#F8EBD5]/30 rounded-lg">
                                        <div class="flex items-center gap-3">
                                            <flux:icon.document class="w-5 h-5 text-[#9B9EA4]" />
                                            <div>
                                                <p class="text-sm font-medium text-[#231F20]">{{ $attachment->getClientOriginalName() }}</p>
                                                <p class="text-xs text-[#9B9EA4]">{{ number_format($attachment->getSize() / 1024, 1) }} KB</p>
                                            </div>
                                        </div>
                                        <flux:button 
                                            type="button"
                                            wire:click="removeAttachment({{ $index }})"
                                            variant="danger"
                                            size="sm"
                                            class="px-2"
                                        >
                                            <flux:icon.trash class="w-4 h-4" />
                                        </flux:button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Challenge Requirements Reminder -->
            <div class="bg-blue-50/70 backdrop-blur-sm rounded-2xl shadow-lg border border-blue-200/50 p-6">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <flux:icon.information-circle class="w-6 h-6 text-blue-600" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-blue-900 mb-2">Reminder: Challenge Requirements</h3>
                        <ul class="space-y-1 text-blue-700 text-sm">
                            @foreach($challenge->requirements as $requirement)
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
                                    <span>{{ $requirement }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                <div class="flex flex-col sm:flex-row gap-4 justify-end">
                    <flux:button 
                        type="button"
                        wire:navigate 
                        href="{{ route('challenges.show', $challenge) }}"
                        variant="outline"
                        class="border-[#9B9EA4] text-[#9B9EA4] hover:bg-[#9B9EA4]/10 rounded-xl px-8 py-3"
                    >
                        Cancel
                    </flux:button>
                    
                    <flux:button 
                        type="submit"
                        variant="primary"
                        class="bg-[#FFF200] hover:bg-yellow-400 text-[#231F20] rounded-xl px-8 py-3 font-semibold"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove>Submit Solution</span>
                        <span wire:loading>Submitting...</span>
                    </flux:button>
                </div>
                
                <p class="text-sm text-[#9B9EA4] mt-3 text-center">
                    By submitting, you confirm that your solution is original work and adheres to the challenge requirements.
                </p>
            </div>
        </form>
    </div>
</div>
