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
        $email = session('suspended_user_email');
        if (!$email) {
            $this->redirectRoute('login', navigate: true);
            return;
        }

        $user = \App\Models\User::where('email', $email)->first();
        if (!$user || !$user->isSuspended()) {
            $this->redirectRoute('login', navigate: true);
            return;
        }

        // Check if user can send an appeal (once per day limit)
        $this->canSendAppeal = AppealMessage::canSendAppeal($user->id, 'suspension');
        $this->lastAppeal = AppealMessage::getLatestAppeal($user->id, 'suspension');

        if (!$this->canSendAppeal && $this->lastAppeal) {
            $nextAllowedTime = $this->lastAppeal->last_sent_at->addDay();
            $this->hoursUntilNextAppeal = now()->diffInHours($nextAllowedTime, false);
        }
    }

    public function sendAppeal(): void
    {
        $this->validate();

        $email = session('suspended_user_email');
        $user = \App\Models\User::where('email', $email)->first();

        if (!$user || !$user->isSuspended()) {
            $this->addError('message', 'Invalid user session. Please try logging in again.');
            return;
        }

        if (!AppealMessage::canSendAppeal($user->id, 'suspension')) {
            $this->addError('message', 'You can only send one appeal per day. Please wait before sending another appeal.');
            return;
        }

        // Create appeal message
        $appeal = AppealMessage::create([
            'user_id' => $user->id,
            'appeal_type' => 'suspension',
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
            ['appeal_type' => 'suspension', 'user_email' => $user->email]
        );

        // Send notifications to developer and admins
        $developerAndAdmins = \App\Models\User::role(['developer', 'administrator'])->get();
        
        foreach ($developerAndAdmins as $admin) {
            // Send in-app notification
            $this->notificationService->sendNotification($admin, 'appeal_submitted', [
                'title' => 'New Account Appeal Submitted',
                'message' => "User {$user->name} ({$user->email}) has submitted an appeal for their suspended account.",
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
        session()->forget('suspended_user_email');
        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#F8EBD5] to-[#9B9EA4] p-4">
    <div class="w-full max-w-2xl">
        <!-- Account Suspended Card -->
        <div class="bg-white/20 backdrop-blur-xl rounded-3xl shadow-2xl border border-white/30 p-8 mb-6">
            <div class="text-center mb-8">
                <!-- Suspended Icon -->
                <div class="inline-flex items-center justify-center w-20 h-20 bg-orange-500/20 backdrop-blur-xl rounded-full mb-4">
                    <svg class="w-10 h-10 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                
                <h1 class="text-3xl font-bold text-[#231F20] mb-2">Account Temporarily Suspended</h1>
                <p class="text-[#9B9EA4] text-lg">Your account has been temporarily suspended from the KeNHAVATE Innovation Portal.</p>
            </div>

            <div class="bg-orange-50/50 backdrop-blur-xl border border-orange-200/50 rounded-2xl p-6 mb-8">
                <h3 class="text-lg font-semibold text-orange-800 mb-2">Account Status</h3>
                <p class="text-orange-700">
                    Your account has been temporarily suspended due to policy violations or security concerns. 
                    During this suspension period, you cannot access the KeNHAVATE Innovation Portal or submit ideas and challenges.
                </p>
            </div>
        </div>

        <!-- Appeal Form Card -->
        <div class="bg-white/20 backdrop-blur-xl rounded-3xl shadow-2xl border border-white/30 p-8">
            <h2 class="text-2xl font-bold text-[#231F20] mb-6">Submit an Appeal</h2>
            
            @if($canSendAppeal)
                <p class="text-[#9B9EA4] mb-6">
                    If you believe your account was suspended in error, you may submit an appeal. 
                    Please provide a detailed explanation of why you think the suspension should be reviewed and lifted.
                    <strong>You can only submit one appeal per day.</strong>
                </p>

                @if(session('success'))
                    <div class="bg-green-50/50 backdrop-blur-xl border border-green-200/50 rounded-2xl p-4 mb-6">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <p class="text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                @endif

                <form wire:submit="sendAppeal" class="space-y-6">
                    <div>
                        <label for="message" class="block text-sm font-medium text-[#231F20] mb-2">
                            Appeal Message <span class="text-red-500">*</span>
                        </label>
                        <textarea 
                            wire:model="message" 
                            id="message"
                            rows="6" 
                            class="w-full px-4 py-3 border border-[#9B9EA4]/30 rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent bg-white/50 backdrop-blur-xl"
                            placeholder="Please explain why you believe your account was suspended in error and why the suspension should be lifted..."
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
                        class="w-full bg-[#FFF200] hover:bg-[#FFF200]/80 text-[#231F20] font-semibold py-3 px-6 rounded-xl transition duration-300 disabled:opacity-50"
                    >
                        <span wire:loading.remove>Submit Appeal</span>
                        <span wire:loading>Submitting...</span>
                    </button>
                </form>
            @else
                <!-- Already sent appeal or cooldown period -->
                <div class="bg-yellow-50/50 backdrop-blur-xl border border-yellow-200/50 rounded-2xl p-6 mb-6">
                    @if($lastAppeal)
                        <h3 class="text-lg font-semibold text-yellow-800 mb-2">Appeal Already Submitted</h3>
                        <p class="text-yellow-700 mb-4">
                            You submitted an appeal on {{ $lastAppeal->created_at->format('M d, Y \a\t g:i A') }}.
                        </p>
                        
                        @if($lastAppeal->status === 'pending')
                            <p class="text-yellow-700">
                                <strong>Status:</strong> Under Review - Your appeal is being reviewed by our administrators.
                            </p>
                        @elseif($lastAppeal->status === 'reviewed')
                            <p class="text-yellow-700">
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
                        <p class="text-yellow-700 mt-4">
                            You can submit another appeal in {{ $hoursUntilNextAppeal }} hours.
                        </p>
                    @endif
                </div>
            @endif

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 mt-8">
                <button 
                    wire:click="logout"
                    class="flex-1 bg-[#9B9EA4] hover:bg-[#9B9EA4]/80 text-white font-semibold py-3 px-6 rounded-xl transition duration-300"
                >
                    Back to Login
                </button>
                
                <a 
                    href="mailto:support@kenha.co.ke?subject=Account%20Suspension%20Appeal" 
                    class="flex-1 bg-white/20 backdrop-blur-xl border border-white/30 hover:bg-white/30 text-[#231F20] font-semibold py-3 px-6 rounded-xl transition duration-300 text-center"
                >
                    Contact Support Directly
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-[#9B9EA4] text-sm">
                © {{ date('Y') }} Kenya National Highways Authority. All rights reserved.
            </p>
        </div>
    </div>
</div>
