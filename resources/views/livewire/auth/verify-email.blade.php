<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $title = 'Verify Email';
    public string $description = 'Please verify your email address by clicking on the link we sent to your email.';

    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="flex flex-col space-y-6">
    <!-- Information Message -->
    <div class="bg-[#F8EBD5] dark:bg-zinc-800/50 border border-[#FFF200] dark:border-yellow-400 rounded-xl p-6">
        <div class="flex items-center mb-3">
            <div class="flex-shrink-0">
                <svg class="w-8 h-8 text-[#FFF200] dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-lg font-semibold text-[#231F20] dark:text-white">Email Verification Required</h3>
            </div>
        </div>
        <p class="text-[#231F20] dark:text-zinc-300">
            Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="bg-[#F8EBD5] dark:bg-zinc-800/50 border border-[#FFF200] dark:border-yellow-400 rounded-xl p-4">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-[#FFF200] dark:text-yellow-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <p class="text-[#231F20] dark:text-white text-sm font-medium">
                    A new verification link has been sent to the email address you provided during registration.
                </p>
            </div>
        </div>
    @endif

    <div class="flex flex-col space-y-4">
        <button 
            wire:click="sendVerification" 
            wire:loading.attr="disabled"
            class="w-full bg-[#FFF200] dark:bg-yellow-400 hover:bg-[#FFF200]/90 dark:hover:bg-yellow-300 text-[#231F20] dark:text-zinc-900 font-semibold py-3 px-6 rounded-xl transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg hover:shadow-xl"
        >
            <span wire:loading.remove>Resend Verification Email</span>
            <span wire:loading class="flex items-center justify-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-[#231F20] dark:text-zinc-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Sending...
            </span>
        </button>

        <button 
            wire:click="logout" 
            class="w-full bg-[#9B9EA4] dark:bg-zinc-600 hover:bg-[#231F20] dark:hover:bg-zinc-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors duration-200"
        >
            Log Out
        </button>
    </div>

    <!-- Help Text -->
    <div class="text-center">
        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">
            Didn't receive the email? Check your spam folder or contact 
            <a href="mailto:support@kenha.co.ke" class="font-medium text-[#FFF200] dark:text-yellow-400 hover:text-[#FFF200]/80 dark:hover:text-yellow-300 transition-colors duration-200">
                support@kenha.co.ke
            </a>
        </p>
    </div>
</div>
