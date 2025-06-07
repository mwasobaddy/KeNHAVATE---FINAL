<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Staff;
use Spatie\Permission\Models\Role;
use App\Services\AuditService;
use Illuminate\Support\Facades\Hash;

new #[Layout('components.layouts.app', title: 'User Management')] class extends Component {
    use WithPagination;

    // User Management Properties
    public $search = '';
    public $selectedRole = '';
    public $selectedStatus = '';
    public $showDeleteUserModal = false;
    public $showBanUserModal = false;
    public $deletingUser = null;
    public $banningUser = null;

    // Security confirmation
    public $password_confirmation = '';

    protected AuditService $auditService;

    public function boot(AuditService $auditService): void
    {
        $this->auditService = $auditService;
    }

    public function mount()
    {
        // Check permission instead of role
        if (!auth()->user()->can('view_users')) {
            abort(403, 'Unauthorized access to user management.');
        }
    }

    public function with()
    {
        $currentUser = auth()->user();
        $isDeveloper = $currentUser->hasRole('developer');
        
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
            // Hide developer users from non-developers
            ->when(!$isDeveloper, function ($q) {
                $q->whereDoesntHave('roles', function ($roleQuery) {
                    $roleQuery->where('name', 'developer');
                });
            })
            ->latest();

        // Filter roles based on user permissions
        $roles = Role::when(!$isDeveloper, function ($q) {
            $q->where('name', '!=', 'developer');
        })->get();

        return [
            'users' => $query->paginate(20),
            'roles' => $roles,
            'totalUsers' => User::when(!$isDeveloper, function ($q) {
                $q->whereDoesntHave('roles', function ($roleQuery) {
                    $roleQuery->where('name', 'developer');
                });
            })->count(),
            'activeUsers' => User::where('account_status', 'active')
                ->when(!$isDeveloper, function ($q) {
                    $q->whereDoesntHave('roles', function ($roleQuery) {
                        $roleQuery->where('name', 'developer');
                    });
                })->count(),
            'bannedUsers' => User::where('account_status', 'banned')
                ->when(!$isDeveloper, function ($q) {
                    $q->whereDoesntHave('roles', function ($roleQuery) {
                        $roleQuery->where('name', 'developer');
                    });
                })->count(),
            'pendingUsers' => User::where('email_verified_at', null)
                ->when(!$isDeveloper, function ($q) {
                    $q->whereDoesntHave('roles', function ($roleQuery) {
                        $roleQuery->where('name', 'developer');
                    });
                })->count(),
        ];
    }

    public function openDeleteUserModal($userId)
    {
        $user = User::with('roles')->findOrFail($userId);
        
        // Prevent non-developers from deleting developer users
        if (!auth()->user()->hasRole('developer') && $user->hasRole('developer')) {
            abort(403, 'You cannot delete developer users.');
        }

        $this->deletingUser = $user;
        $this->showDeleteUserModal = true;
        $this->password_confirmation = '';
    }

    public function deleteUser()
    {
        // Validate password for security
        if (!Hash::check($this->password_confirmation, auth()->user()->password)) {
            $this->addError('password_confirmation', 'Password confirmation is incorrect.');
            return;
        }

        // Prevent deleting yourself
        if ($this->deletingUser->id === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }

        // Prevent non-developers from deleting developer users
        if (!auth()->user()->hasRole('developer') && $this->deletingUser->hasRole('developer')) {
            session()->flash('error', 'You cannot delete developer users.');
            return;
        }

        try {
            $userName = $this->deletingUser->name;
            $userEmail = $this->deletingUser->email;

            // Create audit log before deletion
            $this->auditService->log('user_deleted', 'User', $this->deletingUser->id, [
                'name' => $userName,
                'email' => $userEmail,
                'role' => $this->deletingUser->roles->first()?->name,
                'deleted_by' => auth()->user()->name,
            ], null);

            // Delete user (cascades to staff record)
            $this->deletingUser->delete();

            $this->showDeleteUserModal = false;
            $this->password_confirmation = '';
            session()->flash('message', "User {$userName} deleted successfully!");

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete user: ' . $e->getMessage());
        }
    }

    public function openBanUserModal($userId)
    {
        $user = User::with('roles')->findOrFail($userId);
        
        // Prevent non-developers from banning developer users
        if (!auth()->user()->hasRole('developer') && $user->hasRole('developer')) {
            abort(403, 'You cannot ban developer users.');
        }

        $this->banningUser = $user;
        $this->showBanUserModal = true;
    }

    public function banUser()
    {
        // Prevent banning yourself
        if ($this->banningUser->id === auth()->id()) {
            session()->flash('error', 'You cannot ban your own account.');
            return;
        }

        // Prevent non-developers from banning developer users
        if (!auth()->user()->hasRole('developer') && $this->banningUser->hasRole('developer')) {
            session()->flash('error', 'You cannot ban developer users.');
            return;
        }

        try {
            $oldStatus = $this->banningUser->account_status;
            $newStatus = $oldStatus === 'banned' ? 'active' : 'banned';
            $action = $newStatus === 'banned' ? 'banned' : 'unbanned';

            $this->banningUser->update(['account_status' => $newStatus]);

            // Create audit log
            $this->auditService->log("user_{$action}", 'User', $this->banningUser->id, 
                ['account_status' => $oldStatus], 
                ['account_status' => $newStatus, 'actioned_by' => auth()->user()->name]
            );

            $this->showBanUserModal = false;
            session()->flash('message', "User {$action} successfully!");

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update user status: ' . $e->getMessage());
        }
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

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-[#231F20] dark:text-white">User Management</h1>
                    <p class="text-[#9B9EA4] dark:text-zinc-400 mt-1">Manage users and their permissions</p>
                </div>
                
                @can('create_users')
                <flux:button variant="primary" href="{{ route('users.create') }}" wire:navigate>
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
                                            @can('view_users')
                                            <flux:button 
                                                size="sm" 
                                                variant="subtle" 
                                                href="{{ route('users.show', $user) }}"
                                                wire:navigate
                                            >
                                                <flux:icon.eye class="w-4 h-4" />
                                            </flux:button>
                                            @endcan

                                            @can('edit_users')
                                            @if(auth()->user()->hasRole('developer') || !$user->hasRole('developer'))
                                            <flux:button 
                                                size="sm" 
                                                variant="subtle" 
                                                href="{{ route('users.edit', $user) }}"
                                                wire:navigate
                                            >
                                                <flux:icon.pencil class="w-4 h-4" />
                                            </flux:button>
                                            @endif
                                            @endcan

                                            @can('ban_users')
                                            @if(auth()->user()->hasRole('developer') || !$user->hasRole('developer'))
                                            @if($user->id !== auth()->id())
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
                                            @endif
                                            @endif
                                            @endcan

                                            @can('delete_users')
                                            @if(auth()->user()->hasRole('developer') || !$user->hasRole('developer'))
                                            @if($user->id !== auth()->id())
                                            <flux:button 
                                                size="sm" 
                                                variant="danger" 
                                                wire:click="openDeleteUserModal({{ $user->id }})"
                                            >
                                                <flux:icon.trash class="w-4 h-4" />
                                            </flux:button>
                                            @endif
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
                <flux:input type="password" wire:model="password_confirmation" placeholder="Enter your password" required />
                <flux:error name="password_confirmation" />
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
</div>
