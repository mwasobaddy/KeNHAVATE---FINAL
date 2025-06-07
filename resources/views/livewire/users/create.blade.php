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

<div>
    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-[#231F20] dark:text-white">Create New User</h1>
                    <p class="text-[#9B9EA4] dark:text-zinc-400 mt-1">Add a new user to the KeNHAVATE system</p>
                </div>
                
                <flux:button variant="ghost" href="{{ route('users.index') }}" wire:navigate>
                    <flux:icon.arrow-left class="w-4 h-4 mr-2" />
                    Back to Users
                </flux:button>
            </div>

            <!-- Create User Form -->
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-[#9B9EA4]/20 overflow-hidden">
                <div class="p-6">
                    <form wire:submit="createUser" class="space-y-8">
                        <!-- Basic Information Section -->
                        <div>
                            <h3 class="text-lg font-medium text-[#231F20] dark:text-white mb-4">Basic Information</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- First Name -->
                                <flux:field>
                                    <flux:label>First Name</flux:label>
                                    <flux:input 
                                        wire:model="first_name" 
                                        placeholder="Enter first name"
                                        required 
                                    />
                                    <flux:error name="first_name" />
                                </flux:field>

                                <!-- Last Name -->
                                <flux:field>
                                    <flux:label>Last Name</flux:label>
                                    <flux:input 
                                        wire:model="last_name" 
                                        placeholder="Enter last name"
                                        required 
                                    />
                                    <flux:error name="last_name" />
                                </flux:field>

                                <!-- Email Address -->
                                <flux:field>
                                    <flux:label>Email Address</flux:label>
                                    <flux:input 
                                        type="email" 
                                        wire:model.live="email" 
                                        placeholder="user@example.com"
                                        required 
                                    />
                                    <flux:error name="email" />
                                </flux:field>

                                <!-- Phone Number -->
                                <flux:field>
                                    <flux:label>Phone Number</flux:label>
                                    <flux:input 
                                        wire:model="phone" 
                                        placeholder="+254 7XX XXX XXX"
                                        required 
                                    />
                                    <flux:error name="phone" />
                                </flux:field>

                                <!-- Gender -->
                                <flux:field>
                                    <flux:label>Gender</flux:label>
                                    <flux:select wire:model="gender" placeholder="Select gender" required>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </flux:select>
                                    <flux:error name="gender" />
                                </flux:field>

                                <!-- Role -->
                                <flux:field>
                                    <flux:label>Role</flux:label>
                                    <flux:select wire:model="user_role" required>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->name }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="user_role" />
                                </flux:field>
                            </div>

                            <!-- Account Status -->
                            <div class="mt-6">
                                <flux:field>
                                    <flux:label>Account Status</flux:label>
                                    <flux:select wire:model="account_status" required>
                                        <option value="active">Active</option>
                                        <option value="suspended">Suspended</option>
                                        <option value="banned">Banned</option>
                                    </flux:select>
                                    <flux:error name="account_status" />
                                </flux:field>
                            </div>
                        </div>

                        <!-- KeNHA Staff Information Section -->
                        @if($is_kenha_staff)
                        <div class="border-t border-[#9B9EA4]/20 pt-8">
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-[#231F20] dark:text-white">KeNHA Staff Information</h3>
                                <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mt-1">Additional information required for KeNHA staff members</p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Personal Email -->
                                <flux:field>
                                    <flux:label>Personal Email Address</flux:label>
                                    <flux:input 
                                        type="email" 
                                        wire:model="personal_email" 
                                        placeholder="personal@gmail.com"
                                        required 
                                    />
                                    <flux:description>Must be different from institutional email</flux:description>
                                    <flux:error name="personal_email" />
                                </flux:field>

                                <!-- Staff Number -->
                                <flux:field>
                                    <flux:label>Staff Number</flux:label>
                                    <flux:input 
                                        wire:model="staff_number" 
                                        placeholder="KN2024001"
                                        required 
                                    />
                                    <flux:error name="staff_number" />
                                </flux:field>

                                <!-- Job Title -->
                                <flux:field>
                                    <flux:label>Job Title</flux:label>
                                    <flux:input 
                                        wire:model="job_title" 
                                        placeholder="Senior Engineer"
                                        required 
                                    />
                                    <flux:error name="job_title" />
                                </flux:field>

                                <!-- Department -->
                                <flux:field>
                                    <flux:label>Department</flux:label>
                                    <flux:input 
                                        wire:model="department" 
                                        placeholder="Engineering Department"
                                        required 
                                    />
                                    <flux:error name="department" />
                                </flux:field>

                                <!-- Supervisor Name -->
                                <flux:field>
                                    <flux:label>Supervisor Name</flux:label>
                                    <flux:input 
                                        wire:model="supervisor_name" 
                                        placeholder="John Doe (Optional)"
                                    />
                                    <flux:error name="supervisor_name" />
                                </flux:field>

                                <!-- Work Station -->
                                <flux:field>
                                    <flux:label>Work Station</flux:label>
                                    <flux:input 
                                        wire:model="work_station" 
                                        placeholder="Nairobi Head Office"
                                        required 
                                    />
                                    <flux:error name="work_station" />
                                </flux:field>

                                <!-- Employment Type -->
                                <flux:field>
                                    <flux:label>Employment Type</flux:label>
                                    <flux:select wire:model="employment_type" required>
                                        <option value="permanent">Permanent</option>
                                        <option value="contract">Contract</option>
                                        <option value="temporary">Temporary</option>
                                    </flux:select>
                                    <flux:error name="employment_type" />
                                </flux:field>

                                <!-- Employment Date -->
                                <flux:field>
                                    <flux:label>Employment Date</flux:label>
                                    <flux:input 
                                        type="date" 
                                        wire:model="employment_date" 
                                        required 
                                    />
                                    <flux:error name="employment_date" />
                                </flux:field>
                            </div>
                        </div>
                        @endif

                        <!-- Submit Button -->
                        <div class="border-t border-[#9B9EA4]/20 pt-6">
                            <div class="flex justify-end space-x-4">
                                <flux:button 
                                    variant="ghost" 
                                    href="{{ route('users.index') }}" 
                                    wire:navigate
                                >
                                    Cancel
                                </flux:button>
                                
                                <flux:button type="submit" variant="primary">
                                    Create User
                                </flux:button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Information Note -->
            <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <flux:icon.information-circle class="h-5 w-5 text-blue-400" />
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                            Important Information
                        </h3>
                        <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                            <ul class="list-disc pl-5 space-y-1">
                                <li>The user will receive a temporary password and must set up their own password on first login</li>
                                <li>All admin-created users are automatically verified and have accepted terms</li>
                                <li>KeNHA staff members (@kenha.co.ke emails) require additional staff information</li>
                                <li>Regular users will receive gamification points for signing up</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
