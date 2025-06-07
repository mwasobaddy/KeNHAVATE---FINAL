<?php

use App\Services\OTPService;
use App\Services\AuditService;
use App\Services\DailyLoginService;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string|min:6|max:6')]
    public string $otp = '';

    public bool $remember = false;
    public bool $showOtpForm = false;
    public string $otpMessage = '';
    public int $resendCooldown = 0;

    protected OTPService $otpService;
    protected AuditService $auditService;
    protected DailyLoginService $dailyLoginService;

    public function boot(OTPService $otpService, AuditService $auditService, DailyLoginService $dailyLoginService): void
    {
        $this->otpService = $otpService;
        $this->auditService = $auditService;
        $this->dailyLoginService = $dailyLoginService;
    }

    public function mount(): void
    {
        $this->title = $this->showOtpForm ? 'Enter Verification Code' : 'Welcome back';
        $this->description = $this->showOtpForm ? 'Enter the 6-digit code sent to your email' : 'Sign in to your account to continue';
    }

    /**
     * Handle email submission and OTP generation
     */
    public function sendOTP(): void
    {
        $this->validate(['email' => 'required|string|email']);

        $this->ensureIsNotRateLimited();

        try {
            // Check if user exists
            $user = User::where('email', $this->email)->first();
            if (!$user) {
                // Don't reveal if email exists or not for security
                throw ValidationException::withMessages([
                    'email' => 'If this email is registered, an OTP will be sent.',
                ]);
            }

            // Generate OTP
            $result = $this->otpService->generateOTP($this->email, 'login');
            
            $this->showOtpForm = true;
            $this->otpMessage = $result['action'] === 'resent' 
                ? 'OTP has been resent to your email address.' 
                : 'OTP has been sent to your email address.';
            
            // Start resend cooldown
            $this->resendCooldown = 60;
            $this->dispatch('start-countdown');

            // Log OTP generation attempt
            $this->auditService->log(
                'otp_generation',
                'User',
                $user->id,
                null,
                ['purpose' => 'login', 'email' => $this->email]
            );

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'wait')) {
                $this->addError('email', $e->getMessage());
            } else {
                $this->addError('email', 'If this email is registered, an OTP will be sent.');
            }
        }
    }

    /**
     * Handle OTP verification and login
     */
    public function verifyOTP(): void
    {
        $this->validate(['otp' => 'required|string|min:6|max:6']);

        try {
            // Verify OTP
            $user = $this->otpService->verifyOTP($this->email, $this->otp, 'login');
            
            // Check account status before allowing login
            if ($user->isBanned()) {
                session(['banned_user_email' => $user->email]);
                $this->redirectRoute('banned-account', navigate: true);
                return;
            }
            
            if ($user->isSuspended()) {
                session(['suspended_user_email' => $user->email]);
                $this->redirectRoute('suspended-account', navigate: true);
                return;
            }
            
            // Generate device fingerprint
            $deviceFingerprint = $this->generateDeviceFingerprint();
            
            // Check if this is a new device
            $existingDevice = UserDevice::where('user_id', $user->id)
                ->where('device_fingerprint', $deviceFingerprint)
                ->first();

            if (!$existingDevice) {
                // Create new device record
                UserDevice::create([
                    'user_id' => $user->id,
                    'device_fingerprint' => $deviceFingerprint,
                    'device_name' => $this->getDeviceName(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'is_trusted' => false,
                    'last_used_at' => now(),
                ]);

                // Log new device login
                $this->auditService->log(
                    'new_device_login',
                    'User',
                    $user->id,
                    null,
                    [
                        'device_fingerprint' => $deviceFingerprint,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent()
                    ]
                );

                // TODO: Send email notification about new device login
            } else {
                // Update last used timestamp
                $existingDevice->update(['last_used_at' => now()]);
            }

            // Log successful login
            $this->auditService->log(
                'login',
                'User',
                $user->id,
                null,
                ['login_method' => 'otp', 'ip_address' => request()->ip()]
            );

            // Award daily login points (gamification integration)
            $pointsAwarded = $this->dailyLoginService->processLogin($user);
            if ($pointsAwarded) {
                // Show points notification after login
                session()->flash('points_notification', [
                    'points' => $pointsAwarded->points,
                    'message' => $pointsAwarded->description,
                    'type' => 'daily_login'
                ]);
            }

            // Clear rate limiter
            RateLimiter::clear($this->throttleKey());
            
            // Login the user
            Auth::login($user, $this->remember);
            Session::regenerate();
            
            // Update terms acceptance status
            $user->update(['terms_accepted' => false]);

            // Check if user has accepted terms
            if (!$user->terms_accepted) {
                $this->redirectRoute('terms-and-conditions', navigate: true);
            } else {
                $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
            }

        } catch (\Exception $e) {
            RateLimiter::hit($this->throttleKey());
            $this->addError('otp', $e->getMessage());
        }
    }

    /**
     * Resend OTP if cooldown has expired
     */
    public function resendOTP(): void
    {
        if ($this->resendCooldown > 0) {
            $this->addError('otp', "Please wait {$this->resendCooldown} seconds before requesting a new OTP.");
            return;
        }

        $this->sendOTP();
    }

    /**
     * Go back to email form
     */
    public function backToEmail(): void
    {
        $this->showOtpForm = false;
        $this->otp = '';
        $this->otpMessage = '';
        $this->resendCooldown = 0;
    }

    /**
     * Generate device fingerprint for security tracking
     */
    protected function generateDeviceFingerprint(): string
    {
        $userAgent = request()->userAgent();
        $acceptLanguage = request()->header('Accept-Language', '');
        $acceptEncoding = request()->header('Accept-Encoding', '');
        
        return hash('sha256', $userAgent . $acceptLanguage . $acceptEncoding);
    }

    /**
     * Get human-readable device name
     */
    protected function getDeviceName(): string
    {
        $userAgent = request()->userAgent();
        
        if (str_contains($userAgent, 'Mobile') || str_contains($userAgent, 'Android')) {
            return 'Mobile Device';
        } elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            return str_contains($userAgent, 'iPad') ? 'iPad' : 'iPhone';
        } elseif (str_contains($userAgent, 'Windows')) {
            return 'Windows Computer';
        } elseif (str_contains($userAgent, 'Mac')) {
            return 'Mac Computer';
        } elseif (str_contains($userAgent, 'Linux')) {
            return 'Linux Computer';
        }
        
        return 'Unknown Device';
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<div class="space-y-6">
    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    @if (!$showOtpForm)
        <!-- Email Form -->
        <form wire:submit="sendOTP" class="space-y-6">
            <!-- Email Address -->
            <div>
                <flux:input
                    wire:model="email"
                    :label="__('Email address')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="hello@example.com"
                    class="mt-1 block w-full rounded-lg border-[#9B9EA4] dark:border-zinc-600 shadow-sm focus:border-[#FFF200] focus:ring-[#FFF200] dark:bg-zinc-800/50 dark:text-white dark:placeholder-zinc-400"
                />
                @error('email')
                    <div class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                @enderror
            </div>

            <!-- Remember Me -->
            <div class="flex items-center">
                <flux:checkbox wire:model="remember" :label="__('Remember me')" />
            </div>

            <!-- Submit Button -->
            <div class="space-y-4">
                <flux:button variant="primary" type="submit" class="w-full justify-center rounded-lg bg-[#FFF200] dark:bg-yellow-400 px-4 py-3 text-sm font-semibold text-[#231F20] dark:text-zinc-900 shadow-lg hover:bg-[#FFF200]/90 dark:hover:bg-yellow-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FFF200] dark:focus-visible:outline-yellow-400 transition-all duration-200 hover:shadow-xl">
                    Send Verification Code
                </flux:button>
            </div>
        </form>
    @else
        <!-- OTP Form -->
        <div class="space-y-6">
            @if ($otpMessage)
                <div class="rounded-lg bg-[#F8EBD5] dark:bg-zinc-800/50 p-4 border border-[#FFF200] dark:border-yellow-400">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-[#FFF200] dark:text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-[#231F20] dark:text-white">{{ $otpMessage }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <form wire:submit="verifyOTP" class="space-y-6">
                <!-- OTP Input -->
                <div>
                    <label class="block text-sm font-medium text-[#231F20] dark:text-white mb-4">Verification Code</label>
                    
                    <!-- OTP Input Boxes -->
                    <div class="flex justify-center space-x-3 mb-4" 
                         x-data="{ 
                             otp: ['', '', '', '', '', ''],
                             init() {
                                 // Auto-focus first input when component initializes
                                 this.$nextTick(() => {
                                     this.$refs.otp0?.focus();
                                 });
                             },
                             handleInput(index, event) {
                                 let value = event.target.value;
                                 
                                 // Only allow numeric input and single character
                                 if (!/^[0-9]*$/.test(value)) {
                                     // Remove non-numeric characters
                                     value = value.replace(/[^0-9]/g, '');
                                 }
                                 
                                 // Take only the last entered digit if multiple
                                 if (value.length > 1) {
                                     value = value.slice(-1);
                                 }
                                 
                                 // Update the input value and model
                                 event.target.value = value;
                                 this.otp[index] = value;
                                 this.updateOtp();
                                 
                                 // Auto-advance to next input if digit was entered
                                 if (value && index < 5) {
                                     const nextInput = this.$refs['otp' + (index + 1)];
                                     if (nextInput) {
                                         nextInput.focus();
                                     }
                                 }
                             },
                             handleKeyDown(index, event) {
                                 // Prevent non-numeric input except for control keys
                                 if (!/[0-9]/.test(event.key) && 
                                     !['Backspace', 'Delete', 'Tab', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
                                     event.preventDefault();
                                     return;
                                 }
                                 
                                 // Handle arrow key navigation
                                 if (event.key === 'ArrowRight' && index < 5) {
                                     event.preventDefault();
                                     this.$refs['otp' + (index + 1)]?.focus();
                                 } else if (event.key === 'ArrowLeft' && index > 0) {
                                     event.preventDefault();
                                     this.$refs['otp' + (index - 1)]?.focus();
                                 } else if (event.key === 'Backspace') {
                                     // If current field is empty, move to previous and clear it
                                     if (this.otp[index] === '' && index > 0) {
                                         event.preventDefault();
                                         this.otp[index - 1] = '';
                                         this.$refs['otp' + (index - 1)].value = '';
                                         this.updateOtp();
                                         this.$refs['otp' + (index - 1)]?.focus();
                                     } else {
                                         // Clear current field
                                         this.otp[index] = '';
                                         this.updateOtp();
                                     }
                                 } else if (event.key === 'Delete') {
                                     // Clear current field
                                     this.otp[index] = '';
                                     event.target.value = '';
                                     this.updateOtp();
                                 }
                             },
                             updateOtp() {
                                 $wire.set('otp', this.otp.join(''));
                             },
                             handlePaste(event) {
                                 event.preventDefault();
                                 const paste = (event.clipboardData || window.clipboardData).getData('text');
                                 const numbers = paste.replace(/[^0-9]/g, '').split('').slice(0, 6);
                                 
                                 // Clear all inputs first
                                 for (let i = 0; i < 6; i++) {
                                     this.otp[i] = '';
                                     const input = this.$refs['otp' + i];
                                     if (input) input.value = '';
                                 }
                                 
                                 // Fill with pasted numbers
                                 for (let i = 0; i < numbers.length && i < 6; i++) {
                                     this.otp[i] = numbers[i];
                                     const input = this.$refs['otp' + i];
                                     if (input) input.value = numbers[i];
                                 }
                                 
                                 this.updateOtp();
                                 
                                 // Focus the next empty input or the last filled one
                                 const nextIndex = Math.min(numbers.length, 5);
                                 this.$refs['otp' + nextIndex]?.focus();
                             }
                         }">
                        <!-- Individual OTP inputs -->
                        <input 
                            x-ref="otp0"
                            type="text" 
                            inputmode="numeric"
                            maxlength="1"
                            pattern="[0-9]"
                            x-model="otp[0]"
                            @input="handleInput(0, $event)"
                            @keydown="handleKeyDown(0, $event)"
                            @paste="handlePaste($event)"
                            class="w-14 h-14 text-center text-lg font-bold rounded-full border-2 border-[#9B9EA4] dark:border-zinc-600 focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:ring-opacity-50 bg-white dark:bg-zinc-800/50 text-[#231F20] dark:text-white transition-all duration-200 hover:border-[#FFF200] dark:hover:border-yellow-400"
                            autocomplete="off"
                        />
                        <input 
                            x-ref="otp1"
                            type="text" 
                            inputmode="numeric"
                            maxlength="1"
                            pattern="[0-9]"
                            x-model="otp[1]"
                            @input="handleInput(1, $event)"
                            @keydown="handleKeyDown(1, $event)"
                            @paste="handlePaste($event)"
                            class="w-14 h-14 text-center text-lg font-bold rounded-full border-2 border-[#9B9EA4] dark:border-zinc-600 focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:ring-opacity-50 bg-white dark:bg-zinc-800/50 text-[#231F20] dark:text-white transition-all duration-200 hover:border-[#FFF200] dark:hover:border-yellow-400"
                            autocomplete="off"
                        />
                        <input 
                            x-ref="otp2"
                            type="text" 
                            inputmode="numeric"
                            maxlength="1"
                            pattern="[0-9]"
                            x-model="otp[2]"
                            @input="handleInput(2, $event)"
                            @keydown="handleKeyDown(2, $event)"
                            @paste="handlePaste($event)"
                            class="w-14 h-14 text-center text-lg font-bold rounded-full border-2 border-[#9B9EA4] dark:border-zinc-600 focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:ring-opacity-50 bg-white dark:bg-zinc-800/50 text-[#231F20] dark:text-white transition-all duration-200 hover:border-[#FFF200] dark:hover:border-yellow-400"
                            autocomplete="off"
                        />
                        <input 
                            x-ref="otp3"
                            type="text" 
                            inputmode="numeric"
                            maxlength="1"
                            pattern="[0-9]"
                            x-model="otp[3]"
                            @input="handleInput(3, $event)"
                            @keydown="handleKeyDown(3, $event)"
                            @paste="handlePaste($event)"
                            class="w-14 h-14 text-center text-lg font-bold rounded-full border-2 border-[#9B9EA4] dark:border-zinc-600 focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:ring-opacity-50 bg-white dark:bg-zinc-800/50 text-[#231F20] dark:text-white transition-all duration-200 hover:border-[#FFF200] dark:hover:border-yellow-400"
                            autocomplete="off"
                        />
                        <input 
                            x-ref="otp4"
                            type="text" 
                            inputmode="numeric"
                            maxlength="1"
                            pattern="[0-9]"
                            x-model="otp[4]"
                            @input="handleInput(4, $event)"
                            @keydown="handleKeyDown(4, $event)"
                            @paste="handlePaste($event)"
                            class="w-14 h-14 text-center text-lg font-bold rounded-full border-2 border-[#9B9EA4] dark:border-zinc-600 focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:ring-opacity-50 bg-white dark:bg-zinc-800/50 text-[#231F20] dark:text-white transition-all duration-200 hover:border-[#FFF200] dark:hover:border-yellow-400"
                            autocomplete="off"
                        />
                        <input 
                            x-ref="otp5"
                            type="text" 
                            inputmode="numeric"
                            maxlength="1"
                            pattern="[0-9]"
                            x-model="otp[5]"
                            @input="handleInput(5, $event)"
                            @keydown="handleKeyDown(5, $event)"
                            @paste="handlePaste($event)"
                            class="w-14 h-14 text-center text-lg font-bold rounded-full border-2 border-[#9B9EA4] dark:border-zinc-600 focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:ring-opacity-50 bg-white dark:bg-zinc-800/50 text-[#231F20] dark:text-white transition-all duration-200 hover:border-[#FFF200] dark:hover:border-yellow-400"
                            autocomplete="off"
                        />
                    </div>
                    
                    <!-- Hidden input for Livewire -->
                    <input type="hidden" wire:model="otp" />
                    
                    <p class="text-center text-sm text-[#9B9EA4] dark:text-zinc-400">Enter the 6-digit code sent to <span class="font-medium text-[#231F20] dark:text-white">{{ $email }}</span></p>
                    
                    @error('otp')
                        <div class="mt-2 text-center text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Action Buttons -->
                <div class="space-y-4">
                    <flux:button variant="primary" type="submit" class="w-full justify-center rounded-lg bg-[#FFF200] dark:bg-yellow-400 px-4 py-3 text-sm font-semibold text-[#231F20] dark:text-zinc-900 shadow-lg hover:bg-[#FFF200]/90 dark:hover:bg-yellow-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FFF200] dark:focus-visible:outline-yellow-400 transition-all duration-200 hover:shadow-xl">
                        Verify & Continue
                    </flux:button>
                    
                    <div class="flex items-center justify-between">
                        <flux:button 
                            wire:click="backToEmail" 
                            variant="ghost" 
                            size="sm"
                            type="button"
                            class="text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-white"
                        >
                            ← Change Email
                        </flux:button>
                        
                        @if ($resendCooldown > 0)
                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400"
                                  x-data="{ countdown: @entangle('resendCooldown').live }" 
                                  x-init="
                                      let timer = setInterval(() => {
                                          if (countdown > 0) {
                                              countdown--;
                                          } else {
                                              clearInterval(timer);
                                          }
                                      }, 1000);
                                      
                                      // Clean up on component destroy
                                      $watch('countdown', value => {
                                          if (value <= 0) {
                                              clearInterval(timer);
                                          }
                                      });
                                  ">
                                Resend in <span x-text="countdown" class="font-medium text-[#FFF200] dark:text-yellow-400"></span>s
                            </span>
                        @else
                            <flux:button 
                                wire:click="resendOTP" 
                                variant="ghost" 
                                size="sm"
                                type="button"
                                class="text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-white"
                            >
                                Resend Code
                            </flux:button>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    @endif

    @if (Route::has('register'))
        <div class="text-center border-t border-[#9B9EA4] dark:border-zinc-600 pt-6">
            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                Don't have an account?
                <flux:link :href="route('register')" wire:navigate class="font-medium text-[#FFF200] dark:text-yellow-400 hover:text-[#FFF200]/80 dark:hover:text-yellow-300">Sign up for free</flux:link>
            </p>
        </div>
    @endif
</div>

{{-- <script>
document.addEventListener('livewire:initialized', () => {
    Livewire.on('start-countdown', () => {
        // Countdown is now handled by Alpine.js with proper entanglement
        console.log('Countdown started');
    });
});
</script> --}}
