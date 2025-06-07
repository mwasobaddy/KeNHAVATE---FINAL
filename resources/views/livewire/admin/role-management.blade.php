<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    use WithPagination;

    // Role Management Properties
    public $search = '';
    public $showCreateRoleModal = false;
    public $showEditRoleModal = false;
    public $showDeleteRoleModal = false;
    public $showPermissionsModal = false;
    public $editingRole = null;
    public $deletingRole = null;
    public $managingPermissionsRole = null;

    // Create/Edit Role Form Properties
    public $role_name = '';
    public $role_display_name = '';
    public $role_description = '';
    public $role_permissions = [];

    // Security confirmation
    public $password_confirmation = '';
    public $delete_confirmation = '';

    protected $listeners = ['roleCreated', 'roleUpdated', 'roleDeleted'];

    public function mount()
    {
        // Verify user has permission to manage roles
        if (!auth()->user()->can('manage_roles')) {
            abort(403, 'Unauthorized access to role management.');
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function getRoles()
    {
        return Role::query()
            ->when($this->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('users')
            ->orderBy('name')
            ->paginate(10);
    }

    public function getPermissions()
    {
        return Permission::all()->groupBy(function ($permission) {
            $parts = explode('_', $permission->name);
            return $parts[count($parts) - 1]; // Group by last word
        });
    }

    public function openCreateRoleModal()
    {
        $this->resetValidation();
        $this->resetForm();
        $this->showCreateRoleModal = true;
    }

    public function createRole()
    {
        $this->validate([
            'role_name' => 'required|string|max:255|unique:roles,name',
            'role_display_name' => 'required|string|max:255',
            'role_description' => 'nullable|string|max:500',
            'role_permissions' => 'array',
            'role_permissions.*' => 'exists:permissions,name',
        ]);

        try {
            $role = Role::create([
                'name' => $this->role_name,
                'guard_name' => 'web',
            ]);

            // Assign permissions if selected
            if (!empty($this->role_permissions)) {
                $role->givePermissionTo($this->role_permissions);
            }

            // Create audit log
            auth()->user()->auditLogs()->create([
                'action' => 'role_creation',
                'entity_type' => 'Role',
                'entity_id' => $role->id,
                'new_values' => [
                    'name' => $role->name,
                    'permissions_count' => count($this->role_permissions),
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $this->showCreateRoleModal = false;
            $this->resetForm();
            session()->flash('message', 'Role created successfully!');
            $this->dispatch('role-created');

        } catch (\Exception $e) {
            $this->addError('role_name', 'Error creating role: ' . $e->getMessage());
        }
    }

    public function editRole(Role $role)
    {
        $this->resetValidation();
        $this->editingRole = $role;
        $this->role_name = $role->name;
        $this->role_display_name = ucwords(str_replace(['_', '-'], ' ', $role->name));
        $this->role_description = $role->description ?? '';
        $this->role_permissions = $role->permissions->pluck('name')->toArray();
        $this->showEditRoleModal = true;
    }

    public function updateRole()
    {
        $this->validate([
            'role_name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($this->editingRole->id)],
            'role_display_name' => 'required|string|max:255',
            'role_description' => 'nullable|string|max:500',
            'role_permissions' => 'array',
            'role_permissions.*' => 'exists:permissions,name',
        ]);

        try {
            $oldValues = [
                'name' => $this->editingRole->name,
                'permissions' => $this->editingRole->permissions->pluck('name')->toArray(),
            ];

            // Update role
            $this->editingRole->update([
                'name' => $this->role_name,
            ]);

            // Sync permissions
            $this->editingRole->syncPermissions($this->role_permissions);

            // Create audit log
            auth()->user()->auditLogs()->create([
                'action' => 'role_update',
                'entity_type' => 'Role',
                'entity_id' => $this->editingRole->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'name' => $this->role_name,
                    'permissions' => $this->role_permissions,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $this->showEditRoleModal = false;
            $this->resetForm();
            session()->flash('message', 'Role updated successfully!');
            $this->dispatch('role-updated');

        } catch (\Exception $e) {
            $this->addError('role_name', 'Error updating role: ' . $e->getMessage());
        }
    }

    public function confirmDeleteRole(Role $role)
    {
        // Prevent deletion of core system roles
        if (in_array($role->name, ['developer', 'administrator', 'user'])) {
            session()->flash('error', 'Core system roles cannot be deleted.');
            return;
        }

        $this->resetValidation();
        $this->deletingRole = $role;
        $this->password_confirmation = '';
        $this->delete_confirmation = '';
        $this->showDeleteRoleModal = true;
    }

    public function deleteRole()
    {
        $this->validate([
            'password_confirmation' => 'required',
            'delete_confirmation' => 'required|in:DELETE',
        ]);

        // Verify password
        if (!Hash::check($this->password_confirmation, auth()->user()->password)) {
            $this->addError('password_confirmation', 'Incorrect password.');
            return;
        }

        try {
            $roleName = $this->deletingRole->name;
            $usersCount = $this->deletingRole->users()->count();

            // Remove role from all users first
            if ($usersCount > 0) {
                $this->deletingRole->users()->each(function ($user) {
                    $user->removeRole($this->deletingRole);
                    // Ensure user has at least the 'user' role
                    if ($user->roles()->count() === 0) {
                        $user->assignRole('user');
                    }
                });
            }

            // Create audit log before deletion
            auth()->user()->auditLogs()->create([
                'action' => 'role_deletion',
                'entity_type' => 'Role',
                'entity_id' => $this->deletingRole->id,
                'old_values' => [
                    'name' => $roleName,
                    'users_affected' => $usersCount,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $this->deletingRole->delete();

            $this->showDeleteRoleModal = false;
            $this->resetForm();
            session()->flash('message', "Role '{$roleName}' deleted successfully. {$usersCount} users were reassigned to 'user' role.");
            $this->dispatch('role-deleted');

        } catch (\Exception $e) {
            $this->addError('delete_confirmation', 'Error deleting role: ' . $e->getMessage());
        }
    }

    public function managePermissions(Role $role)
    {
        $this->resetValidation();
        $this->managingPermissionsRole = $role;
        $this->role_permissions = $role->permissions->pluck('name')->toArray();
        $this->showPermissionsModal = true;
    }

    public function updatePermissions()
    {
        $this->validate([
            'role_permissions' => 'array',
            'role_permissions.*' => 'exists:permissions,name',
        ]);

        try {
            $oldPermissions = $this->managingPermissionsRole->permissions->pluck('name')->toArray();
            
            $this->managingPermissionsRole->syncPermissions($this->role_permissions);

            // Create audit log
            auth()->user()->auditLogs()->create([
                'action' => 'role_permissions_update',
                'entity_type' => 'Role',
                'entity_id' => $this->managingPermissionsRole->id,
                'old_values' => ['permissions' => $oldPermissions],
                'new_values' => ['permissions' => $this->role_permissions],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $this->showPermissionsModal = false;
            session()->flash('message', 'Role permissions updated successfully!');
            $this->dispatch('permissions-updated');

        } catch (\Exception $e) {
            $this->addError('role_permissions', 'Error updating permissions: ' . $e->getMessage());
        }
    }

    public function resetForm()
    {
        $this->role_name = '';
        $this->role_display_name = '';
        $this->role_description = '';
        $this->role_permissions = [];
        $this->password_confirmation = '';
        $this->delete_confirmation = '';
        $this->editingRole = null;
        $this->deletingRole = null;
        $this->managingPermissionsRole = null;
    }

    public function closeModal()
    {
        $this->resetForm();
        $this->resetValidation();
        $this->showCreateRoleModal = false;
        $this->showEditRoleModal = false;
        $this->showDeleteRoleModal = false;
        $this->showPermissionsModal = false;
    }
}; ?>

<x-layouts.app>
    <div class="max-w-7xl mx-auto p-6 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-[#231F20]">Role Management</h1>
                <p class="text-[#9B9EA4] mt-2">Manage system roles and permissions</p>
            </div>
            <flux:button icon="plus" wire:click="openCreateRoleModal" variant="filled" class="bg-[#FFF200] text-[#231F20] hover:bg-[#FFF200]/80">
                Create Role
            </flux:button>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="backdrop-blur-md bg-white/70 border border-white/20 rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-[#9B9EA4]">Total Roles</h3>
                        <p class="text-3xl font-bold text-[#231F20]">{{ Role::count() }}</p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <flux:icon.shield-check class="w-6 h-6 text-blue-600" />
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-md bg-white/70 border border-white/20 rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-[#9B9EA4]">Total Permissions</h3>
                        <p class="text-3xl font-bold text-[#231F20]">{{ Permission::count() }}</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-lg">
                        <flux:icon.key class="w-6 h-6 text-green-600" />
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-md bg-white/70 border border-white/20 rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-[#9B9EA4]">Active Users</h3>
                        <p class="text-3xl font-bold text-[#231F20]">{{ \App\Models\User::where('account_status', 'active')->count() }}</p>
                    </div>
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <flux:icon.users class="w-6 h-6 text-purple-600" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="backdrop-blur-md bg-white/70 border border-white/20 rounded-xl p-6 shadow-lg">
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <flux:input 
                        wire:model.live="search" 
                        placeholder="Search roles..." 
                        icon="magnifying-glass"
                        class="w-full"
                    />
                </div>
            </div>
        </div>

        <!-- Roles Table -->
        <div class="backdrop-blur-md bg-white/70 border border-white/20 rounded-xl shadow-lg overflow-hidden">
            @if (session()->has('message'))
                <flux:toast>{{ session('message') }}</flux:toast>
            @endif

            @if (session()->has('error'))
                <flux:toast variant="danger">{{ session('error') }}</flux:toast>
            @endif

            <div class="p-6">
                <h2 class="text-xl font-semibold text-[#231F20] mb-4">System Roles</h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-[#231F20] uppercase bg-[#F8EBD5]/50">
                            <tr>
                                <th class="px-6 py-3">Role Name</th>
                                <th class="px-6 py-3">Users Count</th>
                                <th class="px-6 py-3">Permissions</th>
                                <th class="px-6 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->getRoles() as $role)
                                <tr class="border-b border-[#9B9EA4]/20 hover:bg-[#F8EBD5]/20">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="p-2 bg-blue-100 rounded-lg">
                                                <flux:icon.shield-check class="w-4 h-4 text-blue-600" />
                                            </div>
                                            <div>
                                                <div class="font-semibold text-[#231F20]">{{ ucwords(str_replace(['_', '-'], ' ', $role->name)) }}</div>
                                                <div class="text-[#9B9EA4] text-sm">{{ $role->name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ $role->permissions->count() }} {{ Str::plural('permission', $role->permissions->count()) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-2">
                                            <flux:button size="sm" wire:click="managePermissions({{ $role->id }})" variant="ghost">
                                                <flux:icon.key class="w-4 h-4" />
                                                Permissions
                                            </flux:button>
                                            
                                            <flux:button size="sm" wire:click="editRole({{ $role->id }})" variant="ghost">
                                                <flux:icon.pencil class="w-4 h-4" />
                                                Edit
                                            </flux:button>
                                            
                                            @if (!in_array($role->name, ['developer', 'administrator', 'user']))
                                                <flux:button size="sm" wire:click="confirmDeleteRole({{ $role->id }})" variant="ghost" class="text-red-600 hover:text-red-800">
                                                    <flux:icon.trash class="w-4 h-4" />
                                                    Delete
                                                </flux:button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-[#9B9EA4]">
                                        <flux:icon.shield-exclamation class="w-12 h-12 mx-auto mb-4 text-[#9B9EA4]" />
                                        <p class="text-lg font-medium">No roles found</p>
                                        <p class="text-sm">Create your first role to get started.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $this->getRoles()->links() }}
                </div>
            </div>
        </div>

        <!-- Create Role Modal -->
        <flux:modal name="create-role" :show="$showCreateRoleModal" wire:model="showCreateRoleModal">
            <form wire:submit="createRole" class="space-y-6">
                <div>
                    <flux:heading size="lg">Create New Role</flux:heading>
                    <flux:subheading>Define a new role with specific permissions</flux:subheading>
                </div>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>Role Name</flux:label>
                        <flux:input wire:model="role_name" placeholder="e.g., content_manager" />
                        <flux:error name="role_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Display Name</flux:label>
                        <flux:input wire:model="role_display_name" placeholder="e.g., Content Manager" />
                        <flux:error name="role_display_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Description</flux:label>
                        <flux:textarea wire:model="role_description" placeholder="Brief description of this role's purpose..." />
                        <flux:error name="role_description" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Permissions</flux:label>
                        <div class="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto border border-[#9B9EA4]/20 rounded-lg p-4">
                            @foreach ($this->getPermissions() as $group => $permissions)
                                <div class="col-span-2">
                                    <h4 class="font-semibold text-[#231F20] mb-2">{{ ucwords($group) }}</h4>
                                </div>
                                @foreach ($permissions as $permission)
                                    <div class="flex items-center space-x-2">
                                        <flux:checkbox wire:model="role_permissions" value="{{ $permission->name }}" />
                                        <label class="text-sm text-[#231F20]">{{ str_replace('_', ' ', $permission->name) }}</label>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                        <flux:error name="role_permissions" />
                    </flux:field>
                </div>

                <div class="flex justify-end space-x-2">
                    <flux:button type="button" wire:click="closeModal" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="filled" class="bg-[#FFF200] text-[#231F20] hover:bg-[#FFF200]/80">Create Role</flux:button>
                </div>
            </form>
        </flux:modal>

        <!-- Edit Role Modal -->
        <flux:modal name="edit-role" :show="$showEditRoleModal" wire:model="showEditRoleModal">
            <form wire:submit="updateRole" class="space-y-6">
                <div>
                    <flux:heading size="lg">Edit Role</flux:heading>
                    <flux:subheading>Update role information and permissions</flux:subheading>
                </div>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>Role Name</flux:label>
                        <flux:input wire:model="role_name" />
                        <flux:error name="role_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Display Name</flux:label>
                        <flux:input wire:model="role_display_name" />
                        <flux:error name="role_display_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Description</flux:label>
                        <flux:textarea wire:model="role_description" />
                        <flux:error name="role_description" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Permissions</flux:label>
                        <div class="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto border border-[#9B9EA4]/20 rounded-lg p-4">
                            @foreach ($this->getPermissions() as $group => $permissions)
                                <div class="col-span-2">
                                    <h4 class="font-semibold text-[#231F20] mb-2">{{ ucwords($group) }}</h4>
                                </div>
                                @foreach ($permissions as $permission)
                                    <div class="flex items-center space-x-2">
                                        <flux:checkbox wire:model="role_permissions" value="{{ $permission->name }}" />
                                        <label class="text-sm text-[#231F20]">{{ str_replace('_', ' ', $permission->name) }}</label>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                        <flux:error name="role_permissions" />
                    </flux:field>
                </div>

                <div class="flex justify-end space-x-2">
                    <flux:button type="button" wire:click="closeModal" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="filled" class="bg-[#FFF200] text-[#231F20] hover:bg-[#FFF200]/80">Update Role</flux:button>
                </div>
            </form>
        </flux:modal>

        <!-- Delete Role Modal -->
        <flux:modal name="delete-role" :show="$showDeleteRoleModal" wire:model="showDeleteRoleModal">
            <form wire:submit="deleteRole" class="space-y-6">
                <div>
                    <flux:heading size="lg">Delete Role</flux:heading>
                    <flux:subheading>This action cannot be undone. All users with this role will be reassigned to 'user' role.</flux:subheading>
                </div>

                @if ($deletingRole)
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex">
                            <flux:icon.exclamation-triangle class="w-5 h-5 text-red-400" />
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">
                                    Are you sure you want to delete the "{{ $deletingRole->name }}" role?
                                </h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <p>This role is currently assigned to {{ $deletingRole->users()->count() }} {{ Str::plural('user', $deletingRole->users()->count()) }}.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>Enter your password to confirm</flux:label>
                        <flux:input type="password" wire:model="password_confirmation" />
                        <flux:error name="password_confirmation" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Type "DELETE" to confirm</flux:label>
                        <flux:input wire:model="delete_confirmation" placeholder="DELETE" />
                        <flux:error name="delete_confirmation" />
                    </flux:field>
                </div>

                <div class="flex justify-end space-x-2">
                    <flux:button type="button" wire:click="closeModal" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="danger">Delete Role</flux:button>
                </div>
            </form>
        </flux:modal>

        <!-- Manage Permissions Modal -->
        <flux:modal name="permissions" :show="$showPermissionsModal" wire:model="showPermissionsModal">
            <form wire:submit="updatePermissions" class="space-y-6">
                <div>
                    <flux:heading size="lg">Manage Permissions</flux:heading>
                    @if ($managingPermissionsRole)
                        <flux:subheading>Configure permissions for "{{ $managingPermissionsRole->name }}" role</flux:subheading>
                    @endif
                </div>

                <flux:field>
                    <flux:label>Permissions</flux:label>
                    <div class="grid grid-cols-2 gap-2 max-h-96 overflow-y-auto border border-[#9B9EA4]/20 rounded-lg p-4">
                        @foreach ($this->getPermissions() as $group => $permissions)
                            <div class="col-span-2">
                                <h4 class="font-semibold text-[#231F20] mb-2 border-b border-[#9B9EA4]/20 pb-1">{{ ucwords($group) }}</h4>
                            </div>
                            @foreach ($permissions as $permission)
                                <div class="flex items-center space-x-2">
                                    <flux:checkbox wire:model="role_permissions" value="{{ $permission->name }}" />
                                    <label class="text-sm text-[#231F20]">{{ str_replace('_', ' ', $permission->name) }}</label>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                    <flux:error name="role_permissions" />
                </flux:field>

                <div class="flex justify-end space-x-2">
                    <flux:button type="button" wire:click="closeModal" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="filled" class="bg-[#FFF200] text-[#231F20] hover:bg-[#FFF200]/80">Update Permissions</flux:button>
                </div>
            </form>
        </flux:modal>
    </div>
</x-layouts.app>
