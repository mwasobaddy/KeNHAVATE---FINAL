<?php

use App\Services\OTPService;
use App\Services\AuditService;
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

    public function boot(OTPService $otpService, AuditService $auditService): void
    {
        $this->otpService = $otpService;
        $this->auditService = $auditService;
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

            // Clear rate limiter
            RateLimiter::clear($this->throttleKey());
            
            // Login the user
            Auth::login($user, $this->remember);
            Session::regenerate();

            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

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

<div class="flex flex-col gap-6">
    <x-auth-header 
        :title="$showOtpForm ? 'Enter Verification Code' : __('Log in to your account')" 
        :description="$showOtpForm ? 'Enter the 6-digit code sent to your email' : 'Enter your email address to receive a verification code'" 
    />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    @if (!$showOtpForm)
        <!-- Email Form -->
        <form wire:submit="sendOTP" class="flex flex-col gap-6">
            <!-- Email Address -->
            <flux:input
                wire:model="email"
                :label="__('Email address')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Remember Me -->
            <flux:checkbox wire:model="remember" :label="__('Remember me')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full">
                    Send Verification Code
                </flux:button>
            </div>
        </form>
    @else
        <!-- OTP Form -->
        <div class="flex flex-col gap-4">
            @if ($otpMessage)
                <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200">
                    {{ $otpMessage }}
                </div>
            @endif

            <form wire:submit="verifyOTP" class="flex flex-col gap-6">
                <!-- OTP Input -->
                <flux:input
                    wire:model="otp"
                    label="Verification Code"
                    type="text"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="000000"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    class="text-center text-2xl tracking-widest"
                />

                <div class="flex flex-col gap-3">
                    <flux:button variant="primary" type="submit" class="w-full">
                        Verify Code
                    </flux:button>
                    
                    <div class="flex justify-between items-center text-sm">
                        <flux:button 
                            wire:click="backToEmail" 
                            variant="ghost" 
                            size="sm"
                            type="button"
                        >
                            ← Change Email
                        </flux:button>
                        
                        @if ($resendCooldown > 0)
                            <span class="text-zinc-500">
                                Resend in <span x-data="{ countdown: @entangle('resendCooldown') }" 
                                    x-init="
                                        $watch('countdown', value => {
                                            if (value > 0) {
                                                setTimeout(() => countdown--, 1000)
                                            }
                                        })
                                    " 
                                    x-text="countdown">{{ $resendCooldown }}</span>s
                            </span>
                        @else
                            <flux:button 
                                wire:click="resendOTP" 
                                variant="ghost" 
                                size="sm"
                                type="button"
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
        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            Don't have an account?
            <flux:link :href="route('register')" wire:navigate>Sign up</flux:link>
        </div>
    @endif
</div>

<script>
document.addEventListener('livewire:initialized', () => {
    Livewire.on('start-countdown', () => {
        // Countdown is handled by Alpine.js in the template
    });
});
</script>
