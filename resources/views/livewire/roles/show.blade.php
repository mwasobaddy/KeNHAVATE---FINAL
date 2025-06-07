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

        try {
            $user->removeRole($this->role);

            // Log the action
            app(\App\Services\AuditService::class)->log(
                'role_removed',
                'User',
                $user->id,
                ['roles' => $user->getRoleNames()->toArray()],
                ['roles' => $user->fresh()->getRoleNames()->toArray()]
            );

            session()->flash('success', "User {$user->name} removed from {$this->role->name} role.");

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to remove user from role. Please try again.');
        }
    }
}; ?>

<div class="bg-[#F8EBD5] min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center space-x-4 mb-4">
                <flux:button 
                    wire:navigate 
                    href="{{ route('roles.index') }}" 
                    variant="ghost" 
                    size="sm"
                >
                    <flux:icon.arrow-left class="w-4 h-4 mr-2" />
                    Back to Roles
                </flux:button>
            </div>
            
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-[#231F20]">{{ ucfirst($role->name) }} Role</h1>
                    <p class="mt-2 text-[#9B9EA4]">Role details, permissions, and assigned users</p>
                </div>
                
                <div class="mt-4 sm:mt-0 flex space-x-2">
                    @can('update', $role)
                    @if($role->name !== 'developer' || auth()->user()->hasRole('developer'))
                    <flux:button 
                        wire:navigate 
                        href="{{ route('roles.edit', $role) }}" 
                        variant="primary"
                    >
                        <flux:icon.pencil class="w-4 h-4 mr-2" />
                        Edit Role
                    </flux:button>
                    @endif
                    @endcan
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <flux:icon.users class="w-6 h-6 text-blue-600" />
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-[#9B9EA4]">Total Users</h3>
                        <p class="text-2xl font-bold text-[#231F20]">{{ $stats['total_users'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <flux:icon.check-circle class="w-6 h-6 text-green-600" />
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-[#9B9EA4]">Active Users</h3>
                        <p class="text-2xl font-bold text-[#231F20]">{{ $stats['active_users'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <flux:icon.key class="w-6 h-6 text-purple-600" />
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-[#9B9EA4]">Permissions</h3>
                        <p class="text-2xl font-bold text-[#231F20]">{{ $stats['total_permissions'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-gray-100 rounded-lg">
                        <flux:icon.calendar class="w-6 h-6 text-gray-600" />
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-[#9B9EA4]">Created</h3>
                        <p class="text-sm font-bold text-[#231F20]">{{ $stats['created_date']->format('M j, Y') }}</p>
                        <p class="text-xs text-[#9B9EA4]">{{ $stats['created_date']->diffForHumans() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Role Permissions -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 overflow-hidden">
                    <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                        <h3 class="text-lg font-medium text-[#231F20]">Permissions ({{ $permissions->count() }})</h3>
                        <p class="mt-1 text-sm text-[#9B9EA4]">Capabilities granted to this role</p>
                    </div>
                    
                    <div class="p-6">
                        @if($permissions->count() > 0)
                        <div class="space-y-3">
                            @php
                                $groupedPermissions = $permissions->groupBy(function($permission) {
                                    return explode('_', $permission->name)[0];
                                });
                            @endphp

                            @foreach($groupedPermissions as $group => $groupPermissions)
                            <div>
                                <h4 class="text-sm font-medium text-[#231F20] mb-2 capitalize">
                                    {{ str_replace('_', ' ', $group) }}
                                </h4>
                                <div class="space-y-1 ml-2">
                                    @foreach($groupPermissions as $permission)
                                    <div class="flex items-center space-x-2">
                                        <flux:icon.check class="w-3 h-3 text-green-500" />
                                        <span class="text-sm text-[#9B9EA4]">{{ $permission->name }}</span>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-8">
                            <flux:icon.key class="w-12 h-12 mx-auto text-[#9B9EA4] opacity-50 mb-4" />
                            <p class="text-[#9B9EA4]">No permissions assigned to this role</p>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="mt-8 bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 overflow-hidden">
                    <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                        <h3 class="text-lg font-medium text-[#231F20]">Recent Activities</h3>
                        <p class="mt-1 text-sm text-[#9B9EA4]">Recent actions related to this role</p>
                    </div>
                    
                    <div class="p-6">
                        @if($recentActivities->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentActivities as $activity)
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                        <flux:icon.clock class="w-3 h-3 text-blue-600" />
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-[#231F20]">
                                        <span class="font-medium">{{ $activity->user->name ?? 'System' }}</span>
                                        {{ str_replace('_', ' ', $activity->action) }}
                                    </p>
                                    <p class="text-xs text-[#9B9EA4]">{{ $activity->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-8">
                            <flux:icon.clock class="w-12 h-12 mx-auto text-[#9B9EA4] opacity-50 mb-4" />
                            <p class="text-[#9B9EA4]">No recent activities</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Users with this Role -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 overflow-hidden">
                    <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-[#231F20]">Users ({{ $stats['total_users'] }})</h3>
                                <p class="mt-1 text-sm text-[#9B9EA4]">Users assigned to this role</p>
                            </div>
                        </div>
                    </div>

                    <!-- Search Users -->
                    <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                        <flux:input
                            wire:model.live="userSearch"
                            placeholder="Search users by name or email..."
                            class="w-full"
                        >
                            <flux:icon.magnifying-glass slot="leading" class="w-4 h-4" />
                        </flux:input>
                    </div>

                    <!-- Users List -->
                    <div class="divide-y divide-[#9B9EA4]/20">
                        @forelse($users as $user)
                        <div class="px-6 py-4 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span class="text-sm font-medium text-blue-600">
                                                {{ strtoupper(substr($user->name, 0, 2)) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center space-x-2">
                                            <p class="text-sm font-medium text-[#231F20] truncate">{{ $user->name }}</p>
                                            @if($user->hasRole('developer'))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Developer
                                            </span>
                                            @endif
                                            @if($user->is_banned)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Banned
                                            </span>
                                            @endif
                                        </div>
                                        <p class="text-sm text-[#9B9EA4] truncate">{{ $user->email }}</p>
                                        <div class="flex items-center space-x-4 mt-1">
                                            <span class="text-xs text-[#9B9EA4]">
                                                Joined {{ $user->created_at->format('M j, Y') }}
                                            </span>
                                            @if($user->email_verified_at)
                                            <span class="inline-flex items-center text-xs text-green-600">
                                                <flux:icon.check-circle class="w-3 h-3 mr-1" />
                                                Verified
                                            </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <flux:button 
                                        wire:navigate 
                                        href="{{ route('users.show', $user) }}" 
                                        variant="ghost" 
                                        size="sm"
                                    >
                                        <flux:icon.eye class="w-4 h-4" />
                                    </flux:button>
                                    
                                    @can('update', $role)
                                    @if($user->id !== auth()->id() && ($role->name !== 'developer' || auth()->user()->hasRole('developer')))
                                    <flux:button 
                                        wire:click="removeUserFromRole({{ $user->id }})" 
                                        variant="ghost" 
                                        size="sm"
                                        class="text-red-600 hover:text-red-700"
                                        wire:confirm="Are you sure you want to remove {{ $user->name }} from the {{ $role->name }} role?"
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
                            <div class="text-[#9B9EA4]">
                                <flux:icon.users class="w-12 h-12 mx-auto mb-4 opacity-50" />
                                <p>No users found with this role.</p>
                                @if($userSearch)
                                <p class="text-sm mt-2">Try adjusting your search terms.</p>
                                @endif
                            </div>
                        </div>
                        @endforelse
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
    </div>
</div>
