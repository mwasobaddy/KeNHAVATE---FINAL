<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $password = '';

    public string $title = 'Confirm Password';
    public string $description = 'This is a secure area of the application. Please confirm your password before continuing.';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col space-y-6">
    <!-- Security Notice -->
    <div class="bg-[#F8EBD5] border border-[#FFF200] rounded-xl p-4">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <svg class="w-6 h-6 text-[#FFF200]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-[#231F20] text-sm">
                    This is a secure area of the application. Please confirm your password before continuing.
                </p>
            </div>
        </div>
    </div>

    <!-- Session Status -->
    @if (session('status'))
        <div class="bg-[#F8EBD5] border border-[#FFF200] rounded-xl p-4">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-[#FFF200] mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <p class="text-[#231F20] text-sm">{{ session('status') }}</p>
            </div>
        </div>
    @endif

    <form wire:submit="confirmPassword" class="space-y-6">
        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-[#231F20] mb-2">
                Password <span class="text-red-500">*</span>
            </label>
            <input
                wire:model="password"
                id="password"
                type="password"
                required
                autocomplete="current-password"
                placeholder="Enter your password"
                class="w-full px-4 py-3 border border-[#9B9EA4] rounded-xl focus:ring-2 focus:ring-[#FFF200] focus:border-transparent transition-colors duration-200"
            />
            @error('password')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button 
            type="submit" 
            wire:loading.attr="disabled"
            class="w-full bg-[#FFF200] hover:bg-[#FFF200]/90 text-[#231F20] font-semibold py-3 px-6 rounded-xl transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg hover:shadow-xl"
        >
            <span wire:loading.remove>Confirm Password</span>
            <span wire:loading class="flex items-center justify-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-[#231F20]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Confirming...
            </span>
        </button>
    </form>
</div>
