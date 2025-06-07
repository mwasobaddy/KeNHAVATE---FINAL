<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Staff;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    use WithPagination;

    // User Management Properties
    public $search = '';
    public $selectedRole = '';
    public $selectedStatus = '';
    public $showCreateUserModal = false;
    public $showEditUserModal = false;
    public $showDeleteUserModal = false;
    public $showBanUserModal = false;
    public $editingUser = null;
    public $deletingUser = null;
    public $banningUser = null;

    // Create/Edit User Form Properties
    public $first_name = '';
    public $last_name = '';
    public $email = '';
    public $phone = '';
    public $gender = '';
    public $user_role = 'user';
    public $account_status = 'active';
    public $is_kenha_staff = false;
    
    // Staff-specific fields
    public $staff_number = '';
    public $job_title = '';
    public $department = '';
    public $work_station = '';
    public $employment_type = 'permanent';
    public $employment_date = '';

    // Security confirmation
    public $password_confirmation = '';
    public $delete_confirmation = '';

    protected $listeners = ['userCreated', 'userUpdated', 'userDeleted'];

    public function mount()
    {
        // Check authorization
        if (!auth()->user()->hasAnyRole(['developer', 'administrator'])) {
            abort(403, 'Unauthorized access to user management.');
        }
    }

    public function with()
    {
        $query = User::with(['roles', 'staff'])
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('first_name', 'like', '%' . $this->search . '%')
                          ->orWhere('last_name', 'like', '%' . $this->search . '%')
                          ->orWhere('email', 'like', '%' . $this->search . '%')
                          ->orWhere('phone', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->selectedRole, function ($q) {
                $q->role($this->selectedRole);
            })
            ->when($this->selectedStatus, function ($q) {
                $q->where('account_status', $this->selectedStatus);
            })
            ->latest();

        return [
            'users' => $query->paginate(20),
            'roles' => Role::all(),
            'totalUsers' => User::count(),
            'activeUsers' => User::where('account_status', 'active')->count(),
            'bannedUsers' => User::where('account_status', 'banned')->count(),
            'pendingUsers' => User::where('email_verified_at', null)->count(),
        ];
    }

    public function openCreateUserModal()
    {
        $this->showCreateUserModal = true;
        $this->resetUserForm();
    }

    public function openEditUserModal($userId)
    {
        $user = User::with('staff')->findOrFail($userId);
        $this->editingUser = $user;
        $this->showEditUserModal = true;
        
        // Populate form
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
            $this->staff_number = $user->staff->staff_number;
            $this->job_title = $user->staff->job_title;
            $this->department = $user->staff->department;
            $this->work_station = $user->staff->work_station;
            $this->employment_type = $user->staff->employment_type;
            $this->employment_date = $user->staff->employment_date?->format('Y-m-d');
        }
    }

    public function createUser()
    {
        $this->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'gender' => 'required|in:male,female,other',
            'user_role' => 'required|exists:roles,name',
            'account_status' => 'required|in:active,suspended,banned',
            'staff_number' => $this->is_kenha_staff ? 'required|string|unique:staff,staff_number' : 'nullable',
            'job_title' => $this->is_kenha_staff ? 'required|string|max:255' : 'nullable',
            'department' => $this->is_kenha_staff ? 'required|string|max:255' : 'nullable',
            'work_station' => $this->is_kenha_staff ? 'required|string|max:255' : 'nullable',
            'employment_type' => $this->is_kenha_staff ? 'required|in:permanent,contract,temporary' : 'nullable',
            'employment_date' => $this->is_kenha_staff ? 'required|date|before_or_equal:today' : 'nullable',
        ]);

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
                'terms_accepted' => true,
            ]);

            // Assign role
            $user->assignRole($this->user_role);

            // Create staff record if applicable
            if ($this->is_kenha_staff) {
                Staff::create([
                    'user_id' => $user->id,
                    'staff_number' => $this->staff_number,
                    'job_title' => $this->job_title,
                    'department' => $this->department,
                    'work_station' => $this->work_station,
                    'employment_type' => $this->employment_type,
                    'employment_date' => $this->employment_date,
                ]);
            }

            // Create audit log
            app('audit')->log('user_created', 'User', $user->id, null, [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->user_role,
                'created_by' => auth()->user()->name,
            ]);

            $this->showCreateUserModal = false;
            $this->resetUserForm();
            session()->flash('message', 'User created successfully!');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create user: ' . $e->getMessage());
        }
    }

    public function updateUser()
    {
        $this->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($this->editingUser->id)],
            'phone' => 'required|string|max:20',
            'gender' => 'required|in:male,female,other',
            'user_role' => 'required|exists:roles,name',
            'account_status' => 'required|in:active,suspended,banned',
            'staff_number' => $this->is_kenha_staff ? ['required', 'string', Rule::unique('staff')->ignore($this->editingUser->staff?->id)] : 'nullable',
            'job_title' => $this->is_kenha_staff ? 'required|string|max:255' : 'nullable',
            'department' => $this->is_kenha_staff ? 'required|string|max:255' : 'nullable',
            'work_station' => $this->is_kenha_staff ? 'required|string|max:255' : 'nullable',
            'employment_type' => $this->is_kenha_staff ? 'required|in:permanent,contract,temporary' : 'nullable',
            'employment_date' => $this->is_kenha_staff ? 'required|date|before_or_equal:today' : 'nullable',
        ]);

        try {
            $oldValues = [
                'name' => $this->editingUser->name,
                'email' => $this->editingUser->email,
                'role' => $this->editingUser->roles->first()?->name,
                'account_status' => $this->editingUser->account_status,
            ];

            // Update user
            $this->editingUser->update([
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'phone' => $this->phone,
                'gender' => $this->gender,
                'account_status' => $this->account_status,
            ]);

            // Update role if changed
            if ($this->editingUser->roles->first()?->name !== $this->user_role) {
                $this->editingUser->syncRoles([$this->user_role]);
            }

            // Handle staff record
            if ($this->is_kenha_staff) {
                Staff::updateOrCreate(
                    ['user_id' => $this->editingUser->id],
                    [
                        'staff_number' => $this->staff_number,
                        'job_title' => $this->job_title,
                        'department' => $this->department,
                        'work_station' => $this->work_station,
                        'employment_type' => $this->employment_type,
                        'employment_date' => $this->employment_date,
                    ]
                );
            } else {
                // Remove staff record if no longer KeNHA staff
                $this->editingUser->staff?->delete();
            }

            // Create audit log
            app('audit')->log('user_updated', 'User', $this->editingUser->id, $oldValues, [
                'name' => $this->editingUser->name,
                'email' => $this->email,
                'role' => $this->user_role,
                'account_status' => $this->account_status,
                'updated_by' => auth()->user()->name,
            ]);

            $this->showEditUserModal = false;
            $this->resetUserForm();
            session()->flash('message', 'User updated successfully!');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update user: ' . $e->getMessage());
        }
    }

    public function openDeleteUserModal($userId)
    {
        $this->deletingUser = User::findOrFail($userId);
        $this->showDeleteUserModal = true;
        $this->delete_confirmation = '';
    }

    public function deleteUser()
    {
        // Validate password for security
        if (!Hash::check($this->delete_confirmation, auth()->user()->password)) {
            $this->addError('delete_confirmation', 'Password confirmation is incorrect.');
            return;
        }

        // Prevent deleting yourself
        if ($this->deletingUser->id === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }

        try {
            $userName = $this->deletingUser->name;
            $userEmail = $this->deletingUser->email;

            // Create audit log before deletion
            app('audit')->log('user_deleted', 'User', $this->deletingUser->id, [
                'name' => $userName,
                'email' => $userEmail,
                'role' => $this->deletingUser->roles->first()?->name,
                'deleted_by' => auth()->user()->name,
            ], null);

            // Delete user (cascades to staff record)
            $this->deletingUser->delete();

            $this->showDeleteUserModal = false;
            $this->delete_confirmation = '';
            session()->flash('message', "User {$userName} deleted successfully!");

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete user: ' . $e->getMessage());
        }
    }

    public function openBanUserModal($userId)
    {
        $this->banningUser = User::findOrFail($userId);
        $this->showBanUserModal = true;
    }

    public function banUser()
    {
        try {
            $oldStatus = $this->banningUser->account_status;
            $newStatus = $oldStatus === 'banned' ? 'active' : 'banned';
            $action = $newStatus === 'banned' ? 'banned' : 'unbanned';

            $this->banningUser->update(['account_status' => $newStatus]);

            // Create audit log
            app('audit')->log("user_{$action}", 'User', $this->banningUser->id, 
                ['account_status' => $oldStatus], 
                ['account_status' => $newStatus, 'actioned_by' => auth()->user()->name]
            );

            $this->showBanUserModal = false;
            session()->flash('message', "User {$action} successfully!");

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update user status: ' . $e->getMessage());
        }
    }

    public function resetUserForm()
    {
        $this->first_name = '';
        $this->last_name = '';
        $this->email = '';
        $this->phone = '';
        $this->gender = '';
        $this->user_role = 'user';
        $this->account_status = 'active';
        $this->is_kenha_staff = false;
        $this->staff_number = '';
        $this->job_title = '';
        $this->department = '';
        $this->work_station = '';
        $this->employment_type = 'permanent';
        $this->employment_date = '';
        $this->editingUser = null;
        $this->deletingUser = null;
        $this->banningUser = null;
        $this->password_confirmation = '';
        $this->delete_confirmation = '';
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedSelectedRole()
    {
        $this->resetPage();
    }

    public function updatedSelectedStatus()
    {
        $this->resetPage();
    }

}; ?>

<x-layouts.app title="User Management">
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-[#231F20] dark:text-white">User Management</h1>
                    <p class="text-[#9B9EA4] dark:text-zinc-400 mt-1">Manage users, roles, and permissions</p>
                </div>
                
                @can('create_users')
                <flux:button variant="primary" wire:click="openCreateUserModal">
                    <flux:icon.plus class="w-4 h-4 mr-2" />
                    Create User
                </flux:button>
                @endcan
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl shadow-sm border border-[#9B9EA4]/20">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                            <flux:icon.users class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Total Users</p>
                            <p class="text-2xl font-bold text-[#231F20] dark:text-white">{{ $totalUsers }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl shadow-sm border border-[#9B9EA4]/20">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                            <flux:icon.check-circle class="w-6 h-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Active Users</p>
                            <p class="text-2xl font-bold text-[#231F20] dark:text-white">{{ $activeUsers }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl shadow-sm border border-[#9B9EA4]/20">
                    <div class="flex items-center">
                        <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                            <flux:icon.x-circle class="w-6 h-6 text-red-600 dark:text-red-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Banned Users</p>
                            <p class="text-2xl font-bold text-[#231F20] dark:text-white">{{ $bannedUsers }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl shadow-sm border border-[#9B9EA4]/20">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                            <flux:icon.clock class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Pending Users</p>
                            <p class="text-2xl font-bold text-[#231F20] dark:text-white">{{ $pendingUsers }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl shadow-sm border border-[#9B9EA4]/20 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <flux:input 
                            wire:model.live="search" 
                            placeholder="Search users..."
                            class="w-full"
                        />
                    </div>

                    <!-- Role Filter -->
                    <div>
                        <flux:select wire:model.live="selectedRole" placeholder="All Roles">
                            <option value="">All Roles</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                            @endforeach
                        </flux:select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <flux:select wire:model.live="selectedStatus" placeholder="All Statuses">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="banned">Banned</option>
                        </flux:select>
                    </div>

                    <!-- Clear Filters -->
                    <div>
                        <flux:button 
                            variant="subtle" 
                            wire:click="$set('search', ''); $set('selectedRole', ''); $set('selectedStatus', '')"
                            class="w-full"
                        >
                            Clear Filters
                        </flux:button>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-[#9B9EA4]/20 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-[#F8EBD5]/30 dark:bg-zinc-700">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">User</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">Staff Info</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">Joined</th>
                                <th class="px-6 py-4 text-center text-xs font-medium text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#9B9EA4]/20">
                            @forelse($users as $user)
                                <tr class="hover:bg-[#F8EBD5]/10 dark:hover:bg-zinc-700/50 transition-colors">
                                    <!-- User Info -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full bg-[#FFF200]/20 flex items-center justify-center">
                                                <span class="text-sm font-medium text-[#231F20] dark:text-white">
                                                    {{ strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)) }}
                                                </span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-[#231F20] dark:text-white">{{ $user->name }}</div>
                                                <div class="text-sm text-[#9B9EA4] dark:text-zinc-400">ID: {{ $user->id }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Contact Info -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-[#231F20] dark:text-white">{{ $user->email }}</div>
                                        <div class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->phone ?? 'No phone' }}</div>
                                    </td>

                                    <!-- Role -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php $role = $user->roles->first(); @endphp
                                        @if($role)
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                @if($role->name === 'developer') bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300
                                                @elseif($role->name === 'administrator') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300
                                                @elseif($role->name === 'board_member') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                                                @elseif($role->name === 'manager') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                                @elseif($role->name === 'sme') bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300
                                                @elseif($role->name === 'challenge_reviewer') bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300
                                                @else bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300
                                                @endif">
                                                {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                            </span>
                                        @else
                                            <span class="text-[#9B9EA4] dark:text-zinc-400">No Role</span>
                                        @endif
                                    </td>

                                    <!-- Status -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                            @if($user->account_status === 'active') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                            @elseif($user->account_status === 'suspended') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                                            @elseif($user->account_status === 'banned') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300
                                            @endif">
                                            {{ ucfirst($user->account_status) }}
                                        </span>
                                    </td>

                                    <!-- Staff Info -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($user->staff)
                                            <div class="text-sm text-[#231F20] dark:text-white">{{ $user->staff->staff_number }}</div>
                                            <div class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->staff->job_title }}</div>
                                        @else
                                            <span class="text-[#9B9EA4] dark:text-zinc-400">External User</span>
                                        @endif
                                    </td>

                                    <!-- Joined Date -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-[#9B9EA4] dark:text-zinc-400">
                                        {{ $user->created_at->format('M d, Y') }}
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center space-x-2">
                                            @can('edit_users')
                                            <flux:button 
                                                size="sm" 
                                                variant="subtle" 
                                                wire:click="openEditUserModal({{ $user->id }})"
                                            >
                                                <flux:icon.pencil class="w-4 h-4" />
                                            </flux:button>
                                            @endcan

                                            @can('ban_users')
                                            <flux:button 
                                                size="sm" 
                                                variant="{{ $user->account_status === 'banned' ? 'primary' : 'danger' }}" 
                                                wire:click="openBanUserModal({{ $user->id }})"
                                            >
                                                @if($user->account_status === 'banned')
                                                    <flux:icon.check class="w-4 h-4" />
                                                @else
                                                    <flux:icon.x-mark class="w-4 h-4" />
                                                @endif
                                            </flux:button>
                                            @endcan

                                            @can('delete_users')
                                            @if($user->id !== auth()->id())
                                            <flux:button 
                                                size="sm" 
                                                variant="danger" 
                                                wire:click="openDeleteUserModal({{ $user->id }})"
                                            >
                                                <flux:icon.trash class="w-4 h-4" />
                                            </flux:button>
                                            @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <div class="text-[#9B9EA4] dark:text-zinc-400">
                                            <flux:icon.users class="w-12 h-12 mx-auto mb-4 opacity-40" />
                                            <p>No users found matching your criteria.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($users->hasPages())
                <div class="px-6 py-4 border-t border-[#9B9EA4]/20">
                    {{ $users->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <flux:modal wire:model="showCreateUserModal">
        <form wire:submit="createUser" class="space-y-6">
            <div>
                <flux:heading size="lg">Create New User</flux:heading>
                <flux:subheading>Add a new user to the system</flux:subheading>
            </div>

            <!-- Basic Information -->
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>First Name</flux:label>
                        <flux:input wire:model="first_name" required />
                        <flux:error name="first_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Last Name</flux:label>
                        <flux:input wire:model="last_name" required />
                        <flux:error name="last_name" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Email Address</flux:label>
                        <flux:input type="email" wire:model="email" required />
                        <flux:error name="email" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Phone Number</flux:label>
                        <flux:input wire:model="phone" required />
                        <flux:error name="phone" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <flux:field>
                        <flux:label>Gender</flux:label>
                        <flux:select wire:model="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </flux:select>
                        <flux:error name="gender" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Role</flux:label>
                        <flux:select wire:model="user_role" required>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="user_role" />
                    </flux:field>

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
                <flux:field>
                    <flux:checkbox wire:model.live="is_kenha_staff">KeNHA Staff Member</flux:checkbox>
                </flux:field>
            </div>

            <!-- Staff Information (shown when KeNHA staff is selected) -->
            @if($is_kenha_staff)
            <div class="space-y-4 border-t border-[#9B9EA4]/20 pt-4">
                <flux:heading size="base">Staff Information</flux:heading>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Staff Number</flux:label>
                        <flux:input wire:model="staff_number" required />
                        <flux:error name="staff_number" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Job Title</flux:label>
                        <flux:input wire:model="job_title" required />
                        <flux:error name="job_title" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Department</flux:label>
                        <flux:input wire:model="department" required />
                        <flux:error name="department" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Work Station</flux:label>
                        <flux:input wire:model="work_station" required />
                        <flux:error name="work_station" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Employment Type</flux:label>
                        <flux:select wire:model="employment_type" required>
                            <option value="permanent">Permanent</option>
                            <option value="contract">Contract</option>
                            <option value="temporary">Temporary</option>
                        </flux:select>
                        <flux:error name="employment_type" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Employment Date</flux:label>
                        <flux:input type="date" wire:model="employment_date" required />
                        <flux:error name="employment_date" />
                    </flux:field>
                </div>
            </div>
            @endif

            <div class="flex justify-end space-x-2">
                <flux:button variant="ghost" wire:click="$set('showCreateUserModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Create User</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit User Modal (similar structure) -->
    <flux:modal wire:model="showEditUserModal">
        <form wire:submit="updateUser" class="space-y-6">
            <div>
                <flux:heading size="lg">Edit User</flux:heading>
                <flux:subheading>Update user information</flux:subheading>
            </div>

            <!-- Similar form fields as create modal... -->
            <!-- Basic Information -->
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>First Name</flux:label>
                        <flux:input wire:model="first_name" required />
                        <flux:error name="first_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Last Name</flux:label>
                        <flux:input wire:model="last_name" required />
                        <flux:error name="last_name" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Email Address</flux:label>
                        <flux:input type="email" wire:model="email" required />
                        <flux:error name="email" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Phone Number</flux:label>
                        <flux:input wire:model="phone" required />
                        <flux:error name="phone" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <flux:field>
                        <flux:label>Gender</flux:label>
                        <flux:select wire:model="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </flux:select>
                        <flux:error name="gender" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Role</flux:label>
                        <flux:select wire:model="user_role" required>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="user_role" />
                    </flux:field>

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
                <flux:field>
                    <flux:checkbox wire:model.live="is_kenha_staff">KeNHA Staff Member</flux:checkbox>
                </flux:field>
            </div>

            <!-- Staff Information (shown when KeNHA staff is selected) -->
            @if($is_kenha_staff)
            <div class="space-y-4 border-t border-[#9B9EA4]/20 pt-4">
                <flux:heading size="base">Staff Information</flux:heading>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Staff Number</flux:label>
                        <flux:input wire:model="staff_number" required />
                        <flux:error name="staff_number" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Job Title</flux:label>
                        <flux:input wire:model="job_title" required />
                        <flux:error name="job_title" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Department</flux:label>
                        <flux:input wire:model="department" required />
                        <flux:error name="department" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Work Station</flux:label>
                        <flux:input wire:model="work_station" required />
                        <flux:error name="work_station" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Employment Type</flux:label>
                        <flux:select wire:model="employment_type" required>
                            <option value="permanent">Permanent</option>
                            <option value="contract">Contract</option>
                            <option value="temporary">Temporary</option>
                        </flux:select>
                        <flux:error name="employment_type" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Employment Date</flux:label>
                        <flux:input type="date" wire:model="employment_date" required />
                        <flux:error name="employment_date" />
                    </flux:field>
                </div>
            </div>
            @endif

            <div class="flex justify-end space-x-2">
                <flux:button variant="ghost" wire:click="$set('showEditUserModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Update User</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Delete User Modal -->
    <flux:modal wire:model="showDeleteUserModal">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete User</flux:heading>
                <flux:subheading>This action cannot be undone</flux:subheading>
            </div>

            @if($deletingUser)
            <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                <p class="text-sm text-red-800 dark:text-red-200">
                    Are you sure you want to delete <strong>{{ $deletingUser->name }}</strong> ({{ $deletingUser->email }})?
                    This will permanently remove the user and all associated data.
                </p>
            </div>

            <flux:field>
                <flux:label>Confirm with your password</flux:label>
                <flux:input type="password" wire:model="delete_confirmation" placeholder="Enter your password" required />
                <flux:error name="delete_confirmation" />
            </flux:field>
            @endif

            <div class="flex justify-end space-x-2">
                <flux:button variant="ghost" wire:click="$set('showDeleteUserModal', false)">Cancel</flux:button>
                <flux:button wire:click="deleteUser" variant="danger">Delete User</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Ban/Unban User Modal -->
    <flux:modal wire:model="showBanUserModal">
        <div class="space-y-6">
            @if($banningUser)
            <div>
                <flux:heading size="lg">{{ $banningUser->account_status === 'banned' ? 'Unban' : 'Ban' }} User</flux:heading>
                <flux:subheading>Change user account status</flux:subheading>
            </div>

            <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                    Are you sure you want to {{ $banningUser->account_status === 'banned' ? 'unban' : 'ban' }} 
                    <strong>{{ $banningUser->name }}</strong> ({{ $banningUser->email }})?
                </p>
            </div>
            @endif

            <div class="flex justify-end space-x-2">
                <flux:button variant="ghost" wire:click="$set('showBanUserModal', false)">Cancel</flux:button>
                <flux:button wire:click="banUser" variant="{{ $banningUser && $banningUser->account_status === 'banned' ? 'primary' : 'danger' }}">
                    {{ $banningUser && $banningUser->account_status === 'banned' ? 'Unban' : 'Ban' }} User
                </flux:button>
            </div>
        </div>
    </flux:modal>
</x-layouts.app>
