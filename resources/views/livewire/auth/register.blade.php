<?php

use App\Models\User;
use App\Models\Staff;
use App\Services\OTPService;
use App\Services\AuditService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $personal_email = '';
    public string $phone_number = '';
    public string $staff_number = '';
    public string $department = '';
    public string $otp = '';
    
    public bool $showOtpForm = false;
    public bool $isKenhaStaff = false;
    public string $otpMessage = '';
    public int $resendCooldown = 0;

    protected OTPService $otpService;
    protected AuditService $auditService;

    public function boot(OTPService $otpService, AuditService $auditService): void
    {
        $this->otpService = $otpService;
        $this->auditService = $auditService;
    }

    public function mount(): void
    {
        $this->title = $this->showOtpForm ? 'Verify Your Email' : 'Create Account';
        $this->description = $this->showOtpForm ? 'Enter the verification code sent to your email' : 'Join the KeNHAVATE innovation community';
    }

    public function updatedEmail(): void
    {
        $this->isKenhaStaff = str_ends_with(strtolower($this->email), '@kenha.co.ke');
    }

    /**
     * Handle registration and send OTP
     */
    public function register(): void
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'phone_number' => ['required', 'string', 'max:20'],
        ];

        // Additional validation for KeNHA staff
        if ($this->isKenhaStaff) {
            $rules['personal_email'] = ['required', 'string', 'lowercase', 'email', 'max:255', 'different:email'];
            $rules['staff_number'] = ['required', 'string', 'max:20', 'unique:staff'];
            $rules['department'] = ['required', 'string', 'max:255'];
        }

        $this->validate($rules);

        try {
            // Generate OTP for email verification
            $result = $this->otpService->generateOTP($this->email, 'registration');
            
            $this->showOtpForm = true;
            $this->otpMessage = 'A verification code has been sent to your email address.';
            
            // Start resend cooldown
            $this->resendCooldown = 60;
            $this->dispatch('start-countdown');

        } catch (\Exception $e) {
            $this->addError('email', $e->getMessage());
        }
    }

    /**
     * Verify OTP and complete registration
     */
    public function verifyOTP(): void
    {
        $this->validate(['otp' => 'required|string|min:6|max:6']);

        try {
            // Verify OTP
            $this->otpService->validateOTP($this->email, $this->otp, 'registration');
            
            // Create the user
            $user = User::create([
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'phone' => $this->phone_number,
                'email_verified_at' => now(),
                'password' => Hash::make('temp_password_' . uniqid()), // Temporary password
            ]);

            // Create staff record if KeNHA email
            if ($this->isKenhaStaff) {
                Staff::create([
                    'user_id' => $user->id,
                    'personal_email' => $this->personal_email,
                    'staff_number' => $this->staff_number,
                    'job_title' => 'To be assigned', // Default value - can be updated later
                    'department' => $this->department,
                    'supervisor_name' => null, // Optional field
                    'work_station' => 'To be assigned', // Default value - can be updated later
                    'employment_date' => now()->toDateString(), // Use registration date as default
                    'employment_type' => 'permanent', // Default assumption for @kenha.co.ke emails
                ]);
            }

            // Assign appropriate role
            $role = 'user'; // All users get 'user' role by default, regardless of email domain
            $user->assignRole($role);

            // Log registration
            $this->auditService->log(
                'account_creation',
                'User',
                $user->id,
                null,
                [
                    'email' => $this->email,
                    'is_staff' => $this->isKenhaStaff,
                    'registration_method' => 'otp_verification'
                ]
            );

            // Fire registered event
            event(new Registered($user));

            // Login the user
            Auth::login($user);

            // Redirect to role-specific dashboard
            $redirectRoute = $this->getDashboardRoute($user);
            $this->redirectIntended($redirectRoute, navigate: true);

        } catch (\Exception $e) {
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

        try {
            $result = $this->otpService->generateOTP($this->email, 'registration');
            $this->otpMessage = 'Verification code has been resent to your email address.';
            $this->resendCooldown = 60;
            $this->dispatch('start-countdown');
        } catch (\Exception $e) {
            $this->addError('otp', $e->getMessage());
        }
    }

    /**
     * Get role-specific dashboard route
     */
    private function getDashboardRoute(User $user): string
    {
        $userRole = $user->roles->first()?->name ?? 'user';
        
        return match($userRole) {
            'developer', 'administrator' => route('dashboard.admin'),
            'board_member' => route('dashboard.board-member'),
            'manager' => route('dashboard.manager'),
            'sme' => route('dashboard.sme'),
            'challenge_reviewer' => route('dashboard.challenge-reviewer'),
            'idea_reviewer' => route('dashboard.idea-reviewer'),
            default => route('dashboard.user'),
        };
    }

    /**
     * Go back to registration form
     */
    public function backToRegistration(): void
    {
        $this->showOtpForm = false;
        $this->otp = '';
        $this->otpMessage = '';
        $this->resendCooldown = 0;
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header 
        :title="$showOtpForm ? 'Verify Your Email' : 'Create Account'" 
        :description="$showOtpForm ? 'Enter the verification code sent to your email' : 'Join the KeNHAVATE innovation community'" 
    />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    @if (!$showOtpForm)
        <!-- Registration Form -->
        <form wire:submit="register" class="flex flex-col gap-6">
            <!-- First Name -->
            <flux:input
                wire:model="first_name"
                label="First Name"
                type="text"
                required
                autofocus
                autocomplete="given-name"
                placeholder="First name"
            />

            <!-- Last Name -->
            <flux:input
                wire:model="last_name"
                label="Last Name"
                type="text"
                required
                autocomplete="family-name"
                placeholder="Last name"
            />

            <!-- Email Address -->
            <flux:input
                wire:model.live="email"
                label="Email Address"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- KeNHA Staff Additional Fields -->
            @if ($isKenhaStaff)
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-800 font-medium mb-4">
                        KeNHA Staff Registration - Additional Information Required
                    </p>
                    
                    <!-- Personal Email -->
                    <flux:input
                        wire:model="personal_email"
                        label="Personal Email Address"
                        type="email"
                        required
                        placeholder="personal@gmail.com"
                        class="mb-4"
                    />

                    <!-- Staff Number -->
                    <flux:input
                        wire:model="staff_number"
                        label="Staff Number"
                        type="text"
                        required
                        placeholder="KN2024001"
                        class="mb-4"
                    />

                    <!-- Department -->
                    <flux:input
                        wire:model="department"
                        label="Department"
                        type="text"
                        required
                        placeholder="Engineering Department"
                    />
                </div>
            @endif

            <!-- Phone Number -->
            <flux:input
                wire:model="phone_number"
                label="Phone Number"
                type="tel"
                required
                autocomplete="tel"
                placeholder="+254 7XX XXX XXX"
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full">
                    Create Account
                </flux:button>
            </div>
        </form>
    @else
        <!-- OTP Verification Form -->
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
                        Verify & Create Account
                    </flux:button>
                    
                    <div class="flex justify-between items-center text-sm">
                        <flux:button 
                            wire:click="backToRegistration" 
                            variant="ghost" 
                            size="sm"
                            type="button"
                        >
                            ← Back to Registration
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

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
        Already have an account?
        <flux:link :href="route('login')" wire:navigate>Log in</flux:link>
    </div>
</div>

<script>
document.addEventListener('livewire:initialized', () => {
    Livewire.on('start-countdown', () => {
        // Countdown is handled by Alpine.js in the template
    });
});
</script>
