<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Staff;
use Spatie\Permission\Models\Role;
use App\Services\AuditService;
use Illuminate\Validation\Rule;

new #[Layout('components.layouts.app', title: 'Edit User')] class extends Component {
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

<div>
    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-[#231F20] dark:text-white">Edit User</h1>
                    <p class="text-[#9B9EA4] dark:text-zinc-400 mt-1">Update {{ $user->name }}'s information</p>
                </div>
                
                <div class="flex space-x-3">
                    <flux:button 
                        variant="ghost" 
                        href="{{ route('users.show', $user) }}" 
                        wire:navigate
                    >
                        <flux:icon.eye class="w-4 h-4 mr-2" />
                        View User
                    </flux:button>
                    
                    <flux:button 
                        variant="ghost" 
                        href="{{ route('users.index') }}" 
                        wire:navigate
                    >
                        <flux:icon.arrow-left class="w-4 h-4 mr-2" />
                        Back to Users
                    </flux:button>
                </div>
            </div>

            <!-- Edit User Form -->
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-[#9B9EA4]/20 overflow-hidden">
                <div class="p-6">
                    <form wire:submit="updateUser" class="space-y-8">
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
                                        wire:model="email" 
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
                                    <flux:select wire:model="gender" required>
                                        <option value="">Select gender</option>
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

                            <!-- KeNHA Staff Toggle -->
                            <div class="mt-6">
                                <flux:field>
                                    <flux:checkbox wire:model.live="is_kenha_staff">KeNHA Staff Member</flux:checkbox>
                                    <flux:description>Check if this user is a KeNHA staff member</flux:description>
                                </flux:field>
                            </div>
                        </div>

                        <!-- KeNHA Staff Information Section -->
                        @if($is_kenha_staff)
                        <div class="border-t border-[#9B9EA4]/20 pt-8">
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-[#231F20] dark:text-white">KeNHA Staff Information</h3>
                                <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mt-1">Additional information for KeNHA staff members</p>
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
                                    href="{{ route('users.show', $user) }}" 
                                    wire:navigate
                                >
                                    Cancel
                                </flux:button>
                                
                                <flux:button type="submit" variant="primary">
                                    Update User
                                </flux:button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Warning Note for Self-Edit -->
            @if($user->id === auth()->id())
            <div class="mt-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <flux:icon.exclamation-triangle class="h-5 w-5 text-yellow-400" />
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                            Editing Your Own Account
                        </h3>
                        <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                            <p>You cannot change your own role or account status for security reasons.</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
