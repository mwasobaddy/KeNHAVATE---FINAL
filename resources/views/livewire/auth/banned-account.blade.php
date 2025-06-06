<?php

use App\Models\AppealMessage;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|min:10|max:1000')]
    public string $message = '';

    public bool $canSendAppeal = true;
    public ?AppealMessage $lastAppeal = null;
    public int $hoursUntilNextAppeal = 0;

    protected AuditService $auditService;
    protected NotificationService $notificationService;

    public function boot(AuditService $auditService, NotificationService $notificationService): void
    {
        $this->auditService = $auditService;
        $this->notificationService = $notificationService;
    }

    public function mount(): void
    {
        // Get user from session if they just tried to login
        $email = session('banned_user_email');
        if (!$email) {
            $this->redirectRoute('login', navigate: true);
            return;
        }

        $user = \App\Models\User::where('email', $email)->first();
        if (!$user || !$user->isBanned()) {
            $this->redirectRoute('login', navigate: true);
            return;
        }

        // Check if user can send an appeal (once per day limit)
        $this->canSendAppeal = AppealMessage::canSendAppeal($user->id, 'ban');
        $this->lastAppeal = AppealMessage::getLatestAppeal($user->id, 'ban');

        if (!$this->canSendAppeal && $this->lastAppeal) {
            $nextAllowedTime = $this->lastAppeal->last_sent_at->addDay();
            $this->hoursUntilNextAppeal = now()->diffInHours($nextAllowedTime, false);
        }

        // Set page meta for modern layout
        $this->title = 'Account Suspended';
        $this->description = 'Your account has been suspended. Submit an appeal for review.';
    }

    public function sendAppeal(): void
    {
        $this->validate();

        $email = session('banned_user_email');
        $user = \App\Models\User::where('email', $email)->first();

        if (!$user || !$user->isBanned()) {
            $this->addError('message', 'Invalid user session. Please try logging in again.');
            return;
        }

        if (!AppealMessage::canSendAppeal($user->id, 'ban')) {
            $this->addError('message', 'You can only send one appeal per day. Please wait before sending another appeal.');
            return;
        }

        // Create appeal message
        $appeal = AppealMessage::create([
            'user_id' => $user->id,
            'appeal_type' => 'ban',
            'message' => $this->message,
            'last_sent_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Log the appeal submission
        $this->auditService->log(
            'appeal_submitted',
            'AppealMessage',
            $appeal->id,
            null,
            ['appeal_type' => 'ban', 'user_email' => $user->email]
        );

        // Send notifications to developer and admins
        $developerAndAdmins = \App\Models\User::role(['developer', 'administrator'])->get();
        
        foreach ($developerAndAdmins as $admin) {
            // Send in-app notification
            $this->notificationService->sendNotification($admin, 'appeal_submitted', [
                'title' => 'New Account Appeal Submitted',
                'message' => "User {$user->name} ({$user->email}) has submitted an appeal for their banned account.",
                'related_id' => $appeal->id,
                'related_type' => 'AppealMessage'
            ]);
            
            // Send email notification
            \Illuminate\Support\Facades\Mail::to($admin->email)
                ->send(new \App\Mail\AppealSubmittedMail($appeal, $user));
        }

        // Reset form and update state
        $this->message = '';
        $this->canSendAppeal = false;
        $this->lastAppeal = $appeal;
        $this->hoursUntilNextAppeal = 24;

        session()->flash('success', 'Your appeal has been submitted successfully. Administrators and developers have been notified and will review your request.');
    }

    public function logout(): void
    {
        session()->forget('banned_user_email');
        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="space-y-6">
    <!-- Status Alert -->
    <div class="rounded-lg bg-[#F8EBD5] border border-[#FFF200] p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <svg class="h-8 w-8 text-[#FFF200]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-medium text-[#231F20]">Account Permanently Banned</h3>
                <div class="mt-2 text-sm text-[#231F20]">
                    <p>Your account has been banned due to violations of our community guidelines or terms of service. You cannot access the KeNHAVATE Innovation Portal or submit ideas and challenges.</p>
                </div>
            </div>
        </div>
    </div>

    @if($canSendAppeal)
        <!-- Appeal Instructions -->
        <div class="rounded-lg bg-[#F8EBD5] border border-[#FFF200] p-6">
            <h3 class="text-lg font-medium text-[#231F20] mb-2">Submit an Appeal</h3>
            <p class="text-[#231F20]">
                If you believe your account was banned in error, you may submit an appeal. 
                Please provide a detailed explanation of why you think the ban should be reviewed.
                <strong>You can only submit one appeal per day.</strong>
            </p>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-[#F8EBD5] border border-[#FFF200] p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-[#FFF200]" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-[#231F20]">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Appeal Form -->
        <form wire:submit="sendAppeal" class="space-y-6">
            <div>
                <label for="message" class="block text-sm font-medium text-[#231F20] mb-2">
                    Appeal Message <span class="text-red-500">*</span>
                </label>
                <textarea 
                    wire:model="message" 
                    id="message"
                    rows="6" 
                    class="block w-full rounded-lg border-[#9B9EA4] shadow-sm focus:border-[#FFF200] focus:ring-[#FFF200]"
                    placeholder="Please explain why you believe your account was banned in error and why it should be reinstated..."
                    required
                ></textarea>
                @error('message')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
                <p class="text-xs text-[#9B9EA4] mt-1">Minimum 10 characters, maximum 1000 characters</p>
            </div>

            <button 
                type="submit" 
                wire:loading.attr="disabled"
                class="w-full justify-center rounded-lg bg-[#FFF200] px-4 py-3 text-sm font-semibold text-[#231F20] shadow-lg hover:shadow-xl hover:bg-[#FFF200]/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FFF200] disabled:opacity-50 transition-all duration-200"
            >
                <span wire:loading.remove>Submit Appeal</span>
                <span wire:loading>Submitting...</span>
            </button>
        </form>
    @else
        <!-- Appeal Status -->
        <div class="rounded-lg bg-[#F8EBD5] border border-[#FFF200] p-6">
            @if($lastAppeal)
                <h3 class="text-lg font-medium text-[#231F20] mb-2">Appeal Status</h3>
                <p class="text-[#231F20] mb-4">
                    You submitted an appeal on {{ $lastAppeal->created_at->format('M d, Y \a\t g:i A') }}.
                </p>
                
                @if($lastAppeal->status === 'pending')
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-2 h-2 bg-[#FFF200] rounded-full animate-pulse"></div>
                        </div>
                        <p class="ml-2 text-[#231F20]">
                            <strong>Status:</strong> Under Review - Your appeal is being reviewed by our administrators.
                        </p>
                    </div>
                @elseif($lastAppeal->status === 'reviewed')
                    <p class="text-[#231F20]">
                        <strong>Status:</strong> Reviewed - Your appeal has been reviewed.
                        @if($lastAppeal->admin_response)
                            <br><strong>Response:</strong> {{ $lastAppeal->admin_response }}
                        @endif
                    </p>
                @elseif($lastAppeal->status === 'approved')
                    <p class="text-green-700">
                        <strong>Status:</strong> Approved - Your appeal has been approved. Please try logging in again.
                        @if($lastAppeal->admin_response)
                            <br><strong>Response:</strong> {{ $lastAppeal->admin_response }}
                        @endif
                    </p>
                @elseif($lastAppeal->status === 'rejected')
                    <p class="text-red-700">
                        <strong>Status:</strong> Rejected - Your appeal has been rejected.
                        @if($lastAppeal->admin_response)
                            <br><strong>Response:</strong> {{ $lastAppeal->admin_response }}
                        @endif
                    </p>
                @endif
            @endif
            
            @if($hoursUntilNextAppeal > 0)
                <div class="mt-4 p-4 bg-[#FFF200]/20 rounded-lg">
                    <p class="text-[#231F20] text-sm">
                        You can submit another appeal in <span class="font-medium">{{ $hoursUntilNextAppeal }} hours</span>.
                    </p>
                </div>
            @endif
        </div>
    @endif

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-[#9B9EA4]">
        <button 
            wire:click="logout"
            class="flex-1 justify-center rounded-lg border border-[#9B9EA4] bg-white px-4 py-3 text-sm font-semibold text-[#231F20] shadow-lg hover:shadow-xl hover:bg-[#F8EBD5] transition-all duration-200"
        >
            Back to Login
        </button>
        
        <a 
            href="mailto:support@kenha.co.ke?subject=Account%20Ban%20Appeal" 
            class="flex-1 justify-center rounded-lg bg-[#FFF200] px-4 py-3 text-sm font-semibold text-[#231F20] shadow-lg hover:shadow-xl hover:bg-[#FFF200]/90 text-center transition-all duration-200"
        >
            Contact Support Directly
        </a>
    </div>
</div>
