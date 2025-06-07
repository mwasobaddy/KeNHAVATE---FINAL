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
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-gradient-to-r from-green-500/20 to-blue-500/20 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-gradient-to-r from-yellow-500/20 to-orange-500/20 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Header Section --}}
        <div class="text-center mb-8">
            <div class="flex items-center justify-center space-x-4 mb-4">
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
                                                Joined {{ $user->created_at->format('M j, Y') }}
                                            </span>
                                            @if($user->email_verified_at)
                                            <span class="inline-flex items-center text-xs text-green-600 dark:text-green-400">
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
                                        class="bg-white/50 dark:bg-zinc-700/50 hover:bg-white/70 dark:hover:bg-zinc-600/50 backdrop-blur-sm"
                                    >
                                        <flux:icon.eye class="w-4 h-4" />
                                    </flux:button>
                                    
                                    @can('update', $role)
                                    @if($user->id !== auth()->id() && ($role->name !== 'developer' || auth()->user()->hasRole('developer')))
                                    <flux:button 
                                        wire:click="removeUserFromRole({{ $user->id }})" 
                                        variant="ghost" 
                                        size="sm"
                                        class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 bg-white/50 dark:bg-zinc-700/50 hover:bg-red-50 dark:hover:bg-red-900/20 backdrop-blur-sm"
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
</div>
