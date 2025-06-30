<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Staff;
use Spatie\Permission\Models\Role;
use App\Services\AuditService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\{Layout, Title};

new #[Layout('components.layouts.app')] #[Title('Edit User')] class extends Component
{
    public User $user;
    
    // Edit User Form Properties
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

    public function boot(AuditService $auditService): void
    {
        $this->auditService = $auditService;
    }

    public function mount(User $user)
    {
        // Check permission to edit users
        if (!auth()->user()->can('edit_users')) {
            abort(403, 'Unauthorized access to user editing.');
        }

        // Prevent non-developers from editing developer users
        if (!auth()->user()->hasRole('developer') && $user->hasRole('developer')) {
            abort(403, 'You cannot edit developer users.');
        }

        $this->user = $user->load('staff', 'roles');
        
        // Populate form with user data
        $this->first_name = $user->first_name;
        $this->last_name = $user->last_name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->gender = $user->gender;
        $this->account_status = $user->account_status;
        $this->user_role = $user->roles->first()?->name ?? 'user';
        $this->is_kenha_staff = $user->staff !== null;

        // Populate staff fields if applicable
        if ($user->staff) {
            $this->personal_email = $user->staff->personal_email;
            $this->staff_number = $user->staff->staff_number;
            $this->job_title = $user->staff->job_title;
            $this->department = $user->staff->department;
            $this->supervisor_name = $user->staff->supervisor_name;
            $this->work_station = $user->staff->work_station;
            $this->employment_type = $user->staff->employment_type;
            $this->employment_date = $user->staff->employment_date?->format('Y-m-d');
        }
    }

    public function updateUser()
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($this->user->id)],
            'phone' => ['required', 'string', 'max:20'],
            'gender' => ['required', 'in:male,female,other'],
            'user_role' => ['required', 'exists:roles,name'],
            'account_status' => ['required', 'in:active,suspended,banned'],
        ];

        // Add staff-specific validation if KeNHA staff
        if ($this->is_kenha_staff) {
            $rules = array_merge($rules, [
                'personal_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'different:email'],
                'staff_number' => ['required', 'string', 'max:20', Rule::unique('staff')->ignore($this->user->staff?->id)],
                'job_title' => ['required', 'string', 'max:255'],
                'department' => ['required', 'string', 'max:255'],
                'supervisor_name' => ['nullable', 'string', 'max:255'],
                'work_station' => ['required', 'string', 'max:255'],
                'employment_type' => ['required', 'in:permanent,contract,temporary'],
                'employment_date' => ['required', 'date', 'before_or_equal:today'],
            ]);
        }

        $this->validate($rules);

        // Check if user trying to assign developer role
        $currentUser = auth()->user();
        if ($this->user_role === 'developer' && !$currentUser->hasRole('developer')) {
            $this->addError('user_role', 'You cannot assign the developer role.');
            return;
        }

        // Prevent changing own role or status
        if ($this->user->id === $currentUser->id && ($this->user_role !== $currentUser->roles->first()?->name || $this->account_status !== $currentUser->account_status)) {
            session()->flash('error', 'You cannot change your own role or account status.');
            return;
        }

        try {
            $oldValues = [
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => $this->user->roles->first()?->name,
                'account_status' => $this->user->account_status,
            ];

            // Update user
            $this->user->update([
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'phone' => $this->phone,
                'gender' => $this->gender,
                'account_status' => $this->account_status,
            ]);

            // Update role if changed
            if ($this->user->roles->first()?->name !== $this->user_role) {
                $this->user->syncRoles([$this->user_role]);
            }

            // Handle staff record
            if ($this->is_kenha_staff) {
                Staff::updateOrCreate(
                    ['user_id' => $this->user->id],
                    [
                        'personal_email' => $this->personal_email,
                        'staff_number' => $this->staff_number,
                        'job_title' => $this->job_title,
                        'department' => $this->department,
                        'supervisor_name' => $this->supervisor_name,
                        'work_station' => $this->work_station,
                        'employment_type' => $this->employment_type,
                        'employment_date' => $this->employment_date,
                    ]
                );
            } else {
                // Remove staff record if no longer KeNHA staff
                $this->user->staff?->delete();
            }

            // Create audit log
            $this->auditService->log('user_updated', 'User', $this->user->id, $oldValues, [
                'name' => $this->user->name,
                'email' => $this->email,
                'role' => $this->user_role,
                'account_status' => $this->account_status,
                'updated_by' => $currentUser->name,
            ]);

            session()->flash('message', 'User updated successfully!');
            
            // Redirect to user details page
            $this->redirectRoute('users.show', $this->user, navigate: true);

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update user: ' . $e->getMessage());
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

{{-- KeNHAVATE Innovation Portal - Edit User Page with Glass Morphism & Enhanced UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Enhanced Header with Glass Morphism --}}
        <section aria-labelledby="edit-user-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Animated Gradient Background --}}
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-100"></div>
                
                <div class="relative p-8">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div class="flex items-center space-x-4">
                            {{-- Enhanced User Icon --}}
                            <div class="relative">
                                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 flex items-center justify-center shadow-lg">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </div>
                                <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-60"></div>
                            </div>
                            
                            <div>
                                <h1 id="edit-user-heading" class="text-3xl font-bold text-[#231F20] dark:text-zinc-100">Edit User</h1>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 mt-1">Update {{ $user->name }}'s information and settings</p>
                            </div>
                        </div>
                        
                        {{-- Enhanced Action Buttons --}}
                        <div class="flex flex-wrap gap-3">
                            <flux:button 
                                variant="ghost" 
                                href="{{ route('users.show', $user) }}" 
                                wire:navigate
                                class="group relative overflow-hidden rounded-xl bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm border border-white/20 dark:border-zinc-600/40 hover:shadow-lg transition-all duration-300 hover:-translate-y-1"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-gray-500/10 to-gray-600/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <span>View User</span>
                                </div>
                            </flux:button>
                            
                            <flux:button 
                                variant="ghost" 
                                href="{{ route('users.index') }}" 
                                wire:navigate
                                class="group relative overflow-hidden rounded-xl bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm border border-white/20 dark:border-zinc-600/40 hover:shadow-lg transition-all duration-300 hover:-translate-y-1"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-gray-500/10 to-gray-600/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                    </svg>
                                    <span>Back to Users</span>
                                </div>
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Edit User Form with Glass Morphism --}}
        <section aria-labelledby="edit-form-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Subtle Background Pattern --}}
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 via-transparent to-amber-600/10 dark:from-amber-400/10 dark:via-transparent dark:to-amber-500/20 opacity-100"></div>
                
                <div class="relative p-8">
                    <form wire:submit="updateUser" class="space-y-10">
                        {{-- Basic Information Section --}}
                        <div class="group/section">
                            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                                {{-- Section Header --}}
                                <div class="flex items-center space-x-3 mb-6">
                                    <div class="w-10 h-10 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Basic Information</h3>
                                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">Core user details and credentials</p>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    {{-- First Name --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">First Name</flux:label>
                                            <flux:input 
                                                wire:model="first_name" 
                                                placeholder="Enter first name"
                                                required 
                                                class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-[#FFF200] focus:border-[#FFF200] transition-all duration-300"
                                            />
                                            <flux:error name="first_name" />
                                        </flux:field>
                                    </div>

                                    {{-- Last Name --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Last Name</flux:label>
                                            <flux:input 
                                                wire:model="last_name" 
                                                placeholder="Enter last name"
                                                required 
                                                class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-[#FFF200] focus:border-[#FFF200] transition-all duration-300"
                                            />
                                            <flux:error name="last_name" />
                                        </flux:field>
                                    </div>

                                    {{-- Email Address --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Email Address</flux:label>
                                            <flux:input 
                                                type="email" 
                                                wire:model="email" 
                                                placeholder="user@example.com"
                                                required 
                                                class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-[#FFF200] focus:border-[#FFF200] transition-all duration-300"
                                            />
                                            <flux:error name="email" />
                                        </flux:field>
                                    </div>

                                    {{-- Phone Number --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Phone Number</flux:label>
                                            <flux:input 
                                                wire:model="phone" 
                                                placeholder="+254 7XX XXX XXX"
                                                required 
                                                class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-[#FFF200] focus:border-[#FFF200] transition-all duration-300"
                                            />
                                            <flux:error name="phone" />
                                        </flux:field>
                                    </div>

                                    {{-- Gender --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Gender</flux:label>
                                            <flux:select wire:model="gender" required class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-[#FFF200] focus:border-[#FFF200] transition-all duration-300">
                                                <option value="">Select gender</option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </flux:select>
                                            <flux:error name="gender" />
                                        </flux:field>
                                    </div>

                                    {{-- Role --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Role</flux:label>
                                            <flux:select wire:model="user_role" required class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-[#FFF200] focus:border-[#FFF200] transition-all duration-300">
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
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Account Status</flux:label>
                                        <flux:select wire:model="account_status" required class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-[#FFF200] focus:border-[#FFF200] transition-all duration-300">
                                            <option value="active">Active</option>
                                            <option value="suspended">Suspended</option>
                                            <option value="banned">Banned</option>
                                        </flux:select>
                                        <flux:error name="account_status" />
                                    </flux:field>
                                </div>

                                {{-- KeNHA Staff Toggle --}}
                                <div class="mt-6">
                                    <div class="relative overflow-hidden rounded-xl bg-gradient-to-r from-blue-50/50 to-blue-100/30 dark:from-blue-900/20 dark:to-blue-800/30 border border-blue-200/40 dark:border-blue-700/40 backdrop-blur-sm p-4">
                                        <flux:field>
                                            <flux:checkbox wire:model.live="is_kenha_staff" class="text-[#FFF200] focus:ring-[#FFF200]">
                                                <span class="font-medium text-[#231F20] dark:text-zinc-100">KeNHA Staff Member</span>
                                            </flux:checkbox>
                                            <flux:description class="text-[#9B9EA4] dark:text-zinc-400">Check if this user is a KeNHA staff member requiring additional verification</flux:description>
                                        </flux:field>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- KeNHA Staff Information Section --}}
                        @if($is_kenha_staff)
                        <div class="group/section" x-data x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100">
                            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                                {{-- Section Header --}}
                                <div class="flex items-center space-x-3 mb-6">
                                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">KeNHA Staff Information</h3>
                                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">Additional institutional details and employment information</p>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    {{-- Personal Email --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Personal Email Address</flux:label>
                                            <flux:input 
                                                type="email" 
                                                wire:model="personal_email" 
                                                placeholder="personal@gmail.com"
                                                required 
                                                class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-emerald-400 focus:border-emerald-400 transition-all duration-300"
                                            />
                                            <flux:description class="text-emerald-600 dark:text-emerald-400 text-xs">Must be different from institutional email</flux:description>
                                            <flux:error name="personal_email" />
                                        </flux:field>
                                    </div>

                                    {{-- Staff Number --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Staff Number</flux:label>
                                            <flux:input 
                                                wire:model="staff_number" 
                                                placeholder="KN2024001"
                                                required 
                                                class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-emerald-400 focus:border-emerald-400 transition-all duration-300"
                                            />
                                            <flux:error name="staff_number" />
                                        </flux:field>
                                    </div>

                                    {{-- Job Title --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Job Title</flux:label>
                                            <flux:input 
                                                wire:model="job_title" 
                                                placeholder="Senior Engineer"
                                                required 
                                                class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-emerald-400 focus:border-emerald-400 transition-all duration-300"
                                            />
                                            <flux:error name="job_title" />
                                        </flux:field>
                                    </div>

                                    {{-- Department --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Department</flux:label>
                                            <flux:input 
                                                wire:model="department" 
                                                placeholder="Engineering Department"
                                                required 
                                                class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-emerald-400 focus:border-emerald-400 transition-all duration-300"
                                            />
                                            <flux:error name="department" />
                                        </flux:field>
                                    </div>

                                    {{-- Supervisor Name --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Supervisor Name</flux:label>
                                            <flux:input 
                                                wire:model="supervisor_name" 
                                                placeholder="John Doe (Optional)"
                                                class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-emerald-400 focus:border-emerald-400 transition-all duration-300"
                                            />
                                            <flux:error name="supervisor_name" />
                                        </flux:field>
                                    </div>

                                    {{-- Work Station --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Work Station</flux:label>
                                            <flux:input 
                                                wire:model="work_station" 
                                                placeholder="Nairobi Head Office"
                                                required 
                                                class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-emerald-400 focus:border-emerald-400 transition-all duration-300"
                                            />
                                            <flux:error name="work_station" />
                                        </flux:field>
                                    </div>

                                    {{-- Employment Type --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Employment Type</flux:label>
                                            <flux:select wire:model="employment_type" required class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-emerald-400 focus:border-emerald-400 transition-all duration-300">
                                                <option value="permanent">Permanent</option>
                                                <option value="contract">Contract</option>
                                                <option value="temporary">Temporary</option>
                                            </flux:select>
                                            <flux:error name="employment_type" />
                                        </flux:field>
                                    </div>

                                    {{-- Employment Date --}}
                                    <div class="group/field">
                                        <flux:field>
                                            <flux:label class="text-[#231F20] dark:text-zinc-100 font-medium">Employment Date</flux:label>
                                            <flux:input 
                                                type="date" 
                                                wire:model="employment_date" 
                                                required 
                                                class="rounded-xl border-white/20 dark:border-zinc-600/40 bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm focus:ring-emerald-400 focus:border-emerald-400 transition-all duration-300"
                                            />
                                            <flux:error name="employment_date" />
                                        </flux:field>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- Enhanced Submit Section --}}
                        <div class="group/submit">
                            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/60 to-white/40 dark:from-zinc-800/60 dark:to-zinc-700/40 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6">
                                <div class="flex flex-col sm:flex-row justify-end gap-4">
                                    <flux:button 
                                        variant="ghost" 
                                        href="{{ route('users.show', $user) }}" 
                                        wire:navigate
                                        class="group relative overflow-hidden rounded-xl bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm border border-white/20 dark:border-zinc-600/40 hover:shadow-lg transition-all duration-300 hover:-translate-y-1"
                                    >
                                        <span class="absolute inset-0 bg-gradient-to-br from-gray-500/10 to-gray-600/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                        <span class="relative">Cancel</span>
                                    </flux:button>
                                    
                                    <flux:button 
                                        type="submit" 
                                        variant="primary"
                                        class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 text-[#231F20] font-semibold shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-[#FFF200]/20"
                                    >
                                        <span class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                        <div class="relative flex items-center space-x-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                            </svg>
                                            <span>Update User</span>
                                        </div>
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        {{-- Enhanced Warning Note for Self-Edit --}}
        @if($user->id === auth()->id())
        <section aria-labelledby="self-edit-warning" class="group">
            <div class="relative overflow-hidden rounded-2xl bg-yellow-50/70 dark:bg-yellow-900/20 backdrop-blur-xl border border-yellow-200/40 dark:border-yellow-800/40 shadow-lg">
                {{-- Warning Background Pattern --}}
                <div class="absolute inset-0 bg-gradient-to-br from-yellow-400/10 via-transparent to-yellow-500/20 dark:from-yellow-400/20 dark:via-transparent dark:to-yellow-500/30"></div>
                
                <div class="relative p-6">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 id="self-edit-warning" class="text-lg font-bold text-yellow-800 dark:text-yellow-200 mb-2">
                                Editing Your Own Account
                            </h3>
                            <p class="text-sm text-yellow-700 dark:text-yellow-300 leading-relaxed">
                                For security reasons, you cannot change your own role or account status. These restrictions help maintain system integrity and prevent unauthorized privilege escalation.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        @endif
    </div>
</div>
