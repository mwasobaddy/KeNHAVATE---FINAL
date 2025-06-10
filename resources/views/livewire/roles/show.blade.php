<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\AuditLog;

new #[Layout('components.layouts.app', title: 'Role Details')] class extends Component {
    use WithPagination;

    public Role $role;
    public $userSearch = '';
    public $showRemoveUserModal = false;
    public $userToRemove = null;

    public function mount(Role $role)
    {
        // Check if user has permission to view roles
        $this->authorize('view', $role);
        
        // Hide developer role from non-developers for security
        if ($role->name === 'developer' && !auth()->user()->hasRole('developer')) {
            abort(403, 'Access denied.');
        }

        $this->role = $role;
    }

    public function with()
    {
        // Get users with this role
        $usersQuery = $this->role->users();
        
        if ($this->userSearch) {
            $usersQuery->where(function($query) {
                $query->where('name', 'like', '%' . $this->userSearch . '%')
                      ->orWhere('email', 'like', '%' . $this->userSearch . '%');
            });
        }

        $users = $usersQuery->orderBy('name')->paginate(10, ['*'], 'users');

        // Get recent activities related to this role
        $recentActivities = AuditLog::where(function($query) {
                $query->where('entity_type', 'Role')
                      ->where('entity_id', $this->role->id);
            })
            ->orWhere(function($query) {
                $query->where('action', 'like', '%role%')
                      ->where('new_values->roles', 'like', '%' . $this->role->name . '%');
            })
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Statistics
        $stats = [
            'total_users' => $this->role->users()->count(),
            'active_users' => $this->role->users()->whereNotNull('email_verified_at')->count(),
            'total_permissions' => $this->role->permissions()->count(),
            'created_date' => $this->role->created_at,
        ];

        return [
            'users' => $users,
            'recentActivities' => $recentActivities,
            'stats' => $stats,
            'permissions' => $this->role->permissions()->orderBy('name')->get()
        ];
    }

    public function updatedUserSearch()
    {
        $this->resetPage('users');
    }

    public function removeUserFromRole($userId)
    {
        $this->authorize('update', $this->role);
        
        $user = User::findOrFail($userId);
        
        // Prevent removing developer role from developers by non-developers
        if ($this->role->name === 'developer' && !auth()->user()->hasRole('developer')) {
            session()->flash('error', 'You cannot remove users from the developer role.');
            return;
        }

        // Prevent users from removing their own role
        if ($user->id === auth()->id()) {
            session()->flash('error', 'You cannot remove your own role.');
            return;
        }

        // Prevent removing users from the default "user" role
        if ($this->role->name === 'user') {
            session()->flash('error', 'Users cannot be removed from the default "User" role. This role ensures all users have basic system access.');
            return;
        }

        // Store user for modal and show confirmation
        $this->userToRemove = $user;
        $this->showRemoveUserModal = true;
    }

    public function confirmRemoveUser()
    {
        if (!$this->userToRemove) {
            session()->flash('error', 'No user selected for removal.');
            return;
        }

        try {
            // Store old roles for audit trail
            $oldRoles = $this->userToRemove->getRoleNames()->toArray();

            // Remove the user from the specific role
            $this->userToRemove->removeRole($this->role);

            // Refresh the user to get updated roles
            $this->userToRemove->refresh();

            // Check if user has any remaining roles after removal
            $remainingRoles = $this->userToRemove->getRoleNames();
            $hasOtherRoles = $remainingRoles->count() > 0;
            $hasUserRole = $this->userToRemove->hasRole('user');

            // Ensure user always has at least the "user" role if they don't have any other roles
            // or if they don't already have the user role
            $defaultUserRole = \Spatie\Permission\Models\Role::where('name', 'user')->first();
            if ($defaultUserRole && (!$hasOtherRoles || !$hasUserRole)) {
                $this->userToRemove->assignRole($defaultUserRole);
                $assignedUserRole = true;
            } else {
                $assignedUserRole = false;
            }

            // Get new roles for audit trail
            $newRoles = $this->userToRemove->fresh()->getRoleNames()->toArray();

            // Log the action
            app(\App\Services\AuditService::class)->log(
                'role_removed',
                'User',
                $this->userToRemove->id,
                ['roles' => $oldRoles],
                ['roles' => $newRoles, 'removed_role' => $this->role->name]
            );

            // Set appropriate success message based on whether user role was assigned
            if ($assignedUserRole) {
                session()->flash('success', "User {$this->userToRemove->name} removed from {$this->role->name} role and assigned default User role to maintain system access.");
            } else {
                session()->flash('success', "User {$this->userToRemove->name} removed from {$this->role->name} role successfully.");
            }

        } catch (\Exception $e) {
            \Log::error('Failed to remove user from role', [
                'user_id' => $this->userToRemove->id,
                'role_id' => $this->role->id,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Failed to remove user from role. Please try again.');
        }

        $this->closeRemoveUserModal();
    }

    public function closeRemoveUserModal()
    {
        $this->showRemoveUserModal = false;
        $this->userToRemove = null;
    }
}; ?>

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-gradient-to-r from-green-500/20 to-blue-500/20 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-gradient-to-r from-yellow-500/20 to-orange-500/20 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 lg:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Header Section --}}
        <div class="text-center mb-8">
            <div class="flex items-center justify-start space-x-4 mb-4">
                <flux:button 
                    wire:navigate 
                    href="{{ route('roles.index') }}" 
                    variant="ghost" 
                    size="sm"
                    class="bg-white/20 backdrop-blur-sm hover:bg-white/30 transition-all duration-300"
                >
                    <flux:icon.arrow-left class="w-4 h-4 mr-2" />
                    Back to Roles
                </flux:button>
            </div>
            
            <h1 class="text-4xl font-bold text-[#231F20] dark:text-white mb-4">
                {{ ucfirst($role->name) }} Role Details
            </h1>
            <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">
                Comprehensive role management, permissions, and user assignments
            </p>
        </div>

        {{-- Action Controls --}}
        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
            <div class="flex flex-col lg:flex-row gap-4 items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-500 rounded-xl flex items-center justify-center">
                        <flux:icon.shield-check class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-[#231F20] dark:text-white capitalize">{{ $role->name }} Role</h3>
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">Manage role permissions and assignments</p>
                    </div>
                </div>

                @can('update', $role)
                @if($role->name !== 'developer' || auth()->user()->hasRole('developer'))
                <div class="flex items-center space-x-3">
                    <flux:button 
                        wire:navigate 
                        href="{{ route('roles.edit', $role) }}" 
                        class="flex items-center space-x-2 bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl"
                    >
                        <flux:icon.pencil class="w-4 h-4" />
                        <span>Edit Role</span>
                    </flux:button>
                </div>
                @endif
                @endcan
            </div>
        </div>

        {{-- Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            {{-- Total Users --}}
            <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider">Total Users</p>
                        <p class="text-3xl font-bold text-[#231F20] dark:text-white">{{ number_format($stats['total_users']) }}</p>
                        <p class="text-sm text-blue-600 mt-1">
                            Role assignments
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center">
                        <flux:icon.users class="w-6 h-6 text-white" />
                    </div>
                </div>
            </div>

            {{-- Active Users --}}
            <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider">Active Users</p>
                        <p class="text-3xl font-bold text-[#231F20] dark:text-white">{{ number_format($stats['active_users']) }}</p>
                        <p class="text-sm text-green-600 mt-1">
                            Verified accounts
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center">
                        <flux:icon.check-circle class="w-6 h-6 text-white" />
                    </div>
                </div>
            </div>

            {{-- Permissions Count --}}
            <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider">Permissions</p>
                        <p class="text-3xl font-bold text-[#231F20] dark:text-white">{{ number_format($stats['total_permissions']) }}</p>
                        <p class="text-sm text-purple-600 mt-1">
                            Granted capabilities
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center">
                        <flux:icon.key class="w-6 h-6 text-white" />
                    </div>
                </div>
            </div>

            {{-- Created Date --}}
            <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider">Created</p>
                        <p class="text-lg font-bold text-[#231F20] dark:text-white">{{ $stats['created_date']->format('M j, Y') }}</p>
                        <p class="text-sm text-amber-600 mt-1">
                            {{ $stats['created_date']->diffForHumans() }}
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-amber-500 rounded-xl flex items-center justify-center">
                        <flux:icon.calendar class="w-6 h-6 text-white" />
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Role Permissions --}}
            <div class="lg:col-span-1 space-y-8">
                <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl shadow-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-white/20 dark:border-zinc-700/50 bg-gradient-to-r from-purple-500/10 to-blue-500/10">
                        <h3 class="text-xl font-bold text-[#231F20] dark:text-white">Permissions ({{ $permissions->count() }})</h3>
                        <p class="mt-1 text-sm text-[#9B9EA4] dark:text-zinc-400">Capabilities granted to this role</p>
                    </div>
                    
                    <div class="p-6">
                        @if($permissions->count() > 0)
                        <div class="space-y-4">
                            @php
                                $groupedPermissions = $permissions->groupBy(function($permission) {
                                    return explode('_', $permission->name)[0];
                                });
                            @endphp

                            @foreach($groupedPermissions as $group => $groupPermissions)
                            <div class="bg-white/50 dark:bg-zinc-700/50 rounded-lg p-4">
                                <h4 class="text-sm font-bold text-[#231F20] dark:text-white mb-3 capitalize flex items-center">
                                    <div class="w-6 h-6 bg-blue-100 dark:bg-blue-900/20 rounded-lg flex items-center justify-center mr-2">
                                        <flux:icon.shield-check class="w-3 h-3 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    {{ str_replace('_', ' ', $group) }}
                                </h4>
                                <div class="space-y-2 ml-8">
                                    @foreach($groupPermissions as $permission)
                                    <div class="flex items-center space-x-2">
                                        <flux:icon.check class="w-3 h-3 text-green-500" />
                                        <span class="text-sm text-[#9B9EA4] dark:text-zinc-300">{{ $permission->name }}</span>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                                <flux:icon.key class="w-8 h-8 text-[#9B9EA4] opacity-50" />
                            </div>
                            <p class="text-[#9B9EA4] dark:text-zinc-400">No permissions assigned to this role</p>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Recent Activities --}}
                <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl shadow-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-white/20 dark:border-zinc-700/50 bg-gradient-to-r from-green-500/10 to-blue-500/10">
                        <h3 class="text-xl font-bold text-[#231F20] dark:text-white">Recent Activities</h3>
                        <p class="mt-1 text-sm text-[#9B9EA4] dark:text-zinc-400">Recent actions related to this role</p>
                    </div>
                    
                    <div class="p-6">
                        @if($recentActivities->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentActivities as $activity)
                            <div class="flex items-start space-x-3 p-3 bg-white/50 dark:bg-zinc-700/50 rounded-lg">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/20 rounded-full flex items-center justify-center">
                                        <flux:icon.clock class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-[#231F20] dark:text-white">
                                        <span class="font-medium">{{ $activity->user->name ?? 'System' }}</span>
                                        <span class="text-[#9B9EA4] dark:text-zinc-400">{{ str_replace('_', ' ', $activity->action) }}</span>
                                    </p>
                                    <p class="text-xs text-[#9B9EA4] dark:text-zinc-400 mt-1">{{ $activity->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                                <flux:icon.clock class="w-8 h-8 text-[#9B9EA4] opacity-50" />
                            </div>
                            <p class="text-[#9B9EA4] dark:text-zinc-400">No recent activities</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Users with this Role --}}
            <div class="lg:col-span-2">
                <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl shadow-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-white/20 dark:border-zinc-700/50 bg-gradient-to-r from-blue-500/10 to-purple-500/10">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-[#231F20] dark:text-white">Users ({{ number_format($stats['total_users']) }})</h3>
                                <p class="mt-1 text-sm text-[#9B9EA4] dark:text-zinc-400">Users assigned to this role</p>
                            </div>
                        </div>
                    </div>

                    {{-- Search Users --}}
                    <div class="px-6 py-4 border-b border-white/20 dark:border-zinc-700/50 bg-white/30 dark:bg-zinc-800/30">
                        <flux:input
                            wire:model.live="userSearch"
                            placeholder="Search users by name or email..."
                            class="w-full bg-white/90 dark:bg-zinc-700/90 backdrop-blur-sm border border-[#9B9EA4]/30 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <flux:icon.magnifying-glass slot="leading" class="w-4 h-4" />
                        </flux:input>
                    </div>

                    {{-- Users List --}}
                    <div class="divide-y divide-white/20 dark:divide-zinc-700/50">
                        @forelse($users as $user)
                        <div class="px-6 py-4 hover:bg-white/50 dark:hover:bg-zinc-700/50 transition-all duration-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center shadow-lg">
                                            <span class="text-sm font-bold text-white">
                                                {{ strtoupper(substr($user->name, 0, 2)) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <p class="text-sm font-bold text-[#231F20] dark:text-white truncate">{{ $user->name }}</p>
                                            @if($user->hasRole('developer'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200">
                                                Developer
                                            </span>
                                            @endif
                                            @if($user->is_banned)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200">
                                                Banned
                                            </span>
                                            @endif
                                        </div>
                                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 truncate mb-1">{{ $user->email }}</p>
                                        <div class="flex items-center space-x-4">
                                            <span class="text-xs text-[#9B9EA4] dark:text-zinc-400">
                                                Joined {{ $user->created_at->diffForHumans() }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <flux:button 
                                        wire:navigate 
                                        href="{{ route('users.show', $user) }}" 
                                        variant="ghost" 
                                        size="sm"
                                        class="bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white border-0 rounded-lg transition-all duration-300 hover:shadow-lg transform hover:scale-105 px-3 py-5"
                                    >
                                        <flux:icon.eye class="w-4 h-4" />
                                    </flux:button>
                                    
                                    @can('update', $role)
                                    @if($user->id !== auth()->id() && ($role->name !== 'developer' || auth()->user()->hasRole('developer')))
                                    <flux:button 
                                        wire:click="removeUserFromRole({{ $user->id }})"
                                        variant="ghost" 
                                        size="sm"
                                        class="bg-gradient-to-r from-red-500 to-rose-500 hover:from-red-600 hover:to-rose-600 text-white border-0 rounded-lg transition-all duration-300 hover:shadow-lg transform hover:scale-105 px-3 py-5"
                                    >
                                        <flux:icon.user-minus class="w-4 h-4" />
                                    </flux:button>
                                    @endif
                                    @endcan
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="px-6 py-12 text-center">
                            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                                <flux:icon.users class="w-8 h-8 text-[#9B9EA4] opacity-50" />
                            </div>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg font-medium">No users found with this role.</p>
                            @if($userSearch)
                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mt-2">Try adjusting your search terms.</p>
                            @endif
                        </div>
                        @endforelse
                    </div>

                    {{-- Pagination --}}
                    @if($users->hasPages())
                    <div class="px-6 py-4 border-t border-white/20 dark:border-zinc-700/50 bg-white/30 dark:bg-zinc-800/30">
                        {{ $users->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Success Message --}}
        @if(session('success'))
            <div class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-xl z-50" 
                 x-data="{ show: true }" 
                 x-show="show" 
                 x-transition 
                 x-init="setTimeout(() => show = false, 5000)">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-xl z-50" 
                 x-data="{ show: true }" 
                 x-show="show" 
                 x-transition 
                 x-init="setTimeout(() => show = false, 5000)">
                {{ session('error') }}
            </div>
        @endif
    </div>

    {{-- Remove User Confirmation Modal --}}
    <flux:modal wire:model="showRemoveUserModal" class="max-w-md">
        <div class="">
            {{-- Modal Header --}}
            <div class="flex items-center space-x-4 mb-6">
                <div class="w-12 h-12 bg-gradient-to-r from-red-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg">
                    <flux:icon.user-minus class="w-6 h-6 text-white" />
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#231F20] dark:text-white">Remove User from Role</h3>
                    <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">This action cannot be undone</p>
                </div>
            </div>

            {{-- Warning Message --}}
            <div class="bg-red-50/70 dark:bg-red-900/30 border border-red-200/50 dark:border-red-700/50 rounded-xl p-4 mb-6">
                <div class="flex items-start space-x-3">
                    <flux:icon.exclamation-triangle class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5" />
                    <div>
                        <h4 class="text-sm font-semibold text-red-800 dark:text-red-200 mb-1">
                            Remove User: {{ $userToRemove ? $userToRemove->name : '' }}
                        </h4>
                        <p class="text-sm text-red-700 dark:text-red-300 mb-2">
                            This user will be removed from the <strong>{{ $role->name }}</strong> role. If they have no other roles or don't already have the "User" role, they will automatically be assigned the default "User" role to maintain system access.
                        </p>
                        @if($role->name === 'developer' || $role->name === 'administrator')
                        <p class="text-sm text-red-700 dark:text-red-300">
                            <strong>Warning:</strong> This user will lose all elevated system access and administrative capabilities.
                        </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Confirmation Text --}}
            <div class="mb-6">
                <p class="text-[#231F20] dark:text-white">
                    Are you sure you want to proceed? The user will lose all permissions associated with this role. If they don't have any other roles or already have the "User" role, they will automatically receive the default "User" role to maintain basic system access.
                </p>
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center justify-end space-x-4">
                <flux:button 
                    wire:click="closeRemoveUserModal" 
                    variant="ghost"
                    class="bg-gray-50 dark:bg-zinc-700 hover:bg-gray-100 dark:hover:bg-zinc-600 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-zinc-600 rounded-lg px-6 py-2.5 font-semibold transition-all duration-300"
                >
                    Cancel
                </flux:button>
                
                <flux:button
                    icon="user-minus"
                    wire:click="confirmRemoveUser" 
                    variant="danger"
                    class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-6 py-2.5 rounded-lg font-semibold shadow-lg hover:shadow-xl transition-all duration-300"
                    wire:loading.attr="disabled"
                    wire:target="confirmRemoveUser"
                >
                    <span wire:loading.remove wire:target="confirmRemoveUser">Remove User</span>
                    <span wire:loading wire:target="confirmRemoveUser" class="flex items-center">
                        <svg class="w-4 h-4 mr-2 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Removing...
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
