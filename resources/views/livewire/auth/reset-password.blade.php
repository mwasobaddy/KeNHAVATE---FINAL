<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\{Layout, Title};
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Reset Password')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public string $title = 'Reset Password';
    public string $description = 'Enter your email and new password to reset your account password.';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status != Password::PasswordReset) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
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

    <form wire:submit="resetPassword" class="space-y-6">
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
                autocomplete="email"
                class="w-full px-4 py-3 border border-[#9B9EA4] dark:border-zinc-600 rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent transition-colors duration-200 dark:bg-zinc-800/50 dark:text-white dark:placeholder-zinc-400"
            />
            @error('email')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-[#231F20] dark:text-white mb-2">
                New Password <span class="text-red-500">*</span>
            </label>
            <input
                wire:model="password"
                id="password"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Enter new password"
                class="w-full px-4 py-3 border border-[#9B9EA4] dark:border-zinc-600 rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent transition-colors duration-200 dark:bg-zinc-800/50 dark:text-white dark:placeholder-zinc-400"
            />
            @error('password')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-[#231F20] dark:text-white mb-2">
                Confirm New Password <span class="text-red-500">*</span>
            </label>
            <input
                wire:model="password_confirmation"
                id="password_confirmation"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Confirm new password"
                class="w-full px-4 py-3 border border-[#9B9EA4] dark:border-zinc-600 rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent transition-colors duration-200 dark:bg-zinc-800/50 dark:text-white dark:placeholder-zinc-400"
            />
            @error('password_confirmation')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button 
            type="submit" 
            wire:loading.attr="disabled"
            class="w-full bg-[#FFF200] dark:bg-yellow-400 hover:bg-[#FFF200]/90 dark:hover:bg-yellow-300 text-[#231F20] dark:text-zinc-900 font-semibold py-3 px-6 rounded-xl transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg hover:shadow-xl"
        >
            <span wire:loading.remove>Reset Password</span>
            <span wire:loading class="flex items-center justify-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-[#231F20] dark:text-zinc-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Resetting...
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
