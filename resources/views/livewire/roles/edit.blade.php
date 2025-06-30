<?php

use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Services\AuditService;
use Livewire\Attributes\{Layout, Title};

new #[Layout('components.layouts.app')] #[Title('Edit Role')] class extends Component
{
    public Role $role;
    public $name = '';
    public $selectedPermissions = [];

    protected $rules = [
        'name' => 'required|string|max:255',
        'selectedPermissions' => 'array',
        'selectedPermissions.*' => 'exists:permissions,id'
    ];

    public function mount(Role $role)
    {
        // Check if user has permission to edit roles
        $this->authorize('update', $role);
        
        // Prevent editing developer role by non-developers
        if ($role->name === 'developer' && !auth()->user()->hasRole('developer')) {
            abort(403, 'You cannot edit the developer role.');
        }

        $this->role = $role;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('id')->toArray();
    }

    public function with()
    {
        $availablePermissions = Permission::orderBy('name')->get();

        return [
            'availablePermissions' => $availablePermissions
        ];
    }

    public function getRules()
    {
        return [
            'name' => 'required|string|max:255|unique:roles,name,' . $this->role->id,
            'selectedPermissions' => 'array',
            'selectedPermissions.*' => 'exists:permissions,id'
        ];
    }

    public function save()
    {
        $this->authorize('update', $this->role);
        
        $this->validate($this->getRules());

        try {
            $oldValues = $this->role->toArray();
            $oldPermissions = $this->role->permissions->pluck('name')->toArray();

            $this->role->update(['name' => $this->name]);
            
            if (!empty($this->selectedPermissions)) {
                $permissions = Permission::whereIn('id', $this->selectedPermissions)->get();
                $this->role->syncPermissions($permissions);
            } else {
                $this->role->syncPermissions([]);
            }

            $newValues = $this->role->fresh()->toArray();
            $newPermissions = $this->role->permissions->pluck('name')->toArray();

            // Log the action
            app(AuditService::class)->log(
                'role_updated',
                'Role',
                $this->role->id,
                array_merge($oldValues, ['permissions' => $oldPermissions]),
                array_merge($newValues, ['permissions' => $newPermissions])
            );

            session()->flash('success', 'Role updated successfully!');
            
            return redirect()->route('roles.show', $this->role);

        } catch (\Exception $e) {
            // Log the error in the laravel log
            \Log::error('Failed to update role: ' . $e->getMessage(), [
                'role_id' => $this->role->id,
                'user_id' => auth()->id(),
                'selected_permissions' => $this->selectedPermissions,
                'error' => $e->getMessage()
            ]);
            session()->flash('error', 'Failed to update role. Please try again.');
        }
    }

    public function cancel()
    {
        return redirect()->route('roles.show', $this->role);
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
            <div class="flex items-center justify-start space-x-4 mb-6">
                <flux:button 
                    wire:navigate 
                    href="{{ route('roles.show', $role) }}" 
                    variant="ghost" 
                    size="sm"
                    class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 hover:bg-white/90 dark:hover:bg-zinc-700/90"
                >
                    <flux:icon.arrow-left class="w-4 h-4 mr-2" />
                    Back to Role Details
                </flux:button>
            </div>
            
            <h1 class="text-4xl font-bold text-[#231F20] dark:text-white mb-4">
                Edit Role: {{ ucfirst($role->name) }}
            </h1>
            <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">
                Modify role details and permissions with real-time preview
            </p>
        </div>

        {{-- Role Information Card --}}
        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-xl font-bold text-[#231F20] dark:text-white">Role Information</h3>
                    <p class="text-[#9B9EA4] dark:text-zinc-400">Update role details and modify permissions</p>
                </div>
                <div class="flex items-center space-x-4 text-sm">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $role->users()->count() }}</div>
                        <p class="text-[#9B9EA4]">Users</p>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $role->permissions()->count() }}</div>
                        <p class="text-[#9B9EA4]">Permissions</p>
                    </div>
                </div>
            </div>

            <form wire:submit="save" class="space-y-8">
                {{-- Role Name Section --}}
                <div class="bg-white/50 dark:bg-zinc-700/50 rounded-xl p-6 border border-white/30 dark:border-zinc-600/30">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center">
                            <flux:icon.identification class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <h4 class="text-lg font-semibold text-[#231F20] dark:text-white">Role Name</h4>
                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">Define the role identifier</p>
                        </div>
                    </div>

                    <flux:input 
                        wire:model.live="name" 
                        label="Role Name" 
                        placeholder="Enter role name"
                        required
                        autocomplete="off"
                        :disabled="$role->name === 'developer'"
                        class="bg-white/90 dark:bg-zinc-700/90 backdrop-blur-sm border border-[#9B9EA4]/30 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                    />
                    
                    @if($role->name === 'developer')
                        <div class="mt-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                            <div class="flex items-center">
                                <flux:icon.exclamation-triangle class="w-5 h-5 text-amber-600 dark:text-amber-400 mr-2" />
                                <span class="text-sm text-amber-800 dark:text-amber-200">System role names cannot be changed</span>
                            </div>
                        </div>
                    @else
                        <p class="mt-2 text-sm text-[#9B9EA4] dark:text-zinc-400">
                            Use lowercase with hyphens for multi-word role names
                        </p>
                    @endif
                </div>

                {{-- Permissions Section --}}
                <div class="bg-white/50 dark:bg-zinc-700/50 rounded-xl p-6 border border-white/30 dark:border-zinc-600/30">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-purple-500 rounded-xl flex items-center justify-center">
                                <flux:icon.shield-check class="w-5 h-5 text-white" />
                            </div>
                            <div>
                                <h4 class="text-lg font-semibold text-[#231F20] dark:text-white">Permissions</h4>
                                <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">Configure role capabilities</p>
                            </div>
                        </div>
                        <div class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                            {{ count($selectedPermissions) }}/{{ $availablePermissions->count() }} selected
                        </div>
                    </div>
                    
                    <div class="space-y-6">
                        @php
                            $groupedPermissions = $availablePermissions->groupBy(function($permission) {
                                return explode('_', $permission->name)[0];
                            });
                        @endphp

                        @foreach($groupedPermissions as $group => $permissions)
                            <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-sm border border-white/20 dark:border-zinc-600/20 rounded-xl p-5 hover:shadow-lg transition-all duration-300">
                                <div class="flex items-center justify-between mb-4">
                                    <h5 class="text-base font-semibold text-[#231F20] dark:text-white capitalize flex items-center">
                                        <div class="w-2 h-2 bg-blue-500 rounded-full mr-3"></div>
                                        {{ str_replace('_', ' ', $group) }} Permissions
                                    </h5>
                                    <div class="flex items-center space-x-2">
                                        <div class="text-xs text-[#9B9EA4] dark:text-zinc-400">
                                            {{ $permissions->whereIn('id', $selectedPermissions)->count() }}/{{ $permissions->count() }}
                                        </div>
                                        <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                            @php $percentage = $permissions->count() > 0 ? ($permissions->whereIn('id', $selectedPermissions)->count() / $permissions->count()) * 100 : 0; @endphp
                                            <div class="bg-blue-500 h-1.5 rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                                    @foreach($permissions as $permission)
                                        <label class="flex items-center space-x-3 p-2 rounded-lg hover:bg-white/50 dark:hover:bg-zinc-700/50 transition-colors duration-200 cursor-pointer group">
                                            <input 
                                                type="checkbox" 
                                                wire:model.live="selectedPermissions" 
                                                value="{{ $permission->id }}"
                                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                            >
                                            <span class="text-sm text-[#231F20] dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors duration-200">
                                                {{ str_replace('_', ' ', $permission->name) }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Permission Changes Summary --}}
                @if($role->permissions->pluck('id')->toArray() !== $selectedPermissions)
                    <div class="bg-amber-50/80 dark:bg-amber-900/20 backdrop-blur-sm border border-amber-200/50 dark:border-amber-800/50 rounded-xl p-6 shadow-lg">
                        <h4 class="text-lg font-semibold text-amber-800 dark:text-amber-200 mb-4 flex items-center">
                            <flux:icon.exclamation-triangle class="w-5 h-5 mr-2" />
                            Permission Changes Detected
                        </h4>
                        
                        @php
                            $currentPermissionIds = $role->permissions->pluck('id')->toArray();
                            $addedPermissions = array_diff($selectedPermissions, $currentPermissionIds);
                            $removedPermissions = array_diff($currentPermissionIds, $selectedPermissions);
                        @endphp
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            @if(!empty($addedPermissions))
                                <div>
                                    <span class="text-sm font-semibold text-green-700 dark:text-green-300 flex items-center mb-3">
                                        <flux:icon.plus class="w-4 h-4 mr-1" />
                                        Adding ({{ count($addedPermissions) }})
                                    </span>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($availablePermissions->whereIn('id', $addedPermissions) as $permission)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800">
                                                +{{ str_replace('_', ' ', $permission->name) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            
                            @if(!empty($removedPermissions))
                                <div>
                                    <span class="text-sm font-semibold text-red-700 dark:text-red-300 flex items-center mb-3">
                                        <flux:icon.minus class="w-4 h-4 mr-1" />
                                        Removing ({{ count($removedPermissions) }})
                                    </span>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($availablePermissions->whereIn('id', $removedPermissions) as $permission)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800">
                                                -{{ str_replace('_', ' ', $permission->name) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Action Buttons --}}
                <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-white/20 dark:border-zinc-600/20">
                    <flux:button 
                        icon="check"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        type="submit" 
                        variant="primary" 
                        class="w-full sm:w-auto bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 shadow-lg hover:shadow-xl transition-all duration-300"
                    >
                        Update Role
                    </flux:button>
                    
                    <flux:button 
                        type="button" 
                        wire:click="cancel" 
                        variant="ghost" 
                        class="w-full sm:w-auto bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 hover:bg-white/90 dark:hover:bg-zinc-700/90"
                    >
                        Cancel
                    </flux:button>
                </div>
            </form>
        </div>

        {{-- Users with this Role --}}
        @if($role->users()->count() > 0)
            <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center">
                            <flux:icon.users class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-[#231F20] dark:text-white">Affected Users</h3>
                            <p class="text-[#9B9EA4] dark:text-zinc-400">{{ $role->users()->count() }} users will be affected by changes</p>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ $role->users()->count() }}
                    </div>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($role->users()->limit(9)->get() as $user)
                        <div class="flex items-center space-x-3 p-4 bg-white/50 dark:bg-zinc-700/50 rounded-xl border border-white/30 dark:border-zinc-600/30 hover:shadow-lg transition-all duration-300 group">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                    <flux:icon.user class="w-5 h-5 text-white" />
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-[#231F20] dark:text-white truncate">{{ $user->name }}</p>
                                <p class="text-xs text-[#9B9EA4] dark:text-zinc-400 truncate">{{ $user->email }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                
                @if($role->users()->count() > 9)
                    <div class="mt-6 text-center">
                        <flux:button 
                            wire:navigate 
                            href="{{ route('roles.show', $role) }}" 
                            variant="ghost" 
                            size="sm"
                            class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 hover:bg-white/90 dark:hover:bg-zinc-700/90"
                        >
                            View all {{ $role->users()->count() }} users
                            <flux:icon.arrow-right class="w-4 h-4 ml-2" />
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif

        {{-- Success/Error Messages --}}
        @if(session('success'))
            <div class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-xl shadow-xl z-50" 
                 x-data="{ show: true }" 
                 x-show="show" 
                 x-transition 
                 x-init="setTimeout(() => show = false, 5000)">
                <div class="flex items-center">
                    <flux:icon.check class="w-5 h-5 mr-2" />
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-xl shadow-xl z-50" 
                 x-data="{ show: true }" 
                 x-show="show" 
                 x-transition 
                 x-init="setTimeout(() => show = false, 5000)">
                <div class="flex items-center">
                    <flux:icon.exclamation-triangle class="w-5 h-5 mr-2" />
                    {{ session('error') }}
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Alpine.js for enhanced interactivity --}}
<script>
    // Add smooth transitions and hover effects
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-save draft functionality could be added here
        // Real-time permission validation could be implemented
        
        // Add visual feedback for permission changes
        const checkboxes = document.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const label = this.closest('label');
                if (this.checked) {
                    label.classList.add('bg-blue-50', 'dark:bg-blue-900/20', 'border-blue-200', 'dark:border-blue-800');
                } else {
                    label.classList.remove('bg-blue-50', 'dark:bg-blue-900/20', 'border-blue-200', 'dark:border-blue-800');
                }
            });
        });
    });
</script>
