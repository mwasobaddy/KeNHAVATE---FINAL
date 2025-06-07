<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

new #[Layout('components.layouts.app', title: 'Role Management')] class extends Component {
    use WithPagination;

    public $search = '';

    public function mount()
    {
        // Check if user has permission to view roles
        $this->authorize('viewAny', Role::class);
    }

    public function with()
    {
        $query = Role::query();
        
        // Hide developer role from non-developers for security
        if (!auth()->user()->hasRole('developer')) {
            $query->where('name', '!=', 'developer');
        }

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        $roles = $query->withCount('users')
            ->orderBy('name')
            ->paginate(10);

        $totalRoles = Role::count();
        $totalAssignedRoles = Role::has('users')->count();
        $availablePermissions = Permission::orderBy('name')->get();

        return [
            'roles' => $roles,
            'totalRoles' => $totalRoles,
            'totalAssignedRoles' => $totalAssignedRoles,
            'availablePermissions' => $availablePermissions
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
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
            <h1 class="text-4xl font-bold text-[#231F20] dark:text-white mb-4">
                Role Management
            </h1>
            <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">
                Manage system roles and permissions for the KeNHAVATE Innovation Portal
            </p>
        </div>

        {{-- Actions and Search Section --}}
        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
            <div class="flex flex-col lg:flex-row gap-4 items-center justify-between">
                {{-- Search Input --}}
                <div class="flex-1 max-w-md">
                    <flux:input
                        wire:model.live="search"
                        placeholder="Search roles..."
                        class="bg-white/90 dark:bg-zinc-700/90 backdrop-blur-sm border border-[#9B9EA4]/30 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <flux:icon.magnifying-glass slot="leading" class="w-4 h-4 text-[#9B9EA4]" />
                    </flux:input>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center space-x-4">
                    @can('create', \Spatie\Permission\Models\Role::class)
                    <flux:button 
                        wire:navigate 
                        href="{{ route('roles.create') }}" 
                        variant="primary"
                        class="bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600 text-white px-6 py-2.5 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all duration-300"
                    >
                        <flux:icon.plus class="w-4 h-4 mr-2" />
                        Create Role
                    </flux:button>
                    @endcan
                </div>
            </div>
        </div>

        {{-- Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Total Roles Card --}}
            <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                <div class="flex items-center space-x-4">
                    <div class="w-14 h-14 bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                        <flux:icon.shield-check class="w-7 h-7 text-white" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-[#231F20] dark:text-white">Total Roles</h3>
                        <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $totalRoles }}</p>
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">System-wide roles</p>
                    </div>
                </div>
            </div>

            {{-- Assigned Roles Card --}}
            <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                <div class="flex items-center space-x-4">
                    <div class="w-14 h-14 bg-gradient-to-r from-green-500 to-green-600 rounded-xl flex items-center justify-center shadow-lg">
                        <flux:icon.user-group class="w-7 h-7 text-white" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-[#231F20] dark:text-white">Assigned Roles</h3>
                        <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ $totalAssignedRoles }}</p>
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">Roles with users</p>
                    </div>
                </div>
            </div>

            {{-- Total Permissions Card --}}
            <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
                <div class="flex items-center space-x-4">
                    <div class="w-14 h-14 bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                        <flux:icon.key class="w-7 h-7 text-white" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-[#231F20] dark:text-white">Permissions</h3>
                        <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">{{ $availablePermissions->count() }}</p>
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">Available permissions</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Roles Table --}}
        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-8 py-6 border-b border-white/20 dark:border-zinc-700/50">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-indigo-500 to-indigo-600 rounded-xl flex items-center justify-center">
                        <flux:icon.rectangle-stack class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-[#231F20] dark:text-white">System Roles</h3>
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">Manage roles and their permissions</p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm">
                        <tr>
                            <th class="px-8 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-white">
                                Role Details
                            </th>
                            <th class="px-8 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-white">
                                Users
                            </th>
                            <th class="px-8 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-white">
                                Permissions
                            </th>
                            <th class="px-8 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-white">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/20 dark:divide-zinc-700/50">
                        @forelse($roles as $role)
                        <tr class="hover:bg-white/30 dark:hover:bg-zinc-700/30 transition-all duration-200">
                            <td class="px-8 py-6">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-500 rounded-xl flex items-center justify-center shadow-lg">
                                        <flux:icon.shield-check class="w-6 h-6 text-white" />
                                    </div>
                                    <div>
                                        <div class="text-lg font-semibold text-[#231F20] dark:text-white">
                                            {{ ucwords(str_replace('-', ' ', $role->name)) }}
                                        </div>
                                        @if($role->name === 'developer')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100/70 dark:bg-red-900/50 text-red-800 dark:text-red-200 border border-red-200/50 dark:border-red-700/50">
                                            <flux:icon.exclamation-triangle class="w-3 h-3 mr-1" />
                                            System Role
                                        </span>
                                        @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100/70 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200 border border-blue-200/50 dark:border-blue-700/50">
                                            <flux:icon.check-circle class="w-3 h-3 mr-1" />
                                            Custom Role
                                        </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="flex items-center space-x-2">
                                    <div class="w-8 h-8 bg-green-100 dark:bg-green-900/50 rounded-lg flex items-center justify-center">
                                        <flux:icon.users class="w-4 h-4 text-green-600 dark:text-green-400" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-[#231F20] dark:text-white">{{ $role->users_count }}</div>
                                        <div class="text-xs text-[#9B9EA4] dark:text-zinc-400">Assigned users</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="flex items-center space-x-2">
                                    <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900/50 rounded-lg flex items-center justify-center">
                                        <flux:icon.key class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-[#231F20] dark:text-white">{{ $role->permissions->count() }}</div>
                                        <div class="text-xs text-[#9B9EA4] dark:text-zinc-400">Permissions</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="flex items-center space-x-2">
                                    {{-- View Role Button --}}
                                    <flux:button 
                                        wire:navigate 
                                        href="{{ route('roles.show', $role) }}" 
                                        variant="ghost" 
                                        size="sm"
                                        class="bg-white/70 dark:bg-zinc-700/70 backdrop-blur-sm hover:bg-white/90 dark:hover:bg-zinc-600/70 text-[#231F20] dark:text-white border border-white/30 dark:border-zinc-600/30 rounded-lg transition-all duration-300"
                                    >
                                        <flux:icon.eye class="w-4 h-4" />
                                    </flux:button>
                                    
                                    {{-- Edit Role Button --}}
                                    @can('update', $role)
                                    @if($role->name !== 'developer' || auth()->user()->hasRole('developer'))
                                    <flux:button 
                                        wire:navigate 
                                        href="{{ route('roles.edit', $role) }}" 
                                        variant="ghost" 
                                        size="sm"
                                        class="bg-white/70 dark:bg-zinc-700/70 backdrop-blur-sm hover:bg-white/90 dark:hover:bg-zinc-600/70 text-blue-600 dark:text-blue-400 border border-white/30 dark:border-zinc-600/30 rounded-lg transition-all duration-300"
                                    >
                                        <flux:icon.pencil class="w-4 h-4" />
                                    </flux:button>
                                    @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-8 py-12 text-center">
                                <div class="flex flex-col items-center space-y-4">
                                    <div class="w-20 h-20 bg-gradient-to-r from-gray-400 to-gray-500 rounded-full flex items-center justify-center opacity-50">
                                        <flux:icon.shield-exclamation class="w-10 h-10 text-white" />
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-[#231F20] dark:text-white mb-2">No roles found</h3>
                                        <p class="text-[#9B9EA4] dark:text-zinc-400">
                                            @if($search)
                                                No roles match your search criteria. Try adjusting your search terms.
                                            @else
                                                No roles have been created yet. Create your first role to get started.
                                            @endif
                                        </p>
                                    </div>
                                    @can('create', \Spatie\Permission\Models\Role::class)
                                    @if(!$search)
                                    <flux:button 
                                        wire:navigate 
                                        href="{{ route('roles.create') }}" 
                                        variant="primary"
                                        class="bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600 text-white px-6 py-2.5 rounded-xl font-semibold"
                                    >
                                        <flux:icon.plus class="w-4 h-4 mr-2" />
                                        Create First Role
                                    </flux:button>
                                    @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($roles->hasPages())
            <div class="px-8 py-6 border-t border-white/20 dark:border-zinc-700/50 bg-white/30 dark:bg-zinc-700/30 backdrop-blur-sm">
                {{ $roles->links() }}
            </div>
            @endif
        </div>

        {{-- Success/Error Messages --}}
        @if(session('success'))
            <div class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-xl shadow-xl z-50" 
                 x-data="{ show: true }" 
                 x-show="show" 
                 x-transition 
                 x-init="setTimeout(() => show = false, 5000)">
                <div class="flex items-center space-x-2">
                    <flux:icon.check-circle class="w-5 h-5" />
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-xl shadow-xl z-50" 
                 x-data="{ show: true }" 
                 x-show="show" 
                 x-transition 
                 x-init="setTimeout(() => show = false, 5000)">
                <div class="flex items-center space-x-2">
                    <flux:icon.exclamation-circle class="w-5 h-5" />
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif
    </div>
</div>
