<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    public string $title = 'Forgot Password';
    public string $description = 'Enter your email address and we\'ll send you a link to reset your password.';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}; ?>

<div class="flex flex-col space-y-6">
    <!-- Session Status -->
    @if (session('status'))
        <div class="bg-[#F8EBD5] dark:bg-zinc-800/50 border border-[#FFF200] dark:border-yellow-400 rounded-xl p-4">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-[#FFF200] dark:text-yellow-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <p class="text-[#231F20] dark:text-white text-sm">{{ session('status') }}</p>
            </div>
        </div>
    @endif

    <form wire:submit="sendPasswordResetLink" class="space-y-6">
        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-medium text-[#231F20] dark:text-white mb-2">
                Email Address <span class="text-red-500">*</span>
            </label>
            <input
                wire:model="email"
                id="email"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
                class="w-full px-4 py-3 border border-[#9B9EA4] dark:border-zinc-600 rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent transition-colors duration-200 dark:bg-zinc-800/50 dark:text-white dark:placeholder-zinc-400"
            />
            @error('email')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button 
            type="submit" 
            wire:loading.attr="disabled"
            class="w-full bg-[#FFF200] dark:bg-yellow-400 hover:bg-[#FFF200]/90 dark:hover:bg-yellow-300 text-[#231F20] dark:text-zinc-900 font-semibold py-3 px-6 rounded-xl transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg hover:shadow-xl"
        >
            <span wire:loading.remove>Send Password Reset Link</span>
            <span wire:loading class="flex items-center justify-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-[#231F20] dark:text-zinc-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Sending...
            </span>
        </button>
    </form>

    <div class="text-center">
        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">
            Remember your password? 
            <a href="{{ route('login') }}" wire:navigate class="font-medium text-[#FFF200] dark:text-yellow-400 hover:text-[#FFF200]/80 dark:hover:text-yellow-300 transition-colors duration-200">
                Sign in here
            </a>
        </p>
    </div>
</div>
