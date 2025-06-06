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
        // Set dynamic title and description for the layout
        $this->title = 'Account Suspended';
        $this->description = 'Your account has been temporarily suspended. Submit an appeal to request account reactivation.';

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

    public string $title = '';
    public string $description = '';

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

<div class="flex flex-col space-y-6">
    <!-- Account Status Alert -->
    <div class="bg-orange-50 border border-orange-200 rounded-xl p-6">
        <div class="flex items-center mb-3">
            <div class="flex-shrink-0">
                <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-lg font-semibold text-orange-800">Account Temporarily Suspended</h3>
            </div>
        </div>
        <p class="text-orange-700">
            Your account has been temporarily suspended due to policy violations or security concerns. 
            During this suspension period, you cannot access the KeNHAVATE Innovation Portal.
        </p>
    </div>

    @if($canSendAppeal)
        <!-- Appeal Form -->
        <div class="space-y-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Submit an Appeal</h2>
                <p class="text-gray-600 text-sm">
                    If you believe your account was suspended in error, you may submit an appeal. 
                    Please provide a detailed explanation. <strong>You can only submit one appeal per day.</strong>
                </p>
            </div>

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <p class="text-green-800 text-sm">{{ session('success') }}</p>
                    </div>
                </div>
            @endif

            <form wire:submit="sendAppeal" class="space-y-6">
                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                        Appeal Message <span class="text-red-500">*</span>
                    </label>
                    <textarea 
                        wire:model="message" 
                        id="message"
                        rows="6" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                        placeholder="Please explain why you believe your account was suspended in error and why the suspension should be lifted..."
                        required
                    ></textarea>
                    @error('message')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-gray-500 mt-1">Minimum 10 characters, maximum 1000 characters</p>
                </div>

                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span wire:loading.remove>Submit Appeal</span>
                    <span wire:loading class="flex items-center justify-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Submitting...
                    </span>
                </button>
            </form>
        </div>
    @else
        <!-- Appeal Status -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6">
            @if($lastAppeal)
                <h3 class="text-lg font-semibold text-yellow-800 mb-3">Appeal Status</h3>
                <div class="space-y-3">
                    <p class="text-yellow-700">
                        <strong>Submitted:</strong> {{ $lastAppeal->created_at->format('M d, Y \a\t g:i A') }}
                    </p>
                    
                    <div class="flex items-center space-x-2">
                        <strong class="text-yellow-800">Status:</strong>
                        @if($lastAppeal->status === 'pending')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                Under Review
                            </span>
                            <span class="text-yellow-700">Your appeal is being reviewed by our administrators.</span>
                        @elseif($lastAppeal->status === 'reviewed')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                Reviewed
                            </span>
                        @elseif($lastAppeal->status === 'approved')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Approved
                            </span>
                            <span class="text-green-700">Your appeal has been approved. Please try logging in again.</span>
                        @elseif($lastAppeal->status === 'rejected')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                Rejected
                            </span>
                        @endif
                    </div>

                    @if($lastAppeal->admin_response)
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mt-3">
                            <p class="text-sm font-medium text-gray-700 mb-1">Administrator Response:</p>
                            <p class="text-gray-600 text-sm">{{ $lastAppeal->admin_response }}</p>
                        </div>
                    @endif
                </div>
            @endif
            
            @if($hoursUntilNextAppeal > 0)
                <div class="mt-4 p-3 bg-yellow-100 border border-yellow-200 rounded-lg">
                    <p class="text-yellow-800 text-sm">
                        <strong>Next Appeal:</strong> You can submit another appeal in {{ $hoursUntilNextAppeal }} hours.
                    </p>
                </div>
            @endif
        </div>
    @endif

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row gap-4">
        <button 
            wire:click="logout"
            class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors duration-200"
        >
            Back to Login
        </button>
        
        <a 
            href="mailto:support@kenha.co.ke?subject=Account%20Suspension%20Appeal" 
            class="flex-1 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold py-3 px-6 rounded-xl transition-colors duration-200 text-center"
        >
            Contact Support Directly
        </a>
    </div>
</div>
