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
    public bool $showStaffForm = false;
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
        if ($this->showOtpForm) {
            $this->title = 'Verify Your Email';
            $this->description = 'Enter the verification code sent to your email';
        } elseif ($this->showStaffForm) {
            $this->title = 'Staff Information';
            $this->description = 'Please provide additional information required for KeNHA staff';
        } else {
            $this->title = 'Create Account';
            $this->description = 'Join the KeNHAVATE innovation community';
        }
    }

    public function updatedEmail(): void
    {
        $this->isKenhaStaff = str_ends_with(strtolower($this->email), '@kenha.co.ke');
    }

    /**
     * Handle initial registration form submission
     */
    public function register(): void
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'phone_number' => ['required', 'string', 'max:20'],
        ];

        $this->validate($rules);

        // Check if this is a KeNHA staff email
        $this->isKenhaStaff = str_ends_with(strtolower($this->email), '@kenha.co.ke');
        
        // Show staff form if it's a KeNHA email, otherwise proceed to OTP
        if ($this->isKenhaStaff) {
            $this->showStaffForm = true;
            $this->title = 'Staff Information';
            $this->description = 'Please provide additional information required for KeNHA staff';
        } else {
            // For non-staff users, proceed directly to OTP
            $this->sendOTP();
        }
    }
    
    /**
     * Handle staff form submission
     */
    public function submitStaffInfo(): void
    {
        // Validate staff-specific information
        $this->validate([
            'personal_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'different:email'],
            'staff_number' => ['required', 'string', 'max:20', 'unique:staff'],
            'department' => ['required', 'string', 'max:255'],
        ]);
        
        // Proceed to OTP verification
        $this->sendOTP();
    }
    
    /**
     * Send OTP for verification
     */
    private function sendOTP(): void
    {
        try {
            // Generate OTP for email verification
            $result = $this->otpService->generateOTP($this->email, 'registration');
            
            $this->showOtpForm = true;
            $this->showStaffForm = false;
            $this->otpMessage = 'A verification code has been sent to your email address.';
            
            // Update title and description
            $this->title = 'Verify Your Email';
            $this->description = 'Enter the verification code sent to your email';
            
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
        if ($this->showOtpForm && $this->isKenhaStaff) {
            // Go back to staff form from OTP form
            $this->showOtpForm = false;
            $this->showStaffForm = true;
            $this->otp = '';
            $this->otpMessage = '';
            $this->resendCooldown = 0;
            
            // Update title and description
            $this->title = 'Staff Information';
            $this->description = 'Please provide additional information required for KeNHA staff';
        } else {
            // Go back to initial registration form
            $this->showOtpForm = false;
            $this->showStaffForm = false;
            $this->otp = '';
            $this->otpMessage = '';
            $this->resendCooldown = 0;
            
            // Update title and description
            $this->title = 'Create Account';
            $this->description = 'Join the KeNHAVATE innovation community';
        }
    }
    
    /**
     * Go back from staff form to initial registration form
     */
    public function backToInitialForm(): void
    {
        $this->showStaffForm = false;
        $this->title = 'Create Account';
        $this->description = 'Join the KeNHAVATE innovation community';
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header 
        :title="$title"
        :description="$description"
    />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    @if ($showStaffForm)
        <!-- Staff Form -->
        <form wire:submit="submitStaffInfo" class="flex flex-col gap-6">
            <div class="p-4 mb-4 text-sm text-[#231F20] dark:text-white rounded-lg bg-[#F8EBD5] dark:bg-zinc-800/50 border border-[#FFF200] dark:border-yellow-400">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-[#FFF200] dark:text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="font-medium">KeNHA Staff Registration</p>
                        <p class="mt-1">Please provide additional information required for KeNHA staff.</p>
                    </div>
                </div>
            </div>
            
            <!-- Personal Email -->
            <flux:input
                wire:model="personal_email"
                label="Personal Email Address"
                type="email"
                required
                autofocus
                placeholder="personal@gmail.com"
            />

            <!-- Staff Number -->
            <flux:input
                wire:model="staff_number"
                label="Staff Number"
                type="text"
                required
                placeholder="KN2024001"
            />

            <!-- Department -->
            <flux:input
                wire:model="department"
                label="Department"
                type="text"
                required
                placeholder="Engineering Department"
            />

            <div class="flex items-center justify-between gap-4 mt-2">
                <flux:button 
                    wire:click="backToInitialForm" 
                    variant="outline" 
                    type="button"
                    class="flex-1 justify-center rounded-lg border border-[#9B9EA4] dark:border-zinc-600 px-4 py-3 text-sm font-semibold text-[#231F20] dark:text-white shadow-sm hover:bg-[#9B9EA4]/10 dark:hover:bg-zinc-700/50"
                >
                    Back
                </flux:button>
                
                <flux:button 
                    variant="primary" 
                    type="submit"
                    class="flex-1 justify-center rounded-lg bg-[#FFF200] dark:bg-yellow-400 px-4 py-3 text-sm font-semibold text-[#231F20] dark:text-zinc-900 shadow-lg hover:bg-[#FFF200]/90 dark:hover:bg-yellow-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FFF200] dark:focus-visible:outline-yellow-400 transition-all duration-200 hover:shadow-xl"
                >
                    Continue
                </flux:button>
            </div>
        </form>
    @elseif ($showOtpForm)
        <!-- OTP Verification Form -->
        <div class="flex flex-col gap-4">
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
                                     try {
                                         const firstInput = this.$refs.otp0;
                                         if (firstInput && typeof firstInput.focus === 'function') {
                                             firstInput.focus();
                                         }
                                     } catch (error) {
                                         console.log('OTP focus initialization skipped:', error);
                                     }
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
                </div>

                <!-- Action Buttons -->
                <div class="space-y-4">
                    <flux:button variant="primary" type="submit" class="w-full justify-center rounded-lg bg-[#FFF200] dark:bg-yellow-400 px-4 py-3 text-sm font-semibold text-[#231F20] dark:text-zinc-900 shadow-lg hover:bg-[#FFF200]/90 dark:hover:bg-yellow-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FFF200] dark:focus-visible:outline-yellow-400 transition-all duration-200 hover:shadow-xl">
                        Verify & Create Account
                    </flux:button>
                    
                    <div class="flex items-center justify-between">
                        <flux:button 
                            wire:click="backToRegistration" 
                            variant="ghost" 
                            size="sm"
                            type="button"
                            class="text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-white"
                        >
                            ← Back
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
    @else
        <!-- Initial Registration Form -->
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
                <flux:button variant="primary" type="submit" class="w-full justify-center rounded-lg bg-[#FFF200] dark:bg-yellow-400 px-4 py-3 text-sm font-semibold text-[#231F20] dark:text-zinc-900 shadow-lg hover:bg-[#FFF200]/90 dark:hover:bg-yellow-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FFF200] dark:focus-visible:outline-yellow-400 transition-all duration-200 hover:shadow-xl">
                    Create Account
                </flux:button>
            </div>
        </form>
    @endif

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-[#9B9EA4] dark:text-zinc-400">
        Already have an account?
        <flux:link :href="route('login')" wire:navigate class="text-[#FFF200] dark:text-yellow-400 hover:text-[#FFF200]/80 dark:hover:text-yellow-300">Log in</flux:link>
    </div>
</div>

<script>
document.addEventListener('livewire:initialized', () => {
    Livewire.on('start-countdown', () => {
        // Countdown is handled by Alpine.js in the template
    });
});
</script>
