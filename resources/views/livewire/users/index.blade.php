<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Staff;
use Spatie\Permission\Models\Role;
use App\Services\AuditService;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\{Layout, Title};

new #[Layout('components.layouts.app')] #[Title('User Management')] class extends Component
{
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

{{-- Modern User Management Interface with Glass Morphism & Enhanced UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Enhanced Header with Modern Typography --}}
        <section aria-labelledby="page-heading" class="group">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center space-y-4 md:space-y-0">
                <div>
                    <h1 id="page-heading" class="text-4xl font-bold text-[#231F20] dark:text-white">User Management</h1>
                    <p class="text-[#9B9EA4] dark:text-zinc-400 mt-2 text-lg">Manage users and their permissions</p>
                </div>
                
                @can('create_users')
                <div class="flex">
                    <flux:button 
                        variant="primary" 
                        href="{{ route('users.create') }}" 
                        wire:navigate
                        class="group justify-center rounded-lg bg-[#FFF200] dark:bg-yellow-400 px-4 py-3 text-sm font-semibold text-[#231F20] dark:text-zinc-900 shadow-lg hover:bg-[#FFF200]/90 dark:hover:bg-yellow-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FFF200] dark:focus-visible:outline-yellow-400 transition-all duration-200 hover:shadow-xl"
                    >
                        <span class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                        <div class="relative flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span>Create User</span>
                        </div>
                    </flux:button>
                </div>
                @endcan
            </div>
        </section>

        {{-- Enhanced Statistics Cards with Glass Morphism --}}
        <section aria-labelledby="stats-heading" class="group">
            <h2 id="stats-heading" class="sr-only">User Statistics</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Total Users Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Total Users</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-blue-600 dark:group-hover/card:text-blue-400 transition-colors duration-300">{{ number_format($totalUsers) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-blue-500 dark:bg-blue-400 rounded-full animate-pulse"></div>
                                <span>Registered accounts</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Active Users Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-emerald-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-emerald-500/20 dark:bg-emerald-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Active Users</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-emerald-600 dark:group-hover/card:text-emerald-400 transition-colors duration-300">{{ number_format($activeUsers) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 rounded-full">
                                <div class="w-2 h-2 bg-emerald-500 dark:bg-emerald-400 rounded-full animate-pulse"></div>
                                <span>Currently active</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Banned Users Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-red-500/5 via-transparent to-red-600/10 dark:from-red-400/10 dark:via-transparent dark:to-red-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-500 to-red-600 dark:from-red-400 dark:to-red-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-red-500/20 dark:bg-red-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Banned Users</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-red-600 dark:group-hover/card:text-red-400 transition-colors duration-300">{{ number_format($bannedUsers) }}</p>
                            
                            @if($bannedUsers > 0)
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 px-3 py-1.5 rounded-full">
                                    <div class="w-2 h-2 bg-red-500 dark:bg-red-400 rounded-full"></div>
                                    <span>Restricted access</span>
                                </div>
                            @else
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 px-3 py-1.5 rounded-full">
                                    <span>No banned users</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Pending Users Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 via-transparent to-amber-600/10 dark:from-amber-400/10 dark:via-transparent dark:to-amber-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="absolute -inset-2 bg-amber-500/20 dark:bg-amber-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Pending Users</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-amber-600 dark:group-hover/card:text-amber-400 transition-colors duration-300">{{ number_format($pendingUsers) }}</p>
                            
                            @if($pendingUsers > 0)
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-3 py-1.5 rounded-full">
                                    <div class="w-2 h-2 bg-amber-500 dark:bg-amber-400 rounded-full animate-ping"></div>
                                    <span>Awaiting verification</span>
                                </div>
                            @else
                                <div class="inline-flex items-center space-x-2 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 px-3 py-1.5 rounded-full">
                                    <span>All verified</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Filters Section --}}
        <section aria-labelledby="filters-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl"></div>
                
                <div class="relative z-10 p-8">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 id="filters-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Filter Users</h3>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Search and filter user accounts</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <!-- Search -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#231F20] dark:text-zinc-300">Search Users</label>
                            <flux:input 
                                wire:model.live="search" 
                                placeholder="Search by name, email, phone..."
                                class="w-full rounded-xl border-white/20 dark:border-zinc-700/50 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm"
                            />
                        </div>

                        <!-- Role Filter -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#231F20] dark:text-zinc-300">Filter by Role</label>
                            <flux:select 
                                wire:model.live="selectedRole" 
                                placeholder="All Roles"
                                class="w-full rounded-xl border-white/20 dark:border-zinc-700/50 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm"
                            >
                                <option value="">All Roles</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->name }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                                @endforeach
                            </flux:select>
                        </div>

                        <!-- Status Filter -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#231F20] dark:text-zinc-300">Filter by Status</label>
                            <flux:select 
                                wire:model.live="selectedStatus" 
                                placeholder="All Statuses"
                                class="w-full rounded-xl border-white/20 dark:border-zinc-700/50 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm"
                            >
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="banned">Banned</option>
                            </flux:select>
                        </div>

                        <!-- Clear Filters -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#231F20] dark:text-zinc-300">Reset Filters</label>
                            <flux:button 
                                icon="arrow-path"
                                variant="subtle" 
                                wire:click="$set('search', ''); $set('selectedRole', ''); $set('selectedStatus', '')"
                                class="w-full justify-center rounded-lg bg-[#FFF200] dark:bg-yellow-400 px-4 py-3 text-sm font-semibold text-[#231F20] dark:text-zinc-900 shadow-lg hover:bg-[#FFF200]/90 dark:hover:bg-yellow-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FFF200] dark:focus-visible:outline-yellow-400 transition-all duration-200 hover:shadow-xl"
                            >
                                Clear All Filters
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Users Table --}}
        <section aria-labelledby="users-table-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Table Header --}}
                <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 id="users-table-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Users Directory</h3>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Manage all registered users and their permissions</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gradient-to-r from-[#F8EBD5]/30 via-[#F8EBD5]/20 to-[#F8EBD5]/30 dark:from-zinc-700/50 dark:via-zinc-700/30 dark:to-zinc-700/50">
                            <tr>
                                <th class="px-8 py-6 text-left text-xs font-bold text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">User</th>
                                <th class="px-8 py-6 text-left text-xs font-bold text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">Contact</th>
                                <th class="px-8 py-6 text-left text-xs font-bold text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">Role</th>
                                <th class="px-8 py-6 text-left text-xs font-bold text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">Status</th>
                                <th class="px-8 py-6 text-left text-xs font-bold text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">Staff Info</th>
                                <th class="px-8 py-6 text-left text-xs font-bold text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">Joined</th>
                                <th class="px-8 py-6 text-center text-xs font-bold text-[#231F20] dark:text-zinc-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#9B9EA4]/20">
                            @forelse($users as $user)
                                <tr class="group/row hover:bg-gradient-to-r hover:from-[#F8EBD5]/10 hover:via-transparent hover:to-[#F8EBD5]/10 dark:hover:from-zinc-700/30 dark:hover:via-transparent dark:hover:to-zinc-700/30 transition-all duration-300">
                                    <!-- User Info -->
                                    <td class="px-8 py-6 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="relative">
                                                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 flex items-center justify-center shadow-lg group-hover/row:shadow-xl transition-shadow duration-300">
                                                    <span class="text-sm font-bold text-[#231F20]">
                                                        {{ strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)) }}
                                                    </span>
                                                </div>
                                                <div class="absolute -inset-1 bg-gradient-to-br from-[#FFF200]/20 to-[#F8EBD5]/20 rounded-2xl blur opacity-0 group-hover/row:opacity-100 transition-opacity duration-300"></div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-semibold text-[#231F20] dark:text-white">{{ $user->name }}</div>
                                                <div class="text-sm text-[#9B9EA4] dark:text-zinc-400">ID: #{{ str_pad($user->id, 4, '0', STR_PAD_LEFT) }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Contact Info -->
                                    <td class="px-8 py-6 whitespace-nowrap">
                                        <div class="text-sm font-medium text-[#231F20] dark:text-white">{{ $user->email }}</div>
                                        <div class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->phone ?? 'No phone provided' }}</div>
                                    </td>

                                    <!-- Role -->
                                    <td class="px-8 py-6 whitespace-nowrap">
                                        @php $role = $user->roles->first(); @endphp
                                        @if($role)
                                            <span class="inline-flex px-3 py-2 text-xs font-semibold rounded-2xl shadow-sm 
                                                @if($role->name === 'developer') bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800 dark:from-purple-900/30 dark:to-purple-800/30 dark:text-purple-300
                                                @elseif($role->name === 'administrator') bg-gradient-to-r from-red-100 to-red-200 text-red-800 dark:from-red-900/30 dark:to-red-800/30 dark:text-red-300
                                                @elseif($role->name === 'board_member') bg-gradient-to-r from-blue-100 to-blue-200 text-blue-800 dark:from-blue-900/30 dark:to-blue-800/30 dark:text-blue-300
                                                @elseif($role->name === 'manager') bg-gradient-to-r from-green-100 to-green-200 text-green-800 dark:from-green-900/30 dark:to-green-800/30 dark:text-green-300
                                                @elseif($role->name === 'sme') bg-gradient-to-r from-indigo-100 to-indigo-200 text-indigo-800 dark:from-indigo-900/30 dark:to-indigo-800/30 dark:text-indigo-300
                                                @elseif($role->name === 'challenge_reviewer') bg-gradient-to-r from-orange-100 to-orange-200 text-orange-800 dark:from-orange-900/30 dark:to-orange-800/30 dark:text-orange-300
                                                @else bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800 dark:from-gray-900/30 dark:to-gray-800/30 dark:text-gray-300
                                                @endif">
                                                {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                            </span>
                                        @else
                                            <span class="inline-flex px-3 py-2 text-xs font-semibold rounded-2xl bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">No Role Assigned</span>
                                        @endif
                                    </td>

                                    <!-- Status -->
                                    <td class="px-8 py-6 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-2xl shadow-sm 
                                            @if($user->account_status === 'active') bg-gradient-to-r from-emerald-100 to-emerald-200 text-emerald-800 dark:from-emerald-900/30 dark:to-emerald-800/30 dark:text-emerald-300
                                            @elseif($user->account_status === 'suspended') bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-800 dark:from-yellow-900/30 dark:to-yellow-800/30 dark:text-yellow-300
                                            @elseif($user->account_status === 'banned') bg-gradient-to-r from-red-100 to-red-200 text-red-800 dark:from-red-900/30 dark:to-red-800/30 dark:text-red-300
                                            @else bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800 dark:from-gray-900/30 dark:to-gray-800/30 dark:text-gray-300
                                            @endif">
                                            <div class="w-2 h-2 rounded-full mr-2 
                                                @if($user->account_status === 'active') bg-emerald-500 animate-pulse
                                                @elseif($user->account_status === 'suspended') bg-yellow-500
                                                @elseif($user->account_status === 'banned') bg-red-500
                                                @else bg-gray-500
                                                @endif"></div>
                                            {{ ucfirst($user->account_status) }}
                                        </span>
                                    </td>

                                    <!-- Staff Info -->
                                    <td class="px-8 py-6 whitespace-nowrap">
                                        @if($user->staff)
                                            <div class="text-sm font-medium text-[#231F20] dark:text-white">{{ $user->staff->staff_number }}</div>
                                            <div class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->staff->job_title }}</div>
                                        @else
                                            <span class="inline-flex px-3 py-2 text-xs font-semibold rounded-2xl bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">External User</span>
                                        @endif
                                    </td>

                                    <!-- Joined Date -->
                                    <td class="px-8 py-6 whitespace-nowrap">
                                        <div class="text-sm font-medium text-[#231F20] dark:text-white">{{ $user->created_at->format('M d, Y') }}</div>
                                        <div class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->created_at->diffForHumans() }}</div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-8 py-6 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center space-x-2">
                                            @can('view_users')
                                            <flux:button 
                                                size="sm" 
                                                variant="subtle" 
                                                href="{{ route('users.show', $user) }}"
                                                wire:navigate
                                                class="group/btn relative overflow-hidden rounded-xl bg-white/70 dark:bg-zinc-700/70 backdrop-blur-sm border border-white/20 dark:border-zinc-600/50 hover:shadow-lg transition-all duration-300 p-2"
                                            >
                                                <span class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-blue-600/20 opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></span>
                                                <svg class="relative w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </flux:button>
                                            @endcan

                                            @can('edit_users')
                                            @if(auth()->user()->hasRole('developer') || !$user->hasRole('developer'))
                                            <flux:button 
                                                size="sm" 
                                                variant="subtle" 
                                                href="{{ route('users.edit', $user) }}"
                                                wire:navigate
                                                class="group/btn relative overflow-hidden rounded-xl bg-white/70 dark:bg-zinc-700/70 backdrop-blur-sm border border-white/20 dark:border-zinc-600/50 hover:shadow-lg transition-all duration-300 p-2"
                                            >
                                                <span class="absolute inset-0 bg-gradient-to-br from-purple-500/10 to-purple-600/20 opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></span>
                                                <svg class="relative w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </flux:button>
                                            @endif
                                            @endcan

                                            @can('ban_users')
                                            @if(auth()->user()->hasRole('developer') || !$user->hasRole('developer'))
                                            @if($user->id !== auth()->id())
                                            <flux:button 
                                                size="sm" 
                                                wire:click="openBanUserModal({{ $user->id }})"
                                                class="group/btn relative overflow-hidden rounded-xl backdrop-blur-sm border hover:shadow-lg transition-all duration-300 p-2
                                                    @if($user->account_status === 'banned') 
                                                        bg-emerald-500/20 dark:bg-emerald-400/20 border-emerald-300/50 dark:border-emerald-600/50 hover:bg-emerald-500/30
                                                    @else 
                                                        bg-red-500/20 dark:bg-red-400/20 border-red-300/50 dark:border-red-600/50 hover:bg-red-500/30
                                                    @endif"
                                            >
                                                @if($user->account_status === 'banned')
                                                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                @else
                                                    <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
                                                    </svg>
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
                                                wire:click="openDeleteUserModal({{ $user->id }})"
                                                class="group/btn relative overflow-hidden rounded-xl bg-red-500/20 dark:bg-red-400/20 backdrop-blur-sm border border-red-300/50 dark:border-red-600/50 hover:shadow-lg hover:bg-red-500/30 transition-all duration-300 p-2"
                                            >
                                                <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </flux:button>
                                            @endif
                                            @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-8 py-16 text-center">
                                        <div class="flex flex-col items-center space-y-4">
                                            <div class="w-20 h-20 bg-gradient-to-br from-[#FFF200]/20 to-[#F8EBD5]/20 dark:from-yellow-400/20 dark:to-amber-400/20 rounded-3xl flex items-center justify-center">
                                                <svg class="w-10 h-10 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <h4 class="text-lg font-semibold text-[#231F20] dark:text-zinc-100 mb-2">No Users Found</h4>
                                                <p class="text-[#9B9EA4] dark:text-zinc-400">No users match your current search criteria. Try adjusting your filters.</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Enhanced Pagination -->
                @if($users->hasPages())
                <div class="p-8 border-t border-gray-100/50 dark:border-zinc-700/50 bg-gradient-to-r from-[#F8EBD5]/10 via-transparent to-[#F8EBD5]/10 dark:from-zinc-700/20 dark:via-transparent dark:to-zinc-700/20">
                    {{ $users->links() }}
                </div>
                @endif
            </div>
        </section>
    </div>

    <!-- Enhanced Delete User Modal -->
    <flux:modal wire:model="showDeleteUserModal" class="backdrop-blur-xl">
        <div class="relative overflow-hidden rounded-3xl bg-white/90 dark:bg-zinc-800/90 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-2xl">
            <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-red-500/10 via-transparent to-red-600/20 dark:from-red-400/10 dark:via-transparent dark:to-red-500/20 rounded-full -mr-48 -mt-48 blur-3xl"></div>
            
            <div class="relative z-10 p-8 space-y-6">
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 dark:from-red-400 dark:to-red-500 rounded-2xl flex items-center justify-center shadow-lg">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </div>
                    <div>
                        <flux:heading size="lg" class="text-[#231F20] dark:text-zinc-100">Delete User Account</flux:heading>
                        <flux:subheading class="text-red-600 dark:text-red-400">This action cannot be undone</flux:subheading>
                    </div>
                </div>

                @if($deletingUser)
                <div class="p-6 bg-red-50/70 dark:bg-red-900/20 rounded-2xl border border-red-200/50 dark:border-red-800/50 backdrop-blur-sm">
                    <p class="text-sm text-red-800 dark:text-red-200 leading-relaxed">
                        Are you sure you want to permanently delete <strong>{{ $deletingUser->name }}</strong> ({{ $deletingUser->email }})?
                        This will permanently remove the user account and all associated data from the system.
                    </p>
                </div>

                <flux:field>
                    <flux:label class="text-[#231F20] dark:text-zinc-300 font-semibold">Confirm with your password</flux:label>
                    <flux:input 
                        type="password" 
                        wire:model="password_confirmation" 
                        placeholder="Enter your current password"
                        class="rounded-xl border-white/20 dark:border-zinc-700/50 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm"
                        required 
                    />
                    <flux:error name="password_confirmation" />
                </flux:field>
                @endif

                <div class="flex justify-end space-x-3 pt-4">
                    <flux:button 
                        variant="ghost" 
                        wire:click="$set('showDeleteUserModal', false)"
                        class="rounded-xl"
                    >
                        Cancel
                    </flux:button>
                    <flux:button 
                        wire:click="deleteUser" 
                        variant="danger"
                        class="rounded-xl bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-semibold px-6 py-2 shadow-lg hover:shadow-xl transition-all duration-300"
                    >
                        Delete User Permanently
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <!-- Enhanced Ban/Unban User Modal -->
    <flux:modal wire:model="showBanUserModal" class="backdrop-blur-xl">
        <div class="relative overflow-hidden rounded-3xl bg-white/90 dark:bg-zinc-800/90 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-2xl">
            @if($banningUser)
            <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br 
                @if($banningUser->account_status === 'banned') 
                    from-emerald-500/10 via-transparent to-emerald-600/20 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20
                @else 
                    from-amber-500/10 via-transparent to-amber-600/20 dark:from-amber-400/10 dark:via-transparent dark:to-amber-500/20
                @endif 
                rounded-full -mr-48 -mt-48 blur-3xl"></div>
            @endif
            
            <div class="relative z-10 p-8 space-y-6">
                @if($banningUser)
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 bg-gradient-to-br 
                        @if($banningUser->account_status === 'banned') 
                            from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500
                        @else 
                            from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500
                        @endif 
                        rounded-2xl flex items-center justify-center shadow-lg">
                        @if($banningUser->account_status === 'banned')
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @else
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
                            </svg>
                        @endif
                    </div>
                    <div>
                        <flux:heading size="lg" class="text-[#231F20] dark:text-zinc-100">
                            {{ $banningUser->account_status === 'banned' ? 'Restore User Access' : 'Restrict User Access' }}
                        </flux:heading>
                        <flux:subheading class="
                            @if($banningUser->account_status === 'banned') 
                                text-emerald-600 dark:text-emerald-400
                            @else 
                                text-amber-600 dark:text-amber-400
                            @endif">
                            Change user account status
                        </flux:subheading>
                    </div>
                </div>

                <div class="p-6 rounded-2xl border backdrop-blur-sm
                    @if($banningUser->account_status === 'banned') 
                        bg-emerald-50/70 dark:bg-emerald-900/20 border-emerald-200/50 dark:border-emerald-800/50
                    @else 
                        bg-amber-50/70 dark:bg-amber-900/20 border-amber-200/50 dark:border-amber-800/50
                    @endif">
                    <p class="text-sm leading-relaxed
                        @if($banningUser->account_status === 'banned') 
                            text-emerald-800 dark:text-emerald-200
                        @else 
                            text-amber-800 dark:text-amber-200
                        @endif">
                        Are you sure you want to {{ $banningUser->account_status === 'banned' ? 'restore access for' : 'ban' }} 
                        <strong>{{ $banningUser->name }}</strong> ({{ $banningUser->email }})?
                        @if($banningUser->account_status === 'banned')
                            This will allow the user to access their account again.
                        @else
                            This will prevent the user from accessing their account.
                        @endif
                    </p>
                </div>
                @endif

                <div class="flex justify-end space-x-3 pt-4">
                    <flux:button 
                        variant="ghost" 
                        wire:click="$set('showBanUserModal', false)"
                        class="rounded-xl"
                    >
                        Cancel
                    </flux:button>
                    <flux:button 
                        wire:click="banUser" 
                        class="rounded-xl font-semibold px-6 py-2 shadow-lg hover:shadow-xl transition-all duration-300
                            @if($banningUser && $banningUser->account_status === 'banned') 
                                bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white
                            @else 
                                bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white
                            @endif"
                    >
                        {{ $banningUser && $banningUser->account_status === 'banned' ? 'Restore Access' : 'Ban User' }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
