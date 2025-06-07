<?php

use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Services\AuditService;

new #[Layout('components.layouts.app', title: 'Create Role')] class extends Component {
    public $name = '';
    public $selectedPermissions = [];

    protected $rules = [
        'name' => 'required|string|max:255|unique:roles,name',
        'selectedPermissions' => 'array',
        'selectedPermissions.*' => 'exists:permissions,id'
    ];

    public function mount()
    {
        // Check if user has permission to create roles
        $this->authorize('create', Role::class);
    }

    public function with()
    {
        $availablePermissions = Permission::orderBy('name')->get();

        return [
            'availablePermissions' => $availablePermissions
        ];
    }

    public function save()
    {
        $this->authorize('create', Role::class);
        
        $this->validate();

        try {
            $role = Role::create(['name' => $this->name]);
            
            if (!empty($this->selectedPermissions)) {
                $permissions = Permission::whereIn('id', $this->selectedPermissions)->get();
                $role->syncPermissions($permissions);
            }

            // Log the action
            app(AuditService::class)->log(
                'role_creation',
                'Role',
                $role->id,
                null,
                array_merge($role->toArray(), [
                    'permissions' => $role->permissions->pluck('name')->toArray()
                ])
            );

            session()->flash('success', 'Role created successfully!');
            
            return redirect()->route('roles.index');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create role. Please try again.');
        }
    }

    public function cancel()
    {
        return redirect()->route('roles.index');
    }
}; ?>

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-gradient-to-r from-green-500/20 to-blue-500/20 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-gradient-to-r from-yellow-500/20 to-orange-500/20 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 p-6 space-y-8 max-w-5xl mx-auto">
        {{-- Header Section --}}
        <div class="mb-8">
            <div class="flex items-center space-x-4 mb-6">
                <flux:button 
                    wire:navigate 
                    href="{{ route('roles.index') }}" 
                    variant="ghost" 
                    size="sm"
                    class="bg-white/70 backdrop-blur-xl border border-white/20 hover:bg-white/90 transition-all duration-300"
                >
                    <flux:icon.arrow-left class="w-4 h-4 mr-2" />
                    Back to Roles
                </flux:button>
            </div>
            
            <div class="text-center">
                <h1 class="text-4xl font-bold text-[#231F20] dark:text-white mb-4">Create New Role</h1>
                <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">
                    Define a new role with specific permissions for the KeNHAVATE Innovation Portal
                </p>
            </div>
        </div>

        {{-- Main Creation Form --}}
        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-8 py-6 border-b border-white/20 dark:border-zinc-700/50">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-500 rounded-xl flex items-center justify-center">
                        <flux:icon.shield-check class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-[#231F20] dark:text-white">Role Configuration</h3>
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">Configure role details and assign permissions</p>
                    </div>
                </div>
            </div>

            <form wire:submit="save" class="p-8 space-y-8">
                {{-- Role Name Section --}}
                <div class="bg-white/50 dark:bg-zinc-700/50 rounded-xl p-6 border border-white/30 dark:border-zinc-600/30">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                            <flux:icon.identification class="w-4 h-4 text-white" />
                        </div>
                        <h4 class="text-lg font-semibold text-[#231F20] dark:text-white">Role Identity</h4>
                    </div>
                    
                    <flux:input 
                        wire:model="name" 
                        label="Role Name" 
                        placeholder="Enter role name (e.g., content-manager, reviewer)"
                        required
                        autocomplete="off"
                        class="bg-white/90 dark:bg-zinc-700/90 backdrop-blur-sm border border-[#9B9EA4]/30 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                    <p class="mt-2 text-sm text-[#9B9EA4] dark:text-zinc-400 flex items-center">
                        <flux:icon.information-circle class="w-4 h-4 mr-1" />
                        Use lowercase with hyphens for multi-word role names
                    </p>
                </div>

                {{-- Permissions Selection Section --}}
                <div class="bg-white/50 dark:bg-zinc-700/50 rounded-xl p-6 border border-white/30 dark:border-zinc-600/30">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                            <flux:icon.key class="w-4 h-4 text-white" />
                        </div>
                        <div>
                            <h4 class="text-lg font-semibold text-[#231F20] dark:text-white">Permission Assignment</h4>
                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                                Select the permissions that users with this role should have
                            </p>
                        </div>
                    </div>
                    
                    <div class="space-y-6">
                        @php
                            $groupedPermissions = $availablePermissions->groupBy(function($permission) {
                                return explode('_', $permission->name)[0];
                            });
                        @endphp

                        @foreach($groupedPermissions as $group => $permissions)
                        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-sm border border-white/30 dark:border-zinc-600/30 rounded-xl p-6 hover:shadow-lg transition-all duration-300">
                            <div class="flex items-center space-x-3 mb-4">
                                <div class="w-6 h-6 bg-gradient-to-r from-purple-500 to-pink-500 rounded-lg flex items-center justify-center">
                                    <flux:icon.cog class="w-3 h-3 text-white" />
                                </div>
                                <h5 class="text-md font-semibold text-[#231F20] dark:text-white capitalize">
                                    {{ str_replace('_', ' ', $group) }} Permissions
                                </h5>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach($permissions as $permission)
                                <label class="flex items-center space-x-3 text-sm p-3 bg-white/50 dark:bg-zinc-700/50 rounded-lg hover:bg-white/70 dark:hover:bg-zinc-600/50 transition-all duration-200 cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        wire:model="selectedPermissions" 
                                        value="{{ $permission->id }}"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 w-4 h-4"
                                    >
                                    <span class="text-[#231F20] dark:text-white font-medium">{{ $permission->name }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Selected Permissions Summary --}}
                @if(!empty($selectedPermissions))
                <div class="bg-gradient-to-r from-blue-50/70 to-indigo-50/70 dark:from-blue-900/20 dark:to-indigo-900/20 backdrop-blur-sm border border-blue-200/50 dark:border-blue-700/50 rounded-xl p-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                            <flux:icon.check-circle class="w-4 h-4 text-white" />
                        </div>
                        <h4 class="text-lg font-semibold text-blue-800 dark:text-blue-200">
                            Selected Permissions ({{ count($selectedPermissions) }})
                        </h4>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($availablePermissions->whereIn('id', $selectedPermissions) as $permission)
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-blue-100/70 dark:bg-blue-800/50 text-blue-800 dark:text-blue-200 border border-blue-200/50 dark:border-blue-700/50 backdrop-blur-sm">
                            <flux:icon.shield-check class="w-3 h-3 mr-1" />
                            {{ $permission->name }}
                        </span>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Action Buttons --}}
                <div class="flex flex-col sm:flex-row gap-4 pt-8 border-t border-white/20 dark:border-zinc-700/50">
                    <flux:button 
                        type="submit" 
                        variant="primary" 
                        class="w-full sm:w-auto bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600 text-white px-8 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all duration-300"
                    >
                        <flux:icon.plus class="w-5 h-5 mr-2" />
                        Create Role
                    </flux:button>
                    
                    <flux:button 
                        type="button" 
                        wire:click="cancel" 
                        variant="ghost" 
                        class="w-full sm:w-auto bg-white/70 dark:bg-zinc-700/70 backdrop-blur-sm hover:bg-white/90 dark:hover:bg-zinc-600/70 text-[#231F20] dark:text-white px-8 py-3 rounded-xl font-semibold border border-white/30 dark:border-zinc-600/30 transition-all duration-300"
                    >
                        <flux:icon.x-mark class="w-5 h-5 mr-2" />
                        Cancel
                    </flux:button>
                </div>
            </form>
        </div>

        {{-- Permission Guidelines Section --}}
        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-8 shadow-xl">
            <div class="flex items-center space-x-4 mb-6">
                <div class="w-12 h-12 bg-gradient-to-r from-amber-500 to-orange-500 rounded-xl flex items-center justify-center">
                    <flux:icon.academic-cap class="w-6 h-6 text-white" />
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#231F20] dark:text-white">Permission Guidelines</h3>
                    <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">Best practices and security considerations</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="bg-white/50 dark:bg-zinc-700/50 rounded-xl p-6 border border-white/30 dark:border-zinc-600/30">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                            <flux:icon.check-badge class="w-4 h-4 text-white" />
                        </div>
                        <h4 class="font-semibold text-[#231F20] dark:text-white">Best Practices</h4>
                    </div>
                    <ul class="text-sm text-[#9B9EA4] dark:text-zinc-400 space-y-2">
                        <li class="flex items-center space-x-2">
                            <flux:icon.check-circle class="w-4 h-4 text-green-500" />
                            <span>Grant only necessary permissions</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <flux:icon.check-circle class="w-4 h-4 text-green-500" />
                            <span>Follow principle of least privilege</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <flux:icon.check-circle class="w-4 h-4 text-green-500" />
                            <span>Review permissions regularly</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <flux:icon.check-circle class="w-4 h-4 text-green-500" />
                            <span>Test role functionality before deployment</span>
                        </li>
                    </ul>
                </div>
                
                <div class="bg-white/50 dark:bg-zinc-700/50 rounded-xl p-6 border border-white/30 dark:border-zinc-600/30">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-8 h-8 bg-red-500 rounded-lg flex items-center justify-center">
                            <flux:icon.exclamation-triangle class="w-4 h-4 text-white" />
                        </div>
                        <h4 class="font-semibold text-[#231F20] dark:text-white">Security Notes</h4>
                    </div>
                    <ul class="text-sm text-[#9B9EA4] dark:text-zinc-400 space-y-2">
                        <li class="flex items-center space-x-2">
                            <flux:icon.shield-exclamation class="w-4 h-4 text-red-500" />
                            <span>Developer role has unrestricted access</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <flux:icon.shield-exclamation class="w-4 h-4 text-red-500" />
                            <span>Admin roles can manage most resources</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <flux:icon.shield-exclamation class="w-4 h-4 text-red-500" />
                            <span>User-specific permissions override role permissions</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <flux:icon.shield-exclamation class="w-4 h-4 text-red-500" />
                            <span>Role changes take effect immediately</span>
                        </li>
                    </ul>
                </div>
            </div>
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
