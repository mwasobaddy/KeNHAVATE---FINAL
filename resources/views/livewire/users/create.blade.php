<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Staff;
use Spatie\Permission\Models\Role;
use App\Services\AuditService;
use App\Services\GamificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

new #[Layout('components.layouts.app', title: 'Create User')] class extends Component {
    // Create User Form Properties
    public $first_name = '';
    public $last_name = '';
    public $email = '';
    public $phone = '';
    public $gender = '';
    public $user_role = 'user';
    public $account_status = 'active';
    public $is_kenha_staff = false;
    
    // Staff-specific fields
    public $personal_email = '';
    public $staff_number = '';
    public $job_title = '';
    public $department = '';
    public $supervisor_name = '';
    public $work_station = '';
    public $employment_type = 'permanent';
    public $employment_date = '';

    protected AuditService $auditService;
    protected GamificationService $gamificationService;

    public function boot(AuditService $auditService, GamificationService $gamificationService): void
    {
        $this->auditService = $auditService;
        $this->gamificationService = $gamificationService;
    }

    public function mount()
    {
        // Check permission to create users
        if (!auth()->user()->can('create_users')) {
            abort(403, 'Unauthorized access to user creation.');
        }

        // Set default employment date to today
        $this->employment_date = now()->toDateString();
    }

    public function updatedEmail(): void
    {
        $this->is_kenha_staff = str_ends_with(strtolower($this->email), '@kenha.co.ke');
    }

    public function createUser()
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'max:20'],
            'gender' => ['required', 'in:male,female,other'],
            'user_role' => ['required', 'exists:roles,name'],
            'account_status' => ['required', 'in:active,suspended,banned'],
        ];

        // Add staff-specific validation if KeNHA staff
        if ($this->is_kenha_staff) {
            $rules = array_merge($rules, [
                'personal_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'different:email'],
                'staff_number' => ['required', 'string', 'max:20', 'unique:staff'],
                'job_title' => ['required', 'string', 'max:255'],
                'department' => ['required', 'string', 'max:255'],
                'supervisor_name' => ['nullable', 'string', 'max:255'],
                'work_station' => ['required', 'string', 'max:255'],
                'employment_type' => ['required', 'in:permanent,contract,temporary'],
                'employment_date' => ['required', 'date', 'before_or_equal:today'],
            ]);
        }

        $this->validate($rules);

        // Check if user trying to create developer role
        $currentUser = auth()->user();
        if ($this->user_role === 'developer' && !$currentUser->hasRole('developer')) {
            $this->addError('user_role', 'You cannot assign the developer role.');
            return;
        }

        try {
            // Create user
            $user = User::create([
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'phone' => $this->phone,
                'gender' => $this->gender,
                'account_status' => $this->account_status,
                'email_verified_at' => now(), // Admin-created users are pre-verified
                'terms_accepted' => true, // Admin-created users auto-accept terms
                'password' => Hash::make('temp_password_' . uniqid()), // Temporary password
            ]);

            // Assign role
            $user->assignRole($this->user_role);

            // Create staff record if applicable
            if ($this->is_kenha_staff) {
                Staff::create([
                    'user_id' => $user->id,
                    'personal_email' => $this->personal_email,
                    'staff_number' => $this->staff_number,
                    'job_title' => $this->job_title,
                    'department' => $this->department,
                    'supervisor_name' => $this->supervisor_name,
                    'work_station' => $this->work_station,
                    'employment_date' => $this->employment_date,
                    'employment_type' => $this->employment_type,
                ]);
            }

            // Award signup points if regular user
            if ($this->user_role === 'user') {
                $this->gamificationService->awardFirstTimeSignup($user);
            }

            // Create audit log
            $this->auditService->log(
                'user_created',
                'User',
                $user->id,
                null,
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $this->user_role,
                    'is_staff' => $this->is_kenha_staff,
                    'created_by' => $currentUser->name,
                ]
            );

            session()->flash('message', 'User created successfully! They will need to set up their password on first login.');
            
            // Redirect to user details page
            $this->redirectRoute('users.show', $user, navigate: true);

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create user: ' . $e->getMessage());
        }
    }

    public function with()
    {
        $currentUser = auth()->user();
        $isDeveloper = $currentUser->hasRole('developer');
        
        // Filter roles based on user permissions
        $roles = Role::when(!$isDeveloper, function ($q) {
            $q->where('name', '!=', 'developer');
        })->get();

        return [
            'roles' => $roles,
        ];
    }

}; ?>

{{-- Modern Create User Form with Glass Morphism & Enhanced UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-5xl mx-auto">
        {{-- Enhanced Header with Glass Morphism --}}
        <section aria-labelledby="create-user-heading" class="group">
                        
            <div class="block lg:hidden mb-6">
                <flux:button 
                    href="{{ route('users.index') }}" 
                    wire:navigate
                    variant="primary"
                    class="group/submit justify-center rounded-lg bg-[#FFF200] dark:bg-yellow-400 px-4 py-3 text-sm font-semibold text-[#231F20] dark:text-zinc-900 shadow-lg hover:bg-[#FFF200]/90 dark:hover:bg-yellow-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FFF200] dark:focus-visible:outline-yellow-400 transition-all duration-200 hover:shadow-xl"
                >
                    <span class="absolute inset-0 bg-gradient-to-br from-yellow-300/20 to-amber-300/20 opacity-0 group-hover/submit:opacity-100 transition-opacity duration-300"></span>
                    <div class="relative flex items-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        <span class="font-medium">Back to Users</span>
                    </div>
                </flux:button>
            </div>

            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Animated Gradient Background --}}
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-100"></div>
                
                <div class="relative p-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 flex items-center justify-center shadow-lg">
                                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                    </svg>
                                </div>
                                <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-50"></div>
                            </div>
                            <div>
                                <h1 id="create-user-heading" class="text-3xl font-bold text-[#231F20] dark:text-zinc-100">Create New User</h1>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 mt-1">Add a new user to the KeNHAVATE Innovation Portal</p>
                            </div>
                        </div>
                        
                        <div class="hidden lg:block">
                            <flux:button 
                                variant="ghost" 
                                href="{{ route('users.index') }}" 
                                wire:navigate
                                class="group/back relative overflow-hidden rounded-2xl bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm border border-white/40 dark:border-zinc-600/40 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-6 py-3"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-gray-500/10 to-gray-600/20 dark:from-gray-400/20 dark:to-gray-500/30 opacity-0 group-hover/back:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                    </svg>
                                    <span class="font-medium">Back to Users</span>
                                </div>
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Create User Form with Glass Morphism --}}
        <section aria-labelledby="user-form-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Animated Background Elements --}}
                <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl"></div>
                
                <div class="relative z-10 p-8">
                    <form wire:submit="createUser" class="space-y-10">
                        {{-- Basic Information Section --}}
                        <div class="group/section">
                            <div class="flex items-center space-x-4 mb-6">
                                <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Basic Information</h3>
                                    <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">Essential user account details</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {{-- First Name --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">First Name</flux:label>
                                        <flux:input 
                                            wire:model="first_name" 
                                            placeholder="Enter first name"
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        />
                                        <flux:error name="first_name" />
                                    </flux:field>
                                </div>

                                {{-- Last Name --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Last Name</flux:label>
                                        <flux:input 
                                            wire:model="last_name" 
                                            placeholder="Enter last name"
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        />
                                        <flux:error name="last_name" />
                                    </flux:field>
                                </div>

                                {{-- Email Address --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Email Address</flux:label>
                                        <flux:input 
                                            type="email" 
                                            wire:model.live="email" 
                                            placeholder="user@example.com"
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        />
                                        @if($is_kenha_staff)
                                            <div class="mt-2 inline-flex items-center space-x-2 text-xs font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-3 py-1.5 rounded-full">
                                                <div class="w-2 h-2 bg-amber-500 dark:bg-amber-400 rounded-full animate-pulse"></div>
                                                <span>KeNHA Staff Detected</span>
                                            </div>
                                        @endif
                                        <flux:error name="email" />
                                    </flux:field>
                                </div>

                                {{-- Phone Number --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Phone Number</flux:label>
                                        <flux:input 
                                            wire:model="phone" 
                                            placeholder="+254 7XX XXX XXX"
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        />
                                        <flux:error name="phone" />
                                    </flux:field>
                                </div>

                                {{-- Gender --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Gender</flux:label>
                                        <flux:select 
                                            wire:model="gender" 
                                            placeholder="Select gender" 
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        >
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </flux:select>
                                        <flux:error name="gender" />
                                    </flux:field>
                                </div>

                                {{-- Role --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Role</flux:label>
                                        <flux:select 
                                            wire:model="user_role" 
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        >
                                            @foreach($roles as $role)
                                                <option value="{{ $role->name }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                                            @endforeach
                                        </flux:select>
                                        <flux:error name="user_role" />
                                    </flux:field>
                                </div>
                            </div>

                            {{-- Account Status --}}
                            <div class="mt-6">
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300 max-w-md">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Account Status</flux:label>
                                        <flux:select 
                                            wire:model="account_status" 
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        >
                                            <option value="active">Active</option>
                                            <option value="suspended">Suspended</option>
                                            <option value="banned">Banned</option>
                                        </flux:select>
                                        <flux:error name="account_status" />
                                    </flux:field>
                                </div>
                            </div>
                        </div>

                        {{-- KeNHA Staff Information Section --}}
                        @if($is_kenha_staff)
                        <div class="group/section border-t border-[#9B9EA4]/20 dark:border-zinc-700/50 pt-10">
                            <div class="flex items-center space-x-4 mb-6">
                                <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">KeNHA Staff Information</h3>
                                    <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">Additional information required for KeNHA staff members</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {{-- Personal Email --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Personal Email Address</flux:label>
                                        <flux:input 
                                            type="email" 
                                            wire:model="personal_email" 
                                            placeholder="personal@gmail.com"
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        />
                                        <flux:description class="text-xs text-[#9B9EA4] dark:text-zinc-400 mt-1">Must be different from institutional email</flux:description>
                                        <flux:error name="personal_email" />
                                    </flux:field>
                                </div>

                                {{-- Staff Number --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Staff Number</flux:label>
                                        <flux:input 
                                            wire:model="staff_number" 
                                            placeholder="KN2024001"
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        />
                                        <flux:error name="staff_number" />
                                    </flux:field>
                                </div>

                                {{-- Job Title --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Job Title</flux:label>
                                        <flux:input 
                                            wire:model="job_title" 
                                            placeholder="Senior Engineer"
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        />
                                        <flux:error name="job_title" />
                                    </flux:field>
                                </div>

                                {{-- Department --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Department</flux:label>
                                        <flux:input 
                                            wire:model="department" 
                                            placeholder="Engineering Department"
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        />
                                        <flux:error name="department" />
                                    </flux:field>
                                </div>

                                {{-- Supervisor Name --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Supervisor Name</flux:label>
                                        <flux:input 
                                            wire:model="supervisor_name" 
                                            placeholder="John Doe (Optional)"
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        />
                                        <flux:error name="supervisor_name" />
                                    </flux:field>
                                </div>

                                {{-- Work Station --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Work Station</flux:label>
                                        <flux:input 
                                            wire:model="work_station" 
                                            placeholder="Nairobi Head Office"
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        />
                                        <flux:error name="work_station" />
                                    </flux:field>
                                </div>

                                {{-- Employment Type --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Employment Type</flux:label>
                                        <flux:select 
                                            wire:model="employment_type" 
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        >
                                            <option value="permanent">Permanent</option>
                                            <option value="contract">Contract</option>
                                            <option value="temporary">Temporary</option>
                                        </flux:select>
                                        <flux:error name="employment_type" />
                                    </flux:field>
                                </div>

                                {{-- Employment Date --}}
                                <div class="group/field relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Employment Date</flux:label>
                                        <flux:input 
                                            type="date" 
                                            wire:model="employment_date" 
                                            required
                                            class="mt-2 bg-white/80 dark:bg-zinc-900/80 border-[#9B9EA4]/30 dark:border-zinc-600/50 rounded-xl focus:border-[#FFF200] dark:focus:border-yellow-400 focus:ring-[#FFF200]/20 dark:focus:ring-yellow-400/20"
                                        />
                                        <flux:error name="employment_date" />
                                    </flux:field>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- Enhanced Submit Section --}}
                        <div class="border-t border-[#9B9EA4]/20 dark:border-zinc-700/50 pt-8">
                            <div class="flex justify-end space-x-6">
                                <flux:button 
                                    variant="ghost" 
                                    href="{{ route('users.index') }}" 
                                    wire:navigate
                                    class="group/cancel relative overflow-hidden rounded-2xl bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm border border-white/40 dark:border-zinc-600/40 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-8 py-3"
                                >
                                    <span class="absolute inset-0 bg-gradient-to-br from-gray-500/10 to-gray-600/20 dark:from-gray-400/20 dark:to-gray-500/30 opacity-0 group-hover/cancel:opacity-100 transition-opacity duration-300"></span>
                                    <span class="relative font-medium text-[#231F20] dark:text-zinc-100">Cancel</span>
                                </flux:button>
                                
                                <flux:button 
                                    type="submit" 
                                    variant="primary"
                                    class="group/submit justify-center rounded-lg bg-[#FFF200] dark:bg-yellow-400 px-4 py-3 text-sm font-semibold text-[#231F20] dark:text-zinc-900 shadow-lg hover:bg-[#FFF200]/90 dark:hover:bg-yellow-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FFF200] dark:focus-visible:outline-yellow-400 transition-all duration-200 hover:shadow-xl"
                                >
                                    <span class="absolute inset-0 bg-gradient-to-br from-yellow-300/20 to-amber-300/20 opacity-0 group-hover/submit:opacity-100 transition-opacity duration-300"></span>
                                    <div class="relative flex items-center space-x-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                        </svg>
                                        <span>Create User</span>
                                    </div>
                                </flux:button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        {{-- Enhanced Information Note --}}
        <section aria-labelledby="info-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Animated Gradient Background --}}
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-100"></div>
                
                <div class="relative p-8">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 id="info-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100 mb-3">Important Information</h3>
                            <div class="space-y-3 text-sm text-[#9B9EA4] dark:text-zinc-400 leading-relaxed">
                                <div class="flex items-start space-x-3">
                                    <div class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full mt-2 flex-shrink-0"></div>
                                    <span>The user will receive a temporary password and must set up their own password on first login</span>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <div class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full mt-2 flex-shrink-0"></div>
                                    <span>All admin-created users are automatically verified and have accepted terms</span>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <div class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full mt-2 flex-shrink-0"></div>
                                    <span>KeNHA staff members (@kenha.co.ke emails) require additional staff information</span>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <div class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full mt-2 flex-shrink-0"></div>
                                    <span>Regular users will receive gamification points for signing up</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
