<?php

use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Services\AuditService;

new #[Layout('components.layouts.app', title: 'Edit Role')] class extends Component {
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
                'role_update',
                'Role',
                $this->role->id,
                array_merge($oldValues, ['permissions' => $oldPermissions]),
                array_merge($newValues, ['permissions' => $newPermissions])
            );

            session()->flash('success', 'Role updated successfully!');
            
            return redirect()->route('roles.show', $this->role);

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update role. Please try again.');
        }
    }

    public function cancel()
    {
        return redirect()->route('roles.show', $this->role);
    }
}; ?>

<div class="bg-[#F8EBD5] min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center space-x-4 mb-4">
                <flux:button 
                    wire:navigate 
                    href="{{ route('roles.show', $role) }}" 
                    variant="ghost" 
                    size="sm"
                >
                    <flux:icon.arrow-left class="w-4 h-4 mr-2" />
                    Back to Role Details
                </flux:button>
            </div>
            
            <div>
                <h1 class="text-3xl font-bold text-[#231F20]">Edit Role: {{ ucfirst($role->name) }}</h1>
                <p class="mt-2 text-[#9B9EA4]">Modify role details and permissions</p>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 overflow-hidden">
            <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                <h3 class="text-lg font-medium text-[#231F20]">Role Information</h3>
                <p class="mt-1 text-sm text-[#9B9EA4]">Update the role details and modify permissions</p>
            </div>

            <form wire:submit="save" class="p-6 space-y-6">
                <!-- Role Name -->
                <div>
                    <flux:input 
                        wire:model="name" 
                        label="Role Name" 
                        placeholder="Enter role name"
                        required
                        autocomplete="off"
                        :disabled="$role->name === 'developer'"
                    />
                    @if($role->name === 'developer')
                    <p class="mt-1 text-sm text-amber-600">
                        <flux:icon.exclamation-triangle class="w-4 h-4 inline mr-1" />
                        System role names cannot be changed
                    </p>
                    @else
                    <p class="mt-1 text-sm text-[#9B9EA4]">
                        Use lowercase with hyphens for multi-word role names
                    </p>
                    @endif
                </div>

                <!-- Current Role Info -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-blue-800 mb-2">Current Role Information</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-blue-700 font-medium">Users:</span>
                            <span class="text-blue-600">{{ $role->users()->count() }}</span>
                        </div>
                        <div>
                            <span class="text-blue-700 font-medium">Permissions:</span>
                            <span class="text-blue-600">{{ $role->permissions()->count() }}</span>
                        </div>
                        <div>
                            <span class="text-blue-700 font-medium">Created:</span>
                            <span class="text-blue-600">{{ $role->created_at->format('M j, Y') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Permissions Selection -->
                <div>
                    <div class="field-group">
                        <div class="field-label">Permissions</div>
                        <div class="field-description">
                            Select the permissions that users with this role should have
                        </div>
                        
                        <div class="mt-4 space-y-4">
                            @php
                                $groupedPermissions = $availablePermissions->groupBy(function($permission) {
                                    return explode('_', $permission->name)[0];
                                });
                            @endphp

                            @foreach($groupedPermissions as $group => $permissions)
                            <div class="border border-[#9B9EA4]/20 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-medium text-[#231F20] capitalize">
                                        {{ str_replace('_', ' ', $group) }} Permissions
                                    </h4>
                                    <div class="text-xs text-[#9B9EA4]">
                                        {{ $permissions->whereIn('id', $selectedPermissions)->count() }}/{{ $permissions->count() }} selected
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                                    @foreach($permissions as $permission)
                                    <label class="flex items-center space-x-2 text-sm">
                                        <input 
                                            type="checkbox" 
                                            wire:model="selectedPermissions" 
                                            value="{{ $permission->id }}"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                        >
                                        <span class="text-[#231F20]">{{ $permission->name }}</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Permission Changes Summary -->
                @if($role->permissions->pluck('id')->toArray() !== $selectedPermissions)
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-amber-800 mb-2">
                        <flux:icon.exclamation-triangle class="w-4 h-4 inline mr-1" />
                        Permission Changes Detected
                    </h4>
                    
                    @php
                        $currentPermissionIds = $role->permissions->pluck('id')->toArray();
                        $addedPermissions = array_diff($selectedPermissions, $currentPermissionIds);
                        $removedPermissions = array_diff($currentPermissionIds, $selectedPermissions);
                    @endphp
                    
                    @if(!empty($addedPermissions))
                    <div class="mb-2">
                        <span class="text-sm text-green-700 font-medium">Adding:</span>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach($availablePermissions->whereIn('id', $addedPermissions) as $permission)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                +{{ $permission->name }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    
                    @if(!empty($removedPermissions))
                    <div>
                        <span class="text-sm text-red-700 font-medium">Removing:</span>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach($availablePermissions->whereIn('id', $removedPermissions) as $permission)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                -{{ $permission->name }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-[#9B9EA4]/20">
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto">
                        <flux:icon.check class="w-4 h-4 mr-2" />
                        Update Role
                    </flux:button>
                    
                    <flux:button 
                        type="button" 
                        wire:click="cancel" 
                        variant="ghost" 
                        class="w-full sm:w-auto"
                    >
                        Cancel
                    </flux:button>
                </div>
            </form>
        </div>

        <!-- Users with this Role -->
        @if($role->users()->count() > 0)
        <div class="mt-8 bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 overflow-hidden">
            <div class="px-6 py-4 border-b border-[#9B9EA4]/20">
                <h3 class="text-lg font-medium text-[#231F20]">Users with this Role ({{ $role->users()->count() }})</h3>
                <p class="mt-1 text-sm text-[#9B9EA4]">Changes will affect these users</p>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($role->users()->limit(9)->get() as $user)
                    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <flux:icon.user class="w-4 h-4 text-blue-600" />
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-[#231F20] truncate">{{ $user->name }}</p>
                            <p class="text-sm text-[#9B9EA4] truncate">{{ $user->email }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                
                @if($role->users()->count() > 9)
                <div class="mt-4 text-center">
                    <flux:button 
                        wire:navigate 
                        href="{{ route('roles.show', $role) }}" 
                        variant="ghost" 
                        size="sm"
                    >
                        View all {{ $role->users()->count() }} users
                    </flux:button>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
