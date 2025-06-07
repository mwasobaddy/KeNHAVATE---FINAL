<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Route;

class TestUserRoleManagement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:user-role-management';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the user and role management system functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("\n🔧 USER & ROLE MANAGEMENT SYSTEM TEST");
        $this->info("=====================================\n");

        // Test 1: Check if permissions were created correctly
        $this->info("📋 Testing Permissions...");
        $this->testPermissions();

        // Test 2: Check role permissions
        $this->info("\n👤 Testing Role Permissions...");
        $this->testRolePermissions();

        // Test 3: Test routes
        $this->info("\n🛣️  Testing Routes...");
        $this->testRoutes();

        // Test 4: Test policies
        $this->info("\n🔒 Testing Policies...");
        $this->testPolicies();

        // Test 5: Test file structure
        $this->info("\n📁 Testing File Structure...");
        $this->testFileStructure();

        $this->info("\n✅ USER & ROLE MANAGEMENT SYSTEM TEST COMPLETE!");
        $this->info("=====================================");
        
        return 0;
    }

    private function testPermissions()
    {
        $requiredPermissions = [
            'view_users', 'create_users', 'edit_users', 'delete_users',
            'view_roles', 'create_roles', 'edit_roles', 'delete_roles', 'assign_roles',
            'participate_challenges', 'select_winners'
        ];

        $existingPermissions = Permission::whereIn('name', $requiredPermissions)->pluck('name')->toArray();
        $missingPermissions = array_diff($requiredPermissions, $existingPermissions);

        if (empty($missingPermissions)) {
            $this->info("✅ All required permissions exist (" . count($requiredPermissions) . " permissions)");
            
            // Show permission details
            foreach ($requiredPermissions as $permission) {
                $roles = Permission::where('name', $permission)->first()?->roles->pluck('name')->toArray() ?? [];
                $this->line("   • {$permission}: " . (empty($roles) ? 'No roles assigned' : implode(', ', $roles)));
            }
        } else {
            $this->error("❌ Missing permissions: " . implode(', ', $missingPermissions));
        }
    }

    private function testRolePermissions()
    {
        $roles = ['developer', 'administrator', 'manager', 'user'];

        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $permissions = $role->permissions->pluck('name')->toArray();
                $userMgmtPermissions = array_filter($permissions, fn($p) => str_contains($p, 'users') || str_contains($p, 'roles'));
                
                $this->info("   • {$roleName}: " . count($permissions) . " total permissions");
                if (!empty($userMgmtPermissions)) {
                    $this->line("     User/Role management: " . implode(', ', $userMgmtPermissions));
                }
            } else {
                $this->error("   ❌ Role '{$roleName}' not found");
            }
        }
    }

    private function testRoutes()
    {
        $requiredRoutes = [
            'users.index', 'users.create', 'users.show', 'users.edit',
            'roles.index', 'roles.create', 'roles.show', 'roles.edit'
        ];

        foreach ($requiredRoutes as $routeName) {
            if (Route::has($routeName)) {
                $route = Route::getRoutes()->getByName($routeName);
                $middleware = $route->gatherMiddleware();
                
                // Check for permission-based middleware
                $hasPermissionMiddleware = collect($middleware)->contains(function ($m) {
                    return str_contains($m, 'permission:');
                });
                
                $this->info("   ✅ {$routeName}: " . ($hasPermissionMiddleware ? 'Permission-protected' : 'Basic auth'));
            } else {
                $this->error("   ❌ Route '{$routeName}' not found");
            }
        }
    }

    private function testPolicies()
    {
        // Test if UserPolicy and RolePolicy exist
        $userPolicyPath = app_path('Policies/UserPolicy.php');
        $rolePolicyPath = app_path('Policies/RolePolicy.php');

        if (file_exists($userPolicyPath)) {
            $this->info("   ✅ UserPolicy exists");
        } else {
            $this->error("   ❌ UserPolicy missing");
        }

        if (file_exists($rolePolicyPath)) {
            $this->info("   ✅ RolePolicy exists");
        } else {
            $this->error("   ❌ RolePolicy missing");
        }

        // Test if policies are registered
        try {
            $policies = app('Illuminate\Contracts\Auth\Access\Gate')->policies();
            $userPolicyRegistered = isset($policies['App\Models\User']);
            $rolePolicyRegistered = isset($policies['Spatie\Permission\Models\Role']);

            if ($userPolicyRegistered) {
                $this->info("   ✅ UserPolicy is registered");
            } else {
                $this->error("   ❌ UserPolicy not registered in AuthServiceProvider");
            }

            if ($rolePolicyRegistered) {
                $this->info("   ✅ RolePolicy is registered");
            } else {
                $this->error("   ❌ RolePolicy not registered in AuthServiceProvider");
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Error checking policy registration: " . $e->getMessage());
        }
    }

    private function testFileStructure()
    {
        $requiredFiles = [
            'resources/views/livewire/users/index.blade.php',
            'resources/views/livewire/users/create.blade.php',
            'resources/views/livewire/users/edit.blade.php',
            'resources/views/livewire/users/show.blade.php',
            'resources/views/livewire/roles/index.blade.php',
            'resources/views/livewire/roles/create.blade.php',
            'resources/views/livewire/roles/edit.blade.php',
            'resources/views/livewire/roles/show.blade.php',
        ];

        foreach ($requiredFiles as $file) {
            if (file_exists(base_path($file))) {
                // Check if it's a proper Volt component
                $content = file_get_contents(base_path($file));
                if (str_contains($content, '#[Layout(') && str_contains($content, 'class extends Component')) {
                    $this->info("   ✅ {$file}: Proper Volt component");
                } else {
                    $this->warn("   ⚠️  {$file}: Exists but may not be proper Volt syntax");
                }
            } else {
                $this->error("   ❌ {$file}: Missing");
            }
        }

        // Check if old admin files were removed
        $oldAdminPath = base_path('resources/views/livewire/admin');
        if (!file_exists($oldAdminPath)) {
            $this->info("   ✅ Old admin folder removed");
        } else {
            $this->warn("   ⚠️  Old admin folder still exists - should be cleaned up");
        }
    }
}
